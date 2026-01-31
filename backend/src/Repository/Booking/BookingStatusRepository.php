<?php
// src/Repository/BookingStatusRepository.php

namespace App\Repository;

use App\Entity\BookingStatus;
use App\Entity\Booking;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BookingStatus>
 */
class BookingStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookingStatus::class);
    }

    /**
     * Trouve l'historique des statuts d'une réservation
     */
    public function findByBooking(Booking $booking): array
    {
        return $this->createQueryBuilder('bs')
            ->andWhere('bs.booking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('bs.changedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le dernier changement de statut d'une réservation
     */
    public function findLatestByBooking(Booking $booking): ?BookingStatus
    {
        return $this->createQueryBuilder('bs')
            ->andWhere('bs.booking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('bs.changedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les changements de statut par utilisateur
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('bs')
            ->andWhere('bs.changedBy = :user')
            ->setParameter('user', $user)
            ->orderBy('bs.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les changements vers un statut spécifique
     */
    public function findByNewStatus(string $status): array
    {
        return $this->createQueryBuilder('bs')
            ->andWhere('bs.newStatus = :status')
            ->setParameter('status', $status)
            ->orderBy('bs.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les annulations
     */
    public function findCancellations(): array
    {
        return $this->createQueryBuilder('bs')
            ->andWhere('bs.newStatus = :status')
            ->setParameter('status', 'cancelled')
            ->orderBy('bs.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les changements de statut par type
     */
    public function countByNewStatus(string $status): int
    {
        return $this->createQueryBuilder('bs')
            ->select('COUNT(bs.id)')
            ->andWhere('bs.newStatus = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les changements de statut dans une période
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $newStatus = null
    ): array {
        $qb = $this->createQueryBuilder('bs')
            ->andWhere('bs.changedAt >= :startDate')
            ->andWhere('bs.changedAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('bs.changedAt', 'DESC');

        if ($newStatus) {
            $qb->andWhere('bs.newStatus = :newStatus')
               ->setParameter('newStatus', $newStatus);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques des changements de statut
     */
    public function getStatistics(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('bs')
            ->select('bs.newStatus, COUNT(bs.id) as count')
            ->groupBy('bs.newStatus');

        if ($startDate) {
            $qb->andWhere('bs.changedAt >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('bs.changedAt <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Taux de conversion par statut
     */
    public function getConversionRates(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                bs.old_status,
                bs.new_status,
                COUNT(*) as count
            FROM booking_statuses bs
            GROUP BY bs.old_status, bs.new_status
            ORDER BY count DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Temps moyen entre les changements de statut
     */
    public function getAverageTimeBetweenStatuses(
        string $fromStatus,
        string $toStatus
    ): float {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                TIMESTAMPDIFF(MINUTE, 
                    (SELECT bs1.changed_at 
                     FROM booking_statuses bs1 
                     WHERE bs1.booking_id = bs2.booking_id 
                     AND bs1.new_status = :fromStatus 
                     ORDER BY bs1.changed_at DESC 
                     LIMIT 1),
                    bs2.changed_at
                )
            ) as avg_minutes
            FROM booking_statuses bs2
            WHERE bs2.new_status = :toStatus
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'fromStatus' => $fromStatus,
            'toStatus' => $toStatus
        ]);

        return (float)($result->fetchOne() ?? 0);
    }

    /**
     * Raisons d'annulation les plus fréquentes
     */
    public function getTopCancellationReasons(int $limit = 10): array
    {
        return $this->createQueryBuilder('bs')
            ->select('bs.reason, COUNT(bs.id) as count')
            ->andWhere('bs.newStatus = :status')
            ->andWhere('bs.reason IS NOT NULL')
            ->setParameter('status', 'cancelled')
            ->groupBy('bs.reason')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs qui modifient le plus les statuts
     */
    public function getMostActiveUsers(int $limit = 10): array
    {
        return $this->createQueryBuilder('bs')
            ->select('u.id, u.firstName, u.lastName, COUNT(bs.id) as changes_count')
            ->innerJoin('bs.changedBy', 'u')
            ->groupBy('u.id')
            ->orderBy('changes_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Changements de statut par jour de la semaine
     */
    public function getChangesByDayOfWeek(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                DAYOFWEEK(bs.changed_at) as day_of_week,
                COUNT(*) as count,
                bs.new_status
            FROM booking_statuses bs
            GROUP BY day_of_week, bs.new_status
            ORDER BY day_of_week, count DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Changements de statut par heure de la journée
     */
    public function getChangesByHourOfDay(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                HOUR(bs.changed_at) as hour,
                COUNT(*) as count,
                bs.new_status
            FROM booking_statuses bs
            GROUP BY hour, bs.new_status
            ORDER BY hour, count DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Historique complet d'une réservation avec détails
     */
    public function getBookingHistory(Booking $booking): array
    {
        return $this->createQueryBuilder('bs')
            ->leftJoin('bs.changedBy', 'u')
            ->addSelect('u')
            ->andWhere('bs.booking = :booking')
            ->setParameter('booking', $booking)
            ->orderBy('bs.changedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Changements récents (dernières 24h)
     */
    public function findRecent(int $limit = 50): array
    {
        $yesterday = new \DateTimeImmutable('-24 hours');
        
        return $this->createQueryBuilder('bs')
            ->leftJoin('bs.booking', 'b')
            ->leftJoin('bs.changedBy', 'u')
            ->addSelect('b', 'u')
            ->andWhere('bs.changedAt >= :yesterday')
            ->setParameter('yesterday', $yesterday)
            ->orderBy('bs.changedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Durée moyenne dans chaque statut
     */
    public function getAverageDurationInStatus(string $status): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                TIMESTAMPDIFF(MINUTE, 
                    bs1.changed_at,
                    (SELECT MIN(bs2.changed_at) 
                     FROM booking_statuses bs2 
                     WHERE bs2.booking_id = bs1.booking_id 
                     AND bs2.changed_at > bs1.changed_at)
                )
            ) as avg_duration
            FROM booking_statuses bs1
            WHERE bs1.new_status = :status
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['status' => $status]);

        return (float)($result->fetchOne() ?? 0);
    }

    /**
     * Changements de statut par adresse IP
     */
    public function findByIpAddress(string $ipAddress): array
    {
        return $this->createQueryBuilder('bs')
            ->andWhere('bs.ipAddress = :ipAddress')
            ->setParameter('ipAddress', $ipAddress)
            ->orderBy('bs.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Détection d'activités suspectes (trop de changements rapides)
     */
    public function findSuspiciousActivity(int $maxChangesPerHour = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                bs.booking_id,
                bs.changed_by_id,
                COUNT(*) as changes_count,
                MIN(bs.changed_at) as first_change,
                MAX(bs.changed_at) as last_change
            FROM booking_statuses bs
            WHERE bs.changed_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY bs.booking_id, bs.changed_by_id
            HAVING changes_count >= :maxChanges
            ORDER BY changes_count DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['maxChanges' => $maxChangesPerHour]);

        return $result->fetchAllAssociative();
    }

    /**
     * Répartition mensuelle des changements de statut
     */
    public function getMonthlyDistribution(int $year): array
    {
        return $this->createQueryBuilder('bs')
            ->select('MONTH(bs.changedAt) as month, bs.newStatus, COUNT(bs.id) as count')
            ->andWhere('YEAR(bs.changedAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month', 'bs.newStatus')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Changements par métadonnées spécifiques
     */
    public function findByMetadata(string $key, mixed $value): array
    {
        return $this->createQueryBuilder('bs')
            ->andWhere('JSON_EXTRACT(bs.metadata, :key) = :value')
            ->setParameter('key', '$.' . $key)
            ->setParameter('value', json_encode($value))
            ->orderBy('bs.changedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}