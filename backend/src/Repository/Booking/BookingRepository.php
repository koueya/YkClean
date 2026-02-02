<?php
// src/Repository/Booking/BookingRepository.php

namespace App\Repository\Booking;

use App\Entity\Booking\Booking;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Booking
 * Gère toutes les requêtes liées aux réservations
 * 
 * @extends ServiceEntityRepository<Booking>
 */
class BookingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Booking::class);
    }

    /**
     * Persiste une réservation
     */
    public function save(Booking $booking, bool $flush = false): void
    {
        $this->getEntityManager()->persist($booking);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une réservation
     */
    public function remove(Booking $booking, bool $flush = false): void
    {
        $this->getEntityManager()->remove($booking);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ============================================
    // RECHERCHE PAR CLIENT
    // ============================================

    /**
     * Trouve toutes les réservations d'un client
     */
    public function findByClient(Client $client, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.client = :client')
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
     * Trouve les réservations à venir pour un client
     */
    public function findUpcomingByClient(Client $client, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('now', $now)
            ->setParameter('statuses', [
                Booking::STATUS_SCHEDULED, 
                Booking::STATUS_CONFIRMED
            ])
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations passées d'un client
     */
    public function findPastByClient(Client $client, int $limit = 10): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.scheduledDate < :now OR b.status = :completed')
            ->setParameter('client', $client)
            ->setParameter('now', $now)
            ->setParameter('completed', Booking::STATUS_COMPLETED)
            ->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations en attente de paiement pour un client
     */
    public function findPendingPaymentByClient(Client $client): array
    {
        return $this->createQueryBuilder('b')
            ->leftJoin('b.payment', 'p')
            ->where('b.client = :client')
            ->andWhere('b.status = :status')
            ->andWhere('p.status = :paymentStatus OR p.id IS NULL')
            ->setParameter('client', $client)
            ->setParameter('status', Booking::STATUS_SCHEDULED)
            ->setParameter('paymentStatus', 'pending')
            ->orderBy('b.scheduledDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR PRESTATAIRE
    // ============================================

    /**
     * Trouve toutes les réservations d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
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
     * Trouve les réservations à venir pour un prestataire
     */
    public function findUpcomingByPrestataire(Prestataire $prestataire, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->setParameter('statuses', [
                Booking::STATUS_SCHEDULED, 
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS
            ])
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations du jour pour un prestataire
     */
    public function findTodayByPrestataire(Prestataire $prestataire): array
    {
        $today = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate = :today')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today->format('Y-m-d'))
            ->setParameter('statuses', [
                Booking::STATUS_SCHEDULED,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS
            ])
            ->orderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations passées d'un prestataire
     */
    public function findPastByPrestataire(Prestataire $prestataire, int $limit = 10): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate < :now OR b.status = :completed')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->setParameter('completed', Booking::STATUS_COMPLETED)
            ->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations en cours pour un prestataire
     */
    public function findInProgressByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', Booking::STATUS_IN_PROGRESS)
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR PÉRIODE
    // ============================================

    /**
     * Trouve les réservations pour une période donnée
     */
    public function findByDateRange(
        \DateTimeInterface $startDate, 
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null,
        ?Client $client = null
    ): array {
        $qb = $this->createQueryBuilder('b')
            ->where('b.scheduledDate BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate);

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        if ($client) {
            $qb->andWhere('b.client = :client')
               ->setParameter('client', $client);
        }

        return $qb->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations pour une date spécifique
     */
    public function findByDate(
        \DateTimeInterface $date,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('b')
            ->where('b.scheduledDate = :date')
            ->setParameter('date', $date->format('Y-m-d'));

        if ($prestataire) {
            $qb->andWhere('b.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->orderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // DÉTECTION DE CONFLITS
    // ============================================

    /**
     * Vérifie s'il existe un conflit de réservation pour un prestataire
     * à une date et heure données
     */
    public function hasConflict(
        Prestataire $prestataire,
        \DateTimeInterface $scheduledDate,
        \DateTimeInterface $scheduledTime,
        int $duration,
        ?int $excludeBookingId = null
    ): bool {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate = :date')
            ->andWhere('b.status IN (:activeStatuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $scheduledDate->format('Y-m-d'))
            ->setParameter('activeStatuses', [
                Booking::STATUS_SCHEDULED,
                Booking::STATUS_CONFIRMED,
                Booking::STATUS_IN_PROGRESS
            ]);

        // Calcul de la fin de la nouvelle réservation
        $scheduledDateTime = \DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $scheduledDate->format('Y-m-d') . ' ' . $scheduledTime->format('H:i:s')
        );
        $endTime = $scheduledDateTime->modify("+{$duration} minutes");

        // Vérifier les chevauchements
        $qb->andWhere('(
            (b.scheduledTime <= :scheduledTime AND 
             DATE_ADD(b.scheduledTime, b.duration, \'MINUTE\') > :scheduledTime) OR
            (b.scheduledTime < :endTime AND 
             DATE_ADD(b.scheduledTime, b.duration, \'MINUTE\') >= :endTime) OR
            (b.scheduledTime >= :scheduledTime AND 
             DATE_ADD(b.scheduledTime, b.duration, \'MINUTE\') <= :endTime)
        )')
        ->setParameter('scheduledTime', $scheduledTime->format('H:i:s'))
        ->setParameter('endTime', $endTime->format('H:i:s'));

        // Exclure une réservation spécifique (pour les mises à jour)
        if ($excludeBookingId) {
            $qb->andWhere('b.id != :excludeId')
               ->setParameter('excludeId', $excludeBookingId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    // ============================================
    // RECHERCHE PAR STATUT
    // ============================================

    /**
     * Trouve les réservations par statut
     */
    public function findByStatus(string $status, int $limit = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', $status)
            ->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les réservations qui nécessitent un rappel
     * (24h avant le rendez-vous)
     */
    public function findRequiringReminder(): array
    {
        $tomorrow = new \DateTimeImmutable('+24 hours');
        $tomorrowStart = $tomorrow->setTime(0, 0, 0);
        $tomorrowEnd = $tomorrow->setTime(23, 59, 59);

        return $this->createQueryBuilder('b')
            ->where('b.scheduledDate BETWEEN :start AND :end')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('b.reminderSent = false')
            ->setParameter('start', $tomorrowStart)
            ->setParameter('end', $tomorrowEnd)
            ->setParameter('statuses', [
                Booking::STATUS_SCHEDULED,
                Booking::STATUS_CONFIRMED
            ])
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations en attente de confirmation
     * (créées il y a plus de 24h)
     */
    public function findPendingConfirmation(): array
    {
        $yesterday = new \DateTimeImmutable('-24 hours');

        return $this->createQueryBuilder('b')
            ->where('b.status = :status')
            ->andWhere('b.createdAt < :yesterday')
            ->setParameter('status', Booking::STATUS_SCHEDULED)
            ->setParameter('yesterday', $yesterday)
            ->orderBy('b.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les réservations expirées
     * (passées mais toujours en statut scheduled/confirmed)
     */
    public function findExpired(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('b')
            ->where('b.scheduledDate < :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('now', $now->format('Y-m-d'))
            ->setParameter('statuses', [
                Booking::STATUS_SCHEDULED,
                Booking::STATUS_CONFIRMED
            ])
            ->orderBy('b.scheduledDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR RÉFÉRENCE
    // ============================================

    /**
     * Trouve une réservation par son numéro de référence
     */
    public function findByReferenceNumber(string $referenceNumber): ?Booking
    {
        return $this->createQueryBuilder('b')
            ->where('b.referenceNumber = :reference')
            ->setParameter('reference', $referenceNumber)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Compte le nombre de réservations par statut pour un prestataire
     */
    public function countByStatusForPrestataire(Prestataire $prestataire): array
    {
        $result = $this->createQueryBuilder('b')
            ->select('b.status, COUNT(b.id) as count')
            ->where('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->groupBy('b.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Compte le nombre de réservations par statut pour un client
     */
    public function countByStatusForClient(Client $client): array
    {
        $result = $this->createQueryBuilder('b')
            ->select('b.status, COUNT(b.id) as count')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->groupBy('b.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Calcule le chiffre d'affaires total d'un prestataire
     */
    public function getTotalRevenueForPrestataire(
        Prestataire $prestataire,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): float {
        $qb = $this->createQueryBuilder('b')
            ->select('SUM(b.amount) as total')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', Booking::STATUS_COMPLETED);

        if ($startDate) {
            $qb->andWhere('b.scheduledDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('b.scheduledDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Calcule le montant total dépensé par un client
     */
    public function getTotalSpentByClient(
        Client $client,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): float {
        $qb = $this->createQueryBuilder('b')
            ->select('SUM(b.amount) as total')
            ->where('b.client = :client')
            ->andWhere('b.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', Booking::STATUS_COMPLETED);

        if ($startDate) {
            $qb->andWhere('b.scheduledDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('b.scheduledDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Obtient les statistiques mensuelles pour un prestataire
     */
    public function getMonthlyStatsForPrestataire(
        Prestataire $prestataire,
        int $year,
        int $month
    ): array {
        $startDate = new \DateTimeImmutable("{$year}-{$month}-01");
        $endDate = $startDate->modify('last day of this month');

        $bookings = $this->findByDateRange($startDate, $endDate, $prestataire);

        $stats = [
            'total' => count($bookings),
            'completed' => 0,
            'cancelled' => 0,
            'revenue' => 0,
            'hours_worked' => 0,
        ];

        foreach ($bookings as $booking) {
            if ($booking->getStatus() === Booking::STATUS_COMPLETED) {
                $stats['completed']++;
                $stats['revenue'] += $booking->getAmount();
                $stats['hours_worked'] += $booking->getDuration() / 60;
            } elseif ($booking->getStatus() === Booking::STATUS_CANCELLED) {
                $stats['cancelled']++;
            }
        }

        return $stats;
    }

    // ============================================
    // RECHERCHE AVANCÉE AVEC CRITÈRES
    // ============================================

    /**
     * Recherche avancée de réservations avec multiples critères
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('b');

        // Filtre par numéro de référence
        if (isset($criteria['reference'])) {
            $qb->andWhere('b.referenceNumber LIKE :reference')
               ->setParameter('reference', '%' . $criteria['reference'] . '%');
        }

        // Filtre par nom de client
        if (isset($criteria['client_name'])) {
            $qb->innerJoin('b.client', 'c')
               ->andWhere('c.firstName LIKE :name OR c.lastName LIKE :name')
               ->setParameter('name', '%' . $criteria['client_name'] . '%');
        }

        // Filtre par nom de prestataire
        if (isset($criteria['prestataire_name'])) {
            $qb->innerJoin('b.prestataire', 'p')
               ->andWhere('p.firstName LIKE :name OR p.lastName LIKE :name')
               ->setParameter('name', '%' . $criteria['prestataire_name'] . '%');
        }

        // Filtre par statut
        if (isset($criteria['status'])) {
            $qb->andWhere('b.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        // Filtre par statuts multiples
        if (isset($criteria['statuses']) && is_array($criteria['statuses'])) {
            $qb->andWhere('b.status IN (:statuses)')
               ->setParameter('statuses', $criteria['statuses']);
        }

        // Filtre par date de début
        if (isset($criteria['start_date'])) {
            $qb->andWhere('b.scheduledDate >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        // Filtre par date de fin
        if (isset($criteria['end_date'])) {
            $qb->andWhere('b.scheduledDate <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        // Filtre par montant minimum
        if (isset($criteria['min_amount'])) {
            $qb->andWhere('b.amount >= :minAmount')
               ->setParameter('minAmount', $criteria['min_amount']);
        }

        // Filtre par montant maximum
        if (isset($criteria['max_amount'])) {
            $qb->andWhere('b.amount <= :maxAmount')
               ->setParameter('maxAmount', $criteria['max_amount']);
        }

        // Filtre par ville
        if (isset($criteria['city'])) {
            $qb->andWhere('b.city LIKE :city')
               ->setParameter('city', '%' . $criteria['city'] . '%');
        }

        // Filtre par catégorie de service
        if (isset($criteria['service_category'])) {
            $qb->andWhere('b.serviceCategory = :category')
               ->setParameter('category', $criteria['service_category']);
        }

        // Filtre par récurrence
        if (isset($criteria['recurrence_id'])) {
            $qb->andWhere('b.recurrence = :recurrence')
               ->setParameter('recurrence', $criteria['recurrence_id']);
        }

        // Filtre par présence d'avis
        if (isset($criteria['has_review'])) {
            if ($criteria['has_review']) {
                $qb->andWhere('b.review IS NOT NULL');
            } else {
                $qb->andWhere('b.review IS NULL');
            }
        }

        // Tri
        $orderBy = $criteria['order_by'] ?? 'scheduledDate';
        $orderDirection = $criteria['order_direction'] ?? 'DESC';
        
        $qb->orderBy('b.' . $orderBy, $orderDirection);

        // Limite
        if (isset($criteria['limit'])) {
            $qb->setMaxResults($criteria['limit']);
        }

        // Offset
        if (isset($criteria['offset'])) {
            $qb->setFirstResult($criteria['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // MÉTHODES POUR LES RÉCURRENCES
    // ============================================

    /**
     * Trouve toutes les réservations d'une récurrence
     */
    public function findByRecurrence(int $recurrenceId): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.recurrence = :recurrence')
            ->setParameter('recurrence', $recurrenceId)
            ->orderBy('b.scheduledDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prochaines réservations d'une récurrence
     */
    public function findUpcomingByRecurrence(int $recurrenceId, int $limit = 5): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('b')
            ->where('b.recurrence = :recurrence')
            ->andWhere('b.scheduledDate >= :now')
            ->setParameter('recurrence', $recurrenceId)
            ->setParameter('now', $now)
            ->orderBy('b.scheduledDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // MÉTHODES POUR LES REMPLACEMENTS
    // ============================================

    /**
     * Trouve les réservations nécessitant un remplacement
     */
    public function findRequiringReplacement(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->andWhere('b.scheduledDate > :now')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', Booking::STATUS_SCHEDULED)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('b.scheduledDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // QUERY BUILDER PERSONNALISÉ
    // ============================================

    /**
     * Crée un QueryBuilder de base avec les jointures courantes
     */
    public function createBaseQueryBuilder(string $alias = 'b'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias . '.client', 'c')
            ->leftJoin($alias . '.prestataire', 'p')
            ->leftJoin($alias . '.serviceCategory', 'sc')
            ->leftJoin($alias . '.payment', 'pay')
            ->leftJoin($alias . '.review', 'r');
    }
}