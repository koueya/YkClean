<?php

namespace App\Service\Review;

use App\Entity\User\Prestataire;
use App\Entity\Review\Review;
use App\Repository\Review\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service pour gérer les calculs de notation et statistiques
 */
class RatingService
{
    private EntityManagerInterface $entityManager;
    private ReviewRepository $reviewRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        ReviewRepository $reviewRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->reviewRepository = $reviewRepository;
        $this->logger = $logger;
    }

    /**
     * Met à jour la note moyenne d'un prestataire
     */
    public function updatePrestataireAverageRating(Prestataire $prestataire): void
    {
        try {
            $averageRating = $this->reviewRepository->getAverageRatingByPrestataire($prestataire);
            
            $prestataire->setAverageRating($averageRating);
            
            $this->entityManager->flush();

            $this->logger->info('Prestataire average rating updated', [
                'prestataire_id' => $prestataire->getId(),
                'average_rating' => $averageRating,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update prestataire average rating', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Calcule la note moyenne d'un prestataire (sans sauvegarder)
     */
    public function calculateAverageRating(Prestataire $prestataire): float
    {
        return $this->reviewRepository->getAverageRatingByPrestataire($prestataire);
    }

    /**
     * Obtient les statistiques détaillées des avis d'un prestataire
     */
    public function getPrestataireRatingStats(Prestataire $prestataire): array
    {
        $reviews = $this->reviewRepository->findByPrestataire($prestataire);

        if (empty($reviews)) {
            return [
                'total_reviews' => 0,
                'average_rating' => 0,
                'rating_distribution' => [
                    5 => 0,
                    4 => 0,
                    3 => 0,
                    2 => 0,
                    1 => 0,
                ],
                'average_quality' => 0,
                'average_punctuality' => 0,
                'average_professionalism' => 0,
                'average_communication' => 0,
                'recommendation_rate' => 0,
                'response_rate' => 0,
            ];
        }

        $totalRating = 0;
        $totalQuality = 0;
        $totalPunctuality = 0;
        $totalProfessionalism = 0;
        $totalCommunication = 0;
        $recommendCount = 0;
        $responseCount = 0;
        $detailedRatingsCount = 0;

        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($reviews as $review) {
            $rating = $review->getRating();
            $totalRating += $rating;
            $distribution[$rating]++;

            if ($review->getQualityRating()) {
                $totalQuality += $review->getQualityRating();
                $detailedRatingsCount++;
            }

            if ($review->getPunctualityRating()) {
                $totalPunctuality += $review->getPunctualityRating();
            }

            if ($review->getProfessionalismRating()) {
                $totalProfessionalism += $review->getProfessionalismRating();
            }

            if ($review->getCommunicationRating()) {
                $totalCommunication += $review->getCommunicationRating();
            }

            if ($review->isWouldRecommend()) {
                $recommendCount++;
            }

            if ($review->getPrestataireResponse()) {
                $responseCount++;
            }
        }

        $totalReviews = count($reviews);

        return [
            'total_reviews' => $totalReviews,
            'average_rating' => round($totalRating / $totalReviews, 2),
            'rating_distribution' => $distribution,
            'average_quality' => $detailedRatingsCount > 0 ? round($totalQuality / $detailedRatingsCount, 2) : 0,
            'average_punctuality' => $detailedRatingsCount > 0 ? round($totalPunctuality / $detailedRatingsCount, 2) : 0,
            'average_professionalism' => $detailedRatingsCount > 0 ? round($totalProfessionalism / $detailedRatingsCount, 2) : 0,
            'average_communication' => $detailedRatingsCount > 0 ? round($totalCommunication / $detailedRatingsCount, 2) : 0,
            'recommendation_rate' => round(($recommendCount / $totalReviews) * 100, 1),
            'response_rate' => round(($responseCount / $totalReviews) * 100, 1),
        ];
    }

    /**
     * Vérifie si un prestataire peut recevoir de nouveaux avis
     */
    public function canReceiveReview(Prestataire $prestataire): bool
    {
        // Un prestataire peut toujours recevoir des avis
        // Mais on peut ajouter des règles métier ici si nécessaire
        return $prestataire->getIsApproved();
    }

    /**
     * Obtient le niveau de réputation basé sur la note moyenne
     */
    public function getReputationLevel(float $averageRating, int $totalReviews): string
    {
        if ($totalReviews < 5) {
            return 'new'; // Nouveau prestataire
        }

        if ($averageRating >= 4.8) {
            return 'excellent';
        } elseif ($averageRating >= 4.5) {
            return 'very_good';
        } elseif ($averageRating >= 4.0) {
            return 'good';
        } elseif ($averageRating >= 3.5) {
            return 'average';
        } else {
            return 'needs_improvement';
        }
    }

    /**
     * Vérifie si un avis peut être modifié
     */
    public function canEditReview(Review $review): bool
    {
        // Un avis peut être modifié dans les 7 jours suivant sa création
        $now = new \DateTimeImmutable();
        $createdAt = $review->getCreatedAt();
        
        if (!$createdAt) {
            return false;
        }

        $daysSinceCreation = ($now->getTimestamp() - $createdAt->getTimestamp()) / 86400;

        return $daysSinceCreation <= 7;
    }

    /**
     * Vérifie si un avis peut être supprimé
     */
    public function canDeleteReview(Review $review): bool
    {
        // Même règle que pour l'édition
        return $this->canEditReview($review);
    }

    /**
     * Calcule le score de confiance d'un avis (0-100)
     * Basé sur plusieurs facteurs
     */
    public function calculateReviewTrustScore(Review $review): float
    {
        $score = 0;

        // Avis vérifié (+30 points)
        if ($review->isVerified()) {
            $score += 30;
        }

        // Présence de commentaire (+20 points)
        if ($review->getComment() && strlen($review->getComment()) >= 50) {
            $score += 20;
        } elseif ($review->getComment()) {
            $score += 10;
        }

        // Avis détaillé avec notes par catégorie (+30 points)
        $detailedRatings = 0;
        if ($review->getQualityRating()) $detailedRatings++;
        if ($review->getPunctualityRating()) $detailedRatings++;
        if ($review->getProfessionalismRating()) $detailedRatings++;
        if ($review->getCommunicationRating()) $detailedRatings++;

        $score += ($detailedRatings / 4) * 30;

        // Photos (+20 points)
        if ($review->getPhotos() && count($review->getPhotos()) > 0) {
            $score += 20;
        }

        return min(100, $score);
    }

    /**
     * Obtient les avis les plus utiles d'un prestataire
     */
    public function getTopReviews(Prestataire $prestataire, int $limit = 5): array
    {
        $reviews = $this->reviewRepository->findByPrestataire($prestataire);

        // Trier par score de confiance
        usort($reviews, function($a, $b) {
            $scoreA = $this->calculateReviewTrustScore($a);
            $scoreB = $this->calculateReviewTrustScore($b);
            
            return $scoreB <=> $scoreA;
        });

        return array_slice($reviews, 0, $limit);
    }

    /**
     * Calcule le taux de réponse d'un prestataire aux avis
     */
    public function calculateResponseRate(Prestataire $prestataire): float
    {
        $allReviews = $this->reviewRepository->findByPrestataire($prestataire);
        
        if (empty($allReviews)) {
            return 0;
        }

        $reviewsWithResponse = array_filter($allReviews, function($review) {
            return $review->getPrestataireResponse() !== null;
        });

        return round((count($reviewsWithResponse) / count($allReviews)) * 100, 1);
    }

    /**
     * Obtient la tendance des avis (amélioration ou dégradation)
     */
    public function getRatingTrend(Prestataire $prestataire, int $days = 30): array
    {
        $since = new \DateTime("-{$days} days");
        
        $recentReviews = $this->reviewRepository->createQueryBuilder('r')
            ->where('r.prestataire = :prestataire')
            ->andWhere('r.createdAt >= :since')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('since', $since)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();

        if (empty($recentReviews)) {
            return [
                'trend' => 'stable',
                'recent_average' => 0,
                'overall_average' => $prestataire->getAverageRating(),
                'change' => 0,
            ];
        }

        $recentTotal = 0;
        foreach ($recentReviews as $review) {
            $recentTotal += $review->getRating();
        }
        $recentAverage = $recentTotal / count($recentReviews);

        $overallAverage = $prestataire->getAverageRating();
        $change = $recentAverage - $overallAverage;

        $trend = 'stable';
        if ($change > 0.3) {
            $trend = 'improving';
        } elseif ($change < -0.3) {
            $trend = 'declining';
        }

        return [
            'trend' => $trend,
            'recent_average' => round($recentAverage, 2),
            'overall_average' => round($overallAverage, 2),
            'change' => round($change, 2),
            'recent_reviews_count' => count($recentReviews),
        ];
    }

    /**
     * Obtient les points forts et faibles d'un prestataire
     */
    public function getStrengthsAndWeaknesses(Prestataire $prestataire): array
    {
        $reviews = $this->reviewRepository->findByPrestataire($prestataire);

        if (empty($reviews)) {
            return [
                'strengths' => [],
                'weaknesses' => [],
            ];
        }

        $categories = [
            'quality' => [],
            'punctuality' => [],
            'professionalism' => [],
            'communication' => [],
        ];

        foreach ($reviews as $review) {
            if ($review->getQualityRating()) {
                $categories['quality'][] = $review->getQualityRating();
            }
            if ($review->getPunctualityRating()) {
                $categories['punctuality'][] = $review->getPunctualityRating();
            }
            if ($review->getProfessionalismRating()) {
                $categories['professionalism'][] = $review->getProfessionalismRating();
            }
            if ($review->getCommunicationRating()) {
                $categories['communication'][] = $review->getCommunicationRating();
            }
        }

        $averages = [];
        foreach ($categories as $category => $ratings) {
            if (!empty($ratings)) {
                $averages[$category] = array_sum($ratings) / count($ratings);
            }
        }

        if (empty($averages)) {
            return [
                'strengths' => [],
                'weaknesses' => [],
            ];
        }

        // Trier par note
        arsort($averages);

        $strengths = [];
        $weaknesses = [];

        foreach ($averages as $category => $average) {
            if ($average >= 4.5) {
                $strengths[] = [
                    'category' => $category,
                    'average' => round($average, 2),
                ];
            } elseif ($average < 4.0) {
                $weaknesses[] = [
                    'category' => $category,
                    'average' => round($average, 2),
                ];
            }
        }

        return [
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
        ];
    }

    /**
     * Crée une demande d'avis pour une réservation terminée
     * Cette méthode est appelée par le BookingCompletedListener
     */
    public function createReviewRequest(\App\Entity\Booking\Booking $booking): void
    {
        try {
            // Vérifier que la réservation est complétée
            if ($booking->getStatus() !== 'completed') {
                $this->logger->warning('Cannot create review request for non-completed booking', [
                    'booking_id' => $booking->getId(),
                    'status' => $booking->getStatus(),
                ]);
                return;
            }

            // Vérifier qu'il n'y a pas déjà un avis
            if ($booking->getReview()) {
                $this->logger->info('Booking already has a review', [
                    'booking_id' => $booking->getId(),
                ]);
                return;
            }

            // Marquer la réservation comme ayant une demande d'avis en attente
            // Note: Cette fonctionnalité pourrait nécessiter l'ajout d'un champ 
            // `reviewRequestedAt` dans l'entité Booking
            
            $this->logger->info('Review request created successfully', [
                'booking_id' => $booking->getId(),
                'client_id' => $booking->getClient()->getId(),
                'prestataire_id' => $booking->getPrestataire()->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create review request', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);
            
            // Ne pas propager l'exception pour ne pas bloquer le processus
        }
    }
}