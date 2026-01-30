<?php
// src/Repository/BookingRepository.php

namespace App\Repository;

use App\Entity\Booking;
use App\Entity\Client;
use App\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Trouve les réservations par client
     */
    public function findByClient(Client $client, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC');

        if ($status) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les réservations par prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC');

        if ($status) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les réservations à venir pour un client
     */
    public function findUpcomingByClient(Client $client): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('b')
            ->andWhere('b.client = :client')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('now', $now->format('Y-m-d'))
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations à venir pour un prestataire
     */
    public function findUpcomingByPrestataire(Prestataire $prestataire): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('b')
            ->andWhere('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now->format('Y-m-d'))
            ->setParameter('statuses', ['scheduled', 'confirmed', 'in_progress'])
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations du jour pour un prestataire
     */
    public function findTodayByPrestataire(Prestataire $prestataire): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('b')
            ->andWhere('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate = :today')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('statuses', ['scheduled', 'confirmed', 'in_progress'])
            ->orderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations dans les 24 heures
     */
    public function findBookingsIn24Hours(): array
    {
        $now = new \DateTime();
        $tomorrow = (clone $now)->modify('+24 hours');
        
        return $this->createQueryBuilder('b')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('CONCAT(b.scheduledDate, \' \', b.scheduledTime) >= :now')
            ->andWhere('CONCAT(b.scheduledDate, \' \', b.scheduledTime) <= :tomorrow')
            ->andWhere('b.reminderSent24h = :sent')
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->setParameter('now', $now->format('Y-m-d H:i:s'))
            ->setParameter('tomorrow', $tomorrow->format('Y-m-d H:i:s'))
            ->setParameter('sent', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations dans les 2 heures
     */
    public function findBookingsIn2Hours(): array
    {
        $now = new \DateTime();
        $in2Hours = (clone $now)->modify('+2 hours');
        
        return $this->createQueryBuilder('b')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('CONCAT(b.scheduledDate, \' \', b.scheduledTime) >= :now')
            ->andWhere('CONCAT(b.scheduledDate, \' \', b.scheduledTime) <= :in2Hours')
            ->andWhere('b.reminderSent2h = :sent')
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->setParameter('now', $now->format('Y-m-d H:i:s'))
            ->setParameter('in2Hours', $in2Hours->format('Y-m-d H:i:s'))
            ->setParameter('sent', false)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations par date
     */
    public function findByDate(\DateTimeInterface $date, ?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.scheduledDate = :date')
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('b.scheduledTime', 'ASC');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les réservations entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null,
        ?Client $client = null
    ): array {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.scheduledDate >= :startDate')
            ->andWhere('b.scheduledDate <= :endDate')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        if ($client) {
            $qb->andWhere('b.client = :client')
               ->setParameter('client', $client);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si un prestataire est disponible
     */
    public function isPrestataireAvailable(
        Prestataire $prestataire,
        \DateTimeInterface $date,
        \DateTimeInterface $time,
        int $duration,
        ?int $excludeBookingId = null
    ): bool {
        $scheduledDateTime = (new \DateTime($date->format('Y-m-d')))->setTime(
            (int)$time->format('H'),
            (int)$time->format('i')
        );
        
        $endTime = (clone $scheduledDateTime)->modify("+{$duration} minutes");

        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate = :date')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere(
                '(
                    (CONCAT(b.scheduledDate, \' \', b.scheduledTime) < :endTime 
                    AND DATE_ADD(CONCAT(b.scheduledDate, \' \', b.scheduledTime), b.duration, \'MINUTE\') > :startTime)
                )'
            )
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('statuses', ['scheduled', 'confirmed', 'in_progress'])
            ->setParameter('startTime', $scheduledDateTime->format('Y-m-d H:i:s'))
            ->setParameter('endTime', $endTime->format('Y-m-d H:i:s'));

        if ($excludeBookingId) {
            $qb->andWhere('b.id != :excludeId')
               ->setParameter('excludeId', $excludeBookingId);
        }

        return $qb->getQuery()->getSingleScalarResult() == 0;
    }

    /**
     * Compte les réservations par statut
     */
    public function countByStatus(string $status, ?Prestataire $prestataire = null): int
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.status = :status')
            ->setParameter('status', $status);

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Trouve les réservations complétées sans avis
     */
    public function findCompletedWithoutReview(Client $client): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.review', 'r')
            ->andWhere('b.client = :client')
            ->andWhere('b.status = :status')
            ->andWhere('r.id IS NULL')
            ->setParameter('client', $client)
            ->setParameter('status', 'completed')
            ->orderBy('b.completedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des réservations
     */
    public function getBookingStatistics(?Prestataire $prestataire = null, ?Client $client = null): array
    {
        $qb = $this->createQueryBuilder('b');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        if ($client) {
            $qb->andWhere('b.client = :client')
               ->setParameter('client', $client);
        }

        return [
            'total' => $qb->select('COUNT(b.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'scheduled' => $qb->select('COUNT(b.id)')
                ->andWhere('b.status = :status')
                ->setParameter('status', 'scheduled')
                ->getQuery()
                ->getSingleScalarResult(),

            'confirmed' => $qb->select('COUNT(b.id)')
                ->andWhere('b.status = :status')
                ->setParameter('status', 'confirmed')
                ->getQuery()
                ->getSingleScalarResult(),

            'completed' => $qb->select('COUNT(b.id)')
                ->andWhere('b.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult(),

            'cancelled' => $qb->select('COUNT(b.id)')
                ->andWhere('b.status = :status')
                ->setParameter('status', 'cancelled')
                ->getQuery()
                ->getSingleScalarResult(),

            'total_revenue' => $qb->select('SUM(b.amount)')
                ->andWhere('b.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_amount' => $qb->select('AVG(b.amount)')
                ->andWhere('b.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_duration' => $qb->select('AVG(b.duration)')
                ->andWhere('b.status = :status')
                ->setParameter('status', 'completed')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,
        ];
    }

    /**
     * Réservations par mois
     */
    public function getMonthlyBookings(int $year, ?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->select('MONTH(b.scheduledDate) as month, COUNT(b.id) as count, SUM(b.amount) as revenue')
            ->andWhere('YEAR(b.scheduledDate) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Réservations récurrentes
     */
    public function findRecurrent(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->andWhere('b.recurrence IS NOT NULL')
            ->orderBy('b.scheduledDate', 'DESC');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Taux d'annulation
     */
    public function getCancellationRate(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('b');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $total = $qb->select('COUNT(b.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total == 0) {
            return 0.0;
        }

        $cancelled = $qb->select('COUNT(b.id)')
            ->andWhere('b.status = :status')
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return round(($cancelled / $total) * 100, 2);
    }

    /**
     * Durée moyenne des réservations complétées
     */
    public function getAverageCompletedDuration(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('b')
            ->select('AVG(TIMESTAMPDIFF(MINUTE, b.actualStartTime, b.actualEndTime))')
            ->andWhere('b.status = :status')
            ->andWhere('b.actualStartTime IS NOT NULL')
            ->andWhere('b.actualEndTime IS NOT NULL')
            ->setParameter('status', 'completed');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return (float)($qb->getQuery()->getSingleScalarResult() ?? 0);
    }

    /**
     * Clients les plus actifs
     */
    public function getTopClients(int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->select('c.id, c.firstName, c.lastName, COUNT(b.id) as booking_count, SUM(b.amount) as total_spent')
            ->innerJoin('b.client', 'c')
            ->andWhere('b.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('c.id')
            ->orderBy('booking_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prestataires les plus actifs
     */
    public function getTopPrestataires(int $limit = 10): array
    {
        return $this->createQueryBuilder('b')
            ->select('p.id, p.firstName, p.lastName, COUNT(b.id) as booking_count, SUM(b.amount) as total_revenue')
            ->innerJoin('b.prestataire', 'p')
            ->andWhere('b.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('p.id')
            ->orderBy('booking_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations nécessitant un remplacement
     */
    public function findNeedingReplacement(): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.replacements', 'r')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('r.status = :replacementStatus OR r.id IS NULL')
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->setParameter('replacementStatus', 'pending')
            ->orderBy('b.scheduledDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Réservations avec remplacements actifs
     */
    public function findWithActiveReplacement(): array
    {
        return $this->createQueryBuilder('b')
            ->innerJoin('b.replacements', 'r')
            ->andWhere('r.status = :status')
            ->setParameter('status', 'confirmed')
            ->orderBy('b.scheduledDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Chiffre d'affaires par période
     */
    public function getRevenueBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null
    ): string {
        $qb = $this->createQueryBuilder('b')
            ->select('SUM(b.amount)')
            ->andWhere('b.scheduledDate >= :startDate')
            ->andWhere('b.scheduledDate <= :endDate')
            ->andWhere('b.status = :status')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->setParameter('status', 'completed');

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getSingleScalarResult() ?? '0.00';
    }

    /**
     * Réservations en retard (dépassé l'heure prévue sans être complétée)
     */
    public function findOverdue(): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('b')
            ->andWhere('b.status = :status')
            ->andWhere('CONCAT(b.scheduledDate, \' \', b.scheduledTime) < :now')
            ->setParameter('status', 'in_progress')
            ->setParameter('now', $now->format('Y-m-d H:i:s'))
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Temps moyen entre réservation et exécution
     */
    public function getAverageLeadTime(): float
    {
        $result = $this->createQueryBuilder('b')
            ->select('AVG(TIMESTAMPDIFF(DAY, b.createdAt, b.scheduledDate))')
            ->andWhere('b.status != :status')
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        return (float)($result ?? 0);
    }

    /**
     * Recherche avancée de réservations
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('b');

        if (isset($criteria['reference'])) {
            $qb->andWhere('b.referenceNumber LIKE :reference')
               ->setParameter('reference', '%' . $criteria['reference'] . '%');
        }

        if (isset($criteria['client_name'])) {
            $qb->innerJoin('b.client', 'c')
               ->andWhere('c.firstName LIKE :name OR c.lastName LIKE :name')
               ->setParameter('name', '%' . $criteria['client_name'] . '%');
        }

        if (isset($criteria['prestataire_name'])) {
            $qb->innerJoin('b.prestataire', 'p')
               ->andWhere('p.firstName LIKE :name OR p.lastName LIKE :name')
               ->setParameter('name', '%' . $criteria['prestataire_name'] . '%');
        }

        if (isset($criteria['status'])) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('b.scheduledDate >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('b.scheduledDate <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['min_amount'])) {
            $qb->andWhere('b.amount >= :minAmount')
               ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (isset($criteria['max_amount'])) {
            $qb->andWhere('b.amount <= :maxAmount')
               ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (isset($criteria['city'])) {
            $qb->andWhere('b.city LIKE :city')
               ->setParameter('city', '%' . $criteria['city'] . '%');
        }

        return $qb->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC')
            ->getQuery()
            ->getResult();
    }
}