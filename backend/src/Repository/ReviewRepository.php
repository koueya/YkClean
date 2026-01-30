<?php
// src/Repository/ReviewRepository.php

namespace App\Repository;

use App\Entity\Review;
use App\Entity\Booking;
use App\Entity\Client;
use App\Entity\Prestataire;
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

    /**
     * Trouve les avis d'un client
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
     * Trouve les avis d'un prestataire
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
     * Trouve les meilleurs avis d'un prestataire
     */
    public function findTopByPrestataire(Prestataire $prestataire, int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->andWhere('r.rating >= :minRating')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->setParameter('minRating', 4)
            ->orderBy('r.rating', 'DESC')
            ->addOrderBy('r.helpfulCount', 'DESC')
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
     * Trouve les avis sans réponse
     */
    public function findWithoutResponse(Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.response IS NULL')
            ->andWhere('r.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('r.createdAt', 'ASC');

        if ($prestataire) {
            $qb->andWhere('r.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
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

        return round((float)($result ?? 0), 2);
    }

    /**
     * Compte les avis d'un prestataire
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

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Répartition des notes pour un prestataire
     */
    public function getRatingDistribution(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.rating, COUNT(r.id) as count')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->groupBy('r.rating')
            ->orderBy('r.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des avis
     */
    public function getStatistics(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('r');

        if ($prestataire) {
            $qb->andWhere('r.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $published = (clone $qb)->andWhere('r.isPublished = :published')
            ->setParameter('published', true);

        return [
            'total_reviews' => (clone $qb)->select('COUNT(r.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'published_reviews' => (clone $published)->select('COUNT(r.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'verified_reviews' => (clone $published)->select('COUNT(r.id)')
                ->andWhere('r.isVerified = :verified')
                ->setParameter('verified', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'with_comment' => (clone $published)->select('COUNT(r.id)')
                ->andWhere('r.comment IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult(),

            'with_response' => (clone $published)->select('COUNT(r.id)')
                ->andWhere('r.response IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult(),

            'recommended' => (clone $published)->select('COUNT(r.id)')
                ->andWhere('r.isRecommended = :recommended')
                ->setParameter('recommended', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'flagged' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.isFlagged = :flagged')
                ->setParameter('flagged', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'average_rating' => (clone $published)->select('AVG(r.rating)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,

            'positive_reviews' => (clone $published)->select('COUNT(r.id)')
                ->andWhere('r.rating >= 4')
                ->getQuery()
                ->getSingleScalarResult(),

            'negative_reviews' => (clone $published)->select('COUNT(r.id)')
                ->andWhere('r.rating <= 2')
                ->getQuery()
                ->getSingleScalarResult(),

            'neutral_reviews' => (clone $published)->select('COUNT(r.id)')
                ->andWhere('r.rating = 3')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    /**
     * Taux de recommandation d'un prestataire
     */
    public function getRecommendationRate(Prestataire $prestataire): float
    {
        $total = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $recommended = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.isPublished = :published')
            ->andWhere('r.isRecommended = :recommended')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('published', true)
            ->setParameter('recommended', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($recommended / $total) * 100, 2);
    }

    /**
     * Avis récents
     */
    public function findRecent(int $limit = 20, bool $publishedOnly = true): array
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
     * Avis les plus utiles
     */
    public function findMostHelpful(int $limit = 10, bool $publishedOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.helpfulCount > 0')
            ->orderBy('r.helpfulCount', 'DESC')
            ->setMaxResults($limit);

        if ($publishedOnly) {
            $qb->andWhere('r.isPublished = :published')
               ->setParameter('published', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche d'avis
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.client', 'c')
            ->leftJoin('r.prestataire', 'p')
            ->orderBy('r.createdAt', 'DESC');

        if (!empty($criteria['client'])) {
            $qb->andWhere('r.client = :client')
               ->setParameter('client', $criteria['client']);
        }

        if (!empty($criteria['prestataire'])) {
            $qb->andWhere('r.prestataire = :prestataire')
               ->setParameter('prestataire', $criteria['prestataire']);
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

    /**
     * Prestataires les mieux notés
     */
    public function getTopRatedPrestataires(int $limit = 10, int $minReviews = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('p.id, p.firstName, p.lastName, AVG(r.rating) as average_rating, COUNT(r.id) as review_count')
            ->innerJoin('r.prestataire', 'p')
            ->andWhere('r.isPublished = :published')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('published', true)
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->groupBy('p.id')
            ->having('review_count >= :minReviews')
            ->setParameter('minReviews', $minReviews)
            ->orderBy('average_rating', 'DESC')
            ->addOrderBy('review_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Avis par mois
     */
    public function getMonthlyDistribution(int $year): array
    {
        return $this->createQueryBuilder('r')
            ->select('MONTH(r.createdAt) as month, COUNT(r.id) as count, AVG(r.rating) as average_rating')
            ->andWhere('YEAR(r.createdAt) = :year')
            ->andWhere('r.isPublished = :published')
            ->setParameter('year', $year)
            ->setParameter('published', true)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
}
/**
 * Évolution des notes dans le temps
 */
public function getRatingTrend(Prestataire $prestataire, int $months = 12): array
{
    $startDate = (new \DateTime())->modify("-{$months} months");

    return $this->createQueryBuilder('r')
        ->select('DATE_FORMAT(r.createdAt, \'%Y-%m\') as month, AVG(r.rating) as average_rating, COUNT(r.id) as count')
        ->andWhere('r.prestataire = :prestataire')
        ->andWhere('r.createdAt >= :startDate')
        ->andWhere('r.isPublished = :published')
        ->setParameter('prestataire', $prestataire)
        ->setParameter('startDate', $startDate)
        ->setParameter('published', true)
        ->groupBy('month')
        ->orderBy('month', 'ASC')
        ->getQuery()
        ->getResult();
}

/**
 * Tags les plus utilisés
 */
public function getMostUsedTags(int $limit = 20): array
{
    $conn = $this->getEntityManager()->getConnection();
    
    $sql = '
        SELECT 
            JSON_UNQUOTE(JSON_EXTRACT(tags, CONCAT("$[", numbers.n, "]"))) as tag,
            COUNT(*) as count
        FROM reviews
        CROSS JOIN (
            SELECT 0 as n UNION SELECT 1 UNION SELECT 2 UNION SELECT 3 UNION SELECT 4 
            UNION SELECT 5 UNION SELECT 6 UNION SELECT 7 UNION SELECT 8 UNION SELECT 9
        ) numbers
        WHERE is_published = 1
        AND JSON_LENGTH(tags) > numbers.n
        AND tags IS NOT NULL
        GROUP BY tag
        ORDER BY count DESC
        LIMIT :limit
    ';

    $stmt = $conn->prepare($sql);
    $result = $stmt->executeQuery(['limit' => $limit]);

    return $result->fetchAllAssociative();
}

/**
 * Taux de réponse d'un prestataire
 */
public function getResponseRate(Prestataire $prestataire): float
{
    $total = $this->countByPrestataire($prestataire, true);

    if ($total === 0) {
        return 0;
    }

    $responded = $this->createQueryBuilder('r')
        ->select('COUNT(r.id)')
        ->andWhere('r.prestataire = :prestataire')
        ->andWhere('r.isPublished = :published')
        ->andWhere('r.response IS NOT NULL')
        ->setParameter('prestataire', $prestataire)
        ->setParameter('published', true)
        ->getQuery()
        ->getSingleScalarResult();

    return round(($responded / $total) * 100, 2);
}

/**
 * Délai moyen de réponse d'un prestataire
 */
public function getAverageResponseTime(Prestataire $prestataire): float
{
    $conn = $this->getEntityManager()->getConnection();
    
    $sql = '
        SELECT AVG(
            TIMESTAMPDIFF(HOUR, created_at, responded_at)
        ) as avg_hours
        FROM reviews
        WHERE prestataire_id = :prestataireId
        AND response IS NOT NULL
        AND responded_at IS NOT NULL
    ';

    $stmt = $conn->prepare($sql);
    $result = $stmt->executeQuery(['prestataireId' => $prestataire->getId()]);

    return round((float)($result->fetchOne() ?? 0), 2);
}

/**
 * Notes détaillées moyennes pour un prestataire
 */
public function getDetailedRatings(Prestataire $prestataire): array
{
    $qb = $this->createQueryBuilder('r')
        ->andWhere('r.prestataire = :prestataire')
        ->andWhere('r.isPublished = :published')
        ->setParameter('prestataire', $prestataire)
        ->setParameter('published', true);

    return [
        'quality' => (clone $qb)->select('AVG(r.qualityRating)')
            ->andWhere('r.qualityRating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0,

        'punctuality' => (clone $qb)->select('AVG(r.punctualityRating)')
            ->andWhere('r.punctualityRating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0,

        'professionalism' => (clone $qb)->select('AVG(r.professionalismRating)')
            ->andWhere('r.professionalismRating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0,

        'communication' => (clone $qb)->select('AVG(r.communicationRating)')
            ->andWhere('r.communicationRating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult() ?? 0,
    ];
}

/**
 * Exporte les avis en CSV
 */
public function exportToCsv(array $reviews): string
{
    $handle = fopen('php://temp', 'r+');
    
    // En-têtes
    fputcsv($handle, [
        'ID',
        'Client',
        'Prestataire',
        'Réservation',
        'Note',
        'Recommande',
        'Commentaire',
        'A répondu',
        'Vérifié',
        'Publié',
        'Créé le'
    ]);

    // Données
    foreach ($reviews as $review) {
        fputcsv($handle, [
            $review->getId(),
            $review->getClient()->getFullName(),
            $review->getPrestataire()->getFullName(),
            $review->getBooking()->getReferenceNumber(),
            $review->getRating(),
            $review->isRecommended() ? 'Oui' : 'Non',
            substr($review->getComment() ?? '', 0, 100),
            $review->hasResponse() ? 'Oui' : 'Non',
            $review->isVerified() ? 'Oui' : 'Non',
            $review->isPublished() ? 'Oui' : 'Non',
            $review->getCreatedAt()->format('d/m/Y H:i')
        ]);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

/**
 * Avis en attente de modération
 */
public function findPendingModeration(): array
{
    return $this->createQueryBuilder('r')
        ->andWhere('r.isPublished = :published')
        ->andWhere('r.isVerified = :verified')
        ->setParameter('published', false)
        ->setParameter('verified', false)
        ->orderBy('r.createdAt', 'ASC')
        ->getQuery()
        ->getResult();
}

/**
 * Clients les plus actifs (le plus d'avis)
 */
public function getMostActiveClients(int $limit = 10): array
{
    return $this->createQueryBuilder('r')
        ->select('c.id, c.firstName, c.lastName, COUNT(r.id) as review_count')
        ->innerJoin('r.client', 'c')
        ->andWhere('r.isPublished = :published')
        ->setParameter('published', true)
        ->groupBy('c.id')
        ->orderBy('review_count', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

}