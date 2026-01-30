<?php

namespace App\Service\Planning;

use App\Entity\Planning\Availability;
use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Repository\Planning\AvailabilityRepository;
use App\Repository\Booking\BookingRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des disponibilités des prestataires
 * Gère la création, modification, vérification et optimisation des créneaux
 */
class AvailabilityManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityRepository $availabilityRepository,
        private BookingRepository $bookingRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée une nouvelle disponibilité pour un prestataire
     */
    public function createAvailability(
        Prestataire $prestataire,
        int $dayOfWeek,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        bool $isRecurring = true,
        ?\DateTimeInterface $specificDate = null
    ): Availability {
        // Validation
        $this->validateTimeSlot($startTime, $endTime);
        
        // Vérification des conflits
        if ($this->hasConflict($prestataire, $dayOfWeek, $startTime, $endTime, $specificDate)) {
            throw new \InvalidArgumentException('Ce créneau chevauche une disponibilité existante');
        }

        $availability = new Availability();
        $availability->setPrestataire($prestataire);
        $availability->setDayOfWeek($dayOfWeek);
        $availability->setStartTime($startTime);
        $availability->setEndTime($endTime);
        $availability->setIsRecurring($isRecurring);
        $availability->setSpecificDate($specificDate);
        $availability->setIsActive(true);

        $this->entityManager->persist($availability);
        $this->entityManager->flush();

        $this->logger->info('Availability created', [
            'prestataire_id' => $prestataire->getId(),
            'day' => $dayOfWeek,
            'start' => $startTime->format('H:i'),
            'end' => $endTime->format('H:i')
        ]);

        return $availability;
    }

    /**
     * Met à jour une disponibilité existante
     */
    public function updateAvailability(
        Availability $availability,
        \DateTimeInterface $newStartTime,
        \DateTimeInterface $newEndTime
    ): Availability {
        $this->validateTimeSlot($newStartTime, $newEndTime);

        // Vérifier s'il y a des réservations dans ce créneau
        if ($this->hasBookingsInTimeSlot($availability, $newStartTime, $newEndTime)) {
            throw new \RuntimeException('Impossible de modifier: des réservations existent dans ce créneau');
        }

        $availability->setStartTime($newStartTime);
        $availability->setEndTime($newEndTime);
        $availability->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $availability;
    }

    /**
     * Supprime une disponibilité
     */
    public function deleteAvailability(Availability $availability, bool $force = false): void
    {
        // Vérifier s'il y a des réservations futures
        if (!$force && $this->hasFutureBookings($availability)) {
            throw new \RuntimeException('Impossible de supprimer: des réservations futures existent');
        }

        $this->entityManager->remove($availability);
        $this->entityManager->flush();

        $this->logger->info('Availability deleted', [
            'availability_id' => $availability->getId()
        ]);
    }

    /**
     * Désactive temporairement une disponibilité (sans la supprimer)
     */
    public function disableAvailability(Availability $availability): void
    {
        $availability->setIsActive(false);
        $availability->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
    }

    /**
     * Réactive une disponibilité
     */
    public function enableAvailability(Availability $availability): void
    {
        $availability->setIsActive(true);
        $availability->setUpdatedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();
    }

    /**
     * Vérifie si un prestataire est disponible à une date/heure donnée
     */
    public function isAvailable(
        Prestataire $prestataire,
        \DateTimeInterface $dateTime,
        int $durationMinutes
    ): bool {
        $dayOfWeek = (int) $dateTime->format('w');
        $time = $dateTime->format('H:i:s');
        $endDateTime = (clone $dateTime)->modify("+{$durationMinutes} minutes");

        // Récupérer les disponibilités pour ce jour
        $availabilities = $this->availabilityRepository->findForDayAndTime(
            $prestataire,
            $dayOfWeek,
            $dateTime,
            $endDateTime
        );

        if (empty($availabilities)) {
            return false;
        }

        // Vérifier qu'il n'y a pas de réservation qui chevauche
        return !$this->hasBookingConflict($prestataire, $dateTime, $endDateTime);
    }

    /**
     * Récupère tous les créneaux disponibles pour un prestataire sur une période
     */
    public function getAvailableSlots(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $slotDuration = 60 // en minutes
    ): array {
        $slots = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int) $currentDate->format('w');
            
            // Récupérer les disponibilités pour ce jour
            $availabilities = $this->availabilityRepository->findActiveForDay(
                $prestataire,
                $dayOfWeek,
                $currentDate
            );

            foreach ($availabilities as $availability) {
                $daySlots = $this->generateSlotsFromAvailability(
                    $availability,
                    $currentDate,
                    $slotDuration
                );

                // Filtrer les créneaux déjà réservés
                $availableSlots = array_filter($daySlots, function($slot) use ($prestataire) {
                    return !$this->hasBookingConflict(
                        $prestataire,
                        $slot['start'],
                        $slot['end']
                    );
                });

                $slots = array_merge($slots, array_values($availableSlots));
            }

            $currentDate->modify('+1 day');
        }

        return $slots;
    }

    /**
     * Suggère les meilleurs créneaux basés sur les préférences et l'historique
     */
    public function suggestOptimalSlots(
        Prestataire $prestataire,
        \DateTimeInterface $preferredDate,
        int $durationMinutes,
        int $maxSuggestions = 5
    ): array {
        // Période de recherche: 2 semaines après la date préférée
        $endDate = (clone $preferredDate)->modify('+14 days');
        
        $allSlots = $this->getAvailableSlots(
            $prestataire,
            $preferredDate,
            $endDate,
            $durationMinutes
        );

        // Scoring des créneaux
        $scoredSlots = array_map(function($slot) use ($preferredDate, $prestataire) {
            $score = $this->calculateSlotScore($slot, $preferredDate, $prestataire);
            return array_merge($slot, ['score' => $score]);
        }, $allSlots);

        // Trier par score décroissant
        usort($scoredSlots, fn($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scoredSlots, 0, $maxSuggestions);
    }

    /**
     * Crée des disponibilités récurrentes en masse (ex: tous les lundis 9h-17h)
     */
    public function createRecurringAvailabilities(
        Prestataire $prestataire,
        array $weekSchedule // ['monday' => ['09:00-12:00', '14:00-18:00'], ...]
    ): array {
        $dayMapping = [
            'monday' => 1,
            'tuesday' => 2,
            'wednesday' => 3,
            'thursday' => 4,
            'friday' => 5,
            'saturday' => 6,
            'sunday' => 0
        ];

        $createdAvailabilities = [];

        $this->entityManager->beginTransaction();
        
        try {
            foreach ($weekSchedule as $day => $timeSlots) {
                $dayOfWeek = $dayMapping[strtolower($day)] ?? null;
                
                if ($dayOfWeek === null) {
                    continue;
                }

                foreach ($timeSlots as $timeSlot) {
                    [$startTime, $endTime] = explode('-', $timeSlot);
                    
                    $start = \DateTime::createFromFormat('H:i', trim($startTime));
                    $end = \DateTime::createFromFormat('H:i', trim($endTime));

                    $availability = $this->createAvailability(
                        $prestataire,
                        $dayOfWeek,
                        $start,
                        $end,
                        true
                    );

                    $createdAvailabilities[] = $availability;
                }
            }

            $this->entityManager->commit();
        } catch (\Exception $e) {
            $this->entityManager->rollback();
            throw $e;
        }

        return $createdAvailabilities;
    }

    /**
     * Bloque des dates spécifiques (vacances, absences)
     */
    public function blockDates(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        string $reason = ''
    ): array {
        $blockedDates = [];
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            // Désactiver toutes les disponibilités pour cette date
            $availabilities = $this->availabilityRepository->findForSpecificDate(
                $prestataire,
                $currentDate
            );

            foreach ($availabilities as $availability) {
                $this->disableAvailability($availability);
                $blockedDates[] = $currentDate->format('Y-m-d');
            }

            $currentDate->modify('+1 day');
        }

        $this->logger->info('Dates blocked', [
            'prestataire_id' => $prestataire->getId(),
            'start' => $startDate->format('Y-m-d'),
            'end' => $endDate->format('Y-m-d'),
            'reason' => $reason
        ]);

        return $blockedDates;
    }

    /**
     * Calcule le taux d'occupation d'un prestataire sur une période
     */
    public function calculateOccupancyRate(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): float {
        // Total des heures disponibles
        $totalAvailableHours = $this->calculateTotalAvailableHours(
            $prestataire,
            $startDate,
            $endDate
        );

        if ($totalAvailableHours === 0) {
            return 0;
        }

        // Total des heures réservées
        $totalBookedHours = $this->bookingRepository->getTotalBookedHours(
            $prestataire,
            $startDate,
            $endDate
        );

        return ($totalBookedHours / $totalAvailableHours) * 100;
    }

    /**
     * Trouve des créneaux compatibles pour plusieurs prestataires (service groupé)
     */
    public function findCommonAvailabilities(
        array $prestataires,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $durationMinutes
    ): array {
        if (empty($prestataires)) {
            return [];
        }

        // Récupérer les disponibilités du premier prestataire
        $commonSlots = $this->getAvailableSlots(
            $prestataires[0],
            $startDate,
            $endDate,
            $durationMinutes
        );

        // Filtrer par intersection avec les autres prestataires
        foreach (array_slice($prestataires, 1) as $prestataire) {
            $prestataireSlots = $this->getAvailableSlots(
                $prestataire,
                $startDate,
                $endDate,
                $durationMinutes
            );

            $commonSlots = $this->intersectSlots($commonSlots, $prestataireSlots);
        }

        return $commonSlots;
    }

    // ============ MÉTHODES PRIVÉES ============

    /**
     * Valide qu'un créneau horaire est cohérent
     */
    private function validateTimeSlot(
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime
    ): void {
        if ($startTime >= $endTime) {
            throw new \InvalidArgumentException('L\'heure de fin doit être après l\'heure de début');
        }

        $diffMinutes = ($endTime->getTimestamp() - $startTime->getTimestamp()) / 60;
        
        if ($diffMinutes < 30) {
            throw new \InvalidArgumentException('La durée minimale d\'un créneau est de 30 minutes');
        }

        if ($diffMinutes > 720) { // 12 heures
            throw new \InvalidArgumentException('La durée maximale d\'un créneau est de 12 heures');
        }
    }

    /**
     * Vérifie s'il y a un conflit avec les disponibilités existantes
     */
    private function hasConflict(
        Prestataire $prestataire,
        int $dayOfWeek,
        \DateTimeInterface $startTime,
        \DateTimeInterface $endTime,
        ?\DateTimeInterface $specificDate = null
    ): bool {
        return $this->availabilityRepository->hasTimeSlotConflict(
            $prestataire,
            $dayOfWeek,
            $startTime,
            $endTime,
            $specificDate
        );
    }

    /**
     * Vérifie s'il y a des réservations dans le créneau modifié
     */
    private function hasBookingsInTimeSlot(
        Availability $availability,
        \DateTimeInterface $newStartTime,
        \DateTimeInterface $newEndTime
    ): bool {
        // Logique simplifiée - à adapter selon votre implémentation
        return $this->bookingRepository->count([
            'prestataire' => $availability->getPrestataire(),
            'status' => ['scheduled', 'confirmed']
        ]) > 0;
    }

    /**
     * Vérifie s'il y a des réservations futures pour cette disponibilité
     */
    private function hasFutureBookings(Availability $availability): bool {
        $futureBookings = $this->bookingRepository->findFutureBookingsForAvailability(
            $availability->getPrestataire(),
            $availability->getDayOfWeek(),
            new \DateTime()
        );

        return count($futureBookings) > 0;
    }

    /**
     * Vérifie s'il y a un conflit de réservation pour une période donnée
     */
    private function hasBookingConflict(
        Prestataire $prestataire,
        \DateTimeInterface $startDateTime,
        \DateTimeInterface $endDateTime
    ): bool {
        return $this->bookingRepository->hasConflictInPeriod(
            $prestataire,
            $startDateTime,
            $endDateTime
        );
    }

    /**
     * Génère des créneaux de durée fixe à partir d'une disponibilité
     */
    private function generateSlotsFromAvailability(
        Availability $availability,
        \DateTimeInterface $date,
        int $slotDuration
    ): array {
        $slots = [];
        
        $startTime = clone $availability->getStartTime();
        $endTime = clone $availability->getEndTime();

        $currentSlotStart = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $startTime->format('H:i:s')
        );

        $availabilityEnd = \DateTime::createFromFormat(
            'Y-m-d H:i:s',
            $date->format('Y-m-d') . ' ' . $endTime->format('H:i:s')
        );

        while ($currentSlotStart < $availabilityEnd) {
            $currentSlotEnd = (clone $currentSlotStart)->modify("+{$slotDuration} minutes");

            if ($currentSlotEnd <= $availabilityEnd) {
                $slots[] = [
                    'start' => clone $currentSlotStart,
                    'end' => clone $currentSlotEnd,
                    'duration' => $slotDuration,
                    'availability_id' => $availability->getId()
                ];
            }

            $currentSlotStart->modify("+{$slotDuration} minutes");
        }

        return $slots;
    }

    /**
     * Calcule un score pour un créneau (pour suggestions optimisées)
     */
    private function calculateSlotScore(
        array $slot,
        \DateTimeInterface $preferredDate,
        Prestataire $prestataire
    ): float {
        $score = 100.0;

        // Proximité avec la date préférée (plus c'est proche, mieux c'est)
        $daysDiff = abs($slot['start']->diff($preferredDate)->days);
        $score -= ($daysDiff * 2); // -2 points par jour d'écart

        // Heure de la journée (privilégier matin et après-midi)
        $hour = (int) $slot['start']->format('H');
        if ($hour >= 9 && $hour <= 11) {
            $score += 10; // Créneaux matinaux populaires
        } elseif ($hour >= 14 && $hour <= 16) {
            $score += 5; // Créneaux après-midi
        }

        // Jour de la semaine (privilégier en semaine)
        $dayOfWeek = (int) $slot['start']->format('w');
        if ($dayOfWeek >= 1 && $dayOfWeek <= 5) {
            $score += 5;
        }

        return max(0, $score);
    }

    /**
     * Calcule le nombre total d'heures disponibles sur une période
     */
    private function calculateTotalAvailableHours(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): float {
        $totalHours = 0;
        $currentDate = clone $startDate;

        while ($currentDate <= $endDate) {
            $dayOfWeek = (int) $currentDate->format('w');
            
            $availabilities = $this->availabilityRepository->findActiveForDay(
                $prestataire,
                $dayOfWeek,
                $currentDate
            );

            foreach ($availabilities as $availability) {
                $start = $availability->getStartTime();
                $end = $availability->getEndTime();
                
                $hours = ($end->getTimestamp() - $start->getTimestamp()) / 3600;
                $totalHours += $hours;
            }

            $currentDate->modify('+1 day');
        }

        return $totalHours;
    }

    /**
     * Trouve l'intersection de deux ensembles de créneaux
     */
    private function intersectSlots(array $slots1, array $slots2): array
    {
        $intersection = [];

        foreach ($slots1 as $slot1) {
            foreach ($slots2 as $slot2) {
                // Vérifier si les créneaux se chevauchent
                if ($slot1['start'] < $slot2['end'] && $slot2['start'] < $slot1['end']) {
                    // Calculer l'intersection
                    $intersectionStart = max($slot1['start'], $slot2['start']);
                    $intersectionEnd = min($slot1['end'], $slot2['end']);

                    $intersection[] = [
                        'start' => $intersectionStart,
                        'end' => $intersectionEnd,
                        'duration' => ($intersectionEnd->getTimestamp() - $intersectionStart->getTimestamp()) / 60
                    ];
                }
            }
        }

        return $intersection;
    }
}