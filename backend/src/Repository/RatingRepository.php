<?php
// src/Repository/RatingRepository.php

namespace App\Repository;

use App\Entity\Rating;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Rating>
 */
class RatingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Rating::class);
    }

    /**
     * Trouve toutes les notes d'une entité
     */
    public function findByEntity(string $entityType, int $entityId, bool $publicOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->orderBy('r.createdAt', 'DESC');

        if ($publicOnly) {
            $qb->andWhere('r.isPublic = :public')
               ->setParameter('public', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve la note d'un utilisateur pour une entité
     */
    public function findUserRatingForEntity(
        User $user,
        string $entityType,
        int $entityId,
        ?string $category = null
    ): ?Rating {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.ratedBy = :user')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->setParameter('user', $user)
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId);

        if ($category) {
            $qb->andWhere('r.category = :category')
               ->setParameter('category', $category);
        }

        return $qb->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcule la note moyenne d'une entité
     */
    public function getAverageRating(
        string $entityType,
        int $entityId,
        ?string $category = null
    ): float {
        $qb = $this->createQueryBuilder('r')
            ->select('AVG(r.score)')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->andWhere('r.isPublic = :public')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('public', true);

        if ($category) {
            $qb->andWhere('r.category = :category')
               ->setParameter('category', $category);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return round((float)($result ?? 0), 2);
    }

    /**
     * Compte le nombre de notes pour une entité
     */
    public function countByEntity(
        string $entityType,
        int $entityId,
        ?string $category = null
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->andWhere('r.isPublic = :public')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('public', true);

        if ($category) {
            $qb->andWhere('r.category = :category')
               ->setParameter('category', $category);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Répartition des notes pour une entité
     */
    public function getRatingDistribution(string $entityType, int $entityId): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.score, COUNT(r.id) as count')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->andWhere('r.isPublic = :public')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('public', true)
            ->groupBy('r.score')
            ->orderBy('r.score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notes par catégorie
     */
    public function findByCategory(string $category, bool $publicOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.category = :category')
            ->setParameter('category', $category)
            ->orderBy('r.createdAt', 'DESC');

        if ($publicOnly) {
            $qb->andWhere('r.isPublic = :public')
               ->setParameter('public', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les notes par score
     */
    public function findByScore(int $score, bool $publicOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.score = :score')
            ->setParameter('score', $score)
            ->orderBy('r.createdAt', 'DESC');

        if ($publicOnly) {
            $qb->andWhere('r.isPublic = :public')
               ->setParameter('public', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les notes d'un utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.ratedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notes vérifiées
     */
    public function findVerified(bool $publicOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isVerified = :verified')
            ->setParameter('verified', true)
            ->orderBy('r.createdAt', 'DESC');

        if ($publicOnly) {
            $qb->andWhere('r.isPublic = :public')
               ->setParameter('public', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques globales des notes
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isPublic = :public')
            ->setParameter('public', true);

        return [
            'total_ratings' => (clone $qb)->select('COUNT(r.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'average_score' => (clone $qb)->select('AVG(r.score)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,

            'verified_ratings' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.isVerified = :verified')
                ->setParameter('verified', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'with_comment' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.comment IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult(),

            'positive_ratings' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.score >= 4')
                ->getQuery()
                ->getSingleScalarResult(),

            'negative_ratings' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.score <= 2')
                ->getQuery()
                ->getSingleScalarResult(),

            'neutral_ratings' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.score = 3')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    /**
     * Notes moyennes par catégorie
     */
    public function getAverageScoresByCategory(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.category, AVG(r.score) as average_score, COUNT(r.id) as count')
            ->andWhere('r.isPublic = :public')
            ->andWhere('r.category IS NOT NULL')
            ->setParameter('public', true)
            ->groupBy('r.category')
            ->orderBy('average_score', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Entités les mieux notées par type
     */
    public function getTopRatedEntities(string $entityType, int $limit = 10, int $minRatings = 5): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.entityId, AVG(r.score) as average_score, COUNT(r.id) as rating_count')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.isPublic = :public')
            ->setParameter('entityType', $entityType)
            ->setParameter('public', true)
            ->groupBy('r.entityId')
            ->having('rating_count >= :minRatings')
            ->setParameter('minRatings', $minRatings)
            ->orderBy('average_score', 'DESC')
            ->addOrderBy('rating_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Notes récentes
     */
    public function findRecent(int $limit = 20, bool $publicOnly = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($publicOnly) {
            $qb->andWhere('r.isPublic = :public')
               ->setParameter('public', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Notes par mois
     */
    public function getMonthlyDistribution(int $year): array
    {
        return $this->createQueryBuilder('r')
            ->select('MONTH(r.createdAt) as month, COUNT(r.id) as count, AVG(r.score) as average_score')
            ->andWhere('YEAR(r.createdAt) = :year')
            ->andWhere('r.isPublic = :public')
            ->setParameter('year', $year)
            ->setParameter('public', true)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de notes
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC');

        if (!empty($criteria['entity_type'])) {
            $qb->andWhere('r.entityType = :entityType')
               ->setParameter('entityType', $criteria['entity_type']);
        }

        if (!empty($criteria['entity_id'])) {
            $qb->andWhere('r.entityId = :entityId')
               ->setParameter('entityId', $criteria['entity_id']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('r.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (isset($criteria['score'])) {
            $qb->andWhere('r.score = :score')
               ->setParameter('score', $criteria['score']);
        }

        if (isset($criteria['min_score'])) {
            $qb->andWhere('r.score >= :minScore')
               ->setParameter('minScore', $criteria['min_score']);
        }

        if (isset($criteria['max_score'])) {
            $qb->andWhere('r.score <= :maxScore')
               ->setParameter('maxScore', $criteria['max_score']);
        }

        if (!empty($criteria['user'])) {
            $qb->andWhere('r.ratedBy = :user')
               ->setParameter('user', $criteria['user']);
        }

        if (isset($criteria['verified'])) {
            $qb->andWhere('r.isVerified = :verified')
               ->setParameter('verified', $criteria['verified']);
        }

        if (isset($criteria['public'])) {
            $qb->andWhere('r.isPublic = :public')
               ->setParameter('public', $criteria['public']);
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
     * Exporte les notes en CSV
     */
    public function exportToCsv(array $ratings): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Type Entité',
            'ID Entité',
            'Catégorie',
            'Score',
            'Noté par',
            'Commentaire',
            'Public',
            'Vérifié',
            'Créé le'
        ]);

        // Données
        foreach ($ratings as $rating) {
            fputcsv($handle, [
                $rating->getId(),
                $rating->getEntityType(),
                $rating->getEntityId(),
                $rating->getCategoryLabel(),
                $rating->getScore(),
                $rating->getRatedBy()->getFullName(),
                substr($rating->getComment() ?? '', 0, 100),
                $rating->isPublic() ? 'Oui' : 'Non',
                $rating->isVerified() ? 'Oui' : 'Non',
                $rating->getCreatedAt()->format('d/m/Y H:i')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Utilisateurs les plus actifs (qui notent le plus)
     */
    public function getMostActiveUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->select('u.id, u.firstName, u.lastName, COUNT(r.id) as rating_count')
            ->innerJoin('r.ratedBy', 'u')
            ->groupBy('u.id')
            ->orderBy('rating_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Notes détaillées d'une entité (par catégorie)
     */
    public function getDetailedRatings(string $entityType, int $entityId): array
    {
        $ratings = $this->createQueryBuilder('r')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->andWhere('r.isPublic = :public')
            ->andWhere('r.category IS NOT NULL')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('public', true)
            ->getQuery()
            ->getResult();

        $details = [];
        foreach ($ratings as $rating) {
            $category = $rating->getCategory();
            if (!isset($details[$category])) {
                $details[$category] = [
                    'count' => 0,
                    'total' => 0,
                    'average' => 0
                ];
            }
            $details[$category]['count']++;
            $details[$category]['total'] += $rating->getScore();
        }

        foreach ($details as $category => &$data) {
            $data['average'] = round($data['total'] / $data['count'], 2);
        }

        return $details;
    }

    /**
     * Évolution des notes dans le temps
     */
    public function getRatingTrend(
        string $entityType,
        int $entityId,
        int $months = 12
    ): array {
        $startDate = (new \DateTime())->modify("-{$months} months");

        return $this->createQueryBuilder('r')
            ->select('DATE_FORMAT(r.createdAt, \'%Y-%m\') as month, AVG(r.score) as average_score, COUNT(r.id) as count')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->andWhere('r.createdAt >= :startDate')
            ->andWhere('r.isPublic = :public')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('startDate', $startDate)
            ->setParameter('public', true)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Taux de satisfaction global (% notes >= 4)
     */
    public function getSatisfactionRate(string $entityType, int $entityId): float
    {
        $total = $this->countByEntity($entityType, $entityId);

        if ($total === 0) {
            return 0;
        }

        $satisfied = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.entityId = :entityId')
            ->andWhere('r.score >= 4')
            ->andWhere('r.isPublic = :public')
            ->setParameter('entityType', $entityType)
            ->setParameter('entityId', $entityId)
            ->setParameter('public', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($satisfied / $total) * 100, 2);
    }

    /**
     * Comparaison entre deux entités
     */
    public function compareEntities(
        string $entityType,
        int $entityId1,
        int $entityId2
    ): array {
        return [
            'entity1' => [
                'id' => $entityId1,
                'average_rating' => $this->getAverageRating($entityType, $entityId1),
                'rating_count' => $this->countByEntity($entityType, $entityId1),
                'distribution' => $this->getRatingDistribution($entityType, $entityId1)
            ],
            'entity2' => [
                'id' => $entityId2,
                'average_rating' => $this->getAverageRating($entityType, $entityId2),
                'rating_count' => $this->countByEntity($entityType, $entityId2),
                'distribution' => $this->getRatingDistribution($entityType, $entityId2)
            ]
        ];
    }

    /**
     * Notes par contexte
     */
    public function findByContext(string $context, ?int $contextId = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.context = :context')
            ->setParameter('context', $context)
            ->orderBy('r.createdAt', 'DESC');

        if ($contextId !== null) {
            $qb->andWhere('r.contextId = :contextId')
               ->setParameter('contextId', $contextId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Détecte les notes suspectes (possibles faux avis)
     */
    public function findSuspiciousRatings(): array
    {
        // Notes multiples du même utilisateur pour la même entité
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT entity_type, entity_id, rated_by_id, COUNT(*) as count
            FROM ratings
            GROUP BY entity_type, entity_id, rated_by_id
            HAVING count > 1
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Calcule le score pondéré d'une entité (Bayesian average)
     */
    public function getWeightedScore(
        string $entityType,
        int $entityId,
        int $minRatingsRequired = 10
    ): float {
        $count = $this->countByEntity($entityType, $entityId);
        $average = $this->getAverageRating($entityType, $entityId);
        
        // Note moyenne globale
        $globalAverage = $this->createQueryBuilder('r')
            ->select('AVG(r.score)')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.isPublic = :public')
            ->setParameter('entityType', $entityType)
            ->setParameter('public', true)
            ->getQuery()
            ->getSingleScalarResult() ?? 3;

        // Formule Bayesian Average
        $weightedScore = (($minRatingsRequired * $globalAverage) + ($count * $average)) 
                        / ($minRatingsRequired + $count);

        return round($weightedScore, 2);
    }
}