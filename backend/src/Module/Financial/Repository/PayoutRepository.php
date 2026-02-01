<?php

namespace App\Financial\Repository;

use App\Financial\Entity\Payout;  // ✅ Namespace EXACT selon doctrine.yaml
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Payout
 * 
 * NAMESPACE DOCTRINE : App\Financial\Entity\Payout
 * Selon config/packages/doctrine.yaml :
 *   - dir: src/Financial/Entity
 *   - prefix: App\Financial\Entity
 * 
 * @extends ServiceEntityRepository<Payout>
 */
class PayoutRepository extends ServiceEntityRepository
{
    // Statuts de payout
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payout::class);
    }

    /**
     * Trouve tous les payouts d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('p.requestedAt', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les payouts par statut
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.requestedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les payouts en attente
     */
    public function findPending(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PENDING, $limit);
    }

    /**
     * Trouve les payouts en cours de traitement
     */
    public function findProcessing(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PROCESSING, $limit);
    }

    /**
     * Trouve les payouts complétés
     */
    public function findCompleted(?\DateTimeInterface $since = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->orderBy('p.completedAt', 'DESC');

        if ($since) {
            $qb->andWhere('p.completedAt >= :since')
                ->setParameter('since', $since);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les payouts échoués
     */
    public function findFailed(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_FAILED, $limit);
    }

    /**
     * Trouve un payout par son ID Stripe
     */
    public function findByStripePayoutId(string $stripePayoutId): ?Payout
    {
        return $this->createQueryBuilder('p')
            ->where('p.stripePayoutId = :stripePayoutId')
            ->setParameter('stripePayoutId', $stripePayoutId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les payouts d'une période
     */
    public function findByPeriod(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->where('p.periodStart >= :startDate')
            ->andWhere('p.periodEnd <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.requestedAt', 'DESC');

        if ($prestataire) {
            $qb->andWhere('p.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les payouts demandés entre deux dates
     */
    public function findRequestedBetween(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('p')
            ->where('p.requestedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le dernier payout d'un prestataire
     */
    public function findLatestByPrestataire(Prestataire $prestataire): ?Payout
    {
        return $this->createQueryBuilder('p')
            ->where('p.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('p.requestedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Calcule le montant total des payouts d'un prestataire
     */
    public function getTotalAmountByPrestataire(
        Prestataire $prestataire,
        ?string $status = null
    ): float {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($status) {
            $qb->andWhere('p.status = :status')
                ->setParameter('status', $status);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des payouts complétés
     */
    public function getTotalCompletedAmount(
        ?\DateTimeInterface $since = null,
        ?Prestataire $prestataire = null
    ): float {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED);

        if ($since) {
            $qb->andWhere('p.completedAt >= :since')
                ->setParameter('since', $since);
        }

        if ($prestataire) {
            $qb->andWhere('p.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Compte les payouts par statut pour un prestataire
     */
    public function countByStatus(Prestataire $prestataire, string $status): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.prestataire = :prestataire')
            ->andWhere('p.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques des payouts d'un prestataire
     */
    public function getStatisticsByPrestataire(Prestataire $prestataire): array
    {
        $qb = $this->createQueryBuilder('p')
            ->where('p.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        $total = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $processing = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', self::STATUS_PROCESSING)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        $failed = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', self::STATUS_FAILED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = (float) (clone $qb)
            ->select('SUM(p.amount)')
            ->andWhere('p.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $averageAmount = $completed > 0 ? $totalAmount / $completed : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'total_amount' => $totalAmount,
            'average_amount' => round($averageAmount, 2),
        ];
    }

    /**
     * Obtient les statistiques globales des payouts
     */
    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('p');

        $total = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $processing = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_PROCESSING)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        $failed = (int) (clone $qb)
            ->select('COUNT(p.id)')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_FAILED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = (float) (clone $qb)
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'failed' => $failed,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Trouve les payouts en attente depuis plus de X jours
     */
    public function findPendingOlderThan(int $days): array
    {
        $date = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.requestedAt < :date')
            ->setParameter('status', self::STATUS_PENDING)
            ->setParameter('date', $date)
            ->orderBy('p.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les payouts qui peuvent être traités
     */
    public function findReadyForProcessing(): array
    {
        return $this->createQueryBuilder('p')
            ->where('p.status = :status')
            ->andWhere('p.stripePayoutId IS NULL')
            ->andWhere('p.amount > 0')
            ->setParameter('status', self::STATUS_PENDING)
            ->orderBy('p.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de payouts avec critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('p');

        if (isset($criteria['prestataire_id'])) {
            $qb->andWhere('p.prestataire = :prestataireId')
                ->setParameter('prestataireId', $criteria['prestataire_id']);
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $qb->andWhere('p.status IN (:statuses)')
                    ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('p.status = :status')
                    ->setParameter('status', $criteria['status']);
            }
        }

        if (isset($criteria['min_amount'])) {
            $qb->andWhere('p.amount >= :minAmount')
                ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (isset($criteria['max_amount'])) {
            $qb->andWhere('p.amount <= :maxAmount')
                ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('p.requestedAt >= :startDate')
                ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('p.requestedAt <= :endDate')
                ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['period_start'])) {
            $qb->andWhere('p.periodStart >= :periodStart')
                ->setParameter('periodStart', $criteria['period_start']);
        }

        if (isset($criteria['period_end'])) {
            $qb->andWhere('p.periodEnd <= :periodEnd')
                ->setParameter('periodEnd', $criteria['period_end']);
        }

        if (isset($criteria['has_stripe_id'])) {
            if ($criteria['has_stripe_id']) {
                $qb->andWhere('p.stripePayoutId IS NOT NULL');
            } else {
                $qb->andWhere('p.stripePayoutId IS NULL');
            }
        }

        $qb->orderBy('p.requestedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient le montant moyen des payouts
     */
    public function getAverageAmount(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('AVG(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED);

        if ($prestataire) {
            $qb->andWhere('p.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return round((float) ($result ?? 0), 2);
    }

    /**
     * Obtient le délai moyen de traitement (en jours)
     */
    public function getAverageProcessingTime(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('p')
            ->select('AVG(TIMESTAMPDIFF(DAY, p.requestedAt, p.completedAt))')
            ->where('p.status = :status')
            ->andWhere('p.completedAt IS NOT NULL')
            ->setParameter('status', self::STATUS_COMPLETED);

        if ($prestataire) {
            $qb->andWhere('p.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return round((float) ($result ?? 0), 1);
    }

    /**
     * Obtient les payouts groupés par mois
     */
    public function getMonthlyPayouts(int $year, ?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('MONTH(p.requestedAt) as month, SUM(p.amount) as total, COUNT(p.id) as count')
            ->where('YEAR(p.requestedAt) = :year')
            ->andWhere('p.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', self::STATUS_COMPLETED)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($prestataire) {
            $qb->andWhere('p.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Sauvegarde un payout
     */
    public function save(Payout $payout, bool $flush = false): void
    {
        $this->getEntityManager()->persist($payout);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un payout
     */
    public function remove(Payout $payout, bool $flush = false): void
    {
        $this->getEntityManager()->remove($payout);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}