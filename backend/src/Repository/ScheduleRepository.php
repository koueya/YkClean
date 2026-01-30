<?php
// src/Repository/ScheduleRepository.php

namespace App\Repository;

use App\Entity\Schedule;
use App\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Schedule>
 */
class ScheduleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Schedule::class);
    }

    /**
     * Trouve les événements d'un prestataire pour une date
     */
    public function findByPrestataireAndDate(
        Prestataire $prestataire,
        \DateTimeInterface $date
    ): array {
        return $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.date = :date')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les événements d'un prestataire entre deux dates
     */
    public function findByPrestataireBetweenDates(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.date >= :startDate')
            ->andWhere('s.date <= :endDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les événements par statut
     */
    public function findByStatus(
        Prestataire $prestataire,
        string $status,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', $status)
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC');

        if ($startDate) {
            $qb->andWhere('s.date >= :startDate')
               ->setParameter('startDate', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $qb->andWhere('s.date <= :endDate')
               ->setParameter('endDate', $endDate->format('Y-m-d'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les créneaux disponibles
     */
    public function findAvailableSlots(
        Prestataire $prestataire,
        \DateTimeInterface $date
    ): array {
        return $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.date = :date')
            ->andWhere('s.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('status', 'available')
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les événements du jour pour un prestataire
     */
    public function findTodayByPrestataire(Prestataire $prestataire): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.date = :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today->format('Y-m-d'))
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les événements en cours
     */
    public function findOngoing(Prestataire $prestataire): array
    {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.date = :today')
            ->andWhere('s.startTime <= :now')
            ->andWhere('s.endTime >= :now')
            ->andWhere('s.isAllDay = :allDay OR (s.isAllDay = :notAllDay)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $now->format('Y-m-d'))
            ->setParameter('now', $now->format('H:i:s'))
            ->setParameter('allDay', true)
            ->setParameter('notAllDay', false)
            ->orderBy('s.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les événements à venir
     */
    public function findUpcoming(
        Prestataire $prestataire,
        int $limit = 10
    ): array {
        $now = new \DateTime();
        
        return $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere(
                '(s.date > :today) OR ' .
                '(s.date = :today AND s.startTime > :now)'
            )
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $now->format('Y-m-d'))
            ->setParameter('now', $now->format('H:i:s'))
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie les chevauchements
     */
    public function findOverlapping(
        Prestataire $prestataire,
        \DateTimeInterface $date,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.date = :date')
            ->andWhere(
                '(s.isAllDay = :allDay) OR ' .
                '(s.startTime < :endTime AND s.endTime > :startTime)'
            )
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('allDay', true)
            ->setParameter('startTime', $startTime->format('H:i:s'))
            ->setParameter('endTime', $endTime->format('H:i:s'));

        if ($excludeId) {
            $qb->andWhere('s.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les événements par statut
     */
    public function countByStatus(
        Prestataire $prestataire,
        string $status,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): int {
        $qb = $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', $status);

        if ($startDate) {
            $qb->andWhere('s.date >= :startDate')
               ->setParameter('startDate', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $qb->andWhere('s.date <= :endDate')
               ->setParameter('endDate', $endDate->format('Y-m-d'));
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Trouve les événements récurrents
     */
    public function findRecurring(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.isRecurring = :recurring')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('recurring', true)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les événements par type
     */
    public function findByEventType(
        Prestataire $prestataire,
        string $eventType,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('s')
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.eventType = :eventType')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('eventType', $eventType)
            ->orderBy('s.date', 'ASC')
            ->addOrderBy('s.startTime', 'ASC');

        if ($startDate) {
            $qb->andWhere('s.date >= :startDate')
               ->setParameter('startDate', $startDate->format('Y-m-d'));
        }

        if ($endDate) {
            $qb->andWhere('s.date <= :endDate')
               ->setParameter('endDate', $endDate->format('Y-m-d'));
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques du planning
     */
    public function getStatistics(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $qb = $this->createQueryBuilder('s');

        $baseQb = (clone $qb)
            ->andWhere('s.prestataire = :prestataire')
            ->andWhere('s.date >= :startDate')
            ->andWhere('s.date <= :endDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'));

        return [
            'total_events' => (clone $baseQb)->select('COUNT(s.id)')
                ->getQuery()->getSingleScalarResult(),

            'available_slots' => (clone $baseQb)->select('COUNT(s.id)')
                ->andWhere('s.status = :status')
                ->setParameter('status', 'available')
                ->getQuery()->getSingleScalarResult(),

            'busy_slots' => (clone $baseQb)->select('COUNT(s.id)')
                ->andWhere('s.status = :status')
                ->setParameter('status', 'busy')
                ->getQuery()->getSingleScalarResult(),

            'blocked_slots' => (clone $baseQb)->select('COUNT(s.id)')
                ->andWhere('s.status = :status')
                ->setParameter('status', 'blocked')
                ->getQuery()->getSingleScalarResult(),

            'bookings' => (clone $baseQb)->select('COUNT(s.id)')
                ->andWhere('s.eventType = :eventType')
                ->setParameter('eventType', 'booking')
                ->getQuery()->getSingleScalarResult(),

            'personal_events' => (clone $baseQb)->select('COUNT(s.id)')
                ->andWhere('s.eventType = :eventType')
                ->setParameter('eventType', 'personal')
                ->getQuery()->getSingleScalarResult(),
        ];
    }

    /**
     * Exporte le planning en format calendrier
     */
    public function exportToCalendar(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $schedules = $this->findByPrestataireBetweenDates($prestataire, $startDate, $endDate);

        return array_map(fn(Schedule $schedule) => $schedule->toCalendarEvent(), $schedules);
    }

    /**
     * Trouve les créneaux libres d'une durée minimale
     */
    public function findFreeSlots(
        Prestataire $prestataire,
        \DateTimeInterface $date,
        int $minDuration
    ): array {
        // Récupérer tous les événements du jour
        $events = $this->findByPrestataireAndDate($prestataire, $date);

        $freeSlots = [];
        $workStart = \DateTime::createFromFormat('H:i', '08:00');
        $workEnd = \DateTime::createFromFormat('H:i', '18:00');

        if (empty($events)) {
            // Toute la journée est libre
            return [[
                'start' => $workStart,
                'end' => $workEnd,
                'duration' => 600 // 10 heures en minutes
                ]];
                }
// Trier les événements par heure de début
    usort($events, fn($a, $b) => $a->getStartTime() <=> $b->getStartTime());

    $currentTime = $workStart;

    foreach ($events as $event) {
        if ($event->isAllDay()) {
            return []; // Pas de créneau libre si événement toute la journée
        }

        $eventStart = \DateTime::createFromFormat('H:i:s', $event->getStartTime()->format('H:i:s'));
        
        // Calculer l'écart avant cet événement
        $gap = ($eventStart->getTimestamp() - $currentTime->getTimestamp()) / 60;

        if ($gap >= $minDuration) {
            $freeSlots[] = [
                'start' => clone $currentTime,
                'end' => clone $eventStart,
                'duration' => $gap
            ];
        }

        // Mettre à jour le temps courant
        $eventEnd = \DateTime::createFromFormat('H:i:s', $event->getEndTime()->format('H:i:s'));
        if ($eventEnd > $currentTime) {
            $currentTime = $eventEnd;
        }
    }

    // Vérifier l'écart après le dernier événement
    $finalGap = ($workEnd->getTimestamp() - $currentTime->getTimestamp()) / 60;
    if ($finalGap >= $minDuration) {
        $freeSlots[] = [
            'start' => clone $currentTime,
            'end' => clone $workEnd,
            'duration' => $finalGap
        ];
    }

    return $freeSlots;
}

/**
 * Trouve les jours avec disponibilités
 */
public function findDaysWithAvailability(
    Prestataire $prestataire,
    \DateTimeInterface $startDate,
    \DateTimeInterface $endDate,
    int $minDuration = 60
): array {
    $daysWithAvailability = [];
    $currentDate = clone $startDate;

    while ($currentDate <= $endDate) {
        $freeSlots = $this->findFreeSlots($prestataire, $currentDate, $minDuration);
        
        if (!empty($freeSlots)) {
            $daysWithAvailability[] = [
                'date' => clone $currentDate,
                'slots' => $freeSlots,
                'slots_count' => count($freeSlots)
            ];
        }

        $currentDate->modify('+1 day');
    }

    return $daysWithAvailability;
}

}