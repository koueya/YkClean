<?php
// src/Repository/AvailabilityRepository.php

namespace App\Repository;

use App\Entity\Availability;
use App\Entity\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Availability>
 */
class AvailabilityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Availability::class);
    }

    /**
     * Trouve toutes les disponibilités d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les disponibilités d'un prestataire pour un jour spécifique
     */
    public function findByPrestataireAndDay(Prestataire $prestataire, int $dayOfWeek): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

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
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.startTime <= :time')
            ->andWhere('a.endTime > :time')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('time', $timeString)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Trouve les créneaux disponibles pour un jour donné
     */
    public function findAvailableSlots(
        Prestataire $prestataire,
        int $dayOfWeek,
        int $slotDuration = 60 // en minutes
    ): array {
        $availabilities = $this->findByPrestataireAndDay($prestataire, $dayOfWeek);
        $slots = [];

        foreach ($availabilities as $availability) {
            $currentTime = clone $availability->getStartTime();
            $endTime = $availability->getEndTime();

            while ($currentTime < $endTime) {
                $slotEnd = (clone $currentTime)->modify("+{$slotDuration} minutes");
                
                if ($slotEnd <= $endTime) {
                    $slots[] = [
                        'start' => clone $currentTime,
                        'end' => clone $slotEnd,
                        'duration' => $slotDuration
                    ];
                }

                $currentTime->modify("+{$slotDuration} minutes");
            }
        }

        return $slots;
    }

    /**
     * Trouve les chevauchements de disponibilités pour un prestataire
     */
    public function findOverlapping(
        Prestataire $prestataire,
        int $dayOfWeek,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.startTime < :endTime')
            ->andWhere('a.endTime > :startTime')
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

    /**
     * Compte les heures disponibles par semaine pour un prestataire
     */
    public function countWeeklyHours(Prestataire $prestataire): float
    {
        $availabilities = $this->findByPrestataire($prestataire);
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
     * Trouve les disponibilités par jour de la semaine
     */
    public function findByDayOfWeek(int $dayOfWeek): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des disponibilités
     */
    public function getStatistics(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $availabilities = $qb->getQuery()->getResult();

        $stats = [
            'total_slots' => count($availabilities),
            'total_hours' => 0,
            'by_day' => array_fill(1, 7, ['count' => 0, 'hours' => 0]),
            'earliest_start' => null,
            'latest_end' => null,
        ];

        foreach ($availabilities as $availability) {
            $dayOfWeek = $availability->getDayOfWeek();
            $start = $availability->getStartTime();
            $end = $availability->getEndTime();
            
            $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
            
            $stats['total_hours'] += $hours;
            $stats['by_day'][$dayOfWeek]['count']++;
            $stats['by_day'][$dayOfWeek]['hours'] += $hours;

            // Heure la plus tôt
            if (!$stats['earliest_start'] || $start < $stats['earliest_start']) {
                $stats['earliest_start'] = $start;
            }

            // Heure la plus tard
            if (!$stats['latest_end'] || $end > $stats['latest_end']) {
                $stats['latest_end'] = $end;
            }
        }

        return $stats;
    }

    /**
     * Répartition des disponibilités par jour de la semaine
     */
    public function getDayDistribution(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->select('a.dayOfWeek, COUNT(a.id) as count')
            ->groupBy('a.dayOfWeek')
            ->orderBy('a.dayOfWeek', 'ASC');

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Heures de travail moyennes par jour de la semaine
     */
    public function getAverageHoursByDay(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $availabilities = $qb->getQuery()->getResult();

        $dayStats = array_fill(1, 7, ['total_hours' => 0, 'count' => 0]);

        foreach ($availabilities as $availability) {
            $dayOfWeek = $availability->getDayOfWeek();
            $start = $availability->getStartTime();
            $end = $availability->getEndTime();
            
            $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
            
            $dayStats[$dayOfWeek]['total_hours'] += $hours;
            $dayStats[$dayOfWeek]['count']++;
        }

        $result = [];
        foreach ($dayStats as $day => $stats) {
            $result[$day] = [
                'day' => $day,
                'day_name' => $this->getDayName($day),
                'average_hours' => $stats['count'] > 0 
                    ? round($stats['total_hours'] / $stats['count'], 2) 
                    : 0
            ];
        }

        return $result;
    }

    /**
     * Trouve les prestataires disponibles à un moment donné
     */
    public function findAvailablePrestataires(
        int $dayOfWeek,
        \DateTimeInterface $time
    ): array {
        $timeString = $time->format('H:i:s');

        return $this->createQueryBuilder('a')
            ->select('DISTINCT p')
            ->innerJoin('a.prestataire', 'p')
            ->andWhere('a.dayOfWeek = :dayOfWeek')
            ->andWhere('a.startTime <= :time')
            ->andWhere('a.endTime > :time')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('dayOfWeek', $dayOfWeek)
            ->setParameter('time', $timeString)
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires avec le plus de disponibilités
     */
    public function findMostAvailablePrestataires(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->select('p.id, p.firstName, p.lastName, COUNT(a.id) as availability_count')
            ->innerJoin('a.prestataire', 'p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->groupBy('p.id')
            ->orderBy('availability_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prestataires sans disponibilités définies
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
            ->getRepository('App\Entity\Prestataire')
            ->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $prestataireIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Crée un planning type de disponibilités
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
            $availability->setStartTime(\DateTime::createFromFormat('H:i', $startTime));
            $availability->setEndTime(\DateTime::createFromFormat('H:i', $endTime));

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

            $this->getEntityManager()->persist($availability);
            $clonedAvailabilities[] = $availability;
        }

        $this->getEntityManager()->flush();

        return $clonedAvailabilities;
    }

    /**
     * Exporte les disponibilités en CSV
     */
    public function exportToCsv(array $availabilities): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'Prestataire',
            'Jour',
            'Heure Début',
            'Heure Fin',
            'Durée (heures)'
        ]);

        // Données
        foreach ($availabilities as $availability) {
            $duration = ($availability->getEndTime()->getTimestamp() - 
                        $availability->getStartTime()->getTimestamp()) / 3600;

            fputcsv($handle, [
                $availability->getPrestataire()->getFullName(),
                $this->getDayName($availability->getDayOfWeek()),
                $availability->getStartTime()->format('H:i'),
                $availability->getEndTime()->format('H:i'),
                round($duration, 2)
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Retourne le nom du jour
     */
    private function getDayName(int $dayOfWeek): string
    {
        return match($dayOfWeek) {
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi',
            default => 'Inconnu',
        };
    }

    /**
     * Trouve les disponibilités avec des horaires inhabituels (très tôt ou très tard)
     */
    public function findUnusualHours(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.startTime < :earlyTime OR a.endTime > :lateTime')
            ->setParameter('earlyTime', '07:00:00')
            ->setParameter('lateTime', '20:00:00')
            ->orderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les disponibilités courtes (moins de X heures)
     */
    public function findShortAvailabilities(float $maxHours = 2): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT *
            FROM availabilities
            WHERE TIMESTAMPDIFF(HOUR, start_time, end_time) < :maxHours
            ORDER BY TIMESTAMPDIFF(HOUR, start_time, end_time) ASC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['maxHours' => $maxHours]);

        $availabilityIds = array_column($result->fetchAllAssociative(), 'id');

        if (empty($availabilityIds)) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $availabilityIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les disponibilités longues (plus de X heures)
     */
    public function findLongAvailabilities(float $minHours = 8): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT *
            FROM availabilities
            WHERE TIMESTAMPDIFF(HOUR, start_time, end_time) > :minHours
            ORDER BY TIMESTAMPDIFF(HOUR, start_time, end_time) DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['minHours' => $minHours]);

        $availabilityIds = array_column($result->fetchAllAssociative(), 'id');

        if (empty($availabilityIds)) {
            return [];
        }

        return $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $availabilityIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Optimise les disponibilités en fusionnant les créneaux consécutifs
     */
    public function optimizeAvailabilities(Prestataire $prestataire): int
    {
        $optimized = 0;

        for ($day = 0; $day <= 6; $day++) {
            $dayAvailabilities = $this->findByPrestataireAndDay($prestataire, $day);

            if (count($dayAvailabilities) < 2) {
                continue;
            }

            // Trier par heure de début
            usort($dayAvailabilities, fn($a, $b) => 
                $a->getStartTime() <=> $b->getStartTime()
            );

            $toRemove = [];

            for ($i = 0; $i < count($dayAvailabilities) - 1; $i++) {
                $current = $dayAvailabilities[$i];
                $next = $dayAvailabilities[$i + 1];

                // Si les créneaux se touchent ou se chevauchent
                if ($current->getEndTime() >= $next->getStartTime()) {
                    // Étendre le créneau actuel
                    if ($next->getEndTime() > $current->getEndTime()) {
                        $current->setEndTime($next->getEndTime());
                    }

                    // Marquer le suivant pour suppression
                    $toRemove[] = $next;
                    $optimized++;
                }
            }

            // Supprimer les disponibilités fusionnées
            foreach ($toRemove as $availability) {
                $this->getEntityManager()->remove($availability);
            }
        }

        if ($optimized > 0) {
            $this->getEntityManager()->flush();
        }

        return $optimized;
    }

    /**
     * Vérifie la cohérence des disponibilités (pas de chevauchements)
     */
    public function validateConsistency(Prestataire $prestataire): array
    {
        $errors = [];

        for ($day = 0; $day <= 6; $day++) {
            $dayAvailabilities = $this->findByPrestataireAndDay($prestataire, $day);

            for ($i = 0; $i < count($dayAvailabilities); $i++) {
                $current = $dayAvailabilities[$i];

                // Vérifier que l'heure de fin est après l'heure de début
                if ($current->getStartTime() >= $current->getEndTime()) {
                    $errors[] = [
                        'type' => 'invalid_time_range',
                        'availability' => $current,
                        'message' => 'L\'heure de fin doit être après l\'heure de début'
                    ];
                }

                // Vérifier les chevauchements
                for ($j = $i + 1; $j < count($dayAvailabilities); $j++) {
                    $other = $dayAvailabilities[$j];

                    if ($current->getStartTime() < $other->getEndTime() && 
                        $current->getEndTime() > $other->getStartTime()) {
                        $errors[] = [
                            'type' => 'overlap',
                            'availability1' => $current,
                            'availability2' => $other,
                            'message' => 'Chevauchement de disponibilités'
                        ];
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Génère un calendrier visuel des disponibilités
     */
    public function generateWeeklyCalendar(Prestataire $prestataire): array
    {
        $calendar = [];

        for ($day = 0; $day <= 6; $day++) {
            $availabilities = $this->findByPrestataireAndDay($prestataire, $day);
            
            $calendar[$day] = [
                'day' => $day,
                'day_name' => $this->getDayName($day),
                'availabilities' => $availabilities,
                'total_hours' => 0
            ];

            foreach ($availabilities as $availability) {
                $hours = ($availability->getEndTime()->getTimestamp() - 
                         $availability->getStartTime()->getTimestamp()) / 3600;
                $calendar[$day]['total_hours'] += $hours;
            }

            $calendar[$day]['total_hours'] = round($calendar[$day]['total_hours'], 2);
        }

        return $calendar;
    }

    /**
     * Trouve les trous dans le planning (périodes sans disponibilité)
     */
    public function findGapsInSchedule(
        Prestataire $prestataire,
        string $workStart = '08:00',
        string $workEnd = '19:00'
    ): array {
        $gaps = [];
        $workStartTime = \DateTime::createFromFormat('H:i', $workStart);
        $workEndTime = \DateTime::createFromFormat('H:i', $workEnd);

        for ($day = 1; $day <= 5; $day++) { // Lundi à Vendredi
            $availabilities = $this->findByPrestataireAndDay($prestataire, $day);

            if (empty($availabilities)) {
                $gaps[] = [
                    'day' => $day,
                    'day_name' => $this->getDayName($day),
                    'start' => $workStartTime,
                    'end' => $workEndTime,
                    'duration' => ($workEndTime->getTimestamp() - $workStartTime->getTimestamp()) / 3600
                ];
                continue;
            }

            // Trier par heure de début
            usort($availabilities, fn($a, $b) => 
                $a->getStartTime() <=> $b->getStartTime()
            );

            // Trou avant la première disponibilité
            if ($availabilities[0]->getStartTime() > $workStartTime) {
                $gaps[] = [
                    'day' => $day,
                    'day_name' => $this->getDayName($day),
                    'start' => $workStartTime,
                    'end' => $availabilities[0]->getStartTime(),
                    'duration' => ($availabilities[0]->getStartTime()->getTimestamp() - 
                                  $workStartTime->getTimestamp()) / 3600
                ];
            }

            // Trous entre les disponibilités
            for ($i = 0; $i < count($availabilities) - 1; $i++) {
                $current = $availabilities[$i];
                $next = $availabilities[$i + 1];

                if ($current->getEndTime() < $next->getStartTime()) {
                    $gaps[] = [
                        'day' => $day,
                        'day_name' => $this->getDayName($day),
                        'start' => $current->getEndTime(),
                        'end' => $next->getStartTime(),
                        'duration' => ($next->getStartTime()->getTimestamp() - 
                                      $current->getEndTime()->getTimestamp()) / 3600
                    ];
                }
            }

            // Trou après la dernière disponibilité
            $lastAvailability = end($availabilities);
            if ($lastAvailability->getEndTime() < $workEndTime) {
                $gaps[] = [
                    'day' => $day,
                    'day_name' => $this->getDayName($day),
                    'start' => $lastAvailability->getEndTime(),
                    'end' => $workEndTime,
                    'duration' => ($workEndTime->getTimestamp() - 
                                  $lastAvailability->getEndTime()->getTimestamp()) / 3600
                ];
            }
        }

        return $gaps;
    }

    /**
     * Taux de couverture hebdomadaire
     */
    public function getWeeklyCoverageRate(
        Prestataire $prestataire,
        float $standardWeeklyHours = 40
    ): float {
        $actualHours = $this->countWeeklyHours($prestataire);
        return round(($actualHours / $standardWeeklyHours) * 100, 2);
    }
}