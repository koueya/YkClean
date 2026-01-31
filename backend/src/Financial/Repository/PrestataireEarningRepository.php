<?php

namespace App\Financial\Repository;

use App\Financial\Entity\PrestataireEarning;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use App\Financial\Entity\Payout;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité PrestataireEarning (module Financial)
 * 
 * Gestion des revenus des prestataires
 * 
 * @extends ServiceEntityRepository<PrestataireEarning>
 */
class PrestataireEarningRepository extends ServiceEntityRepository
{
    // Statuts de revenu
    public const STATUS_PENDING = 'pending';
    public const STATUS_AVAILABLE = 'available';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrestataireEarning::class);
    }

    /**
     * Trouve tous les revenus d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->where('pe.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('pe.earnedAt', 'DESC');

        if ($status) {
            $qb->andWhere('pe.status = :status')
                ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve un revenu par réservation
     */
    public function findByBooking(Booking $booking): ?PrestataireEarning
    {
        return $this->createQueryBuilder('pe')
            ->where('pe.booking = :booking')
            ->setParameter('booking', $booking)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les revenus par statut
     */
    public function findByStatus(string $status, ?Prestataire $prestataire = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->where('pe.status = :status')
            ->setParameter('status', $status)
            ->orderBy('pe.earnedAt', 'DESC');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les revenus en attente
     */
    public function findPending(?Prestataire $prestataire = null, ?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PENDING, $prestataire, $limit);
    }

    /**
     * Trouve les revenus disponibles (prêts à être virés)
     */
    public function findAvailable(?Prestataire $prestataire = null, ?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_AVAILABLE, $prestataire, $limit);
    }

    /**
     * Trouve les revenus payés
     */
    public function findPaid(?Prestataire $prestataire = null, ?int $limit = null): array
    {
        return $this->findByStatus(self::STATUS_PAID, $prestataire, $limit);
    }

    /**
     * Trouve les revenus d'un payout
     */
    public function findByPayout(Payout $payout): array
    {
        return $this->createQueryBuilder('pe')
            ->where('pe.payout = :payout')
            ->setParameter('payout', $payout)
            ->orderBy('pe.earnedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les revenus sans payout assigné
     */
    public function findWithoutPayout(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->where('pe.payout IS NULL')
            ->andWhere('pe.status = :status')
            ->setParameter('status', self::STATUS_AVAILABLE)
            ->orderBy('pe.earnedAt', 'ASC');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les revenus entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('pe')
            ->where('pe.earnedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('pe.earnedAt', 'DESC');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Calcule le montant brut total
     */
    public function getTotalGrossAmount(
        ?Prestataire $prestataire = null,
        ?string $status = null,
        ?\DateTimeInterface $since = null
    ): float {
        $qb = $this->createQueryBuilder('pe')
            ->select('SUM(pe.grossAmount)');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        if ($status) {
            $qb->andWhere('pe.status = :status')
                ->setParameter('status', $status);
        }

        if ($since) {
            $qb->andWhere('pe.earnedAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant net total
     */
    public function getTotalNetAmount(
        ?Prestataire $prestataire = null,
        ?string $status = null,
        ?\DateTimeInterface $since = null
    ): float {
        $qb = $this->createQueryBuilder('pe')
            ->select('SUM(pe.netAmount)');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        if ($status) {
            $qb->andWhere('pe.status = :status')
                ->setParameter('status', $status);
        }

        if ($since) {
            $qb->andWhere('pe.earnedAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total des commissions
     */
    public function getTotalCommissions(
        ?Prestataire $prestataire = null,
        ?\DateTimeInterface $since = null
    ): float {
        $qb = $this->createQueryBuilder('pe')
            ->select('SUM(pe.commissionAmount)');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        if ($since) {
            $qb->andWhere('pe.earnedAt >= :since')
                ->setParameter('since', $since);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le solde disponible d'un prestataire
     */
    public function getAvailableBalance(Prestataire $prestataire): float
    {
        return $this->getTotalNetAmount($prestataire, self::STATUS_AVAILABLE);
    }

    /**
     * Compte les revenus par statut
     */
    public function countByStatus(string $status, ?Prestataire $prestataire = null): int
    {
        $qb = $this->createQueryBuilder('pe')
            ->select('COUNT(pe.id)')
            ->where('pe.status = :status')
            ->setParameter('status', $status);

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques d'un prestataire
     */
    public function getStatisticsByPrestataire(Prestataire $prestataire): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->where('pe.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        $total = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->andWhere('pe.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $available = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->andWhere('pe.status = :status')
            ->setParameter('status', self::STATUS_AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult();

        $paid = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->andWhere('pe.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();

        $totalGross = (float) (clone $qb)
            ->select('SUM(pe.grossAmount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $totalNet = (float) (clone $qb)
            ->select('SUM(pe.netAmount)')
            ->andWhere('pe.status != :cancelled')
            ->setParameter('cancelled', self::STATUS_CANCELLED)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $totalCommissions = (float) (clone $qb)
            ->select('SUM(pe.commissionAmount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $availableBalance = (float) (clone $qb)
            ->select('SUM(pe.netAmount)')
            ->andWhere('pe.status = :status')
            ->setParameter('status', self::STATUS_AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $averageCommissionRate = (float) (clone $qb)
            ->select('AVG(pe.commissionRate)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'available' => $available,
            'paid' => $paid,
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'total_commissions' => $totalCommissions,
            'available_balance' => $availableBalance,
            'average_commission_rate' => round($averageCommissionRate, 2),
        ];
    }

    /**
     * Obtient les statistiques globales
     */
    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('pe');

        $total = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->where('pe.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $available = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->where('pe.status = :status')
            ->setParameter('status', self::STATUS_AVAILABLE)
            ->getQuery()
            ->getSingleScalarResult();

        $paid = (int) (clone $qb)
            ->select('COUNT(pe.id)')
            ->where('pe.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->getQuery()
            ->getSingleScalarResult();

        $totalGross = (float) (clone $qb)
            ->select('SUM(pe.grossAmount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $totalNet = (float) (clone $qb)
            ->select('SUM(pe.netAmount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $totalCommissions = (float) (clone $qb)
            ->select('SUM(pe.commissionAmount)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'available' => $available,
            'paid' => $paid,
            'total_gross' => $totalGross,
            'total_net' => $totalNet,
            'total_commissions' => $totalCommissions,
        ];
    }

    /**
     * Obtient les revenus groupés par mois
     */
    public function getMonthlyEarnings(int $year, ?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->select('MONTH(pe.earnedAt) as month, SUM(pe.grossAmount) as gross, SUM(pe.netAmount) as net, COUNT(pe.id) as count')
            ->where('YEAR(pe.earnedAt) = :year')
            ->andWhere('pe.status != :cancelled')
            ->setParameter('year', $year)
            ->setParameter('cancelled', self::STATUS_CANCELLED)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les revenus groupés par jour
     */
    public function getDailyEarnings(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('pe')
            ->select('DATE(pe.earnedAt) as date, SUM(pe.grossAmount) as gross, SUM(pe.netAmount) as net, COUNT(pe.id) as count')
            ->where('pe.earnedAt BETWEEN :startDate AND :endDate')
            ->andWhere('pe.status != :cancelled')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('cancelled', self::STATUS_CANCELLED)
            ->groupBy('date')
            ->orderBy('date', 'ASC');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les top prestataires par revenus
     */
    public function getTopPrestatairesByEarnings(int $limit = 10, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->select('p.id, p.firstName, p.lastName, p.companyName, SUM(pe.netAmount) as total, COUNT(pe.id) as count')
            ->leftJoin('pe.prestataire', 'p')
            ->where('pe.status = :status')
            ->setParameter('status', self::STATUS_PAID)
            ->groupBy('p.id')
            ->orderBy('total', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('pe.earnedAt >= :since')
                ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche de revenus avec critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('pe')
            ->leftJoin('pe.prestataire', 'p')
            ->leftJoin('pe.booking', 'b')
            ->leftJoin('pe.payout', 'po')
            ->addSelect('p', 'b', 'po');

        if (isset($criteria['prestataire_id'])) {
            $qb->andWhere('pe.prestataire = :prestataireId')
                ->setParameter('prestataireId', $criteria['prestataire_id']);
        }

        if (isset($criteria['booking_id'])) {
            $qb->andWhere('pe.booking = :bookingId')
                ->setParameter('bookingId', $criteria['booking_id']);
        }

        if (isset($criteria['payout_id'])) {
            $qb->andWhere('pe.payout = :payoutId')
                ->setParameter('payoutId', $criteria['payout_id']);
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $qb->andWhere('pe.status IN (:statuses)')
                    ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('pe.status = :status')
                    ->setParameter('status', $criteria['status']);
            }
        }

        if (isset($criteria['min_amount'])) {
            $qb->andWhere('pe.netAmount >= :minAmount')
                ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (isset($criteria['max_amount'])) {
            $qb->andWhere('pe.netAmount <= :maxAmount')
                ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('pe.earnedAt >= :startDate')
                ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('pe.earnedAt <= :endDate')
                ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['has_payout'])) {
            if ($criteria['has_payout']) {
                $qb->andWhere('pe.payout IS NOT NULL');
            } else {
                $qb->andWhere('pe.payout IS NULL');
            }
        }

        $qb->orderBy('pe.earnedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient le montant moyen des revenus
     */
    public function getAverageEarning(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('pe')
            ->select('AVG(pe.netAmount)')
            ->where('pe.status = :status')
            ->setParameter('status', self::STATUS_PAID);

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return round((float) ($result ?? 0), 2);
    }

    /**
     * Obtient le taux de commission moyen
     */
    public function getAverageCommissionRate(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('pe')
            ->select('AVG(pe.commissionRate)');

        if ($prestataire) {
            $qb->andWhere('pe.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return round((float) ($result ?? 0), 2);
    }

    /**
     * Exporte les revenus en CSV
     */
    public function exportToCsv(array $earnings): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Prestataire',
            'Réservation',
            'Montant brut (€)',
            'Commission (€)',
            'Montant net (€)',
            'Taux (%)',
            'Statut',
            'Payout',
            'Gagné le',
            'Disponible le',
            'Payé le',
        ]);

        // Données
        foreach ($earnings as $earning) {
            fputcsv($handle, [
                $earning->getId(),
                $earning->getPrestataire()->getFullName(),
                $earning->getBooking() ? $earning->getBooking()->getId() : '-',
                $earning->getGrossAmount(),
                $earning->getCommissionAmount(),
                $earning->getNetAmount(),
                $earning->getCommissionRate(),
                ucfirst($earning->getStatus()),
                $earning->getPayout() ? $earning->getPayout()->getId() : '-',
                $earning->getEarnedAt()->format('d/m/Y H:i'),
                $earning->getAvailableAt() ? $earning->getAvailableAt()->format('d/m/Y H:i') : '-',
                $earning->getPaidAt() ? $earning->getPaidAt()->format('d/m/Y H:i') : '-',
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Sauvegarde un revenu
     */
    public function save(PrestataireEarning $earning, bool $flush = false): void
    {
        $this->getEntityManager()->persist($earning);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un revenu
     */
    public function remove(PrestataireEarning $earning, bool $flush = false): void
    {
        $this->getEntityManager()->remove($earning);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}