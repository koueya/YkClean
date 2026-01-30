<?php
// src/Repository/Financial/CommissionRepository.php

namespace App\Repository\Financial;

use App\Entity\Financial\Commission;
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
     * Trouve les commissions par prestataire
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
     * Calcule le total des commissions pour un prestataire
     */
    public function getTotalCommissionsByPrestataire(
        Prestataire $prestataire,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('c')
            ->select('SUM(c.commissionAmount)')
            ->andWhere('c.prestataire = :prestataire')
            ->andWhere('c.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'collected');

        if ($startDate) {
            $qb->andWhere('c.createdAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('c.createdAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getSingleScalarResult() ?? '0.00';
    }

    /**
     * Trouve les commissions collectées par période
     */
    public function findCollectedByPeriod(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->andWhere('c.collectedAt >= :startDate')
            ->andWhere('c.collectedAt <= :endDate')
            ->setParameter('status', 'collected')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('c.collectedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total des commissions collectées
     */
    public function getTotalCollectedCommissions(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): string {
        $qb = $this->createQueryBuilder('c')
            ->select('SUM(c.commissionAmount)')
            ->andWhere('c.status = :status')
            ->setParameter('status', 'collected');

        if ($startDate) {
            $qb->andWhere('c.collectedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('c.collectedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->getQuery()->getSingleScalarResult() ?? '0.00';
    }

    /**
     * Trouve les commissions en attente
     */
    public function findPending(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.status = :status')
            ->setParameter('status', 'pending')
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des commissions par prestataire
     */
    public function getCommissionStatsByPrestataire(Prestataire $prestataire): array
    {
        $qb = $this->createQueryBuilder('c');

        return [
            'total' => $qb->select('COUNT(c.id)')
                ->andWhere('c.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire)
                ->getQuery()
                ->getSingleScalarResult(),

            'collected' => $qb->select('COUNT(c.id)')
                ->andWhere('c.prestataire = :prestataire')
                ->andWhere('c.status = :status')
                ->setParameter('prestataire', $prestataire)
                ->setParameter('status', 'collected')
                ->getQuery()
                ->getSingleScalarResult(),

            'pending' => $qb->select('COUNT(c.id)')
                ->andWhere('c.prestataire = :prestataire')
                ->andWhere('c.status = :status')
                ->setParameter('prestataire', $prestataire)
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult(),

            'totalAmount' => $qb->select('SUM(c.commissionAmount)')
                ->andWhere('c.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire)
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'collectedAmount' => $qb->select('SUM(c.commissionAmount)')
                ->andWhere('c.prestataire = :prestataire')
                ->andWhere('c.status = :status')
                ->setParameter('prestataire', $prestataire)
                ->setParameter('status', 'collected')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'averageRate' => $qb->select('AVG(c.commissionRate)')
                ->andWhere('c.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire)
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Statistiques globales des commissions
     */
    public function getGlobalStats(): array
    {
        $qb = $this->createQueryBuilder('c');

        return [
            'totalCommissions' => $qb->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'collectedCommissions' => $qb->select('COUNT(c.id)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'collected')
                ->getQuery()
                ->getSingleScalarResult(),

            'totalAmount' => $qb->select('SUM(c.commissionAmount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'collectedAmount' => $qb->select('SUM(c.commissionAmount)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'collected')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'pendingAmount' => $qb->select('SUM(c.commissionAmount)')
                ->andWhere('c.status = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'averageCommissionRate' => $qb->select('AVG(c.commissionRate)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'averageCommissionAmount' => $qb->select('AVG(c.commissionAmount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Commissions par mois
     */
    public function getMonthlyCommissions(int $year): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                MONTH(c.collected_at) as month,
                COUNT(c.id) as count,
                SUM(c.commission_amount) as total_amount
            FROM commissions c
            WHERE YEAR(c.collected_at) = :year
            AND c.status = :status
            GROUP BY MONTH(c.collected_at)
            ORDER BY month
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'year' => $year,
            'status' => 'collected'
        ]);

        return $result->fetchAllAssociative();
    }
}