<?php
// src/Repository/AbsenceRepository.php

namespace App\Repository\Planning;

use App\Entity\Planning\Absence;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /**
     * Trouve toutes les absences d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences actives (en cours)
     */
    public function findActiveByPrestataire(Prestataire $prestataire): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :today')
            ->andWhere('a.endDate >= :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences futures
     */
    public function findFutureByPrestataire(Prestataire $prestataire): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.startDate > :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences passées
     */
    public function findPastByPrestataire(Prestataire $prestataire): array
    {
        $today = new \DateTime();
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.endDate < :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences entre deux dates
     */
    public function findBetweenDates(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :endDate')
            ->andWhere('a.endDate >= :startDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un prestataire est absent à une date donnée
     */
    public function isAbsentOnDate(
        Prestataire $prestataire,
        \DateTimeInterface $date
    ): bool {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :date')
            ->andWhere('a.endDate >= :date')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Trouve les absences qui chevauchent une période
     */
    public function findOverlapping(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :endDate')
            ->andWhere('a.endDate >= :startDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.startDate', 'ASC');

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les absences par raison
     */
    public function findByReason(
        Prestataire $prestataire,
        string $reason
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.reason LIKE :reason')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('reason', '%' . $reason . '%')
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le nombre total de jours d'absence
     */
    public function countTotalDays(
        Prestataire $prestataire,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): int {
        $absences = $this->findBetweenDates(
            $prestataire,
            $startDate ?? new \DateTime('-1 year'),
            $endDate ?? new \DateTime()
        );

        $totalDays = 0;
        foreach ($absences as $absence) {
            $totalDays += $absence->getDuration();
        }

        return $totalDays;
    }

    /**
     * Trouve les absences les plus longues
     */
    public function findLongest(
        Prestataire $prestataire,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('DATEDIFF(a.endDate, a.startDate)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences récentes
     */
    public function findRecent(
        Prestataire $prestataire,
        int $days = 30,
        int $limit = 10
    ): array {
        $sinceDate = (new \DateTime())->modify("-{$days} days");
        
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.createdAt >= :sinceDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('sinceDate', $sinceDate)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des absences
     */
    public function getStatistics(
        Prestataire $prestataire,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $startDate = $startDate ?? (new \DateTime())->modify('-1 year');
        $endDate = $endDate ?? new \DateTime();

        $absences = $this->findBetweenDates($prestataire, $startDate, $endDate);

        $totalDays = 0;
        $reasonCounts = [];
        $longestAbsence = 0;

        foreach ($absences as $absence) {
            $duration = $absence->getDuration();
            $totalDays += $duration;
            
            if ($duration > $longestAbsence) {
                $longestAbsence = $duration;
            }

            $reason = $absence->getReason();
            if (!isset($reasonCounts[$reason])) {
                $reasonCounts[$reason] = 0;
            }
            $reasonCounts[$reason]++;
        }

        arsort($reasonCounts);

        return [
            'total_absences' => count($absences),
            'total_days' => $totalDays,
            'average_duration' => count($absences) > 0 ? round($totalDays / count($absences), 2) : 0,
            'longest_absence' => $longestAbsence,
            'reasons' => $reasonCounts,
            'active_absences' => count($this->findActiveByPrestataire($prestataire)),
            'future_absences' => count($this->findFutureByPrestataire($prestataire))
        ];
    }

    /**
     * Trouve les absences par mois
     */
    public function findByMonth(
        Prestataire $prestataire,
        int $year,
        int $month
    ): array {
        $startDate = new \DateTime("{$year}-{$month}-01");
        $endDate = (clone $startDate)->modify('last day of this month');

        return $this->findBetweenDates($prestataire, $startDate, $endDate);
    }

    /**
     * Trouve les absences par année
     */
    public function findByYear(
        Prestataire $prestataire,
        int $year
    ): array {
        $startDate = new \DateTime("{$year}-01-01");
        $endDate = new \DateTime("{$year}-12-31");

        return $this->findBetweenDates($prestataire, $startDate, $endDate);
    }

    /**
     * Répartition des absences par mois (pour graphique)
     */
    public function getMonthlyDistribution(
        Prestataire $prestataire,
        int $year
    ): array {
        $absences = $this->findByYear($prestataire, $year);

        $distribution = array_fill(1, 12, 0);

        foreach ($absences as $absence) {
            $startMonth = (int)$absence->getStartDate()->format('n');
            $endMonth = (int)$absence->getEndDate()->format('n');

            if ($startMonth === $endMonth) {
                $distribution[$startMonth] += $absence->getDuration();
            } else {
                // Répartir sur plusieurs mois
                for ($m = $startMonth; $m <= $endMonth; $m++) {
                    $monthStart = new \DateTime("{$year}-{$m}-01");
                    $monthEnd = (clone $monthStart)->modify('last day of this month');

                    $periodStart = max($absence->getStartDate(), $monthStart);
                    $periodEnd = min($absence->getEndDate(), $monthEnd);

                    $days = $periodStart->diff($periodEnd)->days + 1;
                    $distribution[$m] += $days;
                }
            }
        }

        return $distribution;
    }

    /**
     * Trouve les raisons d'absence les plus fréquentes
     */
    public function getTopReasons(
        Prestataire $prestataire,
        int $limit = 5
    ): array {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                reason,
                COUNT(*) as count,
                SUM(DATEDIFF(end_date, start_date) + 1) as total_days
            FROM absences
            WHERE prestataire_id = :prestataireId
            GROUP BY reason
            ORDER BY count DESC
            LIMIT :limit
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'prestataireId' => $prestataire->getId(),
            'limit' => $limit
        ]);

        return $result->fetchAllAssociative();
    }

    /**
     * Vérifie si une période d'absence est valide (pas de chevauchement)
     */
    public function validateAbsencePeriod(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?int $excludeId = null
    ): array {
        $overlapping = $this->findOverlapping($prestataire, $startDate, $endDate, $excludeId);

        return [
            'is_valid' => empty($overlapping),
            'conflicts' => $overlapping,
            'conflict_count' => count($overlapping)
        ];
    }

    /**
     * Trouve les absences qui affectent des réservations
     */
    public function findAffectingBookings(Prestataire $prestataire): array
    {
        $today = new \DateTime();
        
        // Absences futures et en cours
        $absences = $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.endDate >= :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->getQuery()
            ->getResult();

        $bookingRepository = $this->getEntityManager()->getRepository(\App\Entity\Booking::class);
        $absencesWithBookings = [];

        foreach ($absences as $absence) {
            $affectedBookings = $bookingRepository->findBetweenDates(
                $absence->getStartDate(),
                $absence->getEndDate(),
                $prestataire
            );

            if (!empty($affectedBookings)) {
                $absencesWithBookings[] = [
                    'absence' => $absence,
                    'affected_bookings' => $affectedBookings,
                    'bookings_count' => count($affectedBookings)
                ];
            }
        }

        return $absencesWithBookings;
    }

    /**
     * Calcule le taux d'absence
     */
    public function calculateAbsenceRate(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): float {
        $totalDays = $startDate->diff($endDate)->days + 1;
        $absenceDays = $this->countTotalDays($prestataire, $startDate, $endDate);

        if ($totalDays === 0) {
            return 0;
        }

        return round(($absenceDays / $totalDays) * 100, 2);
    }

    /**
     * Trouve les absences sans notes
     */
    public function findWithoutNotes(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.notes IS NULL OR a.notes = :empty')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('empty', '')
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche d'absences
     */
    public function search(
        Prestataire $prestataire,
        array $criteria
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if (isset($criteria['reason'])) {
            $qb->andWhere('a.reason LIKE :reason')
               ->setParameter('reason', '%' . $criteria['reason'] . '%');
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('a.startDate >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('a.endDate <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['min_duration'])) {
            $qb->andWhere('DATEDIFF(a.endDate, a.startDate) + 1 >= :minDuration')
               ->setParameter('minDuration', $criteria['min_duration']);
        }

        if (isset($criteria['max_duration'])) {
            $qb->andWhere('DATEDIFF(a.endDate, a.startDate) + 1 <= :maxDuration')
               ->setParameter('maxDuration', $criteria['max_duration']);
        }

        return $qb->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Exporte les absences en CSV
     */
    public function exportToCsv(array $absences): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'Date début',
            'Date fin',
            'Durée (jours)',
            'Raison',
            'Notes',
            'Créé le'
        ]);

        // Données
        foreach ($absences as $absence) {
            fputcsv($handle, [
                $absence->getStartDate()->format('d/m/Y'),
                $absence->getEndDate()->format('d/m/Y'),
                $absence->getDuration(),
                $absence->getReason(),
                $absence->getNotes() ?? '',
                $absence->getCreatedAt()->format('d/m/Y H:i')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Trouve les périodes sans absence
     */
    public function findPeriodsWithoutAbsence(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $absences = $this->findBetweenDates($prestataire, $startDate, $endDate);

        if (empty($absences)) {
            return [[
                'start' => $startDate,
                'end' => $endDate,
                'duration' => $startDate->diff($endDate)->days + 1
            ]];
        }

        usort($absences, fn($a, $b) => $a->getStartDate() <=> $b->getStartDate());

        $periods = [];
        $currentDate = clone $startDate;

        foreach ($absences as $absence) {
            if ($absence->getStartDate() > $currentDate) {
                $periodEnd = (clone $absence->getStartDate())->modify('-1 day');
                $periods[] = [
                    'start' => clone $currentDate,
                    'end' => $periodEnd,
                    'duration' => $currentDate->diff($periodEnd)->days + 1
                ];
            }

            $nextDay = (clone $absence->getEndDate())->modify('+1 day');
            if ($nextDay > $currentDate) {
                $currentDate = $nextDay;
            }
        }

        // Période après la dernière absence
        if ($currentDate <= $endDate) {
            $periods[] = [
                'start' => clone $currentDate,
                'end' => clone $endDate,
                'duration' => $currentDate->diff($endDate)->days + 1
            ];
        }

        return $periods;
    }

    /**
     * Compte les absences par prestataire (pour admin)
     */
    public function countByPrestataires(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->select('p.id, p.firstName, p.lastName, COUNT(a.id) as absence_count, SUM(DATEDIFF(a.endDate, a.startDate) + 1) as total_days')
            ->innerJoin('a.prestataire', 'p')
            ->groupBy('p.id');

        if ($startDate) {
            $qb->andWhere('a.startDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('a.endDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->orderBy('total_days', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences se terminant bientôt
     */
    public function findEndingSoon(
        Prestataire $prestataire,
        int $days = 7
    ): array {
        $today = new \DateTime();
        $futureDate = (clone $today)->modify("+{$days} days");

        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.endDate >= :today')
            ->andWhere('a.endDate <= :futureDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->setParameter('futureDate', $futureDate)
            ->orderBy('a.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences commençant bientôt
     */
    public function findStartingSoon(
        Prestataire $prestataire,
        int $days = 7
    ): array {
        $today = new \DateTime();
        $futureDate = (clone $today)->modify("+{$days} days");

        return $this->createQueryBuilder('a')
            ->andWhere('a.prestataire = :prestataire')
            ->andWhere('a.startDate > :today')
            ->andWhere('a.startDate <= :futureDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->setParameter('futureDate', $futureDate)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}