<?php

namespace App\Service\Planning;

use App\Entity\Planning\Availability;
use App\Entity\Planning\Absence;
use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Repository\Planning\AvailabilityRepository;
use App\Repository\Planning\AbsenceRepository;
use App\Repository\Booking\BookingRepository;
use App\DTO\CreateAbsenceDTO;
use App\DTO\UpdateAbsenceDTO;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service principal de gestion du planning, des disponibilités et des absences
 * Unifie AvailabilityManager et les fonctionnalités de planning
 */
class AvailabilityService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityRepository $availabilityRepository,
        private AbsenceRepository $absenceRepository,
        private BookingRepository $bookingRepository,
        private AvailabilityManager $availabilityManager,
        private ConflictDetector $conflictDetector,
        private LoggerInterface $logger
    ) {}

    // ============ GESTION DES DISPONIBILITÉS ============

    /**
     * Crée une nouvelle disponibilité
     */
    public function createAvailability(
        Prestataire $prestataire,
        int $dayOfWeek,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        bool $isRecurring = true,
        ?\DateTimeInterface $specificDate = null
    ): Availability {
        return $this->availabilityManager->createAvailability(
            $prestataire,
            $dayOfWeek,
            $startTime,
            $endTime,
            $isRecurring,
            $specificDate
        );
    }

    /**
     * Met à jour une disponibilité existante
     */
    public function updateAvailability(
        Availability $availability,
        \DateTimeInterface $newStartTime,
        \DateTimeInterface $newEndTime
    ): Availability {
        return $this->availabilityManager->updateAvailability(
            $availability,
            $newStartTime,
            $newEndTime
        );
    }

    /**
     * Supprime une disponibilité
     */
    public function deleteAvailability(Availability $availability, bool $force = false): void
    {
        $this->availabilityManager->deleteAvailability($availability, $force);
    }

    /**
     * Vérifie si un prestataire est disponible
     */
    public function isAvailable(
        Prestataire $prestataire,
        \DateTimeInterface $dateTime,
        int $durationMinutes
    ): bool {
        return $this->availabilityManager->isAvailable($prestataire, $dateTime, $durationMinutes);
    }

    /**
     * Récupère les créneaux disponibles
     */
    public function getAvailableSlots(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $slotDuration = 60
    ): array {
        return $this->availabilityManager->getAvailableSlots(
            $prestataire,
            $startDate,
            $endDate,
            $slotDuration
        );
    }

    /**
     * Bloque des dates (vacances, absences)
     */
    public function blockDates(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $reason = ''
    ): array {
        return $this->availabilityManager->blockDates(
            $prestataire,
            $startDate,
            $endDate,
            $reason
        );
    }

    /**
     * Calcule le taux d'occupation
     */
    public function calculateOccupancyRate(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): float {
        return $this->availabilityManager->calculateOccupancyRate(
            $prestataire,
            $startDate,
            $endDate
        );
    }

    // ============ GESTION DES ABSENCES ============

    /**
     * Crée une absence
     */
    public function createAbsence(Prestataire $prestataire, CreateAbsenceDTO $dto): Absence
    {
        $absence = new Absence();
        $absence->setPrestataire($prestataire);
        $absence->setStartDate($dto->getStartDateAsDateTime());
        $absence->setEndDate($dto->getEndDateAsDateTime());
        $absence->setReason($dto->getReason());
        $absence->setDescription($dto->getDescription());
        $absence->setStatus('active');

        $this->entityManager->persist($absence);
        $this->entityManager->flush();

        $this->logger->info('Absence created', [
            'prestataire_id' => $prestataire->getId(),
            'absence_id' => $absence->getId(),
            'start_date' => $dto->getStartDate(),
            'end_date' => $dto->getEndDate(),
        ]);

        return $absence;
    }

    /**
     * Met à jour une absence
     */
    public function updateAbsence(Absence $absence, UpdateAbsenceDTO $dto): Absence
    {
        if ($dto->getStartDate()) {
            $absence->setStartDate(new \DateTime($dto->getStartDate()));
        }

        if ($dto->getEndDate()) {
            $absence->setEndDate(new \DateTime($dto->getEndDate()));
        }

        if ($dto->getReason()) {
            $absence->setReason($dto->getReason());
        }

        if ($dto->getDescription() !== null) {
            $absence->setDescription($dto->getDescription());
        }

        $absence->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Absence updated', [
            'absence_id' => $absence->getId(),
        ]);

        return $absence;
    }

    /**
     * Annule une absence
     */
    public function cancelAbsence(Absence $absence): void
    {
        $absence->setStatus('cancelled');
        $absence->setCancelledAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Absence cancelled', [
            'absence_id' => $absence->getId(),
        ]);
    }

    /**
     * Récupère les absences dans une période
     */
    public function getAbsencesInPeriod(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->absenceRepository->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.status != :cancelled')
            ->andWhere(
                '(a.startDate BETWEEN :startDate AND :endDate) OR 
                 (a.endDate BETWEEN :startDate AND :endDate) OR 
                 (a.startDate <= :startDate AND a.endDate >= :endDate)'
            )
            ->setParameter('prestataire', $prestataire)
            ->setParameter('cancelled', 'cancelled')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============ GESTION DES RÉSERVATIONS DANS LE PLANNING ============

    /**
     * Récupère les réservations dans une période
     */
    public function getBookingsInPeriod(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->bookingRepository->findByPrestataireAndPeriod(
            $prestataire,
            $startDate,
            $endDate
        );
    }

    /**
     * Vérifie si une période est libre
     */
    public function isPeriodFree(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime
    ): bool {
        // Vérifier les absences
        $absences = $this->getAbsencesInPeriod($prestataire, $startDateTime, $endDateTime);
        if (!empty($absences)) {
            return false;
        }

        // Vérifier les réservations
        $bookings = $this->getBookingsInPeriod($prestataire, $startDateTime, $endDateTime);
        if (!empty($bookings)) {
            return false;
        }

        // Vérifier les disponibilités
        return $this->isAvailable(
            $prestataire,
            $startDateTime,
            ($endDateTime->getTimestamp() - $startDateTime->getTimestamp()) / 60
        );
    }

    /**
     * Récupère le planning complet pour une période
     */
    public function getCompleteSchedule(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $schedule = [
            'availabilities' => [],
            'bookings' => [],
            'absences' => [],
            'conflicts' => [],
        ];

        // Disponibilités
        $schedule['availabilities'] = $this->availabilityRepository->findForPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        // Réservations
        $schedule['bookings'] = $this->getBookingsInPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        // Absences
        $schedule['absences'] = $this->getAbsencesInPeriod(
            $prestataire,
            $startDate,
            $endDate
        );

        // Détection de conflits
        $schedule['conflicts'] = $this->conflictDetector->detectAllConflicts(
            $prestataire,
            $startDate,
            $endDate
        );

        return $schedule;
    }

    /**
     * Récupère le planning pour une semaine spécifique
     */
    public function getWeeklySchedule(
        Prestataire $prestataire,
        \DateTimeInterface $weekStart
    ): array {
        $weekEnd = (clone $weekStart)->modify('+6 days');
        return $this->getCompleteSchedule($prestataire, $weekStart, $weekEnd);
    }

    /**
     * Récupère le planning pour un mois spécifique
     */
    public function getMonthlySchedule(
        Prestataire $prestataire,
        int $year,
        int $month
    ): array {
        $startDate = new \DateTime("$year-$month-01");
        $endDate = (clone $startDate)->modify('last day of this month');
        
        return $this->getCompleteSchedule($prestataire, $startDate, $endDate);
    }

    /**
     * Vérifie si une réservation peut être ajoutée sans conflit
     */
    public function canAddBooking(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime,
        ?string $address = null
    ): array {
        $conflicts = $this->conflictDetector->wouldCreateConflict(
            $prestataire,
            $startDateTime,
            $endDateTime,
            $address
        );

        return [
            'can_add' => empty($conflicts),
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Récupère les statistiques du planning
     */
    public function getPlanningStats(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $bookings = $this->getBookingsInPeriod($prestataire, $startDate, $endDate);
        $absences = $this->getAbsencesInPeriod($prestataire, $startDate, $endDate);
        
        $totalBookings = count($bookings);
        $totalAbsenceDays = 0;
        
        foreach ($absences as $absence) {
            $totalAbsenceDays += $absence->getStartDate()->diff($absence->getEndDate())->days + 1;
        }

        $occupancyRate = $this->calculateOccupancyRate($prestataire, $startDate, $endDate);

        return [
            'total_bookings' => $totalBookings,
            'total_absence_days' => $totalAbsenceDays,
            'occupancy_rate' => $occupancyRate,
            'period' => [
                'start' => $startDate->format('Y-m-d'),
                'end' => $endDate->format('Y-m-d'),
            ],
        ];
    }

    /**
     * Optimise le planning en suggérant des améliorations
     */
    public function suggestOptimizations(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $suggestions = [];
        
        // Récupérer les créneaux disponibles non utilisés
        $availableSlots = $this->getAvailableSlots($prestataire, $startDate, $endDate);
        $bookings = $this->getBookingsInPeriod($prestataire, $startDate, $endDate);

        // Calculer le taux d'occupation
        $occupancyRate = $this->calculateOccupancyRate($prestataire, $startDate, $endDate);

        if ($occupancyRate < 50) {
            $suggestions[] = [
                'type' => 'low_occupancy',
                'priority' => 'high',
                'message' => 'Votre taux d\'occupation est faible. Envisagez de promouvoir vos services.',
                'occupancy_rate' => $occupancyRate,
            ];
        }

        // Vérifier les conflits
        $conflicts = $this->conflictDetector->detectAllConflicts($prestataire, $startDate, $endDate);
        if (!empty($conflicts)) {
            $suggestions[] = [
                'type' => 'conflicts_detected',
                'priority' => 'critical',
                'message' => 'Des conflits ont été détectés dans votre planning.',
                'conflict_count' => count($conflicts),
            ];
        }

        // Analyser la distribution des réservations
        $bookingsByDay = [];
        foreach ($bookings as $booking) {
            $day = $booking->getScheduledDate()->format('Y-m-d');
            if (!isset($bookingsByDay[$day])) {
                $bookingsByDay[$day] = 0;
            }
            $bookingsByDay[$day]++;
        }

        $maxBookingsPerDay = !empty($bookingsByDay) ? max($bookingsByDay) : 0;
        $avgBookingsPerDay = !empty($bookingsByDay) ? array_sum($bookingsByDay) / count($bookingsByDay) : 0;

        if ($maxBookingsPerDay > $avgBookingsPerDay * 2) {
            $suggestions[] = [
                'type' => 'unbalanced_distribution',
                'priority' => 'medium',
                'message' => 'Vos réservations sont concentrées sur certains jours. Envisagez de rééquilibrer.',
                'max_per_day' => $maxBookingsPerDay,
                'avg_per_day' => round($avgBookingsPerDay, 2),
            ];
        }

        return $suggestions;
    }

    /**
     * Trouve le prochain créneau disponible
     */
    public function findNextAvailableSlot(
        Prestataire $prestataire,
        int $durationMinutes,
        ?\DateTimeInterface $afterDate = null
    ): ?array {
        $searchStart = $afterDate ?? new \DateTime();
        $searchEnd = (clone $searchStart)->modify('+30 days');

        $slots = $this->getAvailableSlots($prestataire, $searchStart, $searchEnd, $durationMinutes);

        if (empty($slots)) {
            return null;
        }

        // Retourner le premier créneau disponible
        return $slots[0];
    }

    /**
     * Exporte le planning au format CSV
     */
    public function exportScheduleToCsv(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): string {
        $schedule = $this->getCompleteSchedule($prestataire, $startDate, $endDate);
        
        $csv = "Type,Date,Heure début,Heure fin,Client,Adresse,Statut,Notes\n";

        // Ajouter les réservations
        foreach ($schedule['bookings'] as $booking) {
            $csv .= sprintf(
                "Réservation,%s,%s,%s,%s,%s,%s,%s\n",
                $booking->getScheduledDate()->format('Y-m-d'),
                $booking->getScheduledTime()->format('H:i'),
                $booking->getEndDateTime()->format('H:i'),
                $booking->getClient()->getFullName(),
                str_replace(',', ';', $booking->getAddress()),
                $booking->getStatus(),
                str_replace(',', ';', $booking->getNotes() ?? '')
            );
        }

        // Ajouter les absences
        foreach ($schedule['absences'] as $absence) {
            $csv .= sprintf(
                "Absence,%s,Toute la journée,Toute la journée,-,-,%s,%s\n",
                $absence->getStartDate()->format('Y-m-d'),
                $absence->getStatus(),
                str_replace(',', ';', $absence->getReason())
            );
        }

        return $csv;
    }

    /**
     * Vérifie si le prestataire a des disponibilités configurées
     */
    public function hasAvailabilities(Prestataire $prestataire): bool
    {
        return $this->availabilityRepository->count(['prestataire' => $prestataire]) > 0;
    }

    /**
     * Récupère les jours de la semaine où le prestataire est disponible
     */
    public function getAvailableDaysOfWeek(Prestataire $prestataire): array
    {
        $availabilities = $this->availabilityRepository->findBy([
            'prestataire' => $prestataire,
            'isRecurring' => true,
        ]);

        $days = [];
        foreach ($availabilities as $availability) {
            $day = $availability->getDayOfWeek();
            if (!in_array($day, $days)) {
                $days[] = $day;
            }
        }

        sort($days);
        return $days;
    }
}