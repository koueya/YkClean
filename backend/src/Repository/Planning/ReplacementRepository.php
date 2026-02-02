<?php

namespace App\Repository\Planning;

use App\Entity\Booking\Booking;
use App\Entity\Planning\Replacement;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Replacement
 * Gère les requêtes liées aux remplacements de prestataires
 */
class ReplacementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Replacement::class);
    }

    /**
     * Sauvegarde une entité Replacement
     */
    public function save(Replacement $replacement, bool $flush = false): void
    {
        $this->getEntityManager()->persist($replacement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une entité Replacement
     */
    public function remove(Replacement $replacement, bool $flush = false): void
    {
        $this->getEntityManager()->remove($replacement);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ==================== RECHERCHES PAR BOOKING ====================

    /**
     * Trouve tous les remplacements pour une réservation
     */
    public function findByBooking(Booking $booking): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.originalBooking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le remplacement actif pour une réservation
     */
    public function findActiveByBooking(Booking $booking): ?Replacement
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.originalBooking = :booking')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('booking', $booking)
            ->setParameter('activeStatuses', [
                Replacement::STATUS_PENDING,
                Replacement::STATUS_SEARCHING,
                Replacement::STATUS_PROPOSED,
                Replacement::STATUS_ACCEPTED,
                Replacement::STATUS_CONFIRMED
            ])
            ->orderBy('r.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si une réservation a un remplacement en cours
     */
    public function hasActiveReplacement(Booking $booking): bool
    {
        return $this->findActiveByBooking($booking) !== null;
    }

    // ==================== RECHERCHES PAR PRESTATAIRE ====================

    /**
     * Trouve tous les remplacements d'un prestataire original
     */
    public function findByOriginalPrestataire(
        Prestataire $prestataire,
        ?string $status = null,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.originalPrestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.requestedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les remplacements effectués par un prestataire
     */
    public function findByReplacementPrestataire(
        Prestataire $prestataire,
        ?string $status = null,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.replacementPrestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.requestedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les remplacements impliquant un prestataire (original ou remplacement)
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.originalPrestataire = :prestataire OR r.replacementPrestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ==================== RECHERCHES PAR STATUT ====================

    /**
     * Trouve tous les remplacements avec un statut donné
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.requestedAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remplacements en attente
     */
    public function findPending(?int $limit = null): array
    {
        return $this->findByStatus(Replacement::STATUS_PENDING, $limit);
    }

    /**
     * Trouve les remplacements en recherche
     */
    public function findSearching(?int $limit = null): array
    {
        return $this->findByStatus(Replacement::STATUS_SEARCHING, $limit);
    }

    /**
     * Trouve les remplacements proposés en attente d'acceptation
     */
    public function findProposed(?int $limit = null): array
    {
        return $this->findByStatus(Replacement::STATUS_PROPOSED, $limit);
    }

    /**
     * Trouve les remplacements confirmés
     */
    public function findConfirmed(?int $limit = null): array
    {
        return $this->findByStatus(Replacement::STATUS_CONFIRMED, $limit);
    }

    // ==================== RECHERCHES PAR PRIORITÉ ====================

    /**
     * Trouve les remplacements urgents
     */
    public function findUrgent(?string $status = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.priority = :priority')
            ->setParameter('priority', Replacement::PRIORITY_URGENT)
            ->orderBy('r.requestedAt', 'ASC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remplacements par priorité
     */
    public function findByPriority(int $priority, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.priority = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('r.requestedAt', 'ASC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    // ==================== RECHERCHES PAR TYPE ====================

    /**
     * Trouve les remplacements par type
     */
    public function findByType(string $type, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.type = :type')
            ->setParameter('type', $type)
            ->orderBy('r.requestedAt', 'DESC');

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remplacements d'urgence
     */
    public function findEmergencies(?int $limit = null): array
    {
        return $this->findByType(Replacement::TYPE_EMERGENCY, $limit);
    }

    /**
     * Trouve les remplacements pour absence
     */
    public function findAbsences(?int $limit = null): array
    {
        return $this->findByType(Replacement::TYPE_ABSENCE, $limit);
    }

    // ==================== RECHERCHES PAR DATE ====================

    /**
     * Trouve les remplacements dans une période
     */
    public function findByDateRange(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.requestedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('r.requestedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remplacements créés aujourd'hui
     */
    public function findToday(?string $status = null): array
    {
        $today = new \DateTime('today');
        $tomorrow = new \DateTime('tomorrow');

        return $this->findByDateRange($today, $tomorrow, $status);
    }

    /**
     * Trouve les remplacements de cette semaine
     */
    public function findThisWeek(?string $status = null): array
    {
        $startOfWeek = new \DateTime('monday this week');
        $endOfWeek = new \DateTime('sunday this week 23:59:59');

        return $this->findByDateRange($startOfWeek, $endOfWeek, $status);
    }

    /**
     * Trouve les remplacements de ce mois
     */
    public function findThisMonth(?string $status = null): array
    {
        $startOfMonth = new \DateTime('first day of this month');
        $endOfMonth = new \DateTime('last day of this month 23:59:59');

        return $this->findByDateRange($startOfMonth, $endOfMonth, $status);
    }

    // ==================== RECHERCHES AVANCÉES ====================

    /**
     * Trouve les remplacements sans remplaçant après plusieurs tentatives
     */
    public function findStuckReplacements(int $minAttempts = 3): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.searchAttempts >= :minAttempts')
            ->andWhere('r.replacementPrestataire IS NULL')
            ->setParameter('statuses', [
                Replacement::STATUS_PENDING,
                Replacement::STATUS_SEARCHING
            ])
            ->setParameter('minAttempts', $minAttempts)
            ->orderBy('r.priority', 'DESC')
            ->addOrderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements expirés (passé la date de réservation)
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('r')
            ->innerJoin('r.originalBooking', 'b')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('b.scheduledDate < :now')
            ->setParameter('statuses', [
                Replacement::STATUS_PENDING,
                Replacement::STATUS_SEARCHING,
                Replacement::STATUS_PROPOSED
            ])
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements nécessitant une action urgente
     * (proche de la date de réservation et sans remplaçant)
     */
    public function findRequiringUrgentAction(int $hoursBeforeBooking = 24): array
    {
        $threshold = new \DateTimeImmutable("+{$hoursBeforeBooking} hours");

        return $this->createQueryBuilder('r')
            ->innerJoin('r.originalBooking', 'b')
            ->andWhere('r.status IN (:statuses)')
            ->andWhere('r.replacementPrestataire IS NULL')
            ->andWhere('b.scheduledDate <= :threshold')
            ->andWhere('b.scheduledDate > :now')
            ->setParameter('statuses', [
                Replacement::STATUS_PENDING,
                Replacement::STATUS_SEARCHING
            ])
            ->setParameter('threshold', $threshold)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.scheduledDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements avec un score de matching élevé
     */
    public function findWithHighMatchingScore(int $minScore = 80): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.matchingScore >= :minScore')
            ->andWhere('r.status = :status')
            ->setParameter('minScore', $minScore)
            ->setParameter('status', Replacement::STATUS_PROPOSED)
            ->orderBy('r.matchingScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements automatiques
     */
    public function findAutomatic(?string $status = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.isAutomatic = :isAutomatic')
            ->setParameter('isAutomatic', true)
            ->orderBy('r.requestedAt', 'DESC');

        if ($status !== null) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($limit !== null) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    // ==================== STATISTIQUES ====================

    /**
     * Compte les remplacements par statut
     */
    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les remplacements actifs
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.status IN (:activeStatuses)')
            ->setParameter('activeStatuses', [
                Replacement::STATUS_PENDING,
                Replacement::STATUS_SEARCHING,
                Replacement::STATUS_PROPOSED,
                Replacement::STATUS_ACCEPTED,
                Replacement::STATUS_CONFIRMED
            ])
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les remplacements pour un prestataire
     */
    public function countByPrestataire(Prestataire $prestataire, bool $asReplacement = false): int
    {
        $field = $asReplacement ? 'replacementPrestataire' : 'originalPrestataire';

        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere("r.{$field} = :prestataire")
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques générales
     */
    public function getStatistics(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                'COUNT(r.id) as total',
                'COUNT(CASE WHEN r.status = :pending THEN 1 END) as pending',
                'COUNT(CASE WHEN r.status = :searching THEN 1 END) as searching',
                'COUNT(CASE WHEN r.status = :proposed THEN 1 END) as proposed',
                'COUNT(CASE WHEN r.status = :confirmed THEN 1 END) as confirmed',
                'COUNT(CASE WHEN r.status = :completed THEN 1 END) as completed',
                'COUNT(CASE WHEN r.status IN (:rejected) THEN 1 END) as rejected',
                'COUNT(CASE WHEN r.status = :cancelled THEN 1 END) as cancelled',
                'COUNT(CASE WHEN r.priority = :urgent THEN 1 END) as urgent',
                'AVG(r.matchingScore) as avgMatchingScore',
                'AVG(r.searchAttempts) as avgSearchAttempts'
            )
            ->setParameter('pending', Replacement::STATUS_PENDING)
            ->setParameter('searching', Replacement::STATUS_SEARCHING)
            ->setParameter('proposed', Replacement::STATUS_PROPOSED)
            ->setParameter('confirmed', Replacement::STATUS_CONFIRMED)
            ->setParameter('completed', Replacement::STATUS_COMPLETED)
            ->setParameter('rejected', [Replacement::STATUS_REJECTED, Replacement::STATUS_DECLINED])
            ->setParameter('cancelled', Replacement::STATUS_CANCELLED)
            ->setParameter('urgent', Replacement::PRIORITY_URGENT);

        if ($startDate !== null) {
            $qb->andWhere('r.requestedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.requestedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'pending' => (int) $result['pending'],
            'searching' => (int) $result['searching'],
            'proposed' => (int) $result['proposed'],
            'confirmed' => (int) $result['confirmed'],
            'completed' => (int) $result['completed'],
            'rejected' => (int) $result['rejected'],
            'cancelled' => (int) $result['cancelled'],
            'urgent' => (int) $result['urgent'],
            'avgMatchingScore' => $result['avgMatchingScore'] ? round((float) $result['avgMatchingScore'], 2) : null,
            'avgSearchAttempts' => $result['avgSearchAttempts'] ? round((float) $result['avgSearchAttempts'], 2) : null,
        ];
    }

    /**
     * Obtient le taux de succès des remplacements
     */
    public function getSuccessRate(?\DateTimeInterface $startDate = null, ?\DateTimeInterface $endDate = null): float
    {
        $qb = $this->createQueryBuilder('r')
            ->select(
                'COUNT(r.id) as total',
                'COUNT(CASE WHEN r.status IN (:success) THEN 1 END) as success'
            )
            ->setParameter('success', [
                Replacement::STATUS_CONFIRMED,
                Replacement::STATUS_COMPLETED
            ]);

        if ($startDate !== null) {
            $qb->andWhere('r.requestedAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate !== null) {
            $qb->andWhere('r.requestedAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleResult();
        
        $total = (int) $result['total'];
        $success = (int) $result['success'];

        return $total > 0 ? round(($success / $total) * 100, 2) : 0;
    }

    // ==================== RECHERCHES AVEC FILTRES AVANCÉS ====================

    /**
     * Crée un QueryBuilder avec des filtres personnalisables
     */
    public function createFilteredQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.originalBooking', 'b')
            ->leftJoin('r.originalPrestataire', 'op')
            ->leftJoin('r.replacementPrestataire', 'rp')
            ->addSelect('b', 'op', 'rp');

        // Filtre par statut
        if (isset($filters['status'])) {
            if (is_array($filters['status'])) {
                $qb->andWhere('r.status IN (:statuses)')
                    ->setParameter('statuses', $filters['status']);
            } else {
                $qb->andWhere('r.status = :status')
                    ->setParameter('status', $filters['status']);
            }
        }

        // Filtre par type
        if (isset($filters['type'])) {
            if (is_array($filters['type'])) {
                $qb->andWhere('r.type IN (:types)')
                    ->setParameter('types', $filters['type']);
            } else {
                $qb->andWhere('r.type = :type')
                    ->setParameter('type', $filters['type']);
            }
        }

        // Filtre par priorité
        if (isset($filters['priority'])) {
            if (is_array($filters['priority'])) {
                $qb->andWhere('r.priority IN (:priorities)')
                    ->setParameter('priorities', $filters['priority']);
            } else {
                $qb->andWhere('r.priority = :priority')
                    ->setParameter('priority', $filters['priority']);
            }
        }

        // Filtre par prestataire original
        if (isset($filters['originalPrestataire'])) {
            $qb->andWhere('r.originalPrestataire = :originalPrestataire')
                ->setParameter('originalPrestataire', $filters['originalPrestataire']);
        }

        // Filtre par prestataire de remplacement
        if (isset($filters['replacementPrestataire'])) {
            $qb->andWhere('r.replacementPrestataire = :replacementPrestataire')
                ->setParameter('replacementPrestataire', $filters['replacementPrestataire']);
        }

        // Filtre par réservation
        if (isset($filters['booking'])) {
            $qb->andWhere('r.originalBooking = :booking')
                ->setParameter('booking', $filters['booking']);
        }

        // Filtre par période de demande
        if (isset($filters['requestedFrom'])) {
            $qb->andWhere('r.requestedAt >= :requestedFrom')
                ->setParameter('requestedFrom', $filters['requestedFrom']);
        }

        if (isset($filters['requestedTo'])) {
            $qb->andWhere('r.requestedAt <= :requestedTo')
                ->setParameter('requestedTo', $filters['requestedTo']);
        }

        // Filtre par score de matching
        if (isset($filters['minMatchingScore'])) {
            $qb->andWhere('r.matchingScore >= :minMatchingScore')
                ->setParameter('minMatchingScore', $filters['minMatchingScore']);
        }

        // Filtre automatique/manuel
        if (isset($filters['isAutomatic'])) {
            $qb->andWhere('r.isAutomatic = :isAutomatic')
                ->setParameter('isAutomatic', $filters['isAutomatic']);
        }

        // Filtre par notification client
        if (isset($filters['clientNotified'])) {
            $qb->andWhere('r.clientNotified = :clientNotified')
                ->setParameter('clientNotified', $filters['clientNotified']);
        }

        // Filtre par présence de remplaçant
        if (isset($filters['hasReplacement'])) {
            if ($filters['hasReplacement']) {
                $qb->andWhere('r.replacementPrestataire IS NOT NULL');
            } else {
                $qb->andWhere('r.replacementPrestataire IS NULL');
            }
        }

        // Tri
        $orderBy = $filters['orderBy'] ?? 'r.requestedAt';
        $orderDirection = $filters['orderDirection'] ?? 'DESC';
        $qb->orderBy($orderBy, $orderDirection);

        return $qb;
    }

    /**
     * Recherche avec filtres et pagination
     */
    public function search(array $filters = [], int $page = 1, int $limit = 20): array
    {
        $qb = $this->createFilteredQueryBuilder($filters);

        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit);

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de résultats avec filtres
     */
    public function countFiltered(array $filters = []): int
    {
        $qb = $this->createFilteredQueryBuilder($filters);
        
        $qb->select('COUNT(r.id)');

        return (int) $qb->getQuery()->getSingleScalarResult();
    }
}