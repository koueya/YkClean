<?php

namespace App\Repository\Financial;

use App\Financial\Entity\ClientExpense;
use App\Entity\User\Client;
use App\Entity\Booking\Booking;
use App\Financial\Entity\Refund;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité ClientExpense (module Financial)
 * 
 * Gestion des dépenses des clients
 * 
 * @extends ServiceEntityRepository<ClientExpense>
 */
class ClientExpenseRepository extends ServiceEntityRepository
{
    // Statuts de dépense
    public const STATUS_PAID = 'paid';
    public const STATUS_PENDING = 'pending';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_CANCELLED = 'cancelled';

    // Méthodes de paiement
    public const METHOD_CARD = 'card';
    public const METHOD_SEPA = 'sepa';
    public const METHOD_WALLET = 'wallet';
    public const METHOD_BANK_TRANSFER = 'bank_transfer';
    public const METHOD_CASH = 'cash';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ClientExpense::class);
    }

    /**
     * Trouve toutes les dépenses d'un client
     */
    public function findByClient(Client $client, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->where('ce.client = :client')
            ->setParameter('client', $client)
            ->orderBy('ce.paidAt', 'DESC');

        if ($status) {
            $qb->andWhere('ce.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve une dépense par réservation
     */
    public function findByBooking(Booking $booking): ?ClientExpense
    {
        return $this->createQueryBuilder('ce')
            ->where('ce.booking = :booking')
            ->setParameter('booking', $booking)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les dépenses par statut
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->where('ce.status = :status')
            ->setParameter('status', $status)
            ->orderBy('ce.paidAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les dépenses payées
     */
    public function findPaid(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PAID, $limit);
    }

    /**
     * Trouve les dépenses en attente
     */
    public function findPending(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PENDING, $limit);
    }

    /**
     * Trouve les dépenses remboursées
     */
    public function findRefunded(?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_REFUNDED, $limit);
    }

    /**
     * Trouve les dépenses par méthode de paiement
     */
    public function findByPaymentMethod(string $paymentMethod): array
    {
        return $this->createQueryBuilder('ce')
            ->where('ce.paymentMethod = :paymentMethod')
            ->setParameter('paymentMethod', $paymentMethod)
            ->orderBy('ce.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les dépenses avec facture
     */
    public function findWithInvoice(): array
    {
        return $this->createQueryBuilder('ce')
            ->where('ce.invoiceNumber IS NOT NULL')
            ->orderBy('ce.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une dépense par numéro de facture
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?ClientExpense
    {
        return $this->createQueryBuilder('ce')
            ->where('ce.invoiceNumber = :invoiceNumber')
            ->setParameter('invoiceNumber', $invoiceNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les dépenses entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Client $client = null
    ): array {
        $qb = $this->createQueryBuilder('ce')
            ->where('ce.paidAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ce.paidAt', 'DESC');

        if ($client) {
            $qb->andWhere('ce.client = :client')
                ->setParameter('client', $client);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calcule le montant total des dépenses
     */
    public function getTotalAmount(
        ?Client $client = null,
        ?string $status = null,
        ?\DateTimeInterface $since = null
    ): float {
        $qb = $this->createQueryBuilder('ce')
            ->select('SUM(ce.amount)');

        if ($client) {
            $qb->andWhere('ce.client = :client')
                ->setParameter('client', $client);
        }

        if ($status) {
            $qb->andWhere('ce.status = :status')
                ->setParameter('status', $status);
        }

        if ($since) {
            $qb->andWhere('ce.paidAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total payé par un client
     */
    public function getTotalPaidByClient(Client $client, ?\DateTimeInterface $since = null): float
    {
        return $this->getTotalAmount($client, self::STATUS_PAID, $since);
    }

    /**
     * Calcule le montant total remboursé pour un client
     */
    public function getTotalRefundedByClient(Client $client): float
    {
        return $this->getTotalAmount($client, self::STATUS_REFUNDED);
    }

    /**
     * Compte les dépenses par statut
     */
    public function countByStatus(string $status, ?Client $client = null): int
    {
        $qb = $this->createQueryBuilder('ce')
            ->select('COUNT(ce.id)')
            ->where('ce.status = :status')
            ->setParameter('status', $status);

        if ($client) {
            $qb->andWhere('ce.client = :client')
                ->setParameter('client', $client);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques d'un client
     */
    public function getStatisticsByClient(Client $client): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->where('ce.client = :client')
            ->setParameter('client', $client);

        $total = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $paid = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->andWhere('ce.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->andWhere('ce.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $refunded = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->andWhere('ce.status = :status')
            ->setParameter('status', self::STATUS_REFUNDED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = (float) (clone $qb)
            ->select('SUM(ce.amount)')
            ->andWhere('ce.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $totalRefunded = (float) (clone $qb)
            ->select('SUM(ce.amount)')
            ->andWhere('ce.status = :status')
            ->setParameter('status', self::STATUS_REFUNDED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $averageAmount = $paid > 0 ? $totalAmount / $paid : 0;

        return [
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
            'refunded' => $refunded,
            'total_amount' => $totalAmount,
            'total_refunded' => $totalRefunded,
            'average_amount' => round($averageAmount, 2),
        ];
    }

    /**
     * Obtient les statistiques globales
     */
    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('ce');

        $total = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $paid = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->where('ce.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->where('ce.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $refunded = (int) (clone $qb)
            ->select('COUNT(ce.id)')
            ->where('ce.status = :status')
            ->setParameter('status', self::STATUS_REFUNDED)
            ->getQuery()
            ->getSingleScalarResult();

        $totalAmount = (float) (clone $qb)
            ->select('SUM(ce.amount)')
            ->where('ce.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'paid' => $paid,
            'pending' => $pending,
            'refunded' => $refunded,
            'total_amount' => $totalAmount,
        ];
    }

    /**
     * Obtient la distribution par méthode de paiement
     */
    public function getPaymentMethodDistribution(?Client $client = null): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->select('ce.paymentMethod, COUNT(ce.id) as count, SUM(ce.amount) as total')
            ->where('ce.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->groupBy('ce.paymentMethod')
            ->orderBy('count', 'DESC');

        if ($client) {
            $qb->andWhere('ce.client = :client')
                ->setParameter('client', $client);
        }

        $results = $qb->getQuery()->getResult();

        $distribution = [];
        foreach ($results as $result) {
            $distribution[$result['paymentMethod']] = [
                'count' => (int) $result['count'],
                'total' => (float) $result['total'],
            ];
        }

        return $distribution;
    }

    /**
     * Obtient les dépenses groupées par mois
     */
    public function getMonthlyExpenses(int $year, ?Client $client = null): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->select('MONTH(ce.paidAt) as month, SUM(ce.amount) as total, COUNT(ce.id) as count')
            ->where('YEAR(ce.paidAt) = :year')
            ->andWhere('ce.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', self::STATUS_PAID)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($client) {
            $qb->andWhere('ce.client = :client')
                ->setParameter('client', $client);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les dépenses groupées par jour
     */
    public function getDailyExpenses(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Client $client = null
    ): array {
        $qb = $this->createQueryBuilder('ce')
            ->select('DATE(ce.paidAt) as date, SUM(ce.amount) as total, COUNT(ce.id) as count')
            ->where('ce.paidAt BETWEEN :startDate AND :endDate')
            ->andWhere('ce.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', self::STATUS_PAID)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        if ($client) {
            $qb->andWhere('ce.client = :client')
                ->setParameter('client', $client);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les top clients par dépenses
     */
    public function getTopClientsByExpenses(int $limit = 10, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->select('c.id, c.firstName, c.lastName, c.email, SUM(ce.amount) as total, COUNT(ce.id) as count')
            ->leftJoin('ce.client', 'c')
            ->where('ce.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->groupBy('c.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('ce.paidAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche de dépenses avec critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('ce')
            ->leftJoin('ce.client', 'c')
            ->leftJoin('ce.booking', 'b')
            ->addSelect('c', 'b');

        if (isset($criteria['client_id'])) {
            $qb->andWhere('ce.client = :clientId')
                ->setParameter('clientId', $criteria['client_id']);
        }

        if (isset($criteria['booking_id'])) {
            $qb->andWhere('ce.booking = :bookingId')
                ->setParameter('bookingId', $criteria['booking_id']);
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $qb->andWhere('ce.status IN (:statuses)')
                    ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('ce.status = :status')
                    ->setParameter('status', $criteria['status']);
            }
        }

        if (isset($criteria['payment_method'])) {
            if (is_array($criteria['payment_method'])) {
                $qb->andWhere('ce.paymentMethod IN (:methods)')
                    ->setParameter('methods', $criteria['payment_method']);
            } else {
                $qb->andWhere('ce.paymentMethod = :method')
                    ->setParameter('method', $criteria['payment_method']);
            }
        }

        if (isset($criteria['min_amount'])) {
            $qb->andWhere('ce.amount >= :minAmount')
                ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (isset($criteria['max_amount'])) {
            $qb->andWhere('ce.amount <= :maxAmount')
                ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('ce.paidAt >= :startDate')
                ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('ce.paidAt <= :endDate')
                ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['invoice_number'])) {
            $qb->andWhere('ce.invoiceNumber = :invoiceNumber')
                ->setParameter('invoiceNumber', $criteria['invoice_number']);
        }

        if (isset($criteria['has_invoice'])) {
            if ($criteria['has_invoice']) {
                $qb->andWhere('ce.invoiceNumber IS NOT NULL');
            } else {
                $qb->andWhere('ce.invoiceNumber IS NULL');
            }
        }

        if (isset($criteria['has_refund'])) {
            if ($criteria['has_refund']) {
                $qb->andWhere('ce.refund IS NOT NULL');
            } else {
                $qb->andWhere('ce.refund IS NULL');
            }
        }

        $qb->orderBy('ce.paidAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient le montant moyen des dépenses
     */
    public function getAverageAmount(?Client $client = null): float
    {
        $qb = $this->createQueryBuilder('ce')
            ->select('AVG(ce.amount)')
            ->where('ce.status = :status')
            ->setParameter('status', self::STATUS_PAID);

        if ($client) {
            $qb->andWhere('ce.client = :client')
                ->setParameter('client', $client);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return round((float) ($result ?? 0), 2);
    }

    /**
     * Exporte les dépenses en CSV
     */
    public function exportToCsv(array $expenses): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Client',
            'Réservation',
            'Montant (€)',
            'Méthode',
            'Statut',
            'Facture',
            'Payé le',
        ]);

        // Données
        foreach ($expenses as $expense) {
            fputcsv($handle, [
                $expense->getId(),
                $expense->getClient()->getFullName(),
                $expense->getBooking() ? $expense->getBooking()->getId() : '-',
                $expense->getAmount(),
                ucfirst($expense->getPaymentMethod()),
                ucfirst($expense->getStatus()),
                $expense->getInvoiceNumber() ?? '-',
                $expense->getPaidAt()->format('d/m/Y H:i'),
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Sauvegarde une dépense
     */
    public function save(ClientExpense $expense, bool $flush = false): void
    {
        $this->getEntityManager()->persist($expense);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une dépense
     */
    public function remove(ClientExpense $expense, bool $flush = false): void
    {
        $this->getEntityManager()->remove($expense);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}