<?php
// src/Repository/PaymentRepository.php

namespace App\Repository;

use App\Entity\Payment;
use App\Entity\Booking;
use App\Entity\Client;
use App\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Payment>
 */
class PaymentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Payment::class);
    }

    /**
     * Trouve tous les paiements par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements d'une réservation
     */
    public function findByBooking(Booking $booking): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.booking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements d'un client
     */
    public function findByClient(Client $client, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.booking', 'b')
            ->andWhere('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('p.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les paiements liés à un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.booking', 'b')
            ->andWhere('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('p.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les paiements par méthode
     */
    public function findByMethod(string $method): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.paymentMethod = :method')
            ->setParameter('method', $method)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements réussis
     */
    public function findSuccessful(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->orderBy('p.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements échoués
     */
    public function findFailed(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'failed')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements en attente
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remboursements
     */
    public function findRefunds(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'refunded')
            ->orderBy('p.refundedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les paiements entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.createdAt >= :startDate')
            ->andWhere('p.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('p.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calcule le montant total payé pour une réservation
     */
    public function getTotalPaidForBooking(Booking $booking): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->andWhere('p.booking = :booking')
            ->andWhere('p.status = :status')
            ->setParameter('booking', $booking)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Vérifie si une réservation est entièrement payée
     */
    public function isBookingFullyPaid(Booking $booking): bool
    {
        $totalPaid = $this->getTotalPaidForBooking($booking);
        $bookingAmount = $booking->getAmount();

        return bccomp($totalPaid, $bookingAmount, 2) >= 0;
    }

    /**
     * Statistiques des paiements
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('p');

        return [
            'total_payments' => (clone $qb)->select('COUNT(p.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'pending' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.status = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult(),

            'completed' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult(),

            'failed' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.status = :status')
                ->setParameter('status', 'failed')
                ->getQuery()
                ->getSingleScalarResult(),

            'refunded' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.status = :status')
                ->setParameter('status', 'refunded')
                ->getQuery()
                ->getSingleScalarResult(),

            'total_amount' => (clone $qb)->select('SUM(p.amount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'completed_amount' => (clone $qb)->select('SUM(p.amount)')
                ->andWhere('p.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'refunded_amount' => (clone $qb)->select('SUM(p.amount)')
                ->andWhere('p.status = :status')
                ->setParameter('status', 'refunded')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_payment' => (clone $qb)->select('AVG(p.amount)')
                ->andWhere('p.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Compte les paiements par statut
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Répartition par statut
     */
    public function getStatusDistribution(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.status, COUNT(p.id) as count, SUM(p.amount) as total')
            ->groupBy('p.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition par méthode de paiement
     */
    public function getMethodDistribution(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.paymentMethod, COUNT(p.id) as count, SUM(p.amount) as total')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('p.paymentMethod')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paiements par mois
     */
    public function getMonthlyDistribution(int $year): array
    {
        return $this->createQueryBuilder('p')
            ->select('MONTH(p.paidAt) as month, COUNT(p.id) as count, SUM(p.amount) as total')
            ->andWhere('YEAR(p.paidAt) = :year')
            ->andWhere('p.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', 'completed')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paiements par jour de la semaine
     */
    public function getWeekdayDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                DAYNAME(paid_at) as day_name,
                DAYOFWEEK(paid_at) as day_number,
                COUNT(*) as count,
                SUM(amount) as total
            FROM payments
            WHERE status = "completed"
            GROUP BY day_name, day_number
            ORDER BY day_number
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Paiements par tranche de montant
     */
    public function getAmountDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN amount < 50 THEN "0-50€"
                    WHEN amount < 100 THEN "50-100€"
                    WHEN amount < 200 THEN "100-200€"
                    WHEN amount < 500 THEN "200-500€"
                    WHEN amount < 1000 THEN "500-1000€"
                    ELSE "1000€+"
                END as amount_range,
                COUNT(*) as count,
                SUM(amount) as total
            FROM payments
            WHERE status = "completed"
            GROUP BY amount_range
            ORDER BY 
                CASE amount_range
                    WHEN "0-50€" THEN 1
                    WHEN "50-100€" THEN 2
                    WHEN "100-200€" THEN 3
                    WHEN "200-500€" THEN 4
                    WHEN "500-1000€" THEN 5
                    WHEN "1000€+" THEN 6
                END
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Taux de réussite des paiements
     */
    public function getSuccessRate(): float
    {
        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status IN (:statuses)')
            ->setParameter('statuses', ['completed', 'failed'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $successful = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return round(($successful / $total) * 100, 2);
    }

    /**
     * Taux de remboursement
     */
    public function getRefundRate(): float
    {
        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $refunded = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'refunded')
            ->getQuery()
            ->getSingleScalarResult();

        return round(($refunded / $total) * 100, 2);
    }

    /**
     * Revenus totaux
     */
    public function getTotalRevenue(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed');

        if ($startDate) {
            $qb->andWhere('p.paidAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('p.paidAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ?? '0.00';
    }

    /**
     * Revenus mensuels
     */
    public function getMonthlyRevenue(int $year): array
    {
        return $this->createQueryBuilder('p')
            ->select('MONTH(p.paidAt) as month, SUM(p.amount) as revenue')
            ->andWhere('YEAR(p.paidAt) = :year')
            ->andWhere('p.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', 'completed')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Clients avec le plus de paiements
     */
    public function getTopPayingClients(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                c.id,
                c.first_name,
                c.last_name,
                COUNT(p.id) as payment_count,
                SUM(p.amount) as total_spent
            FROM payments p
            INNER JOIN bookings b ON p.booking_id = b.id
            INNER JOIN clients c ON b.client_id = c.id
            WHERE p.status = "completed"
            GROUP BY c.id
            ORDER BY total_spent DESC
            LIMIT :limit
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['limit' => $limit]);

        return $result->fetchAllAssociative();
    }

    /**
     * Délai moyen entre création et paiement
     */
    public function getAveragePaymentDelay(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                TIMESTAMPDIFF(MINUTE, created_at, paid_at)
            ) as avg_minutes
            FROM payments
            WHERE status = "completed"
            AND paid_at IS NOT NULL
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        $minutes = (float)($result->fetchOne() ?? 0);
        return round($minutes / 60, 2); // Convertir en heures
    }

    /**
     * Paiements avec transaction externe
     */
    public function findWithTransactionId(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.transactionId IS NOT NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paiements sans transaction externe (anomalie potentielle)
     */
    public function findWithoutTransactionId(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.transactionId IS NULL')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Exporte les paiements en CSV
     */
    public function exportToCsv(array $payments): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Référence',
            'Réservation',
            'Client',
            'Montant (€)',
            'Méthode',
            'Statut',
            'Transaction ID',
            'Créé le',
            'Payé le',
            'Remboursé le'
        ]);

        // Données
        foreach ($payments as $payment) {
            fputcsv($handle, [
                $payment->getId(),
                $payment->getReferenceNumber(),
                $payment->getBooking()->getReferenceNumber(),
                $payment->getBooking()->getClient()->getFullName(),
                $payment->getAmount(),
                $payment->getPaymentMethodLabel(),
                ucfirst($payment->getStatus()),
                $payment->getTransactionId() ?? '',
                $payment->getCreatedAt()->format('d/m/Y H:i'),
                $payment->getPaidAt() ? $payment->getPaidAt()->format('d/m/Y H:i') : '',
                $payment->getRefundedAt() ? $payment->getRefundedAt()->format('d/m/Y H:i') : ''
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Paiements récents
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de paiements
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.booking', 'b')
            ->leftJoin('b.client', 'c')
            ->orderBy('p.createdAt', 'DESC');

        if (!empty($criteria['reference'])) {
            $qb->andWhere('p.referenceNumber LIKE :reference')
               ->setParameter('reference', '%' . $criteria['reference'] . '%');
        }

        if (!empty($criteria['transaction_id'])) {
            $qb->andWhere('p.transactionId LIKE :transactionId')
               ->setParameter('transactionId', '%' . $criteria['transaction_id'] . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('p.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['payment_method'])) {
            $qb->andWhere('p.paymentMethod = :method')
               ->setParameter('method', $criteria['payment_method']);
        }

        if (!empty($criteria['booking'])) {
            $qb->andWhere('p.booking = :booking')
               ->setParameter('booking', $criteria['booking']);
        }

        if (!empty($criteria['client'])) {
            $qb->andWhere('b.client = :client')
               ->setParameter('client', $criteria['client']);
        }

        if (!empty($criteria['min_amount'])) {
            $qb->andWhere('p.amount >= :minAmount')
               ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (!empty($criteria['max_amount'])) {
            $qb->andWhere('p.amount <= :maxAmount')
               ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (!empty($criteria['start_date'])) {
            $qb->andWhere('p.createdAt >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (!empty($criteria['end_date'])) {
            $qb->andWhere('p.createdAt <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les paiements en erreur (échecs répétés)
     */
    public function findRepeatedFailures(int $minAttempts = 3): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT booking_id, COUNT(*) as failed_attempts
            FROM payments
            WHERE status = "failed"
            GROUP BY booking_id
            HAVING failed_attempts >= :minAttempts
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['minAttempts' => $minAttempts]);

        $bookingIds = array_column($result->fetchAllAssociative(), 'booking_id');

        if (empty($bookingIds)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.booking IN (:bookingIds)')
            ->andWhere('p.status = :status')
            ->setParameter('bookingIds', $bookingIds)
            ->setParameter('status', 'failed')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paiements en attente depuis plus de X heures
     */
    public function findPendingOlderThan(int $hours = 24): array
    {
        $date = (new \DateTime())->modify("-{$hours} hours");

        return $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.createdAt < :date')
            ->setParameter('status', 'pending')
            ->setParameter('date', $date)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Montant moyen par méthode de paiement
     */
    public function getAverageAmountByMethod(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.paymentMethod, AVG(p.amount) as average_amount, COUNT(p.id) as count')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('p.paymentMethod')
            ->orderBy('average_amount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Tendance des paiements (croissance)
     */
    public function getPaymentTrend(int $months = 12): array
    {
        $startDate = (new \DateTime())->modify("-{$months} months");

        return $this->createQueryBuilder('p')
            ->select('DATE_FORMAT(p.paidAt, \'%Y-%m\') as month, COUNT(p.id) as count, SUM(p.amount) as total')
            ->andWhere('p.paidAt >= :startDate')
            ->andWhere('p.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'completed')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Paiements par heure de la journée
     */
    public function getHourlyDistribution(): array
    {
        return $this->createQueryBuilder('p')
            ->select('HOUR(p.paidAt) as hour, COUNT(p.id) as count, SUM(p.amount) as total')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Détecte les anomalies de paiement
     */
    public function findAnomalies(): array
    {
        // Paiements avec montant excessif
        $highAmount = $this->createQueryBuilder('p')
            ->andWhere('p.amount > :maxAmount')
            ->setParameter('maxAmount', 5000) // Seuil configurable
            ->getQuery()
            ->getResult();

        // Paiements complétés sans date de paiement
        $missingPaidDate = $this->createQueryBuilder('p')
            ->andWhere('p.status = :status')
            ->andWhere('p.paidAt IS NULL')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getResult();

        // Paiements avec transaction ID en double
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT transaction_id, COUNT(*) as duplicate_count
            FROM payments
            WHERE transaction_id IS NOT NULL
            GROUP BY transaction_id
            HAVING duplicate_count > 1
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $duplicateTransactions = $result->fetchAllAssociative();

        return [
            'high_amount' => $highAmount,
            'missing_paid_date' => $missingPaidDate,
            'duplicate_transactions' => $duplicateTransactions
        ];
    }

    /**
     * Calcule le panier moyen
     */
    public function getAverageBasket(): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.amount)')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Paiements incomplets (réservations partiellement payées)
     */
    public function findIncompletePayments(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                b.id as booking_id,
                b.reference_number,
                b.amount as booking_amount,
                COALESCE(SUM(p.amount), 0) as paid_amount,
                (b.amount - COALESCE(SUM(p.amount), 0)) as remaining_amount
            FROM bookings b
            LEFT JOIN payments p ON p.booking_id = b.id AND p.status = "completed"
            WHERE b.status NOT IN ("cancelled")
            GROUP BY b.id
            HAVING remaining_amount > 0
            ORDER BY remaining_amount DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }
}