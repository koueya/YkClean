<?php
// src/Repository/Review/ReviewRepository.php

namespace App\Repository\Review;

use App\Entity\Review\Review;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    // ============================================
    // RECHERCHE PAR ENTITÉ
    // ============================================

    /**
     * Trouve tous les avis d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.createdAt', 'DESC');

        if ($publishedOnly) {
            $qb->andWhere('r.isPublished = :published')
               ->setParameter('published', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les avis d'un client
     */
    public function findByClient(Client $client): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.client = :client')
            ->setParameter('client', $client)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve l'avis d'une réservation
     */
    public function findByBooking(Booking $booking): ?Review
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.booking = :booking')
            ->setParameter('booking', $booking)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // RECHERCHE AVEC FILTRES
    // ============================================

    /**
     * Trouve les avis publiés d'un prestataire avec pagination
     */
    public function findPublishedByPrestataire(
        Prestataire $prestataire,
        int $page = 1,
        int $limit = 10
    ): array {
        $offset = ($page - 1) * $limit;

        return $this->createQueryBuilder('r')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les avis par note
     */
    public function findByRating(int $rating, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.rating = :rating')
            ->setParameter('rating', $rating)
            ->orderBy('r.createdAt', 'DESC');

        if ($publishedOnly) {
            $qb->andWhere('r.isPublished = :published')
               ->setParameter('published', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les avis vérifiés
     */
    public function findVerified(bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isVerified = :verified')
            ->setParameter('verified', true)
            ->orderBy('r.createdAt', 'DESC');

        if ($publishedOnly) {
            $qb->andWhere('r.isPublished = :published')
               ->setParameter('published', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les avis en attente de modération
     */
    public function findPendingModeration(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isPublished = :published')
            ->andWhere('r.moderatedAt IS NULL')
            ->setParameter('published', false)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les avis signalés
     */
    public function findFlagged(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.isFlagged = :flagged')
            ->setParameter('flagged', true)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les avis sans réponse du prestataire
     */
    public function findWithoutResponse(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.response IS NULL')
            ->andWhere('r.isPublished = :published')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->orderBy('r.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avancée avec critères multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        if (isset($criteria['prestataire'])) {
            $qb->andWhere('r.prestataire = :prestataire')
               ->setParameter('prestataire', $criteria['prestataire']);
        }

        if (isset($criteria['client'])) {
            $qb->andWhere('r.client = :client')
               ->setParameter('client', $criteria['client']);
        }

        if (isset($criteria['rating'])) {
            $qb->andWhere('r.rating = :rating')
               ->setParameter('rating', $criteria['rating']);
        }

        if (isset($criteria['min_rating'])) {
            $qb->andWhere('r.rating >= :minRating')
               ->setParameter('minRating', $criteria['min_rating']);
        }

        if (isset($criteria['published'])) {
            $qb->andWhere('r.isPublished = :published')
               ->setParameter('published', $criteria['published']);
        }

        if (isset($criteria['verified'])) {
            $qb->andWhere('r.isVerified = :verified')
               ->setParameter('verified', $criteria['verified']);
        }

        if (isset($criteria['has_response'])) {
            if ($criteria['has_response']) {
                $qb->andWhere('r.response IS NOT NULL');
            } else {
                $qb->andWhere('r.response IS NULL');
            }
        }

        if (isset($criteria['flagged'])) {
            $qb->andWhere('r.isFlagged = :flagged')
               ->setParameter('flagged', $criteria['flagged']);
        }

        if (!empty($criteria['start_date'])) {
            $qb->andWhere('r.createdAt >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (!empty($criteria['end_date'])) {
            $qb->andWhere('r.createdAt <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        if (!empty($criteria['comment'])) {
            $qb->andWhere('r.comment LIKE :comment')
               ->setParameter('comment', '%' . $criteria['comment'] . '%');
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // STATISTIQUES ET CALCULS
    // ============================================

    /**
     * Calcule la note moyenne d'un prestataire
     */
    public function getAverageRatingByPrestataire(Prestataire $prestataire): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? round((float) $result, 2) : 0.0;
    }

    /**
     * Calcule les moyennes détaillées d'un prestataire
     */
    public function getDetailedAveragesByPrestataire(Prestataire $prestataire): array
    {
        $result = $this->createQueryBuilder('r')
            ->select(
                'AVG(r.rating) as avgRating',
                'AVG(r.qualityRating) as avgQuality',
                'AVG(r.punctualityRating) as avgPunctuality',
                'AVG(r.professionalismRating) as avgProfessionalism',
                'AVG(r.communicationRating) as avgCommunication',
                'COUNT(r.id) as totalReviews'
            )
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleResult();

        return [
            'average_rating' => $result['avgRating'] !== null ? round((float) $result['avgRating'], 2) : 0.0,
            'average_quality' => $result['avgQuality'] !== null ? round((float) $result['avgQuality'], 2) : 0.0,
            'average_punctuality' => $result['avgPunctuality'] !== null ? round((float) $result['avgPunctuality'], 2) : 0.0,
            'average_professionalism' => $result['avgProfessionalism'] !== null ? round((float) $result['avgProfessionalism'], 2) : 0.0,
            'average_communication' => $result['avgCommunication'] !== null ? round((float) $result['avgCommunication'], 2) : 0.0,
            'total_reviews' => (int) $result['totalReviews']
        ];
    }

    /**
     * Compte le nombre d'avis d'un prestataire
     */
    public function countByPrestataire(Prestataire $prestataire, bool $publishedOnly = true): int
    {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($publishedOnly) {
            $qb->andWhere('r.isPublished = :published')
               ->setParameter('published', true);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte les avis par note pour un prestataire
     */
    public function getRatingDistribution(Prestataire $prestataire): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.rating, COUNT(r.id) as count')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'DESC')
            ->getQuery()
            ->getResult();

        // Initialiser toutes les notes à 0
        $distribution = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];

        foreach ($results as $result) {
            $distribution[$result['rating']] = (int) $result['count'];
        }

        return $distribution;
    }

    /**
     * Calcule le taux de recommandation
     */
    public function getRecommendationRate(Prestataire $prestataire): float
    {
        $total = $this->countByPrestataire($prestataire, true);

        if ($total === 0) {
            return 0.0;
        }

        $recommended = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->andWhere('r.wouldRecommend = :recommend')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->setParameter('recommend', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(((int) $recommended / $total) * 100, 1);
    }

    /**
     * Calcule le taux de réponse du prestataire
     */
    public function getResponseRate(Prestataire $prestataire): float
    {
        $total = $this->countByPrestataire($prestataire, true);

        if ($total === 0) {
            return 0.0;
        }

        $withResponse = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->andWhere('r.response IS NOT NULL')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(((int) $withResponse / $total) * 100, 1);
    }

    // ============================================
    // AVIS RÉCENTS ET MEILLEURS
    // ============================================

    /**
     * Trouve les avis les plus récents
     */
    public function findRecent(int $limit = 10, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($publishedOnly) {
            $qb->andWhere('r.isPublished = :published')
               ->setParameter('published', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les meilleurs avis d'un prestataire
     */
    public function findBestReviews(Prestataire $prestataire, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->andWhere('r.rating >= :minRating')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->setParameter('minRating', 4)
            ->orderBy('r.rating', 'DESC')
            ->addOrderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les avis avec commentaires
     */
    public function findWithComments(Prestataire $prestataire, int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->andWhere('r.comment IS NOT NULL')
            ->andWhere('LENGTH(r.comment) > 10')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // COMPARAISONS
    // ============================================

    /**
     * Compare les notes de plusieurs prestataires
     */
    public function comparePrestataires(array $prestataires): array
    {
        $comparison = [];

        foreach ($prestataires as $prestataire) {
            $comparison[$prestataire->getId()] = [
                'prestataire' => $prestataire,
                'stats' => $this->getDetailedAveragesByPrestataire($prestataire),
                'distribution' => $this->getRatingDistribution($prestataire),
                'response_rate' => $this->getResponseRate($prestataire)
            ];
        }

        return $comparison;
    }

    // ============================================
    // VÉRIFICATIONS
    // ============================================

    /**
     * Vérifie si un client a déjà évalué une réservation
     */
    public function hasClientReviewedBooking(Client $client, Booking $booking): bool
    {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.client = :client')
            ->andWhere('r.booking = :booking')
            ->setParameter('client', $client)
            ->setParameter('booking', $booking)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Vérifie si un client peut laisser un avis
     */
    public function canClientReview(Client $client, Booking $booking): bool
    {
        // Le client ne peut pas laisser d'avis s'il en a déjà laissé un
        if ($this->hasClientReviewedBooking($client, $booking)) {
            return false;
        }

        // La réservation doit être terminée
        return $booking->getStatus() === 'completed';
    }

    // ============================================
    // PERSISTENCE
    // ============================================

    public function save(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Review $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}