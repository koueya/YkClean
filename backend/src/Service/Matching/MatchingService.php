<?php

namespace App\Service\Matching;

use App\Entity\Service\ServiceRequest;
use App\Entity\User\Prestataire;
use App\Repository\User\PrestataireRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Repository\Planning\AvailabilityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de matching entre demandes de service et prestataires
 * 
 * Algorithme de scoring basé sur :
 * - Distance géographique (30%)
 * - Disponibilité (25%)
 * - Note moyenne (20%)
 * - Expérience (10%)
 * - Prix (10%)
 * - Taux de réponse (5%)
 */
class MatchingService
{
    private const EARTH_RADIUS_KM = 6371;
    
    // Poids des critères de matching (total = 100%)
    private const WEIGHTS = [
        'distance' => 0.30,          // 30% - Proximité géographique
        'availability' => 0.25,      // 25% - Disponibilité
        'rating' => 0.20,            // 20% - Note moyenne
        'experience' => 0.10,        // 10% - Expérience (nombre de services)
        'price' => 0.10,             // 10% - Tarif horaire
        'response_rate' => 0.05,     // 5% - Taux de réponse
    ];

    // Seuils pour le scoring
    private const DISTANCE_MAX_KM = 50;
    private const PRICE_TOLERANCE = 0.20; // 20% de tolérance sur le budget
    private const MIN_RATING = 3.0;
    private const MIN_SCORE_THRESHOLD = 40; // Score minimum pour être considéré

    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrestataireRepository $prestataireRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private AvailabilityRepository $availabilityRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Trouve les prestataires correspondant à une demande de service
     * 
     * @param ServiceRequest $serviceRequest
     * @param int $limit Nombre maximum de résultats
     * @param array $filters Filtres additionnels
     * @return array Liste de prestataires avec leur score
     */
    public function findMatchingPrestataires(
        ServiceRequest $serviceRequest,
        int $limit = 10,
        array $filters = []
    ): array {
        $category = $serviceRequest->getCategory();
        $address = $serviceRequest->getAddress();
        
        // Géocoder l'adresse du client
        $coordinates = $this->geocodeAddress($address);
        if (!$coordinates) {
            $this->logger->error('Failed to geocode address', [
                'service_request_id' => $serviceRequest->getId(),
                'address' => $address,
            ]);
            return [];
        }

        // Récupérer les prestataires éligibles
        $prestataires = $this->prestataireRepository->findEligibleForServiceRequest(
            $category,
            $coordinates['latitude'],
            $coordinates['longitude'],
            self::DISTANCE_MAX_KM
        );

        if (empty($prestataires)) {
            $this->logger->info('No eligible prestataires found', [
                'service_request_id' => $serviceRequest->getId(),
                'category' => $category->getName(),
            ]);
            return [];
        }

        // Calculer le score de matching pour chaque prestataire
        $scoredPrestataires = [];
        foreach ($prestataires as $prestataire) {
            $score = $this->calculateMatchingScore(
                $serviceRequest,
                $prestataire,
                $coordinates
            );

            // Filtrer par score minimum
            if ($score['total_score'] >= self::MIN_SCORE_THRESHOLD) {
                $scoredPrestataires[] = [
                    'prestataire' => $prestataire,
                    'score' => $score['total_score'],
                    'details' => $score['details'],
                ];
            }
        }

        // Appliquer les filtres additionnels
        if (!empty($filters)) {
            $scoredPrestataires = $this->applyFilters($scoredPrestataires, $filters);
        }

        // Trier par score décroissant
        usort($scoredPrestataires, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Limiter les résultats
        $results = array_slice($scoredPrestataires, 0, $limit);

        $this->logger->info('Matching completed', [
            'service_request_id' => $serviceRequest->getId(),
            'total_candidates' => count($prestataires),
            'matched_candidates' => count($scoredPrestataires),
            'returned_matches' => count($results),
        ]);

        return $results;
    }

    /**
     * Trouve les demandes de service correspondant à un prestataire
     * 
     * @param Prestataire $prestataire
     * @param int $limit
     * @return array Liste de demandes avec leur score
     */
    public function findMatchingRequests(
        Prestataire $prestataire,
        int $limit = 20
    ): array {
        // Récupérer les demandes de service ouvertes dans la zone du prestataire
        $prestataireAddress = $prestataire->getAddress();
        $coordinates = $this->geocodeAddress($prestataireAddress);
        
        if (!$coordinates) {
            $this->logger->error('Failed to geocode prestataire address', [
                'prestataire_id' => $prestataire->getId(),
            ]);
            return [];
        }

        // Trouver les demandes ouvertes
        $serviceRequests = $this->serviceRequestRepository->findOpenRequests(
            $prestataire->getServiceCategories()->toArray(),
            $coordinates['latitude'],
            $coordinates['longitude'],
            $prestataire->getRadius() ?? self::DISTANCE_MAX_KM
        );

        if (empty($serviceRequests)) {
            return [];
        }

        // Calculer le score pour chaque demande
        $scoredRequests = [];
        foreach ($serviceRequests as $request) {
            $requestCoordinates = $this->geocodeAddress($request->getAddress());
            if (!$requestCoordinates) {
                continue;
            }

            $score = $this->calculateMatchingScore($request, $prestataire, $requestCoordinates);

            if ($score['total_score'] >= self::MIN_SCORE_THRESHOLD) {
                $scoredRequests[] = [
                    'service_request' => $request,
                    'score' => $score['total_score'],
                    'details' => $score['details'],
                ];
            }
        }

        // Trier par score
        usort($scoredRequests, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        return array_slice($scoredRequests, 0, $limit);
    }

    /**
     * Calcule le score de matching entre une demande et un prestataire
     * 
     * @param ServiceRequest $serviceRequest
     * @param Prestataire $prestataire
     * @param array $clientCoordinates
     * @return array Score total et détails
     */
    private function calculateMatchingScore(
        ServiceRequest $serviceRequest,
        Prestataire $prestataire,
        array $clientCoordinates
    ): array {
        $scores = [];

        // 1. Score de distance (0-100)
        $distanceScore = $this->calculateDistanceScore(
            $prestataire,
            $clientCoordinates
        );
        $scores['distance'] = $distanceScore;

        // 2. Score de disponibilité (0-100)
        $availabilityScore = $this->calculateAvailabilityScore(
            $prestataire,
            $serviceRequest->getPreferredDate(),
            $serviceRequest->getAlternativeDates()
        );
        $scores['availability'] = $availabilityScore;

        // 3. Score de notation (0-100)
        $ratingScore = $this->calculateRatingScore($prestataire);
        $scores['rating'] = $ratingScore;

        // 4. Score d'expérience (0-100)
        $experienceScore = $this->calculateExperienceScore($prestataire);
        $scores['experience'] = $experienceScore;

        // 5. Score de prix (0-100)
        $priceScore = $this->calculatePriceScore(
            $prestataire,
            $serviceRequest->getBudget()
        );
        $scores['price'] = $priceScore;

        // 6. Score de taux de réponse (0-100)
        $responseRateScore = $this->calculateResponseRateScore($prestataire);
        $scores['response_rate'] = $responseRateScore;

        // Calculer le score total pondéré
        $totalScore = 0;
        foreach (self::WEIGHTS as $criterion => $weight) {
            $totalScore += $scores[$criterion] * $weight;
        }

        return [
            'total_score' => round($totalScore, 2),
            'details' => $scores,
        ];
    }

    /**
     * Calcule le score basé sur la distance
     */
    private function calculateDistanceScore(
        Prestataire $prestataire,
        array $clientCoordinates
    ): float {
        $prestataireAddress = $prestataire->getAddress();
        $prestataireCoordinates = $this->geocodeAddress($prestataireAddress);

        if (!$prestataireCoordinates) {
            return 0;
        }

        $distance = $this->calculateDistance(
            $prestataireCoordinates['latitude'],
            $prestataireCoordinates['longitude'],
            $clientCoordinates['latitude'],
            $clientCoordinates['longitude']
        );

        $maxRadius = $prestataire->getRadius() ?? self::DISTANCE_MAX_KM;

        // Si hors rayon, score = 0
        if ($distance > $maxRadius) {
            return 0;
        }

        // Score inversement proportionnel à la distance
        // Distance = 0 -> Score = 100
        // Distance = maxRadius -> Score = 0
        $score = max(0, 100 - ($distance / $maxRadius * 100));

        return round($score, 2);
    }

    /**
     * Calcule le score basé sur la disponibilité
     */
    private function calculateAvailabilityScore(
        Prestataire $prestataire,
        ?\DateTimeInterface $preferredDate,
        ?array $alternativeDates
    ): float {
        if (!$preferredDate) {
            return 50; // Score neutre si pas de date préférée
        }

        $dates = [$preferredDate];
        if ($alternativeDates) {
            $dates = array_merge($dates, $alternativeDates);
        }

        $availableCount = 0;
        foreach ($dates as $date) {
            if ($this->isPrestataireAvailable($prestataire, $date)) {
                $availableCount++;
            }
        }

        // Score proportionnel au nombre de dates disponibles
        $score = ($availableCount / count($dates)) * 100;

        return round($score, 2);
    }

    /**
     * Calcule le score basé sur la note moyenne
     */
    private function calculateRatingScore(Prestataire $prestataire): float
    {
        $averageRating = $prestataire->getAverageRating() ?? 0;

        // Si pas encore de notes, score neutre
        if ($averageRating == 0) {
            return 50;
        }

        // Si note < minimum acceptable, score = 0
        if ($averageRating < self::MIN_RATING) {
            return 0;
        }

        // Convertir note 1-5 en score 0-100
        // Note = 5 -> Score = 100
        // Note = 3 -> Score = 60
        $score = ($averageRating / 5) * 100;

        return round($score, 2);
    }

    /**
     * Calcule le score basé sur l'expérience
     */
    private function calculateExperienceScore(Prestataire $prestataire): float
    {
        $completedBookings = $prestataire->getCompletedBookingsCount() ?? 0;

        // Score basé sur le nombre de services complétés
        // 0 services -> 0
        // 10 services -> 50
        // 50+ services -> 100
        if ($completedBookings === 0) {
            return 0;
        } elseif ($completedBookings < 10) {
            return ($completedBookings / 10) * 50;
        } elseif ($completedBookings < 50) {
            return 50 + (($completedBookings - 10) / 40) * 50;
        } else {
            return 100;
        }
    }

    /**
     * Calcule le score basé sur le prix
     */
    private function calculatePriceScore(
        Prestataire $prestataire,
        ?float $budget
    ): float {
        $hourlyRate = $prestataire->getHourlyRate();

        // Si pas de budget défini, score neutre
        if (!$budget || $budget == 0) {
            return 50;
        }

        // Si pas de tarif défini, score neutre
        if (!$hourlyRate || $hourlyRate == 0) {
            return 50;
        }

        $difference = abs($hourlyRate - $budget);
        $tolerance = $budget * self::PRICE_TOLERANCE;

        // Si dans la tolérance, score élevé
        if ($difference <= $tolerance) {
            return 100;
        }

        // Si trop cher par rapport au budget
        if ($hourlyRate > $budget) {
            $excess = $hourlyRate - $budget;
            $score = max(0, 100 - ($excess / $budget * 100));
            return round($score, 2);
        }

        // Si moins cher que le budget (bon plan)
        return 100;
    }

    /**
     * Calcule le score basé sur le taux de réponse
     */
    private function calculateResponseRateScore(Prestataire $prestataire): float
    {
        $responseRate = $prestataire->getResponseRate() ?? 0;

        // Convertir taux de réponse (0-100%) directement en score
        return round($responseRate, 2);
    }

    /**
     * Vérifie si un prestataire est disponible à une date donnée
     */
    private function isPrestataireAvailable(
        Prestataire $prestataire,
        \DateTimeInterface $date
    ): bool {
        $availabilities = $this->availabilityRepository->findByPrestataireAndDate(
            $prestataire,
            $date
        );

        return !empty($availabilities);
    }

    /**
     * Calcule la distance entre deux points GPS (formule de Haversine)
     */
    private function calculateDistance(
        float $lat1,
        float $lon1,
        float $lat2,
        float $lon2
    ): float {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return self::EARTH_RADIUS_KM * $c;
    }

    /**
     * Géocode une adresse en coordonnées GPS
     * 
     * @param string $address
     * @return array|null ['latitude' => float, 'longitude' => float]
     */
    private function geocodeAddress(string $address): ?array
    {
        // TODO: Implémenter avec un service de géocodage (Google Maps API, OpenStreetMap, etc.)
        // Pour l'instant, retourner des coordonnées simulées
        
        // Option 1: Utiliser Google Maps Geocoding API
        // Option 2: Utiliser Nominatim (OpenStreetMap)
        // Option 3: Utiliser un service tiers
        
        // Exemple avec cache pour éviter les appels répétés
        $cacheKey = 'geocode_' . md5($address);
        
        // TODO: Vérifier le cache
        
        // TODO: Appel API de géocodage
        
        // Pour le développement, retourner des coordonnées par défaut
        // À remplacer par une vraie implémentation
        $this->logger->warning('Using mock geocoding - implement real geocoding service', [
            'address' => $address
        ]);

        // Coordonnées de Lyon par défaut (à remplacer)
        return [
            'latitude' => 45.764043,
            'longitude' => 4.835659,
        ];
    }

    /**
     * Applique des filtres additionnels sur les résultats
     */
    private function applyFilters(array $scoredPrestataires, array $filters): array
    {
        $filtered = $scoredPrestataires;

        // Filtre par note minimum
        if (isset($filters['min_rating'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                return $item['prestataire']->getAverageRating() >= $filters['min_rating'];
            });
        }

        // Filtre par prix maximum
        if (isset($filters['max_price'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                return $item['prestataire']->getHourlyRate() <= $filters['max_price'];
            });
        }

        // Filtre par disponibilité immédiate
        if (isset($filters['available_now']) && $filters['available_now']) {
            $filtered = array_filter($filtered, function ($item) {
                return $item['details']['availability'] >= 80;
            });
        }

        // Filtre par score minimum
        if (isset($filters['min_score'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                return $item['score'] >= $filters['min_score'];
            });
        }

        return array_values($filtered);
    }

    /**
     * Obtient des statistiques sur le matching
     */
    public function getMatchingStatistics(ServiceRequest $serviceRequest): array
    {
        $matches = $this->findMatchingPrestataires($serviceRequest, 100);

        if (empty($matches)) {
            return [
                'total_candidates' => 0,
                'average_score' => 0,
                'score_distribution' => [],
            ];
        }

        $scores = array_column($matches, 'score');
        
        $distribution = [
            'excellent' => 0,  // 80-100
            'good' => 0,       // 60-79
            'average' => 0,    // 40-59
            'poor' => 0,       // 0-39
        ];

        foreach ($scores as $score) {
            if ($score >= 80) {
                $distribution['excellent']++;
            } elseif ($score >= 60) {
                $distribution['good']++;
            } elseif ($score >= 40) {
                $distribution['average']++;
            } else {
                $distribution['poor']++;
            }
        }

        return [
            'total_candidates' => count($matches),
            'average_score' => round(array_sum($scores) / count($scores), 2),
            'max_score' => max($scores),
            'min_score' => min($scores),
            'score_distribution' => $distribution,
        ];
    }

    /**
     * Obtient les poids de matching configurés
     */
    public function getMatchingWeights(): array
    {
        return self::WEIGHTS;
    }

    /**
     * Configure les poids de matching (pour tests ou personnalisation)
     */
    public function setMatchingWeights(array $weights): void
    {
        // Valider que la somme fait 1.00 (100%)
        $sum = array_sum($weights);
        if (abs($sum - 1.0) > 0.01) {
            throw new \InvalidArgumentException(
                'La somme des poids doit être égale à 1.00 (actuellement: ' . $sum . ')'
            );
        }

        // TODO: Persister dans la configuration ou la base de données
        $this->logger->info('Matching weights updated', $weights);
    }
}