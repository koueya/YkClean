<?php
// src/Repository/CommissionRepository.php

namespace App\Repository;

use App\Entity\Commission;
use App\Entity\Booking;
use App\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commission>
 */
class CommissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commission::class);
    }

    /**
     * Trouve toutes les commissions par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les commissions d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('c.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('c.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve la commission d'une réservation
     */
    public function findByBooking(Booking $booking): ?Commission
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.booking = :booking')
            ->setParameter('booking', $booking)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les commissions en attente de paiement
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status IN (:statuses)')
            ->setParameter('statuses', ['pending', 'calculated'])
            ->orderBy('c.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les commissions en retard
     */
    public function findOverdue(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('c')
            ->andWhere('c.status IN (:statuses)')
            ->andWhere('c.dueDate < :now')
            ->setParameter('statuses', ['pending', 'calculated'])
            ->setParameter('now', $now)
            ->orderBy('c.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les commissions payées dans une période
     */
    public function findPaidBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.paidDate >= :startDate')
            ->andWhere('c.paidDate <= :endDate')
            ->setParameter('status', 'paid')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.paidDate', 'DESC');

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques des commissions
     */
    public function getStatistics(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('c');

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return [
            'total_commissions' => (clone $qb)->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'pending' => (clone $qb)->select('COUNT(c.id)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult(),

            'calculated' => (clone $qb)->select('COUNT(c.id)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'calculated')
                ->getQuery()
                ->getSingleScalarResult(),

            'paid' => (clone $qb)->select('COUNT(c.id)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'paid')
                ->getQuery()
                ->getSingleScalarResult(),

            'cancelled' => (clone $qb)->select('COUNT(c.id)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'cancelled')
                ->getQuery()
                ->getSingleScalarResult(),

            'total_amount' => (clone $qb)->select('SUM(c.commissionAmount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'paid_amount' => (clone $qb)->select('SUM(c.commissionAmount)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'paid')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'pending_amount' => (clone $qb)->select('SUM(c.commissionAmount)')
                ->andWhere('c.status IN (:statuses)')
                ->setParameter('statuses', ['pending', 'calculated'])
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_rate' => (clone $qb)->select('AVG(c.commissionRate)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_commission' => (clone $qb)->select('AVG(c.commissionAmount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Compte les commissions par statut
     */
    public function countByStatus(string $status, ?Prestataire $prestataire = null): int
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status);

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Calcule le montant total des commissions par statut
     */
    public function sumByStatus(string $status, ?Prestataire $prestataire = null): string
    {
        $qb = $this->createQueryBuilder('c')
            ->select('SUM(c.commissionAmount)')
            ->andWhere('c.status = :status')
            ->setParameter('status', $status);

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ?? '0.00';
    }

    /**
     * Répartition par statut
     */
    public function getStatusDistribution(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.status, COUNT(c.id) as count, SUM(c.commissionAmount) as total')
            ->groupBy('c.status')
            ->orderBy('count', 'DESC');

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Répartition par type
     */
    public function getTypeDistribution(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c.type, COUNT(c.id) as count, SUM(c.commissionAmount) as total')
            ->groupBy('c.type')
            ->orderBy('count', 'DESC');

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Commissions par mois
     */
    public function getMonthlyDistribution(int $year, ?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('MONTH(c.createdAt) as month, COUNT(c.id) as count, SUM(c.commissionAmount) as total')
            ->andWhere('YEAR(c.createdAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Taux de commission moyen
     */
    public function getAverageRate(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('c')
            ->select('AVG(c.commissionRate)');

        if ($prestataire) {
            $qb->andWhere('c.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return round((float)($result ?? 0), 2);
    }

    /**
     * Prestataires avec le plus de commissions
     */
    public function getTopPrestataires(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('p.id, p.firstName, p.lastName, COUNT(c.id) as commission_count, SUM(c.commissionAmount) as total_commission')
            ->innerJoin('c.prestataire', 'p')
            ->groupBy('p.id')
            ->orderBy('total_commission', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Commissions par tranche de montant
     */
    public function getAmountDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN commission_amount < 10 THEN "0-10€"
                    WHEN commission_amount < 25 THEN "10-25€"
                    WHEN commission_amount < 50 THEN "25-50€"
                    WHEN commission_amount < 100 THEN "50-100€"
                    WHEN commission_amount < 200 THEN "100-200€"
                    ELSE "200€+"
                END as amount_range,
                COUNT(*) as count,
                AVG(commission_amount) as average_amount
            FROM commissions
            GROUP BY amount_range
            ORDER BY 
                CASE amount_range
                    WHEN "0-10€" THEN 1
                    WHEN "10-25€" THEN 2
                    WHEN "25-50€" THEN 3
                    WHEN "50-100€" THEN 4
                    WHEN "100-200€" THEN 5
                    WHEN "200€+" THEN 6
                END
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Délai moyen de paiement
     */
    public function getAveragePaymentDelay(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                DATEDIFF(paid_date, due_date)
            ) as avg_delay
            FROM commissions
            WHERE status = "paid"
            AND due_date IS NOT NULL
            AND paid_date IS NOT NULL
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return round((float)($result->fetchOne() ?? 0), 2);
    }

    /**
     * Exporte les commissions en CSV
     */
    public function exportToCsv(array $commissions): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'Référence',
            'Réservation',
            'Prestataire',
            'Montant Réservation (€)',
            'Taux (%)',
            'Commission (€)',
            'Montant Prestataire (€)',
            'Statut',
            'Type',
            'Date Échéance',
            'Date Paiement',
            'Créé le'
        ]);

        // Données
        foreach ($commissions as $commission) {
            fputcsv($handle, [
                $commission->getReferenceNumber(),
                $commission->getBooking()->getReferenceNumber(),
                $commission->getPrestataire()->getFullName(),
                $commission->getBookingAmount(),
                $commission->getCommissionRate(),
                $commission->getCommissionAmount(),
                $commission->getPrestataireAmount(),
                ucfirst($commission->getStatus()),
                $commission->getTypeLabel(),
                $commission->getDueDate() ? $commission->getDueDate()->format('d/m/Y') : '',
                $commission->getPaidDate() ? $commission->getPaidDate()->format('d/m/Y') : '',
                $commission->getCreatedAt()->format('d/m/Y H:i')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Commissions récentes
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Commissions à échoir dans X jours
     */
    public function findDueSoon(int $days = 7): array
    {
        $now = new \DateTime();
        $maxDate = (clone $now)->modify("+{$days} days");

        return $this->createQueryBuilder('c')
            ->andWhere('c.status IN (:statuses)')
            ->andWhere('c.dueDate BETWEEN :now AND :maxDate')
            ->setParameter('statuses', ['pending', 'calculated'])
            ->setParameter('now', $now)
            ->setParameter('maxDate', $maxDate)
            ->orderBy('c.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le chiffre d'affaires total (commissions)
     */
    public function getTotalRevenue(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('c')
            ->select('SUM(c.commissionAmount)')
            ->andWhere('c.status = :status')
            ->setParameter('status', 'paid');

        if ($startDate) {
            $qb->andWhere('c.paidDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('c.paidDate <= :endDate')
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
        return $this->createQueryBuilder('c')
            ->select('MONTH(c.paidDate) as month, SUM(c.commissionAmount) as revenue')
            ->andWhere('YEAR(c.paidDate) = :year')
            ->andWhere('c.status = :status')
            ->setParameter('year', $year)
            ->setParameter('status', 'paid')
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Évolution du taux de commission moyen dans le temps
     */
    public function getRateTrend(int $months = 12): array
    {
        $startDate = (new \DateTime())->modify("-{$months} months");

        return $this->createQueryBuilder('c')
            ->select('DATE_FORMAT(c.createdAt, \'%Y-%m\') as month, AVG(c.commissionRate) as avg_rate')
            ->andWhere('c.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Commissions annulées avec le montant
     */
    public function getCancelledAmount(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('c')
            ->select('SUM(c.commissionAmount)')
            ->andWhere('c.status = :status')
            ->setParameter('status', 'cancelled');

        if ($startDate) {
            $qb->andWhere('c.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('c.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        return $result ?? '0.00';
    }

/**
 * Taux de paiement (commissions payées / commissions dues)
 */
public function getPaymentRate(): float
{
    $total = $this->createQueryBuilder('c')
        ->select('COUNT(c.id)')
        ->andWhere('c.status != :status')
        ->setParameter('status', 'cancelled')
        ->getQuery()
        ->getSingleScalarResult();

    if ($total === 0) {
        return 0;
    }

    $paid = $this->createQueryBuilder('c')
        ->select('COUNT(c.id)')
        ->andWhere('c.status = :status')
        ->setParameter('status', 'paid')
        ->getQuery()
        ->getSingleScalarResult();

    return round(($paid / $total) * 100, 2);
}

/**
 * Recherche de commissions
 */
public function search(array $criteria): array
{
    $qb = $this->createQueryBuilder('c')
        ->leftJoin('c.booking', 'b')
        ->leftJoin('c.prestataire', 'p')
        ->orderBy('c.createdAt', 'DESC');

    if (!empty($criteria['reference'])) {
        $qb->andWhere('c.referenceNumber LIKE :reference')
           ->setParameter('reference', '%' . $criteria['reference'] . '%');
    }

    if (!empty($criteria['status'])) {
        $qb->andWhere('c.status = :status')
           ->setParameter('status', $criteria['status']);
    }

    if (!empty($criteria['type'])) {
        $qb->andWhere('c.type = :type')
           ->setParameter('type', $criteria['type']);
    }

    if (!empty($criteria['prestataire'])) {
        $qb->andWhere('c.prestataire = :prestataire')
           ->setParameter('prestataire', $criteria['prestataire']);
    }

    if (!empty($criteria['min_amount'])) {
        $qb->andWhere('c.commissionAmount >= :minAmount')
           ->setParameter('minAmount', $criteria['min_amount']);
    }

    if (!empty($criteria['max_amount'])) {
        $qb->andWhere('c.commissionAmount <= :maxAmount')
           ->setParameter('maxAmount', $criteria['max_amount']);
    }

    if (!empty($criteria['start_date'])) {
        $qb->andWhere('c.createdAt >= :startDate')
           ->setParameter('startDate', $criteria['start_date']);
    }

    if (!empty($criteria['end_date'])) {
        $qb->andWhere('c.createdAt <= :endDate')
           ->setParameter('endDate', $criteria['end_date']);
    }

    return $qb->getQuery()->getResult();
}

/**
 * Détecte les anomalies de commission
 */
public function findAnomalies(): array
{
    // Commissions avec taux incohérent
    $highRate = $this->createQueryBuilder('c')
        ->andWhere('c.commissionRate > :maxRate')
        ->setParameter('maxRate', 30) // Taux max attendu
        ->getQuery()
        ->getResult();

    // Commissions avec calcul incorrect
    $conn = $this->getEntityManager()->getConnection();
    
    $sql = '
        SELECT *
        FROM commissions
        WHERE ABS(
            (booking_amount * commission_rate / 100) - commission_amount
        ) > 0.01
    ';

    $stmt = $conn->prepare($sql);
    $result = $stmt->executeQuery();
    $incorrectCalculation = $result->fetchAllAssociative();

    return [
        'high_rate' => $highRate,
        'incorrect_calculation' => $incorrectCalculation
    ];
}

}