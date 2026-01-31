<?php

namespace App\Service\Matching;

use App\Entity\Service\ServiceRequest;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use App\Repository\User\PrestataireRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Repository\Planning\AvailabilityRepository;
use App\Repository\Quote\QuoteRepository;
use App\Service\Notification\NotificationService;
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
        private QuoteRepository $quoteRepository,
        private NotificationService $notificationService,
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
     * (Méthode pour le ServiceRequestController du prestataire)
     * 
     * @param Prestataire $prestataire
     * @param array $options Options de filtrage et pagination
     * @return array Résultats avec pagination
     */
    public function findMatchingRequests(
        Prestataire $prestataire,
        array $options = []
    ): array {
        $page = $options['page'] ?? 1;
        $limit = $options['limit'] ?? 20;
        $categoryId = $options['category_id'] ?? null;
        $maxDistance = $options['max_distance'] ?? ($prestataire->getServiceRadius() ?? self::DISTANCE_MAX_KM);
        $sortBy = $options['sort_by'] ?? 'created_at';

        // Récupérer l'adresse du prestataire
        $prestataireAddress = $prestataire->getAddress();
        $coordinates = $this->geocodeAddress($prestataireAddress);
        
        if (!$coordinates) {
            $this->logger->error('Failed to geocode prestataire address', [
                'prestataire_id' => $prestataire->getId(),
            ]);
            return [
                'requests' => [],
                'total' => 0,
            ];
        }

        // Construire la requête de base
        $qb = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->leftJoin('sr.category', 'cat')
            ->leftJoin('sr.client', 'c')
            ->addSelect('cat', 'c')
            ->where('sr.status IN (:statuses)')
            ->setParameter('statuses', ['open', 'quoting']);

        // Filtrer par catégories du prestataire
        if ($categoryId) {
            $qb->andWhere('cat.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        } else {
            $categories = $prestataire->getServiceCategories();
            if ($categories->count() > 0) {
                $qb->andWhere('cat IN (:categories)')
                    ->setParameter('categories', $categories);
            }
        }

        // Récupérer toutes les demandes
        $allRequests = $qb->getQuery()->getResult();

        // Calculer le score et filtrer par distance
        $scoredRequests = [];
        foreach ($allRequests as $request) {
            // Vérifier si le prestataire a déjà soumis un devis
            $existingQuote = $this->quoteRepository->findOneBy([
                'prestataire' => $prestataire,
                'serviceRequest' => $request,
            ]);

            if ($existingQuote) {
                continue; // Ignorer les demandes déjà cotées
            }

            $requestCoordinates = $this->geocodeAddress($request->getAddress());
            if (!$requestCoordinates) {
                continue;
            }

            // Calculer la distance
            $distance = $this->calculateDistance(
                $coordinates['latitude'],
                $coordinates['longitude'],
                $requestCoordinates['latitude'],
                $requestCoordinates['longitude']
            );

            // Filtrer par distance max
            if ($distance > $maxDistance) {
                continue;
            }

            // Calculer le score
            $score = $this->calculateMatchingScore($request, $prestataire, $requestCoordinates);

            if ($score['total_score'] >= self::MIN_SCORE_THRESHOLD) {
                $scoredRequests[] = [
                    'service_request' => $request,
                    'score' => $score['total_score'],
                    'distance' => $distance,
                    'details' => $score['details'],
                ];
            }
        }

        // Trier selon le critère
        $this->sortRequests($scoredRequests, $sortBy);

        // Pagination
        $total = count($scoredRequests);
        $offset = ($page - 1) * $limit;
        $paginatedRequests = array_slice($scoredRequests, $offset, $limit);

        // Extraire uniquement les entités ServiceRequest pour la réponse
        $requests = array_map(function($item) {
            return $item['service_request'];
        }, $paginatedRequests);

        return [
            'requests' => $requests,
            'total' => $total,
        ];
    }

    /**
     * Obtient des recommandations personnalisées pour un prestataire
     * 
     * @param Prestataire $prestataire
     * @param int $limit
     * @return array
     */
    public function getRecommendations(Prestataire $prestataire, int $limit = 10): array
    {
        $result = $this->findMatchingRequests($prestataire, [
            'limit' => $limit,
            'sort_by' => 'score', // Tri par score pour les meilleures recommandations
        ]);

        return $result['requests'];
    }

    /**
     * Trouve un prestataire de remplacement pour une réservation
     * 
     * @param Booking $booking
     * @param Prestataire|null $excludePrestataire
     * @return array
     */
    public function findReplacementPrestataire(
        Booking $booking,
        ?Prestataire $excludePrestataire = null
    ): array {
        $serviceRequest = $booking->getServiceRequest();
        
        // Trouver les candidats
        $matches = $this->findMatchingPrestataires($serviceRequest, 20);

        // Exclure le prestataire original
        if ($excludePrestataire) {
            $matches = array_filter($matches, function ($match) use ($excludePrestataire) {
                return $match['prestataire']->getId() !== $excludePrestataire->getId();
            });
        }

        // Filtrer uniquement ceux disponibles à la date exacte du booking
        $scheduledDate = $booking->getScheduledDateTime();
        $availableMatches = array_filter($matches, function ($match) use ($scheduledDate) {
            return $this->isPrestataireAvailable(
                $match['prestataire'],
                $scheduledDate
            );
        });

        return array_values($availableMatches);
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
            return 50.0; // Score moyen si pas de coordonnées
        }

        $distance = $this->calculateDistance(
            $prestataireCoordinates['latitude'],
            $prestataireCoordinates['longitude'],
            $clientCoordinates['latitude'],
            $clientCoordinates['longitude']
        );

        $maxRadius = $prestataire->getServiceRadius() ?? self::DISTANCE_MAX_KM;

        // Si hors rayon, score = 0
        if ($distance > $maxRadius) {
            return 0;
        }

        // Score inversement proportionnel à la distance
        // Distance = 0 -> Score = 100
        // Distance = maxRadius -> Score = 50
        $score = 100 - (($distance / $maxRadius) * 50);

        return max(0, min(100, round($score, 2)));
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
        // 0 services -> 30
        // 10 services -> 50
        // 50+ services -> 100
        if ($completedBookings === 0) {
            return 30;
        } elseif ($completedBookings <= 5) {
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
     * Calcule le score basé sur le prix
     */
    private function calculatePriceScore(
        Prestataire $prestataire,
        ?float $budget
    ): float {
        $hourlyRate = $prestataire->getHourlyRate();

        // Si pas de budget défini, score neutre
        if (!$budget || $budget == 0) {
            return 70;
        }

        // Si pas de tarif défini, score neutre
        if (!$hourlyRate || $hourlyRate == 0) {
            return 70;
        }

        $difference = abs($hourlyRate - $budget);
        $percentDifference = ($difference / $budget) * 100;

        // Score inversement proportionnel à la différence
        if ($percentDifference <= 10) {
            return 100; // Très proche du budget
        } elseif ($percentDifference <= 20) {
            return 85;
        } elseif ($percentDifference <= 30) {
            return 70;
        } elseif ($percentDifference <= 50) {
            return 50;
        } else {
            return 30; // Trop éloigné du budget
        }
    }

    /**
     * Calcule le score basé sur le taux de réponse
     */
    private function calculateResponseRateScore(Prestataire $prestataire): float
    {
        $responseRate = $prestataire->getResponseRate() ?? 0;

        // Si nouveau prestataire, score par défaut
        if ($responseRate === 0) {
            return 70;
        }

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
     * 
     * @param float $lat1
     * @param float $lon1
     * @param float $lat2
     * @param float $lon2
     * @return float Distance en kilomètres
     */
    public function calculateDistance(
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
        // TODO: Implémenter avec un service de géocodage réel
        // Options :
        // 1. Google Maps Geocoding API
        // 2. Nominatim (OpenStreetMap) - gratuit
        // 3. Mapbox
        // 4. Here Maps
        
        // Exemple d'implémentation avec cache
        $cacheKey = 'geocode_' . md5($address);
        
        // TODO: Vérifier le cache Redis/Memcached
        
        // TODO: Appel API de géocodage
        
        // Pour le développement, retourner des coordonnées simulées
        $this->logger->warning('Using mock geocoding - implement real geocoding service', [
            'address' => $address
        ]);

        // Coordonnées de Paris par défaut (à remplacer)
        return [
            'latitude' => 48.8566,
            'longitude' => 2.3522,
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

        // Filtre par expérience minimum
        if (isset($filters['min_experience'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                return $item['prestataire']->getCompletedBookingsCount() >= $filters['min_experience'];
            });
        }

        // Filtre par distance maximum
        if (isset($filters['max_distance'])) {
            $filtered = array_filter($filtered, function ($item) use ($filters) {
                return isset($item['details']['distance']) && 
                       $item['details']['distance'] <= $filters['max_distance'];
            });
        }

        return array_values($filtered);
    }

    /**
     * Trie les demandes selon le critère spécifié
     */
    private function sortRequests(array &$scoredRequests, string $sortBy): void
    {
        switch ($sortBy) {
            case 'score':
                usort($scoredRequests, function ($a, $b) {
                    return $b['score'] <=> $a['score'];
                });
                break;

            case 'distance':
                usort($scoredRequests, function ($a, $b) {
                    return $a['distance'] <=> $b['distance'];
                });
                break;

            case 'budget':
                usort($scoredRequests, function ($a, $b) {
                    $budgetA = $a['service_request']->getBudget() ?? 0;
                    $budgetB = $b['service_request']->getBudget() ?? 0;
                    return $budgetB <=> $budgetA; // Budget décroissant
                });
                break;

            case 'created_at':
            default:
                usort($scoredRequests, function ($a, $b) {
                    return $b['service_request']->getCreatedAt() <=> $a['service_request']->getCreatedAt();
                });
                break;
        }
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
     * Note: Cette méthode ne persiste pas les changements (constantes)
     */
    public function validateWeights(array $weights): bool
    {
        // Valider que la somme fait 1.00 (100%)
        $sum = array_sum($weights);
        if (abs($sum - 1.0) > 0.01) {
            throw new \InvalidArgumentException(
                'La somme des poids doit être égale à 1.00 (actuellement: ' . $sum . ')'
            );
        }

        $this->logger->info('Matching weights validated', $weights);
        return true;
    }

    /**
     * Notifie les prestataires correspondant à une nouvelle demande de service
     * 
     * Cette méthode :
     * 1. Trouve les prestataires les mieux notés pour la demande
     * 2. Les notifie par email, push et in-app
     * 3. Enregistre l'historique des notifications
     * 
     * @param ServiceRequest $serviceRequest La demande de service
     * @param array $options Options de notification
     * @return array Résultats des notifications
     */
    public function notifyMatchingPrestataires(
        ServiceRequest $serviceRequest,
        array $options = []
    ): array {
        // Options par défaut
        $maxPrestataires = $options['max_prestataires'] ?? 10;
        $minScore = $options['min_score'] ?? 60; // Score minimum pour être notifié
        $channels = $options['channels'] ?? ['email', 'push', 'in_app'];
        $priority = $options['priority'] ?? 'high';

        $this->logger->info('Starting prestataire notification process', [
            'service_request_id' => $serviceRequest->getId(),
            'max_prestataires' => $maxPrestataires,
            'min_score' => $minScore,
        ]);

        try {
            // 1. Trouver les prestataires correspondants
            $matches = $this->findMatchingPrestataires($serviceRequest, $maxPrestataires, [
                'min_score' => $minScore,
            ]);

            if (empty($matches)) {
                $this->logger->warning('No matching prestataires found for notification', [
                    'service_request_id' => $serviceRequest->getId(),
                ]);

                return [
                    'success' => false,
                    'message' => 'Aucun prestataire correspondant trouvé',
                    'notified_count' => 0,
                    'matches_found' => 0,
                ];
            }

            // 2. Préparer les données de notification
            $notificationData = $this->prepareNotificationData($serviceRequest, $matches);

            // 3. Notifier chaque prestataire
            $results = [];
            $successCount = 0;
            $failedCount = 0;

            foreach ($matches as $match) {
                $prestataire = $match['prestataire'];
                
                try {
                    // Ajouter le score de matching aux données
                    $prestataireData = array_merge($notificationData, [
                        'matching_score' => $match['score'],
                        'score_details' => $match['details'],
                        'distance_km' => $this->calculateDistanceForPrestataire(
                            $serviceRequest,
                            $prestataire
                        ),
                    ]);

                    // Envoyer la notification
                    $notificationResult = $this->notificationService->notifyNewServiceRequest(
                        $serviceRequest,
                        [$prestataire]
                    );

                    // Enregistrer dans l'historique
                    $this->recordNotification($serviceRequest, $prestataire, $match['score']);

                    $results[$prestataire->getId()] = [
                        'prestataire_id' => $prestataire->getId(),
                        'prestataire_name' => $prestataire->getFullName(),
                        'score' => $match['score'],
                        'notification_sent' => true,
                        'notification_results' => $notificationResult,
                    ];

                    $successCount++;

                    $this->logger->info('Prestataire notified successfully', [
                        'service_request_id' => $serviceRequest->getId(),
                        'prestataire_id' => $prestataire->getId(),
                        'score' => $match['score'],
                    ]);

                } catch (\Exception $e) {
                    $failedCount++;
                    
                    $results[$prestataire->getId()] = [
                        'prestataire_id' => $prestataire->getId(),
                        'prestataire_name' => $prestataire->getFullName(),
                        'score' => $match['score'],
                        'notification_sent' => false,
                        'error' => $e->getMessage(),
                    ];

                    $this->logger->error('Failed to notify prestataire', [
                        'service_request_id' => $serviceRequest->getId(),
                        'prestataire_id' => $prestataire->getId(),
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 4. Mettre à jour le statut de la demande
            if ($successCount > 0) {
                $serviceRequest->setStatus('notified');
                $serviceRequest->setNotifiedAt(new \DateTimeImmutable());
                $this->entityManager->flush();
            }

            $summary = [
                'success' => true,
                'message' => sprintf(
                    '%d prestataire(s) notifié(s) avec succès',
                    $successCount
                ),
                'notified_count' => $successCount,
                'failed_count' => $failedCount,
                'matches_found' => count($matches),
                'details' => $results,
            ];

            $this->logger->info('Notification process completed', [
                'service_request_id' => $serviceRequest->getId(),
                'summary' => $summary,
            ]);

            return $summary;

        } catch (\Exception $e) {
            $this->logger->error('Notification process failed', [
                'service_request_id' => $serviceRequest->getId(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la notification des prestataires',
                'error' => $e->getMessage(),
                'notified_count' => 0,
                'matches_found' => 0,
            ];
        }
    }

    /**
     * Prépare les données communes pour les notifications
     */
    private function prepareNotificationData(ServiceRequest $serviceRequest, array $matches): array
    {
        $client = $serviceRequest->getClient();
        $category = $serviceRequest->getCategory();

        return [
            'service_request_id' => $serviceRequest->getId(),
            'category_name' => $category->getName(),
            'category_id' => $category->getId(),
            'client_name' => $client->getFirstName(), // Pas de nom complet pour la vie privée
            'address' => $this->maskAddress($serviceRequest->getAddress()), // Adresse masquée
            'city' => $serviceRequest->getCity(),
            'postal_code' => $serviceRequest->getPostalCode(),
            'description' => $serviceRequest->getDescription(),
            'preferred_date' => $serviceRequest->getPreferredDate(),
            'alternative_dates' => $serviceRequest->getAlternativeDates(),
            'duration_estimated' => $serviceRequest->getDuration(),
            'budget' => $serviceRequest->getBudget(),
            'frequency' => $serviceRequest->getFrequency(),
            'created_at' => $serviceRequest->getCreatedAt(),
            'expires_at' => $serviceRequest->getExpiresAt(),
            'total_matches' => count($matches),
        ];
    }

    /**
     * Masque partiellement une adresse pour la confidentialité
     * Exemple: "123 Rue de la Paix" -> "Rue de la Paix"
     */
    private function maskAddress(string $address): string
    {
        // Supprimer le numéro de rue
        $address = preg_replace('/^\d+\s*/', '', $address);
        
        return $address;
    }

    /**
     * Calcule la distance entre un prestataire et une demande de service
     */
    private function calculateDistanceForPrestataire(
        ServiceRequest $serviceRequest,
        Prestataire $prestataire
    ): ?float {
        $srLat = $serviceRequest->getLatitude();
        $srLon = $serviceRequest->getLongitude();
        $pLat = $prestataire->getLatitude();
        $pLon = $prestataire->getLongitude();

        if (!$srLat || !$srLon || !$pLat || !$pLon) {
            return null;
        }

        return $this->calculateDistance($pLat, $pLon, $srLat, $srLon);
    }

    /**
     * Enregistre l'historique de notification
     * (Crée une entrée pour tracking et analytics)
     */
    private function recordNotification(
        ServiceRequest $serviceRequest,
        Prestataire $prestataire,
        float $matchingScore
    ): void {
        // Note: Vous pouvez créer une entité NotificationHistory pour tracker cela
        // Pour l'instant, on log simplement
        
        $this->logger->info('Notification recorded', [
            'service_request_id' => $serviceRequest->getId(),
            'prestataire_id' => $prestataire->getId(),
            'matching_score' => $matchingScore,
            'notified_at' => (new \DateTime())->format('Y-m-d H:i:s'),
        ]);

        // TODO: Implémenter une entité NotificationHistory si nécessaire
        // $history = new NotificationHistory();
        // $history->setServiceRequest($serviceRequest);
        // $history->setPrestataire($prestataire);
        // $history->setMatchingScore($matchingScore);
        // $history->setNotifiedAt(new \DateTimeImmutable());
        // $this->entityManager->persist($history);
        // $this->entityManager->flush();
    }

    /**
     * Re-notifie les prestataires si aucun devis n'a été reçu
     * Utile pour relancer après X heures sans réponse
     * 
     * @param ServiceRequest $serviceRequest
     * @param array $options Options (exclude_notified, lower_threshold, etc.)
     * @return array
     */
    public function renotifyPrestataires(
        ServiceRequest $serviceRequest,
        array $options = []
    ): array {
        $excludeAlreadyNotified = $options['exclude_notified'] ?? true;
        $lowerScoreThreshold = $options['lower_threshold'] ?? true; // Baisser le seuil si pas de réponse
        $minScore = $options['min_score'] ?? ($lowerScoreThreshold ? 50 : 60);

        $this->logger->info('Re-notifying prestataires', [
            'service_request_id' => $serviceRequest->getId(),
            'min_score' => $minScore,
        ]);

        // Vérifier qu'il n'y a pas déjà des devis
        $existingQuotes = $this->quoteRepository->findBy([
            'serviceRequest' => $serviceRequest,
        ]);

        if (!empty($existingQuotes)) {
            return [
                'success' => false,
                'message' => 'Des devis ont déjà été reçus',
                'quotes_count' => count($existingQuotes),
            ];
        }

        // TODO: Si exclude_notified est true, exclure les prestataires déjà notifiés
        // Nécessite d'avoir l'historique des notifications

        // Notifier avec un seuil potentiellement plus bas
        return $this->notifyMatchingPrestataires($serviceRequest, [
            'min_score' => $minScore,
            'max_prestataires' => 15, // Plus de prestataires lors de la relance
            'priority' => 'urgent',
        ]);
    }

    /**
     * Obtient les statistiques de notification pour une demande
     * 
     * @param ServiceRequest $serviceRequest
     * @return array
     */
    public function getNotificationStats(ServiceRequest $serviceRequest): array
    {
        // Compter les prestataires notifiés (via historique)
        // Pour l'instant, on utilise les quotes comme proxy
        
        $quotes = $this->quoteRepository->findBy([
            'serviceRequest' => $serviceRequest,
        ]);

        $matches = $this->findMatchingPrestataires($serviceRequest, 100);

        return [
            'service_request_id' => $serviceRequest->getId(),
            'potential_matches' => count($matches),
            'quotes_received' => count($quotes),
            'response_rate' => count($matches) > 0 
                ? round((count($quotes) / count($matches)) * 100, 2) 
                : 0,
            'status' => $serviceRequest->getStatus(),
            'notified_at' => $serviceRequest->getNotifiedAt(),
        ];
    }

    /**
     * Notification ciblée pour un prestataire spécifique
     * Utile pour les invitations manuelles
     * 
     * @param ServiceRequest $serviceRequest
     * @param Prestataire $prestataire
     * @return array
     */
    public function notifySpecificPrestataire(
        ServiceRequest $serviceRequest,
        Prestataire $prestataire
    ): array {
        $this->logger->info('Notifying specific prestataire', [
            'service_request_id' => $serviceRequest->getId(),
            'prestataire_id' => $prestataire->getId(),
        ]);

        // Vérifier que le prestataire n'a pas déjà un devis
        $existingQuote = $this->quoteRepository->findOneBy([
            'serviceRequest' => $serviceRequest,
            'prestataire' => $prestataire,
        ]);

        if ($existingQuote) {
            return [
                'success' => false,
                'message' => 'Ce prestataire a déjà soumis un devis',
                'quote_id' => $existingQuote->getId(),
            ];
        }

        // Vérifier que le prestataire est éligible
        if (!$prestataire->isApproved() || !$prestataire->isActive()) {
            return [
                'success' => false,
                'message' => 'Ce prestataire n\'est pas éligible',
            ];
        }

        try {
            // Calculer le score de matching
            $address = $serviceRequest->getAddress();
            $coordinates = $this->geocodeAddress($address);
            
            if (!$coordinates) {
                throw new \Exception('Impossible de géocoder l\'adresse de la demande');
            }

            $score = $this->calculateMatchingScore(
                $serviceRequest,
                $prestataire,
                $coordinates
            );

            // Notifier le prestataire
            $this->notificationService->notifyNewServiceRequest(
                $serviceRequest,
                [$prestataire]
            );

            // Enregistrer
            $this->recordNotification($serviceRequest, $prestataire, $score['total_score']);

            return [
                'success' => true,
                'message' => 'Prestataire notifié avec succès',
                'prestataire_id' => $prestataire->getId(),
                'matching_score' => $score['total_score'],
                'score_details' => $score['details'],
            ];

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify specific prestataire', [
                'service_request_id' => $serviceRequest->getId(),
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'message' => 'Erreur lors de la notification',
                'error' => $e->getMessage(),
            ];
        }
    }
}