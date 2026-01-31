<?php
// src/Repository/Financial/FinancialReportRepository.php

namespace App\Financial\Repository;

use App\Financial\Entity\FinancialReport;
use App\Entity\User\Prestataire;
use App\Entity\User\Client;
use App\Entity\User\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**²²²²²
 * @extends ServiceEntityRepository<FinancialReport>
 */
class FinancialReportRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FinancialReport::class);
    }

    /**
     * Trouve les rapports par utilisateur
     */
    public function findByUser(User $user, ?string $reportType = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->setParameter('user', $user)
            ->orderBy('r.generatedAt', 'DESC');

        if ($reportType) {
            $qb->andWhere('r.reportType = :reportType')
               ->setParameter('reportType', $reportType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve le rapport pour une période spécifique
     */
    public function findByPeriod(
        \DateTimeInterface $start,
        \DateTimeInterface $end,
        ?string $entityType = null,
        ?User $user = null
    ): ?FinancialReport {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.periodStart = :start')
            ->andWhere('r.periodEnd = :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);

        if ($entityType) {
            $qb->andWhere('r.entityType = :entityType')
               ->setParameter('entityType', $entityType);
        }

        if ($user) {
            $qb->andWhere('r.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getOneOrNullResult();
    }

    /**
     * Trouve les rapports mensuels d'un prestataire
     */
    public function findMonthlyByPrestataire(Prestataire $prestataire, int $year): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.reportType = :reportType')
            ->andWhere('r.year = :year')
            ->setParameter('user', $prestataire)
            ->setParameter('reportType', 'monthly')
            ->setParameter('year', $year)
            ->orderBy('r.month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rapports annuels d'un prestataire
     */
    public function findYearlyByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.reportType = :reportType')
            ->setParameter('user', $prestataire)
            ->setParameter('reportType', 'yearly')
            ->orderBy('r.year', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les rapports de la plateforme
     */
    public function findPlatformReports(
        ?string $reportType = null,
        ?int $year = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.entityType = :entityType')
            ->setParameter('entityType', 'platform')
            ->orderBy('r.generatedAt', 'DESC');

        if ($reportType) {
            $qb->andWhere('r.reportType = :reportType')
               ->setParameter('reportType', $reportType);
        }

        if ($year) {
            $qb->andWhere('r.year = :year')
               ->setParameter('year', $year);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les rapports fiscaux d'un prestataire
     */
    public function findTaxReportsByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.reportType = :reportType')
            ->setParameter('user', $prestataire)
            ->setParameter('reportType', 'tax')
            ->orderBy('r.year', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le rapport fiscal pour une année spécifique
     */
    public function findTaxReportByYear(Prestataire $prestataire, int $year): ?FinancialReport
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.user = :user')
            ->andWhere('r.reportType = :reportType')
            ->andWhere('r.year = :year')
            ->setParameter('user', $prestataire)
            ->setParameter('reportType', 'tax')
            ->setParameter('year', $year)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les rapports non envoyés
     */
    public function findUnsent(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'generated')
            ->orderBy('r.generatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des rapports
     */
    public function getReportStats(?string $entityType = null): array
    {
        $qb = $this->createQueryBuilder('r');

        if ($entityType) {
            $qb->andWhere('r.entityType = :entityType')
               ->setParameter('entityType', $entityType);
        }

        return [
            'total' => $qb->select('COUNT(r.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'generated' => $qb->select('COUNT(r.id)')
                ->andWhere('r.status = :status')
                ->setParameter('status', 'generated')
                ->getQuery()
                ->getSingleScalarResult(),

            'sent' => $qb->select('COUNT(r.id)')
                ->andWhere('r.status = :status')
                ->setParameter('status', 'sent')
                ->getQuery()
                ->getSingleScalarResult(),

            'archived' => $qb->select('COUNT(r.id)')
                ->andWhere('r.status = :status')
                ->setParameter('status', 'archived')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    /**
     * Revenu total par type d'entité
     */
    public function getTotalRevenueByEntityType(
        string $entityType,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('r')
            ->select('SUM(r.totalRevenue)')
            ->andWhere('r.entityType = :entityType')
            ->setParameter('entityType', $entityType);

        if ($startDate) {
            $qb->andWhere('r.periodStart >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('r.periodEnd <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getSingleScalarResult() ?? '0.00';
    }

    /**
     * Comparaison des périodes
     */
    public function comparePeriods(
        \DateTimeInterface $period1Start,
        \DateTimeInterface $period1End,
        \DateTimeInterface $period2Start,
        \DateTimeInterface $period2End,
        string $entityType
    ): array {
        $report1 = $this->findByPeriod($period1Start, $period1End, $entityType);
        $report2 = $this->findByPeriod($period2Start, $period2End, $entityType);

        if (!$report1 || !$report2) {
            return [];
        }

        return [
            'period1' => [
                'revenue' => $report1->getTotalRevenue(),
                'expenses' => $report1->getTotalExpenses(),
                'net' => $report1->getNetAmount(),
            ],
            'period2' => [
                'revenue' => $report2->getTotalRevenue(),
                'expenses' => $report2->getTotalExpenses(),
                'net' => $report2->getNetAmount(),
            ],
            'comparison' => [
                'revenue_change' => $this->calculatePercentageChange(
                    $report1->getTotalRevenue(),
                    $report2->getTotalRevenue()
                ),
                'expenses_change' => $this->calculatePercentageChange(
                    $report1->getTotalExpenses(),
                    $report2->getTotalExpenses()
                ),
                'net_change' => $this->calculatePercentageChange(
                    $report1->getNetAmount(),
                    $report2->getNetAmount()
                ),
            ],
        ];
    }

    /**
     * Calcule le pourcentage de changement
     */
    private function calculatePercentageChange(string $oldValue, string $newValue): string
    {
        if ($oldValue === '0.00') {
            return '0.00';
        }

        $diff = bcsub($newValue, $oldValue, 2);
        $percentage = bcmul(
            bcdiv($diff, $oldValue, 4),
            '100',
            2
        );

        return $percentage;
    }

    /**
     * Évolution annuelle
     */
    public function getYearlyEvolution(int $year, string $entityType, ?User $user = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.reportType = :reportType')
            ->andWhere('r.year = :year')
            ->setParameter('entityType', $entityType)
            ->setParameter('reportType', 'monthly')
            ->setParameter('year', $year)
            ->orderBy('r.month', 'ASC');

        if ($user) {
            $qb->andWhere('r.user = :user')
               ->setParameter('user', $user);
        }

        $reports = $qb->getQuery()->getResult();

        $evolution = [];
        foreach ($reports as $report) {
            $evolution[] = [
                'month' => $report->getMonth(),
                'revenue' => $report->getTotalRevenue(),
                'expenses' => $report->getTotalExpenses(),
                'net' => $report->getNetAmount(),
                'bookings' => $report->getTotalBookings() ?? $report->getCompletedBookings(),
            ];
        }

        return $evolution;
    }

    /**
     * Top prestataires par revenus
     */
    public function getTopPrestatairesByRevenue(
        int $limit = 10,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->select('r.user, SUM(r.grossEarnings) as total_earnings, SUM(r.completedBookings) as total_bookings')
            ->andWhere('r.entityType = :entityType')
            ->andWhere('r.grossEarnings IS NOT NULL')
            ->setParameter('entityType', 'prestataire')
            ->groupBy('r.user')
            ->orderBy('total_earnings', 'DESC')
            ->setMaxResults($limit);

        if ($startDate) {
            $qb->andWhere('r.periodStart >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('r.periodEnd <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getResult();
    }
}