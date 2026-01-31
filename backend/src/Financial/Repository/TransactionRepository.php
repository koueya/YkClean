<?php

namespace App\Financial\Repository;

use App\Financial\Entity\Transaction;
use App\Entity\Booking\Booking;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Transaction (module Financial)
 * 
 * Gestion de toutes les transactions financières de la plateforme
 * (paiements, remboursements, virements, commissions)
 * 
 * @extends ServiceEntityRepository<Transaction>
 */
class TransactionRepository extends ServiceEntityRepository
{
    // Types de transaction
    public const TYPE_PAYMENT = 'payment';
    public const TYPE_REFUND = 'refund';
    public const TYPE_PAYOUT = 'payout';
    public const TYPE_COMMISSION = 'commission';
    public const TYPE_TRANSFER = 'transfer';

    // Statuts de transaction
    public const STATUS_PENDING = 'pending';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Transaction::class);
    }

    /**
     * Trouve toutes les transactions par type
     */
    public function findByType(string $type, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.type = :type')
            ->setParameter('type', $type)
            ->orderBy('t.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les transactions par statut
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les transactions d'un utilisateur
     */
    public function findByUser(User $user, ?string $type = null, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.fromUser = :user OR t.toUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les transactions envoyées par un utilisateur
     */
    public function findSentByUser(User $user, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.fromUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les transactions reçues par un utilisateur
     */
    public function findReceivedByUser(User $user, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.toUser = :user')
            ->setParameter('user', $user)
            ->orderBy('t.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les transactions d'une réservation
     */
    public function findByBooking(Booking $booking): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.booking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une transaction par ID Stripe
     */
    public function findByStripePaymentId(string $stripePaymentId): ?Transaction
    {
        return $this->createQueryBuilder('t')
            ->where('t.stripePaymentId = :stripePaymentId')
            ->setParameter('stripePaymentId', $stripePaymentId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les transactions entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $type = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->where('t.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('t.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les transactions récentes
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('t')
            ->orderBy('t.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les transactions en attente depuis plus de X heures
     */
    public function findPendingOlderThan(int $hours): array
    {
        $date = new \DateTime("-{$hours} hours");

        return $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.createdAt < :date')
            ->setParameter('status', self::STATUS_PENDING)
            ->setParameter('date', $date)
            ->orderBy('t.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le montant total des transactions
     */
    public function getTotalAmount(
        ?string $type = null,
        ?string $status = null,
        ?\DateTimeInterface $since = null
    ): float {
        $qb = $this->createQueryBuilder('t')
            ->select('SUM(t.amount)');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        if ($status) {
            $qb->andWhere('t.status = :status')
                ->setParameter('status', $status);
        }

        if ($since) {
            $qb->andWhere('t.createdAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le volume de transactions par type
     */
    public function getVolumeByType(?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('t.type, SUM(t.amount) as total, COUNT(t.id) as count')
            ->where('t.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->groupBy('t.type')
            ->orderBy('total', 'DESC');

        if ($since) {
            $qb->andWhere('t.createdAt >= :since')
                ->setParameter('since', $since);
        }

        $results = $qb->getQuery()->getResult();

        $volume = [];
        foreach ($results as $result) {
            $volume[$result['type']] = [
                'total' => (float) $result['total'],
                'count' => (int) $result['count'],
            ];
        }

        return $volume;
    }

    /**
     * Compte les transactions par statut
     */
    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les transactions par type
     */
    public function countByType(string $type): int
    {
        return (int) $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.type = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques globales
     */
    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('t');

        $total = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $completed = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        $failed = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->where('t.status = :status')
            ->setParameter('status', self::STATUS_FAILED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = (float) (clone $qb)
            ->select('SUM(t.amount)')
            ->where('t.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $averageAmount = $completed > 0 ? $totalAmount / $completed : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'completed' => $completed,
            'failed' => $failed,
            'total_amount' => $totalAmount,
            'average_amount' => round($averageAmount, 2),
        ];
    }

    /**
     * Obtient les statistiques par utilisateur
     */
    public function getStatisticsByUser(User $user): array
    {
        $qb = $this->createQueryBuilder('t')
            ->where('t.fromUser = :user OR t.toUser = :user')
            ->setParameter('user', $user);

        $total = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $sent = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->andWhere('t.fromUser = :user')
            ->getQuery()
            ->getSingleScalarResult();

        $received = (int) (clone $qb)
            ->select('COUNT(t.id)')
            ->andWhere('t.toUser = :user')
            ->getQuery()
            ->getSingleScalarResult();

        $totalSent = (float) (clone $qb)
            ->select('SUM(t.amount)')
            ->andWhere('t.fromUser = :user')
            ->andWhere('t.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $totalReceived = (float) (clone $qb)
            ->select('SUM(t.amount)')
            ->andWhere('t.toUser = :user')
            ->andWhere('t.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'sent' => $sent,
            'received' => $received,
            'total_sent' => $totalSent,
            'total_received' => $totalReceived,
            'balance' => $totalReceived - $totalSent,
        ];
    }

    /**
     * Obtient les transactions groupées par jour
     */
    public function getDailyTransactions(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $type = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->select('DATE(t.createdAt) as date, SUM(t.amount) as total, COUNT(t.id) as count')
            ->where('t.createdAt BETWEEN :startDate AND :endDate')
            ->andWhere('t.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', self::STATUS_COMPLETED)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les transactions groupées par mois
     */
    public function getMonthlyTransactions(int $year, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('MONTH(t.createdAt) as month, SUM(t.amount) as total, COUNT(t.id) as count')
            ->where('YEAR(t.createdAt) = :year')
            ->andWhere('t.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', self::STATUS_COMPLETED)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient le taux de succès des transactions
     */
    public function getSuccessRate(?\DateTimeInterface $since = null): float
    {
        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->where('t.status IN (:statuses)')
            ->setParameter('statuses', [self::STATUS_COMPLETED, self::STATUS_FAILED]);

        if ($since) {
            $qb->andWhere('t.createdAt >= :since')
                ->setParameter('since', $since);
        }

        $total = (int) $qb->getQuery()->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $qb->andWhere('t.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED);

        $successful = (int) $qb->getQuery()->getSingleScalarResult();

        return round(($successful / $total) * 100, 2);
    }

    /**
     * Recherche de transactions avec critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('t')
            ->leftJoin('t.fromUser', 'fu')
            ->leftJoin('t.toUser', 'tu')
            ->leftJoin('t.booking', 'b')
            ->addSelect('fu', 'tu', 'b');

        if (isset($criteria['type'])) {
            if (is_array($criteria['type'])) {
                $qb->andWhere('t.type IN (:types)')
                    ->setParameter('types', $criteria['type']);
            } else {
                $qb->andWhere('t.type = :type')
                    ->setParameter('type', $criteria['type']);
            }
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $qb->andWhere('t.status IN (:statuses)')
                    ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('t.status = :status')
                    ->setParameter('status', $criteria['status']);
            }
        }

        if (isset($criteria['user_id'])) {
            $qb->andWhere('t.fromUser = :userId OR t.toUser = :userId')
                ->setParameter('userId', $criteria['user_id']);
        }

        if (isset($criteria['from_user_id'])) {
            $qb->andWhere('t.fromUser = :fromUserId')
                ->setParameter('fromUserId', $criteria['from_user_id']);
        }

        if (isset($criteria['to_user_id'])) {
            $qb->andWhere('t.toUser = :toUserId')
                ->setParameter('toUserId', $criteria['to_user_id']);
        }

        if (isset($criteria['booking_id'])) {
            $qb->andWhere('t.booking = :bookingId')
                ->setParameter('bookingId', $criteria['booking_id']);
        }

        if (isset($criteria['min_amount'])) {
            $qb->andWhere('t.amount >= :minAmount')
                ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (isset($criteria['max_amount'])) {
            $qb->andWhere('t.amount <= :maxAmount')
                ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('t.createdAt >= :startDate')
                ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('t.createdAt <= :endDate')
                ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['stripe_payment_id'])) {
            $qb->andWhere('t.stripePaymentId = :stripePaymentId')
                ->setParameter('stripePaymentId', $criteria['stripe_payment_id']);
        }

        if (isset($criteria['has_description'])) {
            if ($criteria['has_description']) {
                $qb->andWhere('t.description IS NOT NULL');
            } else {
                $qb->andWhere('t.description IS NULL');
            }
        }

        $qb->orderBy('t.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient le délai moyen de traitement (en minutes)
     */
    public function getAverageProcessingTime(): float
    {
        $result = $this->createQueryBuilder('t')
            ->select('AVG(TIMESTAMPDIFF(MINUTE, t.createdAt, t.completedAt))')
            ->where('t.status = :status')
            ->andWhere('t.completedAt IS NOT NULL')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) ($result ?? 0), 1);
    }

    /**
     * Obtient le top des utilisateurs par volume de transactions
     */
    public function getTopUsersByVolume(int $limit = 10, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('t')
            ->select('u.id, u.firstName, u.lastName, SUM(t.amount) as total, COUNT(t.id) as count')
            ->leftJoin('t.fromUser', 'u')
            ->where('t.status = :status')
            ->setParameter('status', self::STATUS_COMPLETED)
            ->groupBy('u.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit);

        if ($type) {
            $qb->andWhere('t.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Exporte les transactions en CSV
     */
    public function exportToCsv(array $transactions): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Type',
            'De',
            'Vers',
            'Montant (€)',
            'Statut',
            'Stripe ID',
            'Description',
            'Créé le',
            'Complété le',
        ]);

        // Données
        foreach ($transactions as $transaction) {
            fputcsv($handle, [
                $transaction->getId(),
                ucfirst($transaction->getType()),
                $transaction->getFromUser() ? $transaction->getFromUser()->getFullName() : '-',
                $transaction->getToUser() ? $transaction->getToUser()->getFullName() : '-',
                $transaction->getAmount(),
                ucfirst($transaction->getStatus()),
                $transaction->getStripePaymentId() ?? '',
                $transaction->getDescription() ?? '',
                $transaction->getCreatedAt()->format('d/m/Y H:i'),
                $transaction->getCompletedAt() ? $transaction->getCompletedAt()->format('d/m/Y H:i') : '',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Sauvegarde une transaction
     */
    public function save(Transaction $transaction, bool $flush = false): void
    {
        $this->getEntityManager()->persist($transaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une transaction
     */
    public function remove(Transaction $transaction, bool $flush = false): void
    {
        $this->getEntityManager()->remove($transaction);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}