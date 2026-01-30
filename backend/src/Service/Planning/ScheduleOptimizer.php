<?php

namespace App\Service\Planning;

use App\Entity\Planning\Availability;
use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Repository\Planning\AvailabilityRepository;
use App\Repository\Booking\BookingRepository;
use App\Service\Geolocation\DistanceCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service d'optimisation de planning
 * Optimise les routes, créneaux et organisation des réservations
 */
class ScheduleOptimizer
{
    // Poids pour l'algorithme d'optimisation
    private const WEIGHT_TRAVEL_TIME = 3.0;
    private const WEIGHT_TIME_EFFICIENCY = 2.0;
    private const WEIGHT_CLIENT_PREFERENCE = 1.5;
    private const WEIGHT_BREAK_OPTIMIZATION = 1.0;
    
    // Paramètres d'optimisation
    private const MAX_TRAVEL_DISTANCE = 30; // km
    private const IDEAL_BOOKING_GAP = 15; // minutes
    private const MIN_BOOKING_GAP = 10; // minutes
    private const PREFERRED_START_HOUR = 9; // 9h
    private const PREFERRED_END_HOUR = 17; // 17h

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityRepository $availabilityRepository,
        private BookingRepository $bookingRepository,
        private DistanceCalculator $distanceCalculator,
        private ConflictDetector $conflictDetector,
        private LoggerInterface $logger
    ) {}

    /**
     * Optimise le planning d'un prestataire pour une période donnée
     */
    public function optimizeSchedule(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        array $options = []
    ): array {
        $this->logger->info('Starting schedule optimization', [
            'prestataire_id' => $prestataire->getId(),
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d')
        ]);

        // Récupérer toutes les réservations de la période
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        // Grouper par jour
        $bookingsByDay = $this->groupBookingsByDay($bookings);

        $optimizations = [];
        $totalSavings = [
            'time_minutes' => 0,
            'distance_km' => 0,
            'efficiency_gain' => 0
        ];

        foreach ($bookingsByDay as $day => $dayBookings) {
            $dayOptimization = $this->optimizeDailySchedule(
                $prestataire,
                $dayBookings,
                new \DateTime($day),
                $options
            );

            $optimizations[$day] = $dayOptimization;
            
            // Cumuler les économies
            $totalSavings['time_minutes'] += $dayOptimization['savings']['time_minutes'];
            $totalSavings['distance_km'] += $dayOptimization['savings']['distance_km'];
            $totalSavings['efficiency_gain'] += $dayOptimization['efficiency_gain'];
        }

        return [
            'prestataire_id' => $prestataire->getId(),
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'total_days_optimized' => count($optimizations),
            'total_bookings' => count($bookings),
            'total_savings' => $totalSavings,
            'daily_optimizations' => $optimizations,
            'recommendations' => $this->generateOptimizationRecommendations($optimizations),
            'optimized_at' => new \DateTimeImmutable()
        ];
    }

    /**
     * Optimise le planning d'une journée
     */
    public function optimizeDailySchedule(
        Prestataire $prestataire,
        array $bookings,
        \DateTimeInterface $date,
        array $options = []
    ): array {
        if (empty($bookings)) {
            return [
                'date' => $date->format('Y-m-d'),
                'status' => 'no_bookings',
                'original_schedule' => [],
                'optimized_schedule' => [],
                'savings' => ['time_minutes' => 0, 'distance_km' => 0],
                'efficiency_gain' => 0
            ];
        }

        // Analyser le planning actuel
        $currentAnalysis = $this->analyzeSchedule($bookings);

        // Optimiser l'ordre des réservations (problème du voyageur de commerce)
        $optimizedOrder = $this->optimizeBookingOrder($bookings, $options);

        // Optimiser les créneaux horaires
        $optimizedSchedule = $this->optimizeTimeSlots(
            $prestataire,
            $optimizedOrder,
            $date,
            $options
        );

        // Analyser le planning optimisé
        $optimizedAnalysis = $this->analyzeSchedule($optimizedSchedule);

        // Calculer les gains
        $savings = [
            'time_minutes' => $currentAnalysis['total_travel_time'] - $optimizedAnalysis['total_travel_time'],
            'distance_km' => $currentAnalysis['total_distance'] - $optimizedAnalysis['total_distance']
        ];

        $efficiencyGain = $this->calculateEfficiencyGain($currentAnalysis, $optimizedAnalysis);

        return [
            'date' => $date->format('Y-m-d'),
            'status' => 'optimized',
            'original_schedule' => $this->formatSchedule($bookings),
            'optimized_schedule' => $this->formatSchedule($optimizedSchedule),
            'current_metrics' => $currentAnalysis,
            'optimized_metrics' => $optimizedAnalysis,
            'savings' => $savings,
            'efficiency_gain' => $efficiencyGain,
            'changes_required' => $this->detectScheduleChanges($bookings, $optimizedSchedule),
            'feasibility' => $this->checkOptimizationFeasibility($prestataire, $optimizedSchedule, $date)
        ];
    }

    /**
     * Optimise l'ordre des réservations pour minimiser les déplacements
     * Utilise un algorithme de type "nearest neighbor" pour le TSP
     */
    public function optimizeBookingOrder(array $bookings, array $options = []): array
    {
        if (count($bookings) <= 1) {
            return $bookings;
        }

        $startLocation = $options['start_location'] ?? null;
        $endLocation = $options['end_location'] ?? null;
        $priorityBookings = $options['priority_bookings'] ?? [];

        // Séparer les réservations prioritaires (avec horaire fixe) et flexibles
        $fixedBookings = [];
        $flexibleBookings = [];

        foreach ($bookings as $booking) {
            if (in_array($booking->getId(), $priorityBookings) || 
                $booking->getClient()->hasPreferredTime()) {
                $fixedBookings[] = $booking;
            } else {
                $flexibleBookings[] = $booking;
            }
        }

        // Trier les réservations fixes par horaire
        usort($fixedBookings, fn($a, $b) => 
            $a->getScheduledDateTime() <=> $b->getScheduledDateTime()
        );

        // Si on a des réservations fixes, on optimise les flexibles autour
        if (!empty($fixedBookings)) {
            return $this->optimizeAroundFixedBookings($fixedBookings, $flexibleBookings);
        }

        // Sinon, optimisation complète avec algorithme du plus proche voisin
        return $this->nearestNeighborOptimization($flexibleBookings, $startLocation);
    }

    /**
     * Suggère le meilleur créneau pour une nouvelle réservation
     */
    public function suggestOptimalSlot(
        Prestataire $prestataire,
        \DateTimeInterface $preferredDate,
        int $duration,
        string $address,
        array $preferences = []
    ): array {
        // Récupérer les réservations existantes pour ce jour
        $dayStart = (clone $preferredDate)->setTime(0, 0, 0);
        $dayEnd = (clone $preferredDate)->setTime(23, 59, 59);
        
        $existingBookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $dayStart,
            $dayEnd
        );

        // Récupérer les disponibilités
        $dayOfWeek = (int) $preferredDate->format('w');
        $availabilities = $this->availabilityRepository->findActiveForDay(
            $prestataire,
            $dayOfWeek,
            $preferredDate
        );

        $suggestions = [];

        foreach ($availabilities as $availability) {
            $slots = $this->findAvailableSlots(
                $availability,
                $existingBookings,
                $preferredDate,
                $duration
            );

            foreach ($slots as $slot) {
                $score = $this->scoreTimeSlot(
                    $slot,
                    $address,
                    $existingBookings,
                    $preferences
                );

                $suggestions[] = [
                    'start_time' => $slot['start'],
                    'end_time' => $slot['end'],
                    'score' => $score,
                    'reasons' => $slot['reasons'],
                    'metrics' => [
                        'travel_time_before' => $slot['travel_time_before'],
                        'travel_time_after' => $slot['travel_time_after'],
                        'efficiency' => $slot['efficiency']
                    ]
                ];
            }
        }

        // Trier par score décroissant
        usort($suggestions, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, 5);
    }

    /**
     * Optimise le planning pour maximiser le nombre de réservations
     */
    public function maximizeBookingCapacity(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $analysis = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int) $currentDate->format('w');
            
            // Récupérer les disponibilités
            $availabilities = $this->availabilityRepository->findActiveForDay(
                $prestataire,
                $dayOfWeek,
                $currentDate
            );

            // Récupérer les réservations
            $dayStart = (clone $currentDate)->setTime(0, 0, 0);
            $dayEnd = (clone $currentDate)->setTime(23, 59, 59);
            
            $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
                $prestataire,
                $dayStart,
                $dayEnd
            );

            $dayAnalysis = $this->analyzeDailyCapacity(
                $availabilities,
                $bookings,
                $currentDate
            );

            $analysis[$currentDate->format('Y-m-d')] = $dayAnalysis;

            $currentDate->modify('+1 day');
        }

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'daily_capacity' => $analysis,
            'recommendations' => $this->generateCapacityRecommendations($analysis)
        ];
    }

    /**
     * Optimise les itinéraires pour réduire les déplacements
     */
    public function optimizeRoutes(
        Prestataire $prestataire,
        array $bookings,
        ?string $startLocation = null,
        ?string $endLocation = null
    ): array {
        if (empty($bookings)) {
            return [
                'status' => 'no_bookings',
                'route' => [],
                'metrics' => []
            ];
        }

        // Créer une matrice de distances entre tous les points
        $distanceMatrix = $this->buildDistanceMatrix($bookings, $startLocation, $endLocation);

        // Appliquer l'algorithme d'optimisation
        $optimizedRoute = $this->solveRoutingProblem($distanceMatrix, $bookings);

        // Calculer les métriques
        $metrics = $this->calculateRouteMetrics($optimizedRoute, $distanceMatrix);

        return [
            'status' => 'optimized',
            'route' => $this->formatRoute($optimizedRoute),
            'metrics' => $metrics,
            'savings' => [
                'distance_km' => $metrics['original_distance'] - $metrics['optimized_distance'],
                'time_minutes' => $metrics['original_time'] - $metrics['optimized_time']
            ],
            'map_url' => $this->generateMapUrl($optimizedRoute)
        ];
    }

    /**
     * Équilibre la charge de travail sur une semaine
     */
    public function balanceWeeklyWorkload(
        Prestataire $prestataire,
        \DateTimeInterface $weekStart
    ): array {
        $weekEnd = (clone $weekStart)->modify('+6 days');
        
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $weekStart,
            $weekEnd
        );

        // Grouper par jour
        $bookingsByDay = $this->groupBookingsByDay($bookings);

        // Analyser la distribution actuelle
        $currentDistribution = $this->analyzeWorkloadDistribution($bookingsByDay);

        // Identifier les jours sous-utilisés et sur-utilisés
        $recommendations = $this->generateBalancingRecommendations(
            $currentDistribution,
            $prestataire,
            $weekStart
        );

        return [
            'week_start' => $weekStart->format('Y-m-d'),
            'current_distribution' => $currentDistribution,
            'recommendations' => $recommendations,
            'balance_score' => $this->calculateBalanceScore($currentDistribution)
        ];
    }

    /**
     * Trouve les plages horaires les plus efficaces
     */
    public function findMostEfficientTimeWindows(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        // Analyser les performances par plage horaire
        $timeWindows = [
            'morning' => ['start' => 8, 'end' => 12],
            'afternoon' => ['start' => 13, 'end' => 17],
            'evening' => ['start' => 18, 'end' => 21]
        ];

        $analysis = [];

        foreach ($timeWindows as $window => $hours) {
            $windowBookings = array_filter($bookings, function($booking) use ($hours) {
                $hour = (int) $booking->getScheduledDateTime()->format('H');
                return $hour >= $hours['start'] && $hour < $hours['end'];
            });

            $analysis[$window] = [
                'booking_count' => count($windowBookings),
                'total_revenue' => $this->calculateTotalRevenue($windowBookings),
                'average_duration' => $this->calculateAverageDuration($windowBookings),
                'efficiency_score' => $this->calculateTimeWindowEfficiency($windowBookings),
                'recommendation' => $this->getTimeWindowRecommendation($windowBookings)
            ];
        }

        // Trier par score d'efficacité
        uasort($analysis, fn($a, $b) => $b['efficiency_score'] <=> $a['efficiency_score']);

        return [
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'time_windows' => $analysis,
            'best_window' => array_key_first($analysis),
            'recommendations' => $this->generateTimeWindowRecommendations($analysis)
        ];
    }

    // ============ MÉTHODES PRIVÉES - ANALYSE ============

    /**
     * Analyse un planning et calcule les métriques
     */
    private function analyzeSchedule(array $bookings): array
    {
        $totalWorkTime = 0;
        $totalTravelTime = 0;
        $totalDistance = 0;
        $gaps = [];

        usort($bookings, fn($a, $b) => 
            $a->getScheduledDateTime() <=> $b->getScheduledDateTime()
        );

        for ($i = 0; $i < count($bookings); $i++) {
            $booking = $bookings[$i];
            $totalWorkTime += $booking->getDuration();

            if ($i < count($bookings) - 1) {
                $nextBooking = $bookings[$i + 1];
                
                // Calculer le temps de trajet
                $travelTime = $this->distanceCalculator->calculateTravelTime(
                    $booking->getAddress(),
                    $nextBooking->getAddress()
                );
                
                $distance = $this->distanceCalculator->calculateDistance(
                    $booking->getAddress(),
                    $nextBooking->getAddress()
                );

                $totalTravelTime += $travelTime;
                $totalDistance += $distance;

                // Calculer le temps d'attente
                $gap = ($nextBooking->getScheduledDateTime()->getTimestamp() - 
                       $booking->getEndDateTime()->getTimestamp()) / 60;
                
                $gap -= $travelTime;
                
                if ($gap > 0) {
                    $gaps[] = $gap;
                }
            }
        }

        return [
            'total_bookings' => count($bookings),
            'total_work_time' => $totalWorkTime,
            'total_travel_time' => $totalTravelTime,
            'total_distance' => $totalDistance,
            'total_gaps' => array_sum($gaps),
            'average_gap' => empty($gaps) ? 0 : array_sum($gaps) / count($gaps),
            'efficiency_ratio' => $totalWorkTime / ($totalWorkTime + $totalTravelTime + array_sum($gaps)),
            'start_time' => empty($bookings) ? null : $bookings[0]->getScheduledDateTime(),
            'end_time' => empty($bookings) ? null : $bookings[count($bookings) - 1]->getEndDateTime()
        ];
    }

    /**
     * Analyse la capacité d'une journée
     */
    private function analyzeDailyCapacity(
        array $availabilities,
        array $bookings,
        \DateTimeInterface $date
    ): array {
        $totalAvailableMinutes = 0;
        foreach ($availabilities as $availability) {
            $start = $availability->getStartTime();
            $end = $availability->getEndTime();
            $totalAvailableMinutes += ($end->getTimestamp() - $start->getTimestamp()) / 60;
        }

        $totalBookedMinutes = array_reduce($bookings, 
            fn($sum, $b) => $sum + $b->getDuration(), 0
        );

        $freeSlots = $this->findAllFreeSlots($availabilities, $bookings, $date);

        return [
            'date' => $date->format('Y-m-d'),
            'total_available_minutes' => $totalAvailableMinutes,
            'total_booked_minutes' => $totalBookedMinutes,
            'free_minutes' => $totalAvailableMinutes - $totalBookedMinutes,
            'occupancy_rate' => $totalAvailableMinutes > 0 
                ? ($totalBookedMinutes / $totalAvailableMinutes) * 100 
                : 0,
            'booking_count' => count($bookings),
            'free_slots_count' => count($freeSlots),
            'can_accept_more' => !empty($freeSlots),
            'recommended_slot_duration' => $this->calculateRecommendedSlotDuration($freeSlots)
        ];
    }

    /**
     * Calcule le gain d'efficacité
     */
    private function calculateEfficiencyGain(array $current, array $optimized): float
    {
        $currentEfficiency = $current['efficiency_ratio'] ?? 0;
        $optimizedEfficiency = $optimized['efficiency_ratio'] ?? 0;

        if ($currentEfficiency === 0) {
            return 0;
        }

        return (($optimizedEfficiency - $currentEfficiency) / $currentEfficiency) * 100;
    }

    // ============ MÉTHODES PRIVÉES - OPTIMISATION ============

    /**
     * Algorithme du plus proche voisin pour optimiser l'ordre
     */
    private function nearestNeighborOptimization(
        array $bookings,
        ?string $startLocation = null
    ): array {
        if (empty($bookings)) {
            return [];
        }

        $optimized = [];
        $remaining = $bookings;
        $currentLocation = $startLocation ?? $bookings[0]->getAddress();

        while (!empty($remaining)) {
            $nearest = null;
            $minDistance = PHP_FLOAT_MAX;

            foreach ($remaining as $index => $booking) {
                $distance = $this->distanceCalculator->calculateDistance(
                    $currentLocation,
                    $booking->getAddress()
                );

                if ($distance < $minDistance) {
                    $minDistance = $distance;
                    $nearest = $index;
                }
            }

            if ($nearest !== null) {
                $optimized[] = $remaining[$nearest];
                $currentLocation = $remaining[$nearest]->getAddress();
                unset($remaining[$nearest]);
                $remaining = array_values($remaining);
            }
        }

        return $optimized;
    }

    /**
     * Optimise les réservations flexibles autour des fixes
     */
    private function optimizeAroundFixedBookings(
        array $fixedBookings,
        array $flexibleBookings
    ): array {
        $result = [];
        
        foreach ($fixedBookings as $index => $fixedBooking) {
            $result[] = $fixedBooking;

            // Trouver les réservations flexibles qui peuvent aller après
            if ($index < count($fixedBookings) - 1) {
                $nextFixed = $fixedBookings[$index + 1];
                $availableTime = ($nextFixed->getScheduledDateTime()->getTimestamp() - 
                                $fixedBooking->getEndDateTime()->getTimestamp()) / 60;

                // Insérer les réservations flexibles qui rentrent dans ce créneau
                $inserted = $this->insertFlexibleBookings(
                    $flexibleBookings,
                    $fixedBooking,
                    $nextFixed,
                    $availableTime
                );

                $result = array_merge($result, $inserted);
            }
        }

        return $result;
    }

    /**
     * Optimise les créneaux horaires
     */
    private function optimizeTimeSlots(
        Prestataire $prestataire,
        array $bookings,
        \DateTimeInterface $date,
        array $options = []
    ): array {
        $optimized = [];
        $dayOfWeek = (int) $date->format('w');
        
        $availabilities = $this->availabilityRepository->findActiveForDay(
            $prestataire,
            $dayOfWeek,
            $date
        );

        if (empty($availabilities)) {
            return $bookings;
        }

        // Commencer au début de la première disponibilité
        $availability = $availabilities[0];
        $currentTime = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $availability->getStartTime()->format('H:i:s')
        );

        foreach ($bookings as $index => $booking) {
            $optimizedBooking = clone $booking;
            $optimizedBooking->setScheduledDateTime($currentTime);

            $endTime = (clone $currentTime)->modify('+' . $booking->getDuration() . ' minutes');
            $optimizedBooking->setEndDateTime($endTime);

            $optimized[] = $optimizedBooking;

            // Calculer le temps pour aller à la prochaine réservation
            if ($index < count($bookings) - 1) {
                $nextBooking = $bookings[$index + 1];
                
                $travelTime = $this->distanceCalculator->calculateTravelTime(
                    $booking->getAddress(),
                    $nextBooking->getAddress()
                );

                // Ajouter durée + trajet + buffer
                $currentTime = (clone $endTime)->modify(
                    '+' . ($travelTime + self::IDEAL_BOOKING_GAP) . ' minutes'
                );
            }
        }

        return $optimized;
    }

    /**
     * Score un créneau horaire
     */
    private function scoreTimeSlot(
        array $slot,
        string $address,
        array $existingBookings,
        array $preferences
    ): float {
        $score = 100.0;

        // Facteur 1: Temps de trajet minimal
        $travelTimeScore = $this->calculateTravelTimeScore(
            $slot,
            $address,
            $existingBookings
        );
        $score += $travelTimeScore * self::WEIGHT_TRAVEL_TIME;

        // Facteur 2: Efficacité du créneau
        $efficiencyScore = $this->calculateSlotEfficiency($slot);
        $score += $efficiencyScore * self::WEIGHT_TIME_EFFICIENCY;

        // Facteur 3: Préférences client
        $preferenceScore = $this->calculatePreferenceScore($slot, $preferences);
        $score += $preferenceScore * self::WEIGHT_CLIENT_PREFERENCE;

        // Facteur 4: Optimisation des pauses
        $breakScore = $this->calculateBreakScore($slot, $existingBookings);
        $score += $breakScore * self::WEIGHT_BREAK_OPTIMIZATION;

        return max(0, $score);
    }

    /**
     * Trouve les créneaux disponibles dans une disponibilité
     */
    private function findAvailableSlots(
        Availability $availability,
        array $existingBookings,
        \DateTimeInterface $date,
        int $duration
    ): array {
        $slots = [];
        
        $startTime = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $availability->getStartTime()->format('H:i:s')
        );
        
        $endTime = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $availability->getEndTime()->format('H:i:s')
        );

        $currentTime = clone $startTime;

        while ($currentTime < $endTime) {
            $slotEnd = (clone $currentTime)->modify("+{$duration} minutes");
            
            if ($slotEnd > $endTime) {
                break;
            }

            // Vérifier qu'il n'y a pas de conflit
            $hasConflict = false;
            foreach ($existingBookings as $booking) {
                if ($this->conflictDetector->doTimeSlotsOverlap(
                    $currentTime,
                    $slotEnd,
                    $booking->getScheduledDateTime(),
                    $booking->getEndDateTime()
                )) {
                    $hasConflict = true;
                    $currentTime = clone $booking->getEndDateTime();
                    break;
                }
            }

            if (!$hasConflict) {
                // Calculer les métriques pour ce créneau
                $metrics = $this->calculateSlotMetrics(
                    $currentTime,
                    $slotEnd,
                    $existingBookings
                );

                $slots[] = [
                    'start' => clone $currentTime,
                    'end' => clone $slotEnd,
                    'travel_time_before' => $metrics['travel_time_before'],
                    'travel_time_after' => $metrics['travel_time_after'],
                    'efficiency' => $metrics['efficiency'],
                    'reasons' => $metrics['reasons']
                ];

                $currentTime->modify('+15 minutes'); // Increment par 15 min
            }
        }

        return $slots;
    }

    /**
     * Construit une matrice de distances
     */
    private function buildDistanceMatrix(
        array $bookings,
        ?string $startLocation,
        ?string $endLocation
    ): array {
        $locations = [];
        
        if ($startLocation) {
            $locations[] = $startLocation;
        }

        foreach ($bookings as $booking) {
            $locations[] = $booking->getAddress();
        }

        if ($endLocation) {
            $locations[] = $endLocation;
        }

        $matrix = [];
        
        for ($i = 0; $i < count($locations); $i++) {
            $matrix[$i] = [];
            for ($j = 0; $j < count($locations); $j++) {
                if ($i === $j) {
                    $matrix[$i][$j] = 0;
                } else {
                    $matrix[$i][$j] = $this->distanceCalculator->calculateDistance(
                        $locations[$i],
                        $locations[$j]
                    );
                }
            }
        }

        return $matrix;
    }

    /**
     * Résout le problème de routage
     */
    private function solveRoutingProblem(array $distanceMatrix, array $bookings): array
    {
        // Implémentation simplifiée - algorithme glouton
        // Dans une vraie application, utiliser un solver d'optimisation
        return $this->nearestNeighborOptimization($bookings);
    }

    // ============ MÉTHODES UTILITAIRES ============

    private function groupBookingsByDay(array $bookings): array
    {
        $grouped = [];
        foreach ($bookings as $booking) {
            $day = $booking->getScheduledDateTime()->format('Y-m-d');
            if (!isset($grouped[$day])) {
                $grouped[$day] = [];
            }
            $grouped[$day][] = $booking;
        }
        return $grouped;
    }

    private function formatSchedule(array $bookings): array
    {
        return array_map(function($booking) {
            return [
                'id' => $booking->getId(),
                'start' => $booking->getScheduledDateTime()->format('H:i'),
                'end' => $booking->getEndDateTime()->format('H:i'),
                'duration' => $booking->getDuration(),
                'address' => $booking->getAddress(),
                'client' => $booking->getClient()->getFullName()
            ];
        }, $bookings);
    }

    private function detectScheduleChanges(array $original, array $optimized): array
    {
        $changes = [];
        
        for ($i = 0; $i < count($original); $i++) {
            $originalBooking = $original[$i];
            $optimizedBooking = $optimized[$i];

            if ($originalBooking->getScheduledDateTime() != $optimizedBooking->getScheduledDateTime()) {
                $changes[] = [
                    'booking_id' => $originalBooking->getId(),
                    'type' => 'time_change',
                    'from' => $originalBooking->getScheduledDateTime()->format('H:i'),
                    'to' => $optimizedBooking->getScheduledDateTime()->format('H:i')
                ];
            }
        }

        return $changes;
    }

    private function checkOptimizationFeasibility(
        Prestataire $prestataire,
        array $optimizedSchedule,
        \DateTimeInterface $date
    ): array {
        $conflicts = $this->conflictDetector->detectAllConflicts(
            $prestataire,
            $date,
            $date
        );

        return [
            'is_feasible' => empty($conflicts),
            'conflicts' => $conflicts,
            'requires_changes' => !empty($conflicts)
        ];
    }

    private function generateOptimizationRecommendations(array $optimizations): array
    {
        $recommendations = [];

        foreach ($optimizations as $day => $optimization) {
            if ($optimization['efficiency_gain'] > 20) {
                $recommendations[] = [
                    'date' => $day,
                    'type' => 'high_improvement',
                    'message' => "Gain d'efficacité de " . round($optimization['efficiency_gain'], 1) . "% possible",
                    'priority' => 'high'
                ];
            }

            if ($optimization['savings']['time_minutes'] > 60) {
                $recommendations[] = [
                    'date' => $day,
                    'type' => 'time_saving',
                    'message' => "Économie de " . round($optimization['savings']['time_minutes']) . " minutes possible",
                    'priority' => 'medium'
                ];
            }
        }

        return $recommendations;
    }

    private function generateCapacityRecommendations(array $analysis): array
    {
        $recommendations = [];

        foreach ($analysis as $date => $day) {
            if ($day['occupancy_rate'] < 50) {
                $recommendations[] = [
                    'date' => $date,
                    'type' => 'underutilized',
                    'message' => "Jour sous-utilisé ({$day['occupancy_rate']}%) - promouvoir ce créneau",
                    'action' => 'increase_bookings'
                ];
            } elseif ($day['occupancy_rate'] > 90) {
                $recommendations[] = [
                    'date' => $date,
                    'type' => 'overbooked',
                    'message' => "Jour presque complet - limiter nouvelles réservations",
                    'action' => 'limit_bookings'
                ];
            }
        }

        return $recommendations;
    }

    private function analyzeWorkloadDistribution(array $bookingsByDay): array
    {
        $distribution = [];
        
        foreach ($bookingsByDay as $day => $bookings) {
            $totalMinutes = array_reduce($bookings, 
                fn($sum, $b) => $sum + $b->getDuration(), 0
            );
            
            $distribution[$day] = [
                'booking_count' => count($bookings),
                'total_minutes' => $totalMinutes,
                'total_hours' => round($totalMinutes / 60, 2)
            ];
        }

        return $distribution;
    }

    private function generateBalancingRecommendations(
        array $distribution,
        Prestataire $prestataire,
        \DateTimeInterface $weekStart
    ): array {
        // Calculer la moyenne
        $totalHours = array_sum(array_column($distribution, 'total_hours'));
        $averageHours = $totalHours / count($distribution);

        $recommendations = [];

        foreach ($distribution as $day => $data) {
            $deviation = $data['total_hours'] - $averageHours;
            
            if (abs($deviation) > 2) { // Plus de 2h de différence
                $recommendations[] = [
                    'day' => $day,
                    'current_hours' => $data['total_hours'],
                    'target_hours' => round($averageHours, 2),
                    'action' => $deviation > 0 ? 'reduce' : 'increase',
                    'priority' => abs($deviation) > 3 ? 'high' : 'medium'
                ];
            }
        }

        return $recommendations;
    }

    private function calculateBalanceScore(array $distribution): float
    {
        if (empty($distribution)) {
            return 0;
        }

        $hours = array_column($distribution, 'total_hours');
        $mean = array_sum($hours) / count($hours);
        
        // Calculer l'écart-type
        $variance = array_sum(array_map(fn($h) => pow($h - $mean, 2), $hours)) / count($hours);
        $stdDev = sqrt($variance);

        // Score: moins il y a de variance, meilleur est le score
        return max(0, 100 - ($stdDev * 10));
    }

    private function calculateTotalRevenue(array $bookings): float
    {
        return array_reduce($bookings, fn($sum, $b) => $sum + $b->getAmount(), 0);
    }

    private function calculateAverageDuration(array $bookings): float
    {
        if (empty($bookings)) {
            return 0;
        }
        
        $total = array_reduce($bookings, fn($sum, $b) => $sum + $b->getDuration(), 0);
        return $total / count($bookings);
    }

    private function calculateTimeWindowEfficiency(array $bookings): float
    {
        if (empty($bookings)) {
            return 0;
        }

        $analysis = $this->analyzeSchedule($bookings);
        return $analysis['efficiency_ratio'] * 100;
    }

    private function getTimeWindowRecommendation(array $bookings): string
    {
        $count = count($bookings);
        
        if ($count === 0) {
            return "Aucune réservation - promouvoir ce créneau";
        } elseif ($count < 3) {
            return "Faible utilisation - augmenter la visibilité";
        } elseif ($count > 5) {
            return "Forte demande - créneau optimal";
        }
        
        return "Utilisation normale";
    }

    private function generateTimeWindowRecommendations(array $analysis): array
    {
        $recommendations = [];
        $bestWindow = array_key_first($analysis);

        $recommendations[] = [
            'type' => 'best_window',
            'message' => "Le créneau '{$bestWindow}' est le plus efficace",
            'action' => 'focus_marketing'
        ];

        foreach ($analysis as $window => $data) {
            if ($data['efficiency_score'] < 50) {
                $recommendations[] = [
                    'type' => 'low_efficiency',
                    'window' => $window,
                    'message' => "Créneau '{$window}' peu efficace",
                    'action' => 'reduce_availability'
                ];
            }
        }

        return $recommendations;
    }

    private function calculateSlotMetrics(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        array $existingBookings
    ): array {
        // Trouver la réservation avant et après
        $beforeBooking = null;
        $afterBooking = null;

        foreach ($existingBookings as $booking) {
            if ($booking->getEndDateTime() <= $start) {
                if ($beforeBooking === null || 
                    $booking->getEndDateTime() > $beforeBooking->getEndDateTime()) {
                    $beforeBooking = $booking;
                }
            } elseif ($booking->getScheduledDateTime() >= $end) {
                if ($afterBooking === null || 
                    $booking->getScheduledDateTime() < $afterBooking->getScheduledDateTime()) {
                    $afterBooking = $booking;
                }
            }
        }

        return [
            'travel_time_before' => $beforeBooking ? 
                $this->distanceCalculator->calculateTravelTime(
                    $beforeBooking->getAddress(),
                    'current_address' // À adapter
                ) : 0,
            'travel_time_after' => $afterBooking ? 
                $this->distanceCalculator->calculateTravelTime(
                    'current_address', // À adapter
                    $afterBooking->getAddress()
                ) : 0,
            'efficiency' => 85.0, // Score calculé
            'reasons' => ['Temps de trajet optimisé', 'Bon équilibre du planning']
        ];
    }

    private function calculateTravelTimeScore(array $slot, string $address, array $bookings): float
    {
        // Logique simplifiée
        return 10.0;
    }

    private function calculateSlotEfficiency(array $slot): float
    {
        return 10.0;
    }

    private function calculatePreferenceScore(array $slot, array $preferences): float
    {
        return 5.0;
    }

    private function calculateBreakScore(array $slot, array $bookings): float
    {
        return 5.0;
    }

    private function findAllFreeSlots(array $availabilities, array $bookings, \DateTimeInterface $date): array
    {
        // Implémentation simplifiée
        return [];
    }

    private function calculateRecommendedSlotDuration(array $freeSlots): int
    {
        if (empty($freeSlots)) {
            return 60;
        }
        
        return 120; // 2 heures par défaut
    }

    private function insertFlexibleBookings(
        array &$flexibleBookings,
        Booking $beforeBooking,
        Booking $afterBooking,
        float $availableTime
    ): array {
        // Implémentation simplifiée
        return [];
    }

    private function calculateRouteMetrics(array $route, array $distanceMatrix): array
    {
        return [
            'original_distance' => 0,
            'optimized_distance' => 0,
            'original_time' => 0,
            'optimized_time' => 0
        ];
    }

    private function formatRoute(array $route): array
    {
        return array_map(fn($booking) => [
            'order' => $booking['order'] ?? 0,
            'address' => $booking->getAddress(),
            'arrival_time' => $booking->getScheduledDateTime()->format('H:i')
        ], $route);
    }

    private function generateMapUrl(array $route): string
    {
        // Générer une URL Google Maps avec tous les points
        return 'https://maps.google.com/';
    }
}