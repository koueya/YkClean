<?php

namespace App\Service\Matching;

use App\Entity\Service\ServiceRequest;
use App\Entity\User\Prestataire;
use App\Repository\User\PrestataireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class PrestataireMatchingService
{
    private EntityManagerInterface $entityManager;
    private PrestataireRepository $prestataireRepository;
    private LoggerInterface $logger;
    private array $weights;

    public function __construct(
        EntityManagerInterface $entityManager,
        PrestataireRepository $prestataireRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->prestataireRepository = $prestataireRepository;
        $this->logger = $logger;

        // Poids des critères de matching (total = 1.00)
        $this->weights = [
            'distance' => 0.30,          // 30% - Proximité géographique
            'availability' => 0.25,      // 25% - Disponibilité
            'rating' => 0.20,            // 20% - Note moyenne
            'experience' => 0.10,        // 10% - Expérience (nombre de services)
            'price' => 0.10,             // 10% - Tarif horaire
            'response_rate' => 0.05,     // 5% - Taux de réponse
        ];
    }

    /**
     * Trouver les meilleurs prestataires pour une demande de service
     */
    public function findBestMatches(
        ServiceRequest $serviceRequest,
        int $limit = 10,
        array $filters = []
    ): array {
        $category = $serviceRequest->getCategory();
        $address = $serviceRequest->getAddress();
        $coordinates = $this->geocodeAddress($address);

        if (!$coordinates) {
            $this->logger->error('Failed to geocode address', [
                'service_request_id' => $serviceRequest->getId(),
                'address' => $address,
            ]);
            return [];
        }

        // Récupérer les prestataires éligibles
        $prestataires = $this->prestataireRepository->findEligiblePrestataires(
            $category,
            $coordinates['latitude'],
            $coordinates['longitude'],
            $filters
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

            $scoredPrestataires[] = [
                'prestataire' => $prestataire,
                'score' => $score['total_score'],
                'details' => $score['details'],
            ];
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
            'returned_matches' => count($results),
        ]);

        return $results;
    }

    /**
     * Calculer le score de matching entre un service et un prestataire
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

        // Calcul du score total pondéré
        $totalScore = 0;
        foreach ($scores as $criterion => $score) {
            $totalScore += $score * $this->weights[$criterion];
        }

        return [
            'total_score' => round($totalScore, 2),
            'details' => $scores,
        ];
    }

    /**
     * Score basé sur la distance (plus proche = meilleur score)
     */
    private function calculateDistanceScore(
        Prestataire $prestataire,
        array $clientCoordinates
    ): float {
        $prestataireCoordinates = [
            'latitude' => $prestataire->getLatitude(),
            'longitude' => $prestataire->getLongitude(),
        ];

        if (!$prestataireCoordinates['latitude'] || !$prestataireCoordinates['longitude']) {
            return 50.0; // Score moyen si pas de coordonnées
        }

        $distance = $this->calculateDistance(
            $clientCoordinates,
            $prestataireCoordinates
        );

        $maxRadius = $prestataire->getRadius() ?? 20; // rayon max en km

        // Si le prestataire est hors de son rayon d'intervention, score = 0
        if ($distance > $maxRadius) {
            return 0.0;
        }

        // Score inversement proportionnel à la distance
        // 0km = 100, maxRadius = 50
        $score = 100 - (($distance / $maxRadius) * 50);
        
        return max(0, min(100, $score));
    }

    /**
     * Score basé sur la disponibilité
     */
    private function calculateAvailabilityScore(
        Prestataire $prestataire,
        ?\DateTime $preferredDate,
        ?array $alternativeDates
    ): float {
        if (!$preferredDate) {
            return 80.0; // Score par défaut si pas de date spécifiée
        }

        $availabilities = $prestataire->getAvailabilities();
        
        if ($availabilities->isEmpty()) {
            return 30.0; // Faible score si pas de disponibilités configurées
        }

        // Vérifier si le prestataire est disponible à la date préférée
        $isAvailablePreferred = $this->isAvailableOnDate(
            $prestataire,
            $preferredDate
        );

        if ($isAvailablePreferred) {
            return 100.0;
        }

        // Vérifier les dates alternatives
        if ($alternativeDates) {
            foreach ($alternativeDates as $altDate) {
                if ($this->isAvailableOnDate($prestataire, $altDate)) {
                    return 80.0; // Disponible sur une date alternative
                }
            }
        }

        // Calcul de la disponibilité générale (nombre de créneaux disponibles)
        $availableSlots = count($availabilities);
        $score = min(60, $availableSlots * 10); // Max 60 si pas dispo aux dates demandées

        return $score;
    }

    /**
     * Score basé sur la note moyenne
     */
    private function calculateRatingScore(Prestataire $prestataire): float
    {
        $averageRating = $prestataire->getAverageRating();
        
        if ($averageRating === null || $averageRating === 0.0) {
            return 50.0; // Score moyen si pas encore noté
        }

        // Conversion de la note (0-5) en score (0-100)
        // 5/5 = 100, 4/5 = 80, 3/5 = 60, etc.
        return ($averageRating / 5) * 100;
    }

    /**
     * Score basé sur l'expérience (nombre de services effectués)
     */
    private function calculateExperienceScore(Prestataire $prestataire): float
    {
        $completedBookings = $prestataire->getCompletedBookingsCount();

        if ($completedBookings === 0) {
            return 30.0; // Score faible pour débutant
        }

        // Score progressif selon l'expérience
        // 0-5 services: 30-50
        // 5-20 services: 50-70
        // 20-50 services: 70-85
        // 50+ services: 85-100

        if ($completedBookings <= 5) {
            return 30 + ($completedBookings * 4);
        } elseif ($completedBookings <= 20) {
            return 50 + (($completedBookings - 5) * 1.33);
        } elseif ($completedBookings <= 50) {
            return 70 + (($completedBookings - 20) * 0.5);
        } else {
            return min(100, 85 + (($completedBookings - 50) * 0.3));
        }
    }

    /**
     * Score basé sur le prix (plus proche du budget = meilleur score)
     */
    private function calculatePriceScore(
        Prestataire $prestataire,
        ?float $budget
    ): float {
        $hourlyRate = $prestataire->getHourlyRate();

        if (!$budget || !$hourlyRate) {
            return 70.0; // Score par défaut
        }

        // Calculer l'écart entre le taux horaire et le budget estimé
        $difference = abs($hourlyRate - $budget);
        $percentDifference = ($difference / $budget) * 100;

        // Score inversement proportionnel à la différence
        if ($percentDifference <= 10) {
            return 100.0; // Très proche du budget
        } elseif ($percentDifference <= 20) {
            return 85.0;
        } elseif ($percentDifference <= 30) {
            return 70.0;
        } elseif ($percentDifference <= 50) {
            return 50.0;
        } else {
            return 30.0; // Trop éloigné du budget
        }
    }

    /**
     * Score basé sur le taux de réponse aux demandes
     */
    private function calculateResponseRateScore(Prestataire $prestataire): float
    {
        $responseRate = $prestataire->getResponseRate();

        if ($responseRate === null) {
            return 70.0; // Score par défaut pour nouveau prestataire
        }

        // Conversion directe du taux de réponse (0-1) en score (0-100)
        return $responseRate * 100;
    }

    /**
     * Calculer la distance entre deux points géographiques (formule de Haversine)
     */
    private function calculateDistance(array $point1, array $point2): float
    {
        $earthRadius = 6371; // Rayon de la Terre en km

        $lat1 = deg2rad($point1['latitude']);
        $lon1 = deg2rad($point1['longitude']);
        $lat2 = deg2rad($point2['latitude']);
        $lon2 = deg2rad($point2['longitude']);

        $deltaLat = $lat2 - $lat1;
        $deltaLon = $lon2 - $lon1;

        $a = sin($deltaLat / 2) * sin($deltaLat / 2) +
             cos($lat1) * cos($lat2) *
             sin($deltaLon / 2) * sin($deltaLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Géocoder une adresse pour obtenir les coordonnées
     */
    private function geocodeAddress(string $address): ?array
    {
        // Utiliser un service de géocodage (Google Maps, OpenStreetMap, etc.)
        // Pour l'exemple, on retourne un résultat fictif
        
        // TODO: Implémenter l'appel à l'API de géocodage
        // Exemple avec Google Geocoding API:
        /*
        $apiKey = 'YOUR_GOOGLE_API_KEY';
        $url = sprintf(
            'https://maps.googleapis.com/maps/api/geocode/json?address=%s&key=%s',
            urlencode($address),
            $apiKey
        );
        
        $response = file_get_contents($url);
        $data = json_decode($response, true);
        
        if ($data['status'] === 'OK') {
            $location = $data['results'][0]['geometry']['location'];
            return [
                'latitude' => $location['lat'],
                'longitude' => $location['lng'],
            ];
        }
        */

        // Pour l'instant, retourner des coordonnées de Paris par défaut
        return [
            'latitude' => 48.8566,
            'longitude' => 2.3522,
        ];
    }

    /**
     * Vérifier si un prestataire est disponible à une date donnée
     */
    private function isAvailableOnDate(
        Prestataire $prestataire,
        \DateTime $date
    ): bool {
        $availabilities = $prestataire->getAvailabilities();
        $dayOfWeek = (int)$date->format('w'); // 0 (dimanche) à 6 (samedi)

        foreach ($availabilities as $availability) {
            // Vérifier les disponibilités récurrentes
            if ($availability->isRecurring() && 
                $availability->getDayOfWeek() === $dayOfWeek) {
                return true;
            }

            // Vérifier les disponibilités spécifiques à une date
            if ($availability->getSpecificDate() &&
                $availability->getSpecificDate()->format('Y-m-d') === $date->format('Y-m-d')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Filtrer les prestataires par critères supplémentaires
     */
    public function applyFilters(array $prestataires, array $filters): array
    {
        $filtered = $prestataires;

        // Filtre par note minimum
        if (isset($filters['min_rating'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                $prestataire = $item['prestataire'];
                return $prestataire->getAverageRating() >= $filters['min_rating'];
            });
        }

        // Filtre par nombre de services minimum
        if (isset($filters['min_experience'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                $prestataire = $item['prestataire'];
                return $prestataire->getCompletedBookingsCount() >= $filters['min_experience'];
            });
        }

        // Filtre par tarif maximum
        if (isset($filters['max_hourly_rate'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                $prestataire = $item['prestataire'];
                return $prestataire->getHourlyRate() <= $filters['max_hourly_rate'];
            });
        }

        // Filtre par distance maximum
        if (isset($filters['max_distance'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                return $item['details']['distance'] <= $filters['max_distance'];
            });
        }

        // Filtre par disponibilité immédiate
        if (isset($filters['available_now']) && $filters['available_now']) {
            $filtered = array_filter($filtered, function ($item) {
                return $item['details']['availability'] >= 80;
            });
        }

        return array_values($filtered);
    }

    /**
     * Obtenir des statistiques sur le matching
     */
    public function getMatchingStatistics(ServiceRequest $serviceRequest): array
    {
        $matches = $this->findBestMatches($serviceRequest, 100); // Tous les candidats

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
     * Obtenir les poids de matching configurés
     */
    public function getMatchingWeights(): array
    {
        return $this->weights;
    }

    /**
     * Modifier les poids de matching (pour ajustements)
     */
    public function setMatchingWeights(array $newWeights): void
    {
        // Vérifier que la somme fait 1.00
        $sum = array_sum($newWeights);
        
        if (abs($sum - 1.0) > 0.01) {
            throw new \InvalidArgumentException(
                'The sum of weights must equal 1.00'
            );
        }

        $this->weights = array_merge($this->weights, $newWeights);

        $this->logger->info('Matching weights updated', [
            'new_weights' => $this->weights,
        ]);
    }

    /**
     * Recherche de prestataire de remplacement pour un booking
     */
    public function findReplacementPrestataire(
        \App\Entity\Booking\Booking $booking,
        ?Prestataire $excludePrestataire = null
    ): array {
        $serviceRequest = $booking->getServiceRequest();
        
        // Trouver les candidats
        $matches = $this->findBestMatches($serviceRequest, 20);

        // Exclure le prestataire original
        if ($excludePrestataire) {
            $matches = array_filter($matches, function ($match) use ($excludePrestataire) {
                return $match['prestataire']->getId() !== $excludePrestataire->getId();
            });
        }

        // Filtrer uniquement ceux disponibles à la date exacte du booking
        $availableMatches = array_filter($matches, function ($match) use ($booking) {
            return $this->isAvailableOnDate(
                $match['prestataire'],
                $booking->getScheduledDate()
            );
        });

        return array_values($availableMatches);
    }
}