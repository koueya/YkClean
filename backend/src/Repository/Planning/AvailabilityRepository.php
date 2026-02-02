<?php
// src/Repository/Planning/AvailabilityRepository.php

namespace App\Repository\Planning;

use App\Entity\Planning\Availability;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Availability
 * Gère toutes les requêtes liées aux disponibilités des prestataires
 * 
 * @extends ServiceEntityRepository<Availability>
 */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    /**
     * Persiste une disponibilité
     */
    public function save(Availability $availability, bool $flush = false): void
    {
        $this->getEntityManager()->persist($availability);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une disponibilité
     */
    public function remove(Availability $availability, bool $flush = false): void
    {
        $this->getEntityManager()->remove($availability);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ============================================
    // RECHERCHE PAR PRESTATAIRE
    // ============================================

    /**
     * Trouve toutes les disponibilités d'un prestataire
     */
    public function findByPrestataire(
        Prestataire $prestataire,
        ?bool $isRecurring = null,
        ?bool $isActive = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC');

        if ($isRecurring !== null) {
            $qb->andWhere('a.isRecurring = :isRecurring')
               ->setParameter('isRecurring', $isRecurring);
        }

        if ($isActive !== null) {
            $qb->andWhere('a.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les disponibilités récurrentes d'un prestataire
     */
    public function findRecurringByPrestataire(Prestataire $prestataire): array
    {
        return $this->findByPrestataire($prestataire, true, true);
    }

    /**
     * Trouve les disponibilités ponctuelles d'un prestataire
     */
    public function findSpecificByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.isRecurring = false')
            ->andWhere('a.specificDate IS NOT NULL')
            ->andWhere('a.isActive = true')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('a.specificDate', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les disponibilités d'un prestataire
     */
    public function countByPrestataire(Prestataire $prestataire): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.isActive = true')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ============================================
    // RECHERCHE PAR JOUR
    // ============================================

    /**
     * Trouve les disponibilités d'un prestataire pour un jour spécifique de la semaine
     */
    public function findByPrestataireAndDay(
        Prestataire $prestataire, 
        int $dayOfWeek
    ): array {
        return $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.isActive = true')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les disponibilités pour un jour de la semaine (tous prestataires)
     */
    public function findByDayOfWeek(int $dayOfWeek): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.isActive = true')
            ->andWhere('a.isRecurring = true')
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les disponibilités pour une date spécifique
     */
    public function findBySpecificDate(
        \DateTimeInterface $date,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.specificDate = :date')
            ->andWhere('a.isActive = true')
            ->setParameter('date', $date)
            ->orderBy('a.startTime', 'ASC');

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // VÉRIFICATION DE DISPONIBILITÉ
    // ============================================

    /**
     * Vérifie si un prestataire est disponible à un jour et heure donnés
     */
    public function isAvailable(
        Prestataire $prestataire,
        int $dayOfWeek,
        \DateTimeInterface $time
    ): bool {
        $timeString = $time->format('H:i:s');

        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.startTime <= :time')
            ->andWhere('a.endTime > :time')
            ->andWhere('a.isActive = true')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('time', $timeString)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Vérifie si un prestataire est disponible à une date et heure spécifiques
     */
    public function isAvailableAtDateTime(
        Prestataire $prestataire,
        \DateTimeInterface $dateTime
    ): bool {
        $dayOfWeek = (int) $dateTime->format('w');
        $time = $dateTime->format('H:i:s');

        // Vérifier les disponibilités récurrentes
        $recurringAvailable = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.isRecurring = true')
            ->andWhere('a.startTime <= :time')
            ->andWhere('a.endTime > :time')
            ->andWhere('a.isActive = true')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('time', $time)
            ->getQuery()
            ->getSingleScalarResult();

        if ($recurringAvailable > 0) {
            return true;
        }

        // Vérifier les disponibilités ponctuelles
        $specificDate = $dateTime->format('Y-m-d');
        
        $specificAvailable = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.specificDate = :date')
            ->andWhere('a.startTime <= :time')
            ->andWhere('a.endTime > :time')
            ->andWhere('a.isActive = true')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $specificDate)
            ->setParameter('time', $time)
            ->getQuery()
            ->getSingleScalarResult();

        return $specificAvailable > 0;
    }

    /**
     * Trouve les disponibilités se chevauchant avec un créneau
     */
    public function findOverlapping(
        Prestataire $prestataire,
        int $dayOfWeek,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.isActive = true')
            ->andWhere('(
                (a.startTime <= :startTime AND a.endTime > :startTime) OR
                (a.startTime < :endTime AND a.endTime >= :endTime) OR
                (a.startTime >= :startTime AND a.endTime <= :endTime)
            )')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('startTime', $startTime->format('H:i:s'))
            ->setParameter('endTime', $endTime->format('H:i:s'));

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE DE PRESTATAIRES DISPONIBLES
    // ============================================

    /**
     * Trouve les prestataires disponibles à un moment donné
     */
    public function findAvailablePrestataires(
        int $dayOfWeek,
        \DateTimeInterface $time,
        ?array $serviceCategories = null
    ): array {
        $timeString = $time->format('H:i:s');

        $qb = $this->createQueryBuilder('a')
            ->select('DISTINCT p')
            ->innerJoin('a.prestataire', 'p')
            ->where('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.startTime <= :time')
            ->andWhere('a.endTime > :time')
            ->andWhere('a.isActive = true')
            ->andWhere('p.isActive = true')
            ->andWhere('p.isApproved = true')
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('time', $timeString);

        if ($serviceCategories) {
            $qb->andWhere('p.serviceCategories IN (:categories)')
               ->setParameter('categories', $serviceCategories);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les prestataires avec le plus de disponibilités
     */
    public function findMostAvailablePrestataires(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('p.id, p.firstName, p.lastName, COUNT(a.id) as availability_count')
            ->innerJoin('a.prestataire', 'p')
            ->where('a.isActive = true')
            ->andWhere('p.isActive = true')
            ->andWhere('p.isApproved = true')
            ->groupBy('p.id')
            ->orderBy('availability_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires sans disponibilités définies
     */
    public function findPrestatairesWithoutAvailability(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT p.*
            FROM prestataires p
            LEFT JOIN availabilities a ON a.prestataire_id = p.id
            WHERE a.id IS NULL
            AND p.is_active = 1
            AND p.is_approved = 1
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        $prestataireIds = array_column($result->fetchAllAssociative(), 'id');

        if (empty($prestataireIds)) {
            return [];
        }

        return $this->getEntityManager()
            ->getRepository(Prestataire::class)
            ->createQueryBuilder('p')
            ->where('p.id IN (:ids)')
            ->setParameter('ids', $prestataireIds)
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Compte les heures disponibles par semaine pour un prestataire
     */
    public function countWeeklyHours(Prestataire $prestataire): float
    {
        $availabilities = $this->findRecurringByPrestataire($prestataire);
        $totalMinutes = 0;

        foreach ($availabilities as $availability) {
            $start = $availability->getStartTime();
            $end = $availability->getEndTime();
            
            $diff = ($end->getTimestamp() - $start->getTimestamp()) / 60;
            $totalMinutes += $diff;
        }

        return round($totalMinutes / 60, 2);
    }

    /**
     * Obtient les statistiques de disponibilités pour un prestataire
     */
    public function getStatistics(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($prestataire) {
            $qb->where('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $qb->andWhere('a.isActive = true');

        $availabilities = $qb->getQuery()->getResult();

        $stats = [
            'total_slots' => count($availabilities),
            'total_hours' => 0,
            'by_day' => array_fill(0, 7, ['count' => 0, 'hours' => 0]),
            'earliest_start' => null,
            'latest_end' => null,
            'recurring_count' => 0,
            'specific_count' => 0,
        ];

        foreach ($availabilities as $availability) {
            $dayOfWeek = $availability->getDayOfWeek() ?? 0;
            $start = $availability->getStartTime();
            $end = $availability->getEndTime();
            
            $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
            
            $stats['total_hours'] += $hours;
            $stats['by_day'][$dayOfWeek]['count']++;
            $stats['by_day'][$dayOfWeek]['hours'] += $hours;

            // Plus tôt / plus tard
            if (!$stats['earliest_start'] || $start < $stats['earliest_start']) {
                $stats['earliest_start'] = $start->format('H:i');
            }

            if (!$stats['latest_end'] || $end > $stats['latest_end']) {
                $stats['latest_end'] = $end->format('H:i');
            }

            // Comptage récurrentes vs ponctuelles
            if ($availability->isRecurring()) {
                $stats['recurring_count']++;
            } else {
                $stats['specific_count']++;
            }
        }

        // Calcul moyenne heures par jour
        foreach ($stats['by_day'] as $day => $data) {
            $stats['by_day'][$day]['avg_hours'] = $data['count'] > 0 
                ? round($data['hours'] / $data['count'], 2) 
                : 0;
        }

        $stats['total_hours'] = round($stats['total_hours'], 2);

        return $stats;
    }

    /**
     * Obtient la distribution des disponibilités par heure de la journée
     */
    public function getHourlyDistribution(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($prestataire) {
            $qb->where('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $qb->andWhere('a.isActive = true');

        $availabilities = $qb->getQuery()->getResult();

        $distribution = array_fill(0, 24, 0);

        foreach ($availabilities as $availability) {
            $startHour = (int) $availability->getStartTime()->format('H');
            $endHour = (int) $availability->getEndTime()->format('H');

            for ($hour = $startHour; $hour < $endHour; $hour++) {
                $distribution[$hour]++;
            }
        }

        return $distribution;
    }

    /**
     * Compte les disponibilités par prestataire (admin)
     */
    public function countByPrestataires(): array
    {
        return $this->createQueryBuilder('a')
            ->select('p.id, p.firstName, p.lastName, COUNT(a.id) as availability_count, SUM(TIMESTAMPDIFF(HOUR, a.startTime, a.endTime)) as total_hours')
            ->innerJoin('a.prestataire', 'p')
            ->where('a.isActive = true')
            ->groupBy('p.id')
            ->orderBy('availability_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // UTILITAIRES
    // ============================================

    /**
     * Crée un planning type de disponibilités (Lun-Ven 9h-18h)
     */
    public function createDefaultSchedule(
        Prestataire $prestataire,
        string $startTime = '09:00',
        string $endTime = '18:00',
        array $excludedDays = [0, 6] // Dimanche et Samedi par défaut
    ): array {
        $availabilities = [];

        for ($day = 0; $day <= 6; $day++) {
            if (in_array($day, $excludedDays)) {
                continue;
            }

            $availability = new Availability();
            $availability->setPrestataire($prestataire);
            $availability->setDayOfWeek($day);
            $availability->setStartTime(\DateTimeImmutable::createFromFormat('H:i', $startTime));
            $availability->setEndTime(\DateTimeImmutable::createFromFormat('H:i', $endTime));
            $availability->setIsRecurring(true);
            $availability->setIsActive(true);

            $this->getEntityManager()->persist($availability);
            $availabilities[] = $availability;
        }

        $this->getEntityManager()->flush();

        return $availabilities;
    }

    /**
     * Clone les disponibilités d'un prestataire vers un autre
     */
    public function cloneAvailabilities(
        Prestataire $sourcePrestataire,
        Prestataire $targetPrestataire
    ): array {
        $sourceAvailabilities = $this->findByPrestataire($sourcePrestataire);
        $clonedAvailabilities = [];

        foreach ($sourceAvailabilities as $source) {
            $availability = new Availability();
            $availability->setPrestataire($targetPrestataire);
            $availability->setDayOfWeek($source->getDayOfWeek());
            $availability->setStartTime($source->getStartTime());
            $availability->setEndTime($source->getEndTime());
            $availability->setIsRecurring($source->isRecurring());
            $availability->setSpecificDate($source->getSpecificDate());
            $availability->setIsActive(true);

            $this->getEntityManager()->persist($availability);
            $clonedAvailabilities[] = $availability;
        }

        $this->getEntityManager()->flush();

        return $clonedAvailabilities;
    }

    /**
     * Désactive toutes les disponibilités d'un prestataire
     */
    public function deactivateAll(Prestataire $prestataire): int
    {
        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.isActive', 'false')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->execute();
    }

    /**
     * Active toutes les disponibilités d'un prestataire
     */
    public function activateAll(Prestataire $prestataire): int
    {
        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.isActive', 'true')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime toutes les disponibilités d'un prestataire
     */
    public function deleteAll(Prestataire $prestataire): int
    {
        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les disponibilités ponctuelles passées
     */
    public function deleteOldSpecificAvailabilities(int $days = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('a')
            ->delete()
            ->where('a.isRecurring = false')
            ->andWhere('a.specificDate IS NOT NULL')
            ->andWhere('a.specificDate < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche avancée de disponibilités avec multiples critères
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('a');

        // Filtre par prestataire
        if (isset($criteria['prestataire_id'])) {
            $qb->andWhere('a.prestataire = :prestataireId')
               ->setParameter('prestataireId', $criteria['prestataire_id']);
        }

        // Filtre par jour de la semaine
        if (isset($criteria['day_of_week'])) {
            $qb->andWhere('a.dayOfWeek = :dayOfWeek')
               ->setParameter('dayOfWeek', $criteria['day_of_week']);
        }

        // Filtre par jours multiples
        if (isset($criteria['days_of_week']) && is_array($criteria['days_of_week'])) {
            $qb->andWhere('a.dayOfWeek IN (:daysOfWeek)')
               ->setParameter('daysOfWeek', $criteria['days_of_week']);
        }

        // Filtre récurrent/ponctuel
        if (isset($criteria['is_recurring'])) {
            $qb->andWhere('a.isRecurring = :isRecurring')
               ->setParameter('isRecurring', $criteria['is_recurring']);
        }

        // Filtre actif/inactif
        if (isset($criteria['is_active'])) {
            $qb->andWhere('a.isActive = :isActive')
               ->setParameter('isActive', $criteria['is_active']);
        }

        // Filtre par heure de début
        if (isset($criteria['start_time_from'])) {
            $qb->andWhere('a.startTime >= :startTimeFrom')
               ->setParameter('startTimeFrom', $criteria['start_time_from']);
        }

        if (isset($criteria['start_time_to'])) {
            $qb->andWhere('a.startTime <= :startTimeTo')
               ->setParameter('startTimeTo', $criteria['start_time_to']);
        }

        // Filtre par heure de fin
        if (isset($criteria['end_time_from'])) {
            $qb->andWhere('a.endTime >= :endTimeFrom')
               ->setParameter('endTimeFrom', $criteria['end_time_from']);
        }

        if (isset($criteria['end_time_to'])) {
            $qb->andWhere('a.endTime <= :endTimeTo')
               ->setParameter('endTimeTo', $criteria['end_time_to']);
        }

        // Filtre par date spécifique
        if (isset($criteria['specific_date'])) {
            $qb->andWhere('a.specificDate = :specificDate')
               ->setParameter('specificDate', $criteria['specific_date']);
        }

        // Tri
        $orderBy = $criteria['order_by'] ?? 'dayOfWeek';
        $orderDirection = $criteria['order_direction'] ?? 'ASC';
        
        $qb->orderBy('a.' . $orderBy, $orderDirection);

        // Tri secondaire
        if ($orderBy !== 'startTime') {
            $qb->addOrderBy('a.startTime', 'ASC');
        }

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
    // QUERY BUILDER PERSONNALISÉ
    // ============================================

    /**
     * Crée un QueryBuilder de base avec les jointures courantes
     */
    public function createBaseQueryBuilder(string $alias = 'a'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias . '.prestataire', 'p')
            ->leftJoin($alias . '.generatedSlots', 's');
    }
}