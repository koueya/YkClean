<?php
// src/Repository/ReplacementRepository.php

namespace App\Repository\Planning;

use App\Entity\Replacement;
use App\Entity\Booking;
use App\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Replacement>
 */
class ReplacementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Replacement::class);
    }

    /**
     * Trouve tous les remplacements d'une réservation
     */
    public function findByBooking(Booking $booking): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.originalBooking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le remplacement actif d'une réservation
     */
    public function findActiveByBooking(Booking $booking): ?Replacement
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.originalBooking = :booking')
            ->andWhere('r.status = :status')
            ->setParameter('booking', $booking)
            ->setParameter('status', 'confirmed')
            ->orderBy('r.confirmedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les remplacements où le prestataire est l'original
     */
    public function findByOriginalPrestataire(
        Prestataire $prestataire,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.originalPrestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.requestedAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remplacements où le prestataire est le remplaçant
     */
    public function findByReplacementPrestataire(
        Prestataire $prestataire,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.replacementPrestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.requestedAt', 'DESC');

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les remplacements en attente pour un prestataire remplaçant
     */
    public function findPendingForReplacement(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.replacementPrestataire = :prestataire')
            ->andWhere('r.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'pending')
            ->orderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements confirmés à venir
     */
    public function findUpcomingConfirmed(Prestataire $prestataire): array
    {
        $today = new \DateTime();

        return $this->createQueryBuilder('r')
            ->innerJoin('r.originalBooking', 'b')
            ->andWhere('r.replacementPrestataire = :prestataire')
            ->andWhere('r.status = :status')
            ->andWhere('b.scheduledDate >= :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'confirmed')
            ->setParameter('today', $today)
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', $status)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements en attente (sans remplaçant assigné)
     */
    public function findPendingWithoutReplacement(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.replacementPrestataire IS NULL')
            ->setParameter('status', 'pending')
            ->orderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements par raison
     */
    public function findByReason(string $reason): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.reason LIKE :reason')
            ->setParameter('reason', '%' . $reason . '%')
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les remplacements par prestataire original
     */
    public function countByOriginalPrestataire(
        Prestataire $prestataire,
        ?string $status = null
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.originalPrestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Compte les remplacements par prestataire remplaçant
     */
    public function countByReplacementPrestataire(
        Prestataire $prestataire,
        ?string $status = null
    ): int {
        $qb = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.replacementPrestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($status) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statistiques des remplacements pour un prestataire
     */
    public function getStatisticsByPrestataire(Prestataire $prestataire): array
    {
        return [
            'as_original' => [
                'total' => $this->countByOriginalPrestataire($prestataire),
                'pending' => $this->countByOriginalPrestataire($prestataire, 'pending'),
                'confirmed' => $this->countByOriginalPrestataire($prestataire, 'confirmed'),
                'rejected' => $this->countByOriginalPrestataire($prestataire, 'rejected'),
                'cancelled' => $this->countByOriginalPrestataire($prestataire, 'cancelled'),
            ],
            'as_replacement' => [
                'total' => $this->countByReplacementPrestataire($prestataire),
                'pending' => $this->countByReplacementPrestataire($prestataire, 'pending'),
                'confirmed' => $this->countByReplacementPrestataire($prestataire, 'confirmed'),
                'rejected' => $this->countByReplacementPrestataire($prestataire, 'rejected'),
                'cancelled' => $this->countByReplacementPrestataire($prestataire, 'cancelled'),
            ]
        ];
    }

    /**
     * Trouve les remplacements récents
     */
    public function findRecent(int $limit = 20): array
    {
        return $this->createQueryBuilder('r')
            ->orderBy('r.requestedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('r')
            ->innerJoin('r.originalBooking', 'b')
            ->andWhere('b.scheduledDate >= :startDate')
            ->andWhere('b.scheduledDate <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('b.scheduledDate', 'ASC');

        if ($prestataire) {
            $qb->andWhere(
                'r.originalPrestataire = :prestataire OR r.replacementPrestataire = :prestataire'
            )
            ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les raisons de remplacement les plus fréquentes
     */
    public function getTopReasons(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                reason,
                COUNT(*) as count
            FROM replacements
            WHERE reason IS NOT NULL
            GROUP BY reason
            ORDER BY count DESC
            LIMIT :limit
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['limit' => $limit]);

        return $result->fetchAllAssociative();
    }

    /**
     * Taux d'acceptation des remplacements pour un prestataire
     */
    public function getAcceptanceRate(Prestataire $prestataire): float
    {
        $total = $this->countByReplacementPrestataire($prestataire);
        
        if ($total === 0) {
            return 0;
        }

        $confirmed = $this->countByReplacementPrestataire($prestataire, 'confirmed');

        return round(($confirmed / $total) * 100, 2);
    }

    /**
     * Taux de remplacement réussi pour un prestataire original
     */
    public function getSuccessRate(Prestataire $prestataire): float
    {
        $total = $this->countByOriginalPrestataire($prestataire);
        
        if ($total === 0) {
            return 0;
        }

        $confirmed = $this->countByOriginalPrestataire($prestataire, 'confirmed');

        return round(($confirmed / $total) * 100, 2);
    }

    /**
     * Trouve les prestataires les plus sollicités pour des remplacements
     */
    public function getTopReplacementPrestataires(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->select('p.id, p.firstName, p.lastName, COUNT(r.id) as replacement_count')
            ->innerJoin('r.replacementPrestataire', 'p')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'confirmed')
            ->groupBy('p.id')
            ->orderBy('replacement_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires qui demandent le plus de remplacements
     */
    public function getTopRequestingPrestataires(int $limit = 10): array
    {
        return $this->createQueryBuilder('r')
            ->select('p.id, p.firstName, p.lastName, COUNT(r.id) as request_count')
            ->innerJoin('r.originalPrestataire', 'p')
            ->groupBy('p.id')
            ->orderBy('request_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Temps moyen de réponse aux demandes de remplacement
     */
    public function getAverageResponseTime(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                TIMESTAMPDIFF(MINUTE, requested_at, 
                    COALESCE(confirmed_at, rejected_at)
                )
            ) as avg_minutes
            FROM replacements
            WHERE confirmed_at IS NOT NULL OR rejected_at IS NOT NULL
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return (float)($result->fetchOne() ?? 0);
    }

    /**
     * Répartition des remplacements par statut
     */
    public function getStatusDistribution(): array
    {
        return $this->createQueryBuilder('r')
            ->select('r.status, COUNT(r.id) as count')
            ->groupBy('r.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Remplacements par mois
     */
    public function getMonthlyDistribution(int $year): array
    {
        return $this->createQueryBuilder('r')
            ->select('MONTH(r.requestedAt) as month, COUNT(r.id) as count')
            ->andWhere('YEAR(r.requestedAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements avec des rejets multiples
     */
    public function findWithMultipleRejections(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                original_booking_id,
                COUNT(*) as rejection_count
            FROM replacements
            WHERE status = :status
            GROUP BY original_booking_id
            HAVING rejection_count > 1
            ORDER BY rejection_count DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['status' => 'rejected']);

        $bookingIds = array_column($result->fetchAllAssociative(), 'original_booking_id');

        if (empty($bookingIds)) {
            return [];
        }

        return $this->createQueryBuilder('r')
            ->andWhere('r.originalBooking IN (:bookingIds)')
            ->setParameter('bookingIds', $bookingIds)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les remplacements urgents (moins de 24h avant la réservation)
     */
    public function findUrgent(): array
    {
        $now = new \DateTime();
        $in24Hours = (clone $now)->modify('+24 hours');

        return $this->createQueryBuilder('r')
            ->innerJoin('r.originalBooking', 'b')
            ->andWhere('r.status = :status')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere(
                'CONCAT(b.scheduledDate, \' \', b.scheduledTime) <= :in24Hours'
            )
            ->setParameter('status', 'pending')
            ->setParameter('now', $now->format('Y-m-d'))
            ->setParameter('in24Hours', $in24Hours->format('Y-m-d H:i:s'))
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de remplacements
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.originalBooking', 'b')
            ->leftJoin('r.originalPrestataire', 'op')
            ->leftJoin('r.replacementPrestataire', 'rp');

        if (isset($criteria['status'])) {
            $qb->andWhere('r.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['original_prestataire_id'])) {
            $qb->andWhere('r.originalPrestataire = :originalId')
               ->setParameter('originalId', $criteria['original_prestataire_id']);
        }

        if (isset($criteria['replacement_prestataire_id'])) {
            $qb->andWhere('r.replacementPrestataire = :replacementId')
               ->setParameter('replacementId', $criteria['replacement_prestataire_id']);
        }

        if (isset($criteria['reason'])) {
            $qb->andWhere('r.reason LIKE :reason')
               ->setParameter('reason', '%' . $criteria['reason'] . '%');
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('b.scheduledDate >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('b.scheduledDate <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        return $qb->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Historique des remplacements d'une réservation
     */
    public function getBookingReplacementHistory(Booking $booking): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.originalPrestataire', 'op')
            ->leftJoin('r.replacementPrestataire', 'rp')
            ->addSelect('op', 'rp')
            ->andWhere('r.originalBooking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un prestataire peut être remplaçant pour une réservation
     */
    public function canBeReplacement(
        Prestataire $prestataire,
        Booking $booking
    ): bool {
        // Vérifier qu'il n'y a pas déjà un remplacement confirmé avec ce prestataire
        $existing = $this->createQueryBuilder('r')
            ->andWhere('r.originalBooking = :booking')
            ->andWhere('r.replacementPrestataire = :prestataire')
            ->andWhere('r.status = :status')
            ->setParameter('booking', $booking)
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'confirmed')
            ->getQuery()
            ->getOneOrNullResult();

        return $existing === null;
    }

    /**
     * Exporte les remplacements en CSV
     */
    public function exportToCsv(array $replacements): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'Réservation',
            'Prestataire original',
            'Prestataire remplaçant',
            'Raison',
            'Statut',
            'Date demande',
            'Date confirmation',
            'Date réservation'
        ]);

        // Données
        foreach ($replacements as $replacement) {
            fputcsv($handle, [
                $replacement->getOriginalBooking()->getReferenceNumber(),
                $replacement->getOriginalPrestataire()->getFullName(),
                $replacement->getReplacementPrestataire() 
                    ? $replacement->getReplacementPrestataire()->getFullName() 
                    : 'Non assigné',
                $replacement->getReason(),
                ucfirst($replacement->getStatus()),
                $replacement->getRequestedAt()->format('d/m/Y H:i'),
                $replacement->getConfirmedAt() 
                    ? $replacement->getConfirmedAt()->format('d/m/Y H:i') 
                    : '',
                $replacement->getOriginalBooking()->getScheduledDate()->format('d/m/Y') . ' ' .
                $replacement->getOriginalBooking()->getScheduledTime()->format('H:i')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Statistiques globales des remplacements
     */
    public function getGlobalStatistics(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('r');

        if ($startDate) {
            $qb->andWhere('r.requestedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('r.requestedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return [
            'total_replacements' => (clone $qb)->select('COUNT(r.id)')
                ->getQuery()->getSingleScalarResult(),

            'pending' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.status = :status')
                ->setParameter('status', 'pending')
                ->getQuery()->getSingleScalarResult(),

            'confirmed' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.status = :status')
                ->setParameter('status', 'confirmed')
                ->getQuery()->getSingleScalarResult(),

            'rejected' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.status = :status')
                ->setParameter('status', 'rejected')
                ->getQuery()->getSingleScalarResult(),

            'cancelled' => (clone $qb)->select('COUNT(r.id)')
                ->andWhere('r.status = :status')
                ->setParameter('status', 'cancelled')
                ->getQuery()->getSingleScalarResult(),

            'success_rate' => $this->calculateGlobalSuccessRate($qb),
            'average_response_time' => $this->getAverageResponseTime(),
        ];
    }

    /**
     * Calcule le taux de succès global
     */
    private function calculateGlobalSuccessRate($qb): float
    {
        $total = (clone $qb)->select('COUNT(r.id)')
            ->getQuery()->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $confirmed = (clone $qb)->select('COUNT(r.id)')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'confirmed')
            ->getQuery()->getSingleScalarResult();

        return round(($confirmed / $total) * 100, 2);
    }

    /**
     * Trouve les remplacements sans réponse depuis X jours
     */
    public function findPendingOlderThan(int $days = 2): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->andWhere('r.requestedAt < :date')
            ->setParameter('status', 'pending')
            ->setParameter('date', $date)
            ->orderBy('r.requestedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}