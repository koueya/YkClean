<?php
// src/Repository/InvoiceRepository.php

namespace App\Repository\Payment;

use App\Entity\Payment\Invoice;
use App\Entity\Booking\Booking;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Invoice>
 */
class InvoiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Invoice::class);
    }

    /**
     * Trouve toutes les factures par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->setParameter('status', $status)
            ->orderBy('i.invoiceDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve la facture d'une réservation
     */
    public function findByBooking(Booking $booking): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.booking = :booking')
            ->setParameter('booking', $booking)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les factures d'un client
     */
    public function findByClient(Client $client, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.client = :client')
            ->setParameter('client', $client)
            ->orderBy('i.invoiceDate', 'DESC');

        if ($status) {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les factures d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('i.invoiceDate', 'DESC');

        if ($status) {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve une facture par numéro
     */
    public function findByInvoiceNumber(string $invoiceNumber): ?Invoice
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.invoiceNumber = :invoiceNumber')
            ->setParameter('invoiceNumber', $invoiceNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les factures payées
     */
    public function findPaid(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'paid')
            ->orderBy('i.paidAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures impayées
     */
    public function findUnpaid(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status IN (:statuses)')
            ->setParameter('statuses', ['pending', 'sent'])
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures en retard
     */
    public function findOverdue(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('i')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.dueDate < :now')
            ->setParameter('statuses', ['pending', 'sent'])
            ->setParameter('now', $now)
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures envoyées mais non payées
     */
    public function findSentUnpaid(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'sent')
            ->orderBy('i.sentAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les factures entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('i')
            ->andWhere('i.invoiceDate >= :startDate')
            ->andWhere('i.invoiceDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('i.invoiceDate', 'DESC');

        if ($status) {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques des factures
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('i');

        return [
            'total_invoices' => (clone $qb)->select('COUNT(i.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'pending' => (clone $qb)->select('COUNT(i.id)')
                ->andWhere('i.status = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult(),

            'sent' => (clone $qb)->select('COUNT(i.id)')
                ->andWhere('i.status = :status')
                ->setParameter('status', 'sent')
                ->getQuery()
                ->getSingleScalarResult(),

            'paid' => (clone $qb)->select('COUNT(i.id)')
                ->andWhere('i.status = :status')
                ->setParameter('status', 'paid')
                ->getQuery()
                ->getSingleScalarResult(),

            'cancelled' => (clone $qb)->select('COUNT(i.id)')
                ->andWhere('i.status = :status')
                ->setParameter('status', 'cancelled')
                ->getQuery()
                ->getSingleScalarResult(),

            'overdue' => (clone $qb)->select('COUNT(i.id)')
                ->andWhere('i.status IN (:statuses)')
                ->andWhere('i.dueDate < :now')
                ->setParameter('statuses', ['pending', 'sent'])
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->getSingleScalarResult(),

            'total_amount' => (clone $qb)->select('SUM(i.totalAmount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'paid_amount' => (clone $qb)->select('SUM(i.totalAmount)')
                ->andWhere('i.status = :status')
                ->setParameter('status', 'paid')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'unpaid_amount' => (clone $qb)->select('SUM(i.totalAmount)')
                ->andWhere('i.status IN (:statuses)')
                ->setParameter('statuses', ['pending', 'sent'])
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'overdue_amount' => (clone $qb)->select('SUM(i.totalAmount)')
                ->andWhere('i.status IN (:statuses)')
                ->andWhere('i.dueDate < :now')
                ->setParameter('statuses', ['pending', 'sent'])
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_amount' => (clone $qb)->select('AVG(i.totalAmount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Compte les factures par statut
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->andWhere('i.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Répartition par statut
     */
    public function getStatusDistribution(): array
    {
        return $this->createQueryBuilder('i')
            ->select('i.status, COUNT(i.id) as count, SUM(i.totalAmount) as total')
            ->groupBy('i.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Factures par mois
     */
    public function getMonthlyDistribution(int $year): array
    {
        return $this->createQueryBuilder('i')
            ->select('MONTH(i.invoiceDate) as month, COUNT(i.id) as count, SUM(i.totalAmount) as total')
            ->andWhere('YEAR(i.invoiceDate) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Revenus mensuels (factures payées)
     */
    public function getMonthlyRevenue(int $year): array
    {
        return $this->createQueryBuilder('i')
            ->select('MONTH(i.paidAt) as month, SUM(i.totalAmount) as revenue')
            ->andWhere('YEAR(i.paidAt) = :year')
            ->andWhere('i.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', 'paid')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Délai moyen de paiement
     */
    public function getAveragePaymentDelay(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                DATEDIFF(paid_at, invoice_date)
            ) as avg_days
            FROM invoices
            WHERE status = "paid"
            AND paid_at IS NOT NULL
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return round((float)($result->fetchOne() ?? 0), 2);
    }

    /**
     * Taux de paiement dans les délais
     */
    public function getOnTimePaymentRate(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN paid_at <= due_date THEN 1 ELSE 0 END) as on_time
            FROM invoices
            WHERE status = "paid"
            AND paid_at IS NOT NULL
            AND due_date IS NOT NULL
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        
        $data = $result->fetchAssociative();
        $total = $data['total'] ?? 0;

        if ($total === 0) {
            return 0;
        }

        return round(($data['on_time'] / $total) * 100, 2);
    }

    /**
     * Clients avec le plus de factures
     */
    public function getTopClientsByInvoiceCount(int $limit = 10): array
    {
        return $this->createQueryBuilder('i')
            ->select('c.id, c.firstName, c.lastName, COUNT(i.id) as invoice_count, SUM(i.totalAmount) as total_amount')
            ->innerJoin('i.client', 'c')
            ->groupBy('c.id')
            ->orderBy('invoice_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Clients avec le plus de retard de paiement
     */
    public function getTopLatePayingClients(int $limit = 10): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('i')
            ->select('c.id, c.firstName, c.lastName, COUNT(i.id) as overdue_count, SUM(i.totalAmount) as overdue_amount')
            ->innerJoin('i.client', 'c')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.dueDate < :now')
            ->setParameter('statuses', ['pending', 'sent'])
            ->setParameter('now', $now)
            ->groupBy('c.id')
            ->orderBy('overdue_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Factures par tranche de montant
     */
    public function getAmountDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN total_amount < 50 THEN "0-50€"
                    WHEN total_amount < 100 THEN "50-100€"
                    WHEN total_amount < 200 THEN "100-200€"
                    WHEN total_amount < 500 THEN "200-500€"
                    WHEN total_amount < 1000 THEN "500-1000€"
                    ELSE "1000€+"
                END as amount_range,
                COUNT(*) as count,
                SUM(total_amount) as total
            FROM invoices
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
     * Exporte les factures en CSV
     */
    public function exportToCsv(array $invoices): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'Numéro',
            'Client',
            'Prestataire',
            'Date Facture',
            'Date Échéance',
            'Montant HT (€)',
            'TVA (€)',
            'Montant TTC (€)',
            'Statut',
            'Date Paiement',
            'Réservation'
        ]);

        // Données
        foreach ($invoices as $invoice) {
            fputcsv($handle, [
                $invoice->getInvoiceNumber(),
                $invoice->getClient()->getFullName(),
                $invoice->getPrestataire()->getFullName(),
                $invoice->getInvoiceDate()->format('d/m/Y'),
                $invoice->getDueDate() ? $invoice->getDueDate()->format('d/m/Y') : '',
                $invoice->getSubtotal(),
                $invoice->getTaxAmount(),
                $invoice->getTotalAmount(),
                ucfirst($invoice->getStatus()),
                $invoice->getPaidAt() ? $invoice->getPaidAt()->format('d/m/Y') : '',
                $invoice->getBooking()->getReferenceNumber()
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Factures récentes
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('i')
            ->orderBy('i.invoiceDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de factures
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('i')
            ->leftJoin('i.client', 'c')
            ->leftJoin('i.prestataire', 'p')
            ->leftJoin('i.booking', 'b')
            ->orderBy('i.invoiceDate', 'DESC');

        if (!empty($criteria['invoice_number'])) {
            $qb->andWhere('i.invoiceNumber LIKE :invoiceNumber')
               ->setParameter('invoiceNumber', '%' . $criteria['invoice_number'] . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('i.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['client'])) {
            $qb->andWhere('i.client = :client')
               ->setParameter('client', $criteria['client']);
        }

        if (!empty($criteria['prestataire'])) {
            $qb->andWhere('i.prestataire = :prestataire')
               ->setParameter('prestataire', $criteria['prestataire']);
        }

        if (!empty($criteria['booking'])) {
            $qb->andWhere('i.booking = :booking')
               ->setParameter('booking', $criteria['booking']);
        }

        if (!empty($criteria['min_amount'])) {
            $qb->andWhere('i.totalAmount >= :minAmount')
               ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (!empty($criteria['max_amount'])) {
            $qb->andWhere('i.totalAmount <= :maxAmount')
               ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (!empty($criteria['start_date'])) {
            $qb->andWhere('i.invoiceDate >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (!empty($criteria['end_date'])) {
            $qb->andWhere('i.invoiceDate <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['overdue']) && $criteria['overdue'] === true) {
            $qb->andWhere('i.status IN (:statuses)')
               ->andWhere('i.dueDate < :now')
               ->setParameter('statuses', ['pending', 'sent'])
               ->setParameter('now', new \DateTime());
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Factures à échéance proche (dans X jours)
     */
    public function findDueSoon(int $days = 7): array
    {
        $now = new \DateTime();
        $maxDate = (clone $now)->modify("+{$days} days");

        return $this->createQueryBuilder('i')
            ->andWhere('i.status IN (:statuses)')
            ->andWhere('i.dueDate BETWEEN :now AND :maxDate')
            ->setParameter('statuses', ['pending', 'sent'])
            ->setParameter('now', $now)
            ->setParameter('maxDate', $maxDate)
            ->orderBy('i.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Génère le prochain numéro de facture
     */
    public function generateNextInvoiceNumber(int $year = null): string
    {
        $year = $year ?? (int)date('Y');
        
        // Format: FAC-YYYY-NNNN
        $prefix = "FAC-{$year}-";
        
        $lastInvoice = $this->createQueryBuilder('i')
            ->andWhere('i.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('i.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$lastInvoice) {
            return $prefix . '0001';
        }

        // Extraire le numéro de séquence
        $lastNumber = $lastInvoice->getInvoiceNumber();
        $sequence = (int)substr($lastNumber, -4);
        $newSequence = $sequence + 1;

        return $prefix . str_pad((string)$newSequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Calcule le chiffre d'affaires total
     */
    public function getTotalRevenue(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('i')
            ->select('SUM(i.totalAmount)')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'paid');

        if ($startDate) {
            $qb->andWhere('i.paidAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('i.paidAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ?? '0.00';
    }

    /**
     * Taux de recouvrement
     */
    public function getCollectionRate(): float
    {
        $qb = $this->createQueryBuilder('i');

        $total = (clone $qb)->select('SUM(i.totalAmount)')
            ->andWhere('i.status != :status')
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        if (!$total || bccomp($total, '0', 2) === 0) {
            return 0;
        }

        $collected = (clone $qb)->select('SUM(i.totalAmount)')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getSingleScalarResult();

        return round((floatval($collected ?? 0) / floatval($total)) * 100, 2);
    }

    /**
     * DSO - Days Sales Outstanding (délai moyen de paiement)
     */
    public function getDSO(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                (SUM(total_amount) / 
                (SELECT SUM(total_amount) / 
                 DATEDIFF(MAX(invoice_date), MIN(invoice_date)) 
                 FROM invoices 
                 WHERE status = "paid"
                 AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
                )) * 
                AVG(DATEDIFF(paid_at, invoice_date))
            as dso
            FROM invoices
            WHERE status = "paid"
            AND paid_at IS NOT NULL
            AND invoice_date >= DATE_SUB(CURDATE(), INTERVAL 90 DAY)
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return round((float)($result->fetchOne() ?? 0), 2);
    }

    /**
     * Factures avec notes
     */
    public function findWithNotes(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.notes IS NOT NULL')
            ->andWhere('i.notes != :empty')
            ->setParameter('empty', '')
            ->orderBy('i.invoiceDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Détecte les anomalies de facturation
     */
    public function findAnomalies(): array
    {
        // Factures payées sans date de paiement
        $paidWithoutDate = $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.paidAt IS NULL')
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getResult();

        // Factures avec montant TTC incorrect
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT *
            FROM invoices
            WHERE ABS((subtotal + tax_amount) - total_amount) > 0.01
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $incorrectTotal = $result->fetchAllAssociative();

        // Factures envoyées mais non marquées comme envoyées
        $sentWithoutDate = $this->createQueryBuilder('i')
            ->andWhere('i.status = :status')
            ->andWhere('i.sentAt IS NULL')
            ->setParameter('status', 'sent')
            ->getQuery()
            ->getResult();

        // Numéros de facture en double
        $sql = '
            SELECT invoice_number, COUNT(*) as duplicate_count
            FROM invoices
            GROUP BY invoice_number
            HAVING duplicate_count > 1
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();
        $duplicateNumbers = $result->fetchAllAssociative();

        return [
            'paid_without_date' => $paidWithoutDate,
            'incorrect_total' => $incorrectTotal,
            'sent_without_date' => $sentWithoutDate,
            'duplicate_numbers' => $duplicateNumbers
        ];
    }

    /**
     * Évolution du chiffre d'affaires
     */
    public function getRevenueTrend(int $months = 12): array
    {
        $startDate = (new \DateTime())->modify("-{$months} months");

        return $this->createQueryBuilder('i')
            ->select('DATE_FORMAT(i.paidAt, \'%Y-%m\') as month, SUM(i.totalAmount) as revenue, COUNT(i.id) as count')
            ->andWhere('i.paidAt >= :startDate')
            ->andWhere('i.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('status', 'paid')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Factures par jour de la semaine
     */
    public function getWeekdayDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                DAYNAME(invoice_date) as day_name,
                DAYOFWEEK(invoice_date) as day_number,
                COUNT(*) as count,
                SUM(total_amount) as total
            FROM invoices
            GROUP BY day_name, day_number
            ORDER BY day_number
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Taux de TVA moyen
     */
    public function getAverageTaxRate(): float
    {
        $result = $this->createQueryBuilder('i')
            ->select('AVG(i.taxRate)')
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)($result ?? 0), 2);
    }

    /**
     * Total TVA collectée
     */
    public function getTotalTaxCollected(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('i')
            ->select('SUM(i.taxAmount)')
            ->andWhere('i.status = :status')
            ->setParameter('status', 'paid');

        if ($startDate) {
            $qb->andWhere('i.paidAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('i.paidAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ?? '0.00';
    }

    /**
     * Factures sans réservation associée (anomalie)
     */
    public function findWithoutBooking(): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('i.booking IS NULL')
            ->orderBy('i.invoiceDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Panier moyen
     */
    public function getAverageInvoiceAmount(): string
    {
        $result = $this->createQueryBuilder('i')
            ->select('AVG(i.totalAmount)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Factures par année
     */
    public function findByYear(int $year): array
    {
        return $this->createQueryBuilder('i')
            ->andWhere('YEAR(i.invoiceDate) = :year')
            ->setParameter('year', $year)
            ->orderBy('i.invoiceDate', 'DESC')
            ->getQuery()
            ->getResult();
    }
}