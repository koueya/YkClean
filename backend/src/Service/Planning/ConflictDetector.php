<?php

namespace App\Service\Planning;

use App\Entity\Planning\Availability;
use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Repository\Planning\AvailabilityRepository;
use App\Repository\Booking\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de détection de conflits dans le planning
 * Identifie les chevauchements, conflits et problèmes d'allocation
 */
class ConflictDetector
{
    // Types de conflits
    public const CONFLICT_TYPE_BOOKING_OVERLAP = 'booking_overlap';
    public const CONFLICT_TYPE_AVAILABILITY_MISSING = 'availability_missing';
    public const CONFLICT_TYPE_DOUBLE_BOOKING = 'double_booking';
    public const CONFLICT_TYPE_TRAVEL_TIME = 'travel_time';
    public const CONFLICT_TYPE_MAX_HOURS = 'max_hours_exceeded';
    public const CONFLICT_TYPE_BREAK_MISSING = 'break_missing';
    public const CONFLICT_TYPE_REPLACEMENT_CONFLICT = 'replacement_conflict';

    // Paramètres de configuration
    private const MIN_BREAK_DURATION = 30; // minutes
    private const MIN_TRAVEL_TIME = 15; // minutes
    private const MAX_DAILY_HOURS = 10; // heures
    private const MAX_WEEKLY_HOURS = 48; // heures

    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityRepository $availabilityRepository,
        private BookingRepository $bookingRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Détecte tous les conflits pour un prestataire sur une période
     */
    public function detectAllConflicts(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];

        // 1. Conflits de réservations qui se chevauchent
        $conflicts = array_merge(
            $conflicts,
            $this->detectBookingOverlaps($prestataire, $startDate, $endDate)
        );

        // 2. Réservations en dehors des disponibilités
        $conflicts = array_merge(
            $conflicts,
            $this->detectBookingsOutsideAvailability($prestataire, $startDate, $endDate)
        );

        // 3. Temps de trajet insuffisant
        $conflicts = array_merge(
            $conflicts,
            $this->detectTravelTimeConflicts($prestataire, $startDate, $endDate)
        );

        // 4. Dépassement des heures maximales
        $conflicts = array_merge(
            $conflicts,
            $this->detectMaxHoursViolations($prestataire, $startDate, $endDate)
        );

        // 5. Pauses manquantes
        $conflicts = array_merge(
            $conflicts,
            $this->detectMissingBreaks($prestataire, $startDate, $endDate)
        );

        // 6. Conflits de remplacement
        $conflicts = array_merge(
            $conflicts,
            $this->detectReplacementConflicts($prestataire, $startDate, $endDate)
        );

        // Tri par date et gravité
        usort($conflicts, function($a, $b) {
            $severityOrder = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
            
            $dateCompare = $a['date'] <=> $b['date'];
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return $severityOrder[$a['severity']] <=> $severityOrder[$b['severity']];
        });

        $this->logger->info('Conflicts detected', [
            'prestataire_id' => $prestataire->getId(),
            'period' => $startDate->format('Y-m-d') . ' to ' . $endDate->format('Y-m-d'),
            'total_conflicts' => count($conflicts)
        ]);

        return $conflicts;
    }

    /**
     * Vérifie si une nouvelle réservation créerait un conflit
     */
    public function wouldCreateConflict(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime,
        ?string $address = null,
        ?Booking $excludeBooking = null
    ): array {
        $conflicts = [];

        // 1. Vérifier le chevauchement avec d'autres réservations
        $overlapConflict = $this->checkBookingOverlap(
            $prestataire,
            $startDateTime,
            $endDateTime,
            $excludeBooking
        );
        if ($overlapConflict) {
            $conflicts[] = $overlapConflict;
        }

        // 2. Vérifier la disponibilité
        $availabilityConflict = $this->checkAvailabilityForBooking(
            $prestataire,
            $startDateTime,
            $endDateTime
        );
        if ($availabilityConflict) {
            $conflicts[] = $availabilityConflict;
        }

        // 3. Vérifier le temps de trajet avec la réservation précédente
        if ($address) {
            $travelConflict = $this->checkTravelTimeForNewBooking(
                $prestataire,
                $startDateTime,
                $address
            );
            if ($travelConflict) {
                $conflicts[] = $travelConflict;
            }
        }

        // 4. Vérifier les heures maximales journalières
        $maxHoursConflict = $this->checkDailyMaxHours(
            $prestataire,
            $startDateTime,
            $endDateTime
        );
        if ($maxHoursConflict) {
            $conflicts[] = $maxHoursConflict;
        }

        // 5. Vérifier les pauses obligatoires
        $breakConflict = $this->checkBreakRequirement(
            $prestataire,
            $startDateTime,
            $endDateTime
        );
        if ($breakConflict) {
            $conflicts[] = $breakConflict;
        }

        return $conflicts;
    }

    /**
     * Vérifie si deux créneaux horaires se chevauchent
     */
    public function doTimeSlotsOverlap(
        \DateTimeInterface $start1,
        \DateTimeInterface $end1,
        \DateTimeInterface $start2,
        \DateTimeInterface $end2
    ): bool {
        return $start1 < $end2 && $start2 < $end1;
    }

    /**
     * Calcule le temps minimum nécessaire entre deux réservations
     */
    public function calculateMinimumTimeBetween(
        Booking $booking1,
        Booking $booking2,
        bool $includeTravelTime = true
    ): int {
        $minTime = 0;

        if ($includeTravelTime) {
            // Calculer le temps de trajet estimé entre les deux adresses
            $travelTime = $this->estimateTravelTime(
                $booking1->getAddress(),
                $booking2->getAddress()
            );
            $minTime += $travelTime;
        }

        // Ajouter un buffer minimum
        $minTime += self::MIN_TRAVEL_TIME;

        return $minTime; // en minutes
    }

    /**
     * Trouve des suggestions pour résoudre un conflit
     */
    public function suggestConflictResolutions(array $conflict): array {
        $suggestions = [];

        switch ($conflict['type']) {
            case self::CONFLICT_TYPE_BOOKING_OVERLAP:
                $suggestions = $this->suggestOverlapResolutions($conflict);
                break;

            case self::CONFLICT_TYPE_AVAILABILITY_MISSING:
                $suggestions = $this->suggestAvailabilityResolutions($conflict);
                break;

            case self::CONFLICT_TYPE_TRAVEL_TIME:
                $suggestions = $this->suggestTravelTimeResolutions($conflict);
                break;

            case self::CONFLICT_TYPE_MAX_HOURS:
                $suggestions = $this->suggestMaxHoursResolutions($conflict);
                break;

            case self::CONFLICT_TYPE_BREAK_MISSING:
                $suggestions = $this->suggestBreakResolutions($conflict);
                break;

            case self::CONFLICT_TYPE_DOUBLE_BOOKING:
                $suggestions = $this->suggestDoubleBookingResolutions($conflict);
                break;
        }

        return $suggestions;
    }

    /**
     * Génère un rapport de conflits détaillé
     */
    public function generateConflictReport(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = $this->detectAllConflicts($prestataire, $startDate, $endDate);

        // Regrouper par type
        $conflictsByType = [];
        foreach ($conflicts as $conflict) {
            $type = $conflict['type'];
            if (!isset($conflictsByType[$type])) {
                $conflictsByType[$type] = [];
            }
            $conflictsByType[$type][] = $conflict;
        }

        // Regrouper par gravité
        $conflictsBySeverity = [];
        foreach ($conflicts as $conflict) {
            $severity = $conflict['severity'];
            if (!isset($conflictsBySeverity[$severity])) {
                $conflictsBySeverity[$severity] = [];
            }
            $conflictsBySeverity[$severity][] = $conflict;
        }

        // Calculer des statistiques
        return [
            'prestataire_id' => $prestataire->getId(),
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d')
            ],
            'summary' => [
                'total_conflicts' => count($conflicts),
                'critical' => count($conflictsBySeverity['critical'] ?? []),
                'high' => count($conflictsBySeverity['high'] ?? []),
                'medium' => count($conflictsBySeverity['medium'] ?? []),
                'low' => count($conflictsBySeverity['low'] ?? [])
            ],
            'by_type' => array_map(fn($arr) => count($arr), $conflictsByType),
            'conflicts' => $conflicts,
            'conflicts_by_type' => $conflictsByType,
            'conflicts_by_severity' => $conflictsBySeverity,
            'generated_at' => new \DateTimeImmutable()
        ];
    }

    /**
     * Valide un planning complet avant sauvegarde
     */
    public function validateSchedule(
        Prestataire $prestataire,
        array $proposedBookings
    ): array {
        $errors = [];

        // Trier les réservations par date
        usort($proposedBookings, fn($a, $b) => 
            $a['start_date_time'] <=> $b['start_date_time']
        );

        foreach ($proposedBookings as $index => $booking) {
            // Vérifier chaque réservation individuellement
            $bookingConflicts = $this->wouldCreateConflict(
                $prestataire,
                $booking['start_date_time'],
                $booking['end_date_time'],
                $booking['address'] ?? null
            );

            if (!empty($bookingConflicts)) {
                $errors[] = [
                    'booking_index' => $index,
                    'booking_data' => $booking,
                    'conflicts' => $bookingConflicts
                ];
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'total_bookings' => count($proposedBookings),
            'valid_bookings' => count($proposedBookings) - count($errors)
        ];
    }

    // ============ MÉTHODES PRIVÉES - DÉTECTION ============

    /**
     * Détecte les chevauchements de réservations
     */
    private function detectBookingOverlaps(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];
        
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        for ($i = 0; $i < count($bookings); $i++) {
            for ($j = $i + 1; $j < count($bookings); $j++) {
                $booking1 = $bookings[$i];
                $booking2 = $bookings[$j];

                if ($this->doTimeSlotsOverlap(
                    $booking1->getScheduledDateTime(),
                    $booking1->getEndDateTime(),
                    $booking2->getScheduledDateTime(),
                    $booking2->getEndDateTime()
                )) {
                    $conflicts[] = [
                        'type' => self::CONFLICT_TYPE_BOOKING_OVERLAP,
                        'severity' => 'critical',
                        'date' => $booking1->getScheduledDateTime(),
                        'message' => 'Deux réservations se chevauchent',
                        'details' => [
                            'booking1_id' => $booking1->getId(),
                            'booking1_time' => $booking1->getScheduledDateTime()->format('H:i') . ' - ' . 
                                             $booking1->getEndDateTime()->format('H:i'),
                            'booking2_id' => $booking2->getId(),
                            'booking2_time' => $booking2->getScheduledDateTime()->format('H:i') . ' - ' . 
                                             $booking2->getEndDateTime()->format('H:i')
                        ]
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Détecte les réservations en dehors des disponibilités
     */
    private function detectBookingsOutsideAvailability(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];
        
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        foreach ($bookings as $booking) {
            $dayOfWeek = (int) $booking->getScheduledDateTime()->format('w');
            
            $availabilities = $this->availabilityRepository->findForDayAndTime(
                $prestataire,
                $dayOfWeek,
                $booking->getScheduledDateTime(),
                $booking->getEndDateTime()
            );

            if (empty($availabilities)) {
                $conflicts[] = [
                    'type' => self::CONFLICT_TYPE_AVAILABILITY_MISSING,
                    'severity' => 'high',
                    'date' => $booking->getScheduledDateTime(),
                    'message' => 'Réservation en dehors des disponibilités',
                    'details' => [
                        'booking_id' => $booking->getId(),
                        'scheduled_time' => $booking->getScheduledDateTime()->format('Y-m-d H:i'),
                        'duration' => $booking->getDuration() . ' minutes'
                    ]
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Détecte les problèmes de temps de trajet
     */
    private function detectTravelTimeConflicts(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];
        
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate,
            ['scheduledDateTime' => 'ASC']
        );

        for ($i = 0; $i < count($bookings) - 1; $i++) {
            $currentBooking = $bookings[$i];
            $nextBooking = $bookings[$i + 1];

            // Vérifier si les réservations sont le même jour
            if ($currentBooking->getScheduledDateTime()->format('Y-m-d') !== 
                $nextBooking->getScheduledDateTime()->format('Y-m-d')) {
                continue;
            }

            $timeBetween = ($nextBooking->getScheduledDateTime()->getTimestamp() - 
                          $currentBooking->getEndDateTime()->getTimestamp()) / 60;

            $requiredTravelTime = $this->estimateTravelTime(
                $currentBooking->getAddress(),
                $nextBooking->getAddress()
            );

            if ($timeBetween < $requiredTravelTime) {
                $conflicts[] = [
                    'type' => self::CONFLICT_TYPE_TRAVEL_TIME,
                    'severity' => 'medium',
                    'date' => $currentBooking->getScheduledDateTime(),
                    'message' => 'Temps de trajet insuffisant entre deux réservations',
                    'details' => [
                        'from_booking_id' => $currentBooking->getId(),
                        'to_booking_id' => $nextBooking->getId(),
                        'available_time' => round($timeBetween) . ' minutes',
                        'required_time' => $requiredTravelTime . ' minutes',
                        'missing_time' => round($requiredTravelTime - $timeBetween) . ' minutes'
                    ]
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Détecte les dépassements d'heures maximales
     */
    private function detectMaxHoursViolations(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];
        
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        // Grouper par jour
        $bookingsByDay = [];
        foreach ($bookings as $booking) {
            $day = $booking->getScheduledDateTime()->format('Y-m-d');
            if (!isset($bookingsByDay[$day])) {
                $bookingsByDay[$day] = [];
            }
            $bookingsByDay[$day][] = $booking;
        }

        // Vérifier chaque jour
        foreach ($bookingsByDay as $day => $dayBookings) {
            $totalMinutes = array_reduce($dayBookings, 
                fn($sum, $b) => $sum + $b->getDuration(), 0
            );
            
            $totalHours = $totalMinutes / 60;

            if ($totalHours > self::MAX_DAILY_HOURS) {
                $conflicts[] = [
                    'type' => self::CONFLICT_TYPE_MAX_HOURS,
                    'severity' => 'high',
                    'date' => new \DateTime($day),
                    'message' => 'Dépassement des heures maximales journalières',
                    'details' => [
                        'date' => $day,
                        'total_hours' => round($totalHours, 2),
                        'max_hours' => self::MAX_DAILY_HOURS,
                        'excess_hours' => round($totalHours - self::MAX_DAILY_HOURS, 2),
                        'booking_count' => count($dayBookings)
                    ]
                ];
            }
        }

        // Vérifier hebdomadairement
        $weeklyConflicts = $this->detectWeeklyMaxHoursViolations(
            $bookings,
            $startDate,
            $endDate
        );
        $conflicts = array_merge($conflicts, $weeklyConflicts);

        return $conflicts;
    }

    /**
     * Détecte les pauses manquantes
     */
    private function detectMissingBreaks(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];
        
        $bookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate,
            ['scheduledDateTime' => 'ASC']
        );

        // Grouper par jour
        $bookingsByDay = [];
        foreach ($bookings as $booking) {
            $day = $booking->getScheduledDateTime()->format('Y-m-d');
            if (!isset($bookingsByDay[$day])) {
                $bookingsByDay[$day] = [];
            }
            $bookingsByDay[$day][] = $booking;
        }

        foreach ($bookingsByDay as $day => $dayBookings) {
            $consecutiveMinutes = 0;
            $lastEndTime = null;

            foreach ($dayBookings as $booking) {
                if ($lastEndTime) {
                    $breakTime = ($booking->getScheduledDateTime()->getTimestamp() - 
                                $lastEndTime->getTimestamp()) / 60;
                    
                    if ($breakTime < self::MIN_BREAK_DURATION) {
                        $consecutiveMinutes += $booking->getDuration();
                    } else {
                        $consecutiveMinutes = $booking->getDuration();
                    }
                } else {
                    $consecutiveMinutes = $booking->getDuration();
                }

                // Si plus de 6 heures consécutives sans pause de 30 min
                if ($consecutiveMinutes > 360 && 
                    ($lastEndTime === null || 
                     ($booking->getScheduledDateTime()->getTimestamp() - $lastEndTime->getTimestamp()) / 60 < self::MIN_BREAK_DURATION)) {
                    
                    $conflicts[] = [
                        'type' => self::CONFLICT_TYPE_BREAK_MISSING,
                        'severity' => 'medium',
                        'date' => new \DateTime($day),
                        'message' => 'Pause obligatoire manquante après 6 heures de travail',
                        'details' => [
                            'date' => $day,
                            'consecutive_minutes' => $consecutiveMinutes,
                            'booking_id' => $booking->getId()
                        ]
                    ];
                }

                $lastEndTime = $booking->getEndDateTime();
            }
        }

        return $conflicts;
    }

    /**
     * Détecte les conflits de remplacement
     */
    private function detectReplacementConflicts(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];

        // À implémenter selon votre logique de remplacement
        // Vérifier si un prestataire de remplacement a ses propres réservations
        
        return $conflicts;
    }

    /**
     * Vérifie le chevauchement avec d'autres réservations
     */
    private function checkBookingOverlap(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime,
        ?Booking $excludeBooking = null
    ): ?array {
        $existingBookings = $this->bookingRepository->findOverlappingBookings(
            $prestataire,
            $startDateTime,
            $endDateTime,
            $excludeBooking?->getId()
        );

        if (!empty($existingBookings)) {
            return [
                'type' => self::CONFLICT_TYPE_DOUBLE_BOOKING,
                'severity' => 'critical',
                'message' => 'Une autre réservation existe déjà sur ce créneau',
                'details' => [
                    'conflicting_bookings' => array_map(
                        fn($b) => [
                            'id' => $b->getId(),
                            'time' => $b->getScheduledDateTime()->format('H:i') . ' - ' . 
                                    $b->getEndDateTime()->format('H:i')
                        ],
                        $existingBookings
                    )
                ]
            ];
        }

        return null;
    }

    /**
     * Vérifie si la disponibilité existe pour cette réservation
     */
    private function checkAvailabilityForBooking(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime
    ): ?array {
        $dayOfWeek = (int) $startDateTime->format('w');
        
        $availabilities = $this->availabilityRepository->findForDayAndTime(
            $prestataire,
            $dayOfWeek,
            $startDateTime,
            $endDateTime
        );

        if (empty($availabilities)) {
            return [
                'type' => self::CONFLICT_TYPE_AVAILABILITY_MISSING,
                'severity' => 'high',
                'message' => 'Aucune disponibilité configurée pour ce créneau',
                'details' => [
                    'requested_time' => $startDateTime->format('Y-m-d H:i') . ' - ' . 
                                       $endDateTime->format('H:i'),
                    'day_of_week' => $dayOfWeek
                ]
            ];
        }

        return null;
    }

    /**
     * Vérifie le temps de trajet pour une nouvelle réservation
     */
    private function checkTravelTimeForNewBooking(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        string $address
    ): ?array {
        // Trouver la réservation juste avant
        $previousBooking = $this->bookingRepository->findPreviousBooking(
            $prestataire,
            $startDateTime
        );

        if ($previousBooking) {
            $timeBetween = ($startDateTime->getTimestamp() - 
                          $previousBooking->getEndDateTime()->getTimestamp()) / 60;

            $requiredTravelTime = $this->estimateTravelTime(
                $previousBooking->getAddress(),
                $address
            );

            if ($timeBetween < $requiredTravelTime) {
                return [
                    'type' => self::CONFLICT_TYPE_TRAVEL_TIME,
                    'severity' => 'medium',
                    'message' => 'Temps de trajet insuffisant depuis la réservation précédente',
                    'details' => [
                        'previous_booking_id' => $previousBooking->getId(),
                        'available_time' => round($timeBetween) . ' minutes',
                        'required_time' => $requiredTravelTime . ' minutes'
                    ]
                ];
            }
        }

        return null;
    }

    /**
     * Vérifie les heures maximales journalières
     */
    private function checkDailyMaxHours(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime
    ): ?array {
        $day = $startDateTime->format('Y-m-d');
        $dayStart = new \DateTime($day . ' 00:00:00');
        $dayEnd = new \DateTime($day . ' 23:59:59');

        $existingBookings = $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $dayStart,
            $dayEnd
        );

        $totalMinutes = array_reduce($existingBookings, 
            fn($sum, $b) => $sum + $b->getDuration(), 0
        );

        $newDuration = ($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60;
        $totalWithNew = ($totalMinutes + $newDuration) / 60;

        if ($totalWithNew > self::MAX_DAILY_HOURS) {
            return [
                'type' => self::CONFLICT_TYPE_MAX_HOURS,
                'severity' => 'high',
                'message' => 'Cette réservation dépasserait les heures maximales journalières',
                'details' => [
                    'current_hours' => round($totalMinutes / 60, 2),
                    'new_booking_hours' => round($newDuration / 60, 2),
                    'total_hours' => round($totalWithNew, 2),
                    'max_hours' => self::MAX_DAILY_HOURS
                ]
            ];
        }

        return null;
    }

    /**
     * Vérifie les pauses obligatoires
     */
    private function checkBreakRequirement(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime
    ): ?array {
        // Logique simplifiée - à adapter selon vos besoins
        $duration = ($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60;

        if ($duration > 360) { // Plus de 6 heures
            return [
                'type' => self::CONFLICT_TYPE_BREAK_MISSING,
                'severity' => 'low',
                'message' => 'Réservation longue: prévoir une pause',
                'details' => [
                    'duration_minutes' => $duration,
                    'recommendation' => 'Prévoir une pause de ' . self::MIN_BREAK_DURATION . ' minutes'
                ]
            ];
        }

        return null;
    }

    /**
     * Détecte les violations hebdomadaires d'heures max
     */
    private function detectWeeklyMaxHoursViolations(
        array $bookings,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $conflicts = [];
        $bookingsByWeek = [];

        foreach ($bookings as $booking) {
            $week = $booking->getScheduledDateTime()->format('Y-W');
            if (!isset($bookingsByWeek[$week])) {
                $bookingsByWeek[$week] = [];
            }
            $bookingsByWeek[$week][] = $booking;
        }

        foreach ($bookingsByWeek as $week => $weekBookings) {
            $totalMinutes = array_reduce($weekBookings, 
                fn($sum, $b) => $sum + $b->getDuration(), 0
            );
            
            $totalHours = $totalMinutes / 60;

            if ($totalHours > self::MAX_WEEKLY_HOURS) {
                $conflicts[] = [
                    'type' => self::CONFLICT_TYPE_MAX_HOURS,
                    'severity' => 'high',
                    'date' => $weekBookings[0]->getScheduledDateTime(),
                    'message' => 'Dépassement des heures maximales hebdomadaires',
                    'details' => [
                        'week' => $week,
                        'total_hours' => round($totalHours, 2),
                        'max_hours' => self::MAX_WEEKLY_HOURS,
                        'excess_hours' => round($totalHours - self::MAX_WEEKLY_HOURS, 2)
                    ]
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Estime le temps de trajet entre deux adresses
     */
    private function estimateTravelTime(string $address1, string $address2): int
    {
        // Implémentation simplifiée
        // Dans la réalité, utiliser une API de géolocalisation (Google Maps, Here, etc.)
        
        if ($address1 === $address2) {
            return 0;
        }

        // Estimation basique: 15 minutes par défaut
        return self::MIN_TRAVEL_TIME;
    }

    // ============ MÉTHODES PRIVÉES - SUGGESTIONS ============

    private function suggestOverlapResolutions(array $conflict): array
    {
        return [
            [
                'action' => 'reschedule',
                'description' => 'Décaler l\'une des réservations',
                'priority' => 1
            ],
            [
                'action' => 'cancel',
                'description' => 'Annuler l\'une des réservations',
                'priority' => 2
            ],
            [
                'action' => 'assign_replacement',
                'description' => 'Assigner un prestataire de remplacement',
                'priority' => 3
            ]
        ];
    }

    private function suggestAvailabilityResolutions(array $conflict): array
    {
        return [
            [
                'action' => 'add_availability',
                'description' => 'Ajouter une disponibilité pour ce créneau',
                'priority' => 1
            ],
            [
                'action' => 'reschedule',
                'description' => 'Déplacer la réservation vers un créneau disponible',
                'priority' => 2
            ]
        ];
    }

    private function suggestTravelTimeResolutions(array $conflict): array
    {
        return [
            [
                'action' => 'adjust_time',
                'description' => 'Ajuster l\'heure de début pour ajouter du temps de trajet',
                'priority' => 1
            ],
            [
                'action' => 'optimize_route',
                'description' => 'Optimiser l\'ordre des réservations',
                'priority' => 2
            ]
        ];
    }

    private function suggestMaxHoursResolutions(array $conflict): array
    {
        return [
            [
                'action' => 'reschedule',
                'description' => 'Déplacer certaines réservations à un autre jour',
                'priority' => 1
            ],
            [
                'action' => 'assign_replacement',
                'description' => 'Assigner certaines réservations à un autre prestataire',
                'priority' => 2
            ]
        ];
    }

    private function suggestBreakResolutions(array $conflict): array
    {
        return [
            [
                'action' => 'add_break',
                'description' => 'Ajouter une pause entre les réservations',
                'priority' => 1
            ],
            [
                'action' => 'adjust_schedule',
                'description' => 'Réorganiser le planning pour inclure des pauses',
                'priority' => 2
            ]
        ];
    }

    private function suggestDoubleBookingResolutions(array $conflict): array
    {
        return [
            [
                'action' => 'cancel_one',
                'description' => 'Choisir quelle réservation conserver',
                'priority' => 1
            ],
            [
                'action' => 'assign_replacement',
                'description' => 'Assigner un prestataire de remplacement pour l\'une',
                'priority' => 2
            ]
        ];
    }
}