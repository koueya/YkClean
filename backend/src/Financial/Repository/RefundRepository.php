<?php

namespace App\Financial\Repository;

use App\Financial\Entity\Refund;
use App\Entity\Payment\Payment;
use App\Entity\User\Client;
use App\Entity\Booking\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Refund (module Financial)
 * 
 * Gestion des remboursements
 * 
 * @extends ServiceEntityRepository<Refund>
 */
class RefundRepository extends ServiceEntityRepository
{
    // Statuts de remboursement
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    // Raisons de remboursement
    public const REASON_CANCELLED_BY_CLIENT = 'cancelled_by_client';
    public const REASON_CANCELLED_BY_PRESTATAIRE = 'cancelled_by_prestataire';
    public const REASON_SERVICE_NOT_PROVIDED = 'service_not_provided';
    public const REASON_POOR_SERVICE = 'poor_service';
    public const REASON_DUPLICATE_PAYMENT = 'duplicate_payment';
    public const REASON_FRAUDULENT = 'fraudulent';
    public const REASON_OTHER = 'other';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Refund::class);
    }

    /**
     * Trouve un remboursement par payment
     */
    public function findByPayment(Payment $payment): ?Refund
    {
        return $this->createQueryBuilder('r')
            ->where('r.payment = :payment')
            ->setParameter('payment', $payment)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve tous les remboursements par statut
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.requestedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remboursements en attente
     */
    public function findPending(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PENDING, $limit);
    }

    /**
     * Trouve les remboursements en cours de traitement
     */
    public function findProcessing(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PROCESSING, $limit);
    }

    /**
     * Trouve les remboursements complétés
     */
    public function findCompleted(?\DateTimeInterface $since = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->orderBy('r.completedAt', 'DESC');

        if ($since) {
            $qb->andWhere('r.completedAt >= :since')
                ->setParameter('since', $since);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remboursements rejetés
     */
    public function findRejected(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_REJECTED, $limit);
    }

    /**
     * Trouve les remboursements d'un client
     */
    public function findByClient(Client $client, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.payment', 'p')
            ->leftJoin('p.booking', 'b')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('r.requestedAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve un remboursement par ID Stripe
     */
    public function findByStripeRefundId(string $stripeRefundId): ?Refund
    {
        return $this->createQueryBuilder('r')
            ->where('r.stripeRefundId = :stripeRefundId')
            ->setParameter('stripeRefundId', $stripeRefundId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les remboursements par raison
     */
    public function findByReason(string $reason): array
    {
        return $this->createQueryBuilder('r')
            ->where('r.reason = :reason')
            ->setParameter('reason', $reason)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remboursements demandés entre deux dates
     */
    public function findRequestedBetween(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('r')
            ->where('r.requestedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remboursements en attente depuis plus de X jours
     */
    public function findPendingOlderThan(int $days): array
    {
        $date = new \DateTime("-{$days} days");

        return $this->createQueryBuilder('r')
            ->where('r.status = :status')
            ->andWhere('r.requestedAt < :date')
            ->setParameter('status', self::STATUS_PENDING)
            ->setParameter('date', $date)
            ->orderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total des remboursements
     */
    public function getTotalAmount(
        ?string $status = null,
        ?\DateTimeInterface $since = null
    ): float {
        $qb = $this->createQueryBuilder('r')
            ->select('SUM(r.amount)');

        if ($status) {
            $qb->andWhere('r.status = :status')
                ->setParameter('status', $status);
        }

        if ($since) {
            $qb->andWhere('r.requestedAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des remboursements complétés
     */
    public function getTotalCompletedAmount(?\DateTimeInterface $since = null): float
    {
        return $this->getTotalAmount(self::STATUS_COMPLETED, $since);
    }

    /**
     * Compte les remboursements par statut
     */
    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques globales des remboursements
     */
    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('r');

        $total = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $processing = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', self::STATUS_PROCESSING)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        $rejected = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->where('r.status = :status')
            ->setParameter('status', self::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = (float) (clone $qb)
            ->select('SUM(r.amount)')
            ->where('r.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $averageAmount = $completed > 0 ? $totalAmount / $completed : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'processing' => $processing,
            'completed' => $completed,
            'rejected' => $rejected,
            'total_amount' => $totalAmount,
            'average_amount' => round($averageAmount, 2),
        ];
    }

    /**
     * Obtient les statistiques des remboursements par client
     */
    public function getStatisticsByClient(Client $client): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.payment', 'p')
            ->leftJoin('p.booking', 'b')
            ->where('b.client = :client')
            ->setParameter('client', $client);

        $total = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = (int) (clone $qb)
            ->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = (float) (clone $qb)
            ->select('SUM(r.amount)')
            ->andWhere('r.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'completed' => $completed,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Obtient la distribution des raisons de remboursement
     */
    public function getReasonDistribution(): array
    {
        $results = $this->createQueryBuilder('r')
            ->select('r.reason, COUNT(r.id) as count')
            ->groupBy('r.reason')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $distribution = [];
        foreach ($results as $result) {
            $distribution[$result['reason']] = (int) $result['count'];
        }

        return $distribution;
    }

    /**
     * Obtient le délai moyen de traitement (en heures)
     */
    public function getAverageProcessingTime(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('AVG(TIMESTAMPDIFF(HOUR, r.requestedAt, r.completedAt))')
            ->where('r.status = :status')
            ->andWhere('r.completedAt IS NOT NULL')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) ($result ?? 0), 1);
    }

    /**
     * Recherche de remboursements avec critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.payment', 'p')
            ->addSelect('p');

        if (isset($criteria['payment_id'])) {
            $qb->andWhere('r.payment = :paymentId')
                ->setParameter('paymentId', $criteria['payment_id']);
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $qb->andWhere('r.status IN (:statuses)')
                    ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('r.status = :status')
                    ->setParameter('status', $criteria['status']);
            }
        }

        if (isset($criteria['reason'])) {
            if (is_array($criteria['reason'])) {
                $qb->andWhere('r.reason IN (:reasons)')
                    ->setParameter('reasons', $criteria['reason']);
            } else {
                $qb->andWhere('r.reason = :reason')
                    ->setParameter('reason', $criteria['reason']);
            }
        }

        if (isset($criteria['min_amount'])) {
            $qb->andWhere('r.amount >= :minAmount')
                ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (isset($criteria['max_amount'])) {
            $qb->andWhere('r.amount <= :maxAmount')
                ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('r.requestedAt >= :startDate')
                ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('r.requestedAt <= :endDate')
                ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['has_stripe_id'])) {
            if ($criteria['has_stripe_id']) {
                $qb->andWhere('r.stripeRefundId IS NOT NULL');
            } else {
                $qb->andWhere('r.stripeRefundId IS NULL');
            }
        }

        $qb->orderBy('r.requestedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les remboursements groupés par mois
     */
    public function getMonthlyRefunds(int $year): array
    {
        return $this->createQueryBuilder('r')
            ->select('MONTH(r.requestedAt) as month, SUM(r.amount) as total, COUNT(r.id) as count')
            ->where('YEAR(r.requestedAt) = :year')
            ->andWhere('r.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', self::STATUS_COMPLETED)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le taux de remboursement (pourcentage de paiements remboursés)
     */
    public function getRefundRate(?\DateTimeInterface $since = null): float
    {
        // Total des paiements complétés
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from('App\Entity\Payment\Payment', 'p')
            ->where('p.status = :status')
            ->setParameter('status', 'completed');

        if ($since) {
            $qb->andWhere('p.paidAt >= :since')
                ->setParameter('since', $since);
        }

        $totalPayments = (int) $qb->getQuery()->getSingleScalarResult();

        if ($totalPayments === 0) {
            return 0;
        }

        // Total des remboursements complétés
        $totalRefunds = $this->countByStatus(self::STATUS_COMPLETED);

        return round(($totalRefunds / $totalPayments) * 100, 2);
    }

    /**
     * Sauvegarde un remboursement
     */
    public function save(Refund $refund, bool $flush = false): void
    {
        $this->getEntityManager()->persist($refund);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un remboursement
     */
    public function remove(Refund $refund, bool $flush = false): void
    {
        $this->getEntityManager()->remove($refund);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}