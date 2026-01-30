<?php

namespace App\Repository\Planning;

use App\Entity\Planning\AvailableSlot;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité AvailableSlot
 */
class AvailableSlotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvailableSlot::class);
    }

    /**
     * Trouve tous les créneaux d'un prestataire pour une date donnée
     */
    public function findByPrestataireAndDate(Prestataire $prestataire, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('slot')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date = :date')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->orderBy('slot.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les créneaux prioritaires d'un prestataire pour une date donnée
     */
    public function findPrioritySlotsByPrestataireAndDate(
        Prestataire $prestataire, 
        \DateTimeInterface $date
    ): array {
        return $this->createQueryBuilder('slot')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date = :date')
            ->andWhere('slot.isPriority = true')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les créneaux disponibles d'un prestataire pour une période
     */
    public function findAvailableSlots(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('slot')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date BETWEEN :startDate AND :endDate')
            ->andWhere('slot.isBooked = false')
            ->andWhere('slot.isBlocked = false')
            ->andWhere('slot.bookedCount < slot.capacity')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('slot.date', 'ASC')
            ->addOrderBy('slot.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les créneaux qui chevauchent une période donnée
     */
    public function findOverlappingSlots(
        Prestataire $prestataire,
        \DateTimeInterface $date,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeSlotId = null
    ): array {
        $qb = $this->createQueryBuilder('slot')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date = :date')
            ->andWhere('slot.startTime < :endTime')
            ->andWhere('slot.endTime > :startTime')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('startTime', $startTime->format('H:i:s'))
            ->setParameter('endTime', $endTime->format('H:i:s'));

        if ($excludeSlotId) {
            $qb->andWhere('slot.id != :excludeId')
                ->setParameter('excludeId', $excludeSlotId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les créneaux disponibles pour une durée spécifique
     */
    public function findAvailableSlotsForDuration(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $requiredDuration
    ): array {
        return $this->createQueryBuilder('slot')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date BETWEEN :startDate AND :endDate')
            ->andWhere('slot.duration >= :duration')
            ->andWhere('slot.isBooked = false')
            ->andWhere('slot.isBlocked = false')
            ->andWhere('slot.bookedCount < slot.capacity')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->setParameter('duration', $requiredDuration)
            ->orderBy('slot.date', 'ASC')
            ->addOrderBy('slot.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les créneaux disponibles dans une zone géographique
     */
    public function findAvailableSlotsNearLocation(
        float $latitude,
        float $longitude,
        float $radiusKm,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        // Calcul approximatif des limites de la zone
        // 1 degré de latitude ≈ 111 km
        // 1 degré de longitude ≈ 111 km * cos(latitude)
        $latDelta = $radiusKm / 111;
        $lonDelta = $radiusKm / (111 * cos(deg2rad($latitude)));

        $minLat = $latitude - $latDelta;
        $maxLat = $latitude + $latDelta;
        $minLon = $longitude - $lonDelta;
        $maxLon = $longitude + $lonDelta;

        return $this->createQueryBuilder('slot')
            ->where('slot.date BETWEEN :startDate AND :endDate')
            ->andWhere('slot.latitude BETWEEN :minLat AND :maxLat')
            ->andWhere('slot.longitude BETWEEN :minLon AND :maxLon')
            ->andWhere('slot.isBooked = false')
            ->andWhere('slot.isBlocked = false')
            ->andWhere('slot.bookedCount < slot.capacity')
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->setParameter('minLat', $minLat)
            ->setParameter('maxLat', $maxLat)
            ->setParameter('minLon', $minLon)
            ->setParameter('maxLon', $maxLon)
            ->orderBy('slot.date', 'ASC')
            ->addOrderBy('slot.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre de créneaux d'un prestataire pour une période
     */
    public function countSlotsByPrestataireAndPeriod(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): int {
        return (int) $this->createQueryBuilder('slot')
            ->select('COUNT(slot.id)')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date BETWEEN :startDate AND :endDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les créneaux expirés qui doivent être supprimés
     */
    public function findExpiredSlots(): array
    {
        $now = new \DateTime();

        return $this->createQueryBuilder('slot')
            ->where('slot.expiresAt IS NOT NULL')
            ->andWhere('slot.expiresAt < :now')
            ->andWhere('slot.isBooked = false')
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les créneaux dans le passé qui n'ont pas été réservés
     */
    public function findPastUnbookedSlots(\DateTimeInterface $beforeDate = null): array
    {
        if (!$beforeDate) {
            $beforeDate = new \DateTime('-1 week');
        }

        return $this->createQueryBuilder('slot')
            ->where('slot.date < :beforeDate')
            ->andWhere('slot.isBooked = false')
            ->setParameter('beforeDate', $beforeDate->format('Y-m-d'))
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les créneaux d'un prestataire pour une catégorie de service
     */
    public function findByPrestataireAndServiceType(
        Prestataire $prestataire,
        string $serviceType,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('slot')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.serviceType = :serviceType')
            ->andWhere('slot.date BETWEEN :startDate AND :endDate')
            ->andWhere('slot.isBooked = false')
            ->andWhere('slot.isBlocked = false')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('serviceType', $serviceType)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('slot.date', 'ASC')
            ->addOrderBy('slot.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les créneaux bloqués d'un prestataire
     */
    public function findBlockedSlots(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('slot')
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date BETWEEN :startDate AND :endDate')
            ->andWhere('slot.isBlocked = true')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->orderBy('slot.date', 'ASC')
            ->addOrderBy('slot.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour le statut de tous les créneaux d'une disponibilité source
     */
    public function updateSlotsFromAvailability(int $availabilityId, array $data): int
    {
        $qb = $this->createQueryBuilder('slot')
            ->update()
            ->where('slot.sourceAvailability = :availabilityId')
            ->setParameter('availabilityId', $availabilityId);

        foreach ($data as $field => $value) {
            $qb->set('slot.' . $field, ':' . $field)
                ->setParameter($field, $value);
        }

        return $qb->getQuery()->execute();
    }

    /**
     * Supprime les créneaux générés automatiquement pour une disponibilité
     */
    public function deleteAutomaticSlotsByAvailability(int $availabilityId): int
    {
        return $this->createQueryBuilder('slot')
            ->delete()
            ->where('slot.sourceAvailability = :availabilityId')
            ->andWhere('slot.isBooked = false')
            ->andWhere('slot.isManual = false')
            ->setParameter('availabilityId', $availabilityId)
            ->getQuery()
            ->execute();
    }

    /**
     * Calcule les statistiques des créneaux pour un prestataire
     */
    public function getSlotStatistics(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $result = $this->createQueryBuilder('slot')
            ->select(
                'COUNT(slot.id) as totalSlots',
                'SUM(CASE WHEN slot.isBooked = true THEN 1 ELSE 0 END) as bookedSlots',
                'SUM(CASE WHEN slot.isBlocked = true THEN 1 ELSE 0 END) as blockedSlots',
                'SUM(CASE WHEN slot.isBooked = false AND slot.isBlocked = false THEN 1 ELSE 0 END) as availableSlots',
                'AVG(slot.duration) as avgDuration',
                'SUM(slot.duration) as totalDuration'
            )
            ->where('slot.prestataire = :prestataire')
            ->andWhere('slot.date BETWEEN :startDate AND :endDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate->format('Y-m-d'))
            ->setParameter('endDate', $endDate->format('Y-m-d'))
            ->getQuery()
            ->getSingleResult();

        return [
            'totalSlots' => (int) $result['totalSlots'],
            'bookedSlots' => (int) $result['bookedSlots'],
            'blockedSlots' => (int) $result['blockedSlots'],
            'availableSlots' => (int) $result['availableSlots'],
            'avgDuration' => round((float) $result['avgDuration'], 2),
            'totalDuration' => (int) $result['totalDuration'],
            'bookingRate' => $result['totalSlots'] > 0 
                ? round(($result['bookedSlots'] / $result['totalSlots']) * 100, 2) 
                : 0,
        ];
    }

    public function save(AvailableSlot $slot, bool $flush = false): void
    {
        $this->getEntityManager()->persist($slot);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(AvailableSlot $slot, bool $flush = false): void
    {
        $this->getEntityManager()->remove($slot);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}