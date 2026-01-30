<?php
// src/Service/Booking/BookingValidator.php

namespace App\Service\Booking;

use App\Entity\Booking;
use App\Entity\Quote;
use App\Entity\Prestataire;
use App\Repository\BookingRepository;
use App\Service\Planning\AvailabilityManager;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingValidator
{
    public function __construct(
        private BookingRepository $bookingRepository,
        private AvailabilityManager $availabilityManager
    ) {}

    /**
     * Valide un devis avant de créer une réservation
     */
    public function validateQuote(Quote $quote): void
    {
        // Vérifier que le devis est accepté
        if ($quote->getStatus() !== 'accepted') {
            throw new BadRequestHttpException('Le devis doit être accepté pour créer une réservation');
        }

        // Vérifier que le devis n'est pas expiré
        if ($quote->isExpired()) {
            throw new BadRequestHttpException('Le devis a expiré');
        }

        // Vérifier que la date proposée n'est pas dans le passé
        if ($quote->getProposedDate() < new \DateTime()) {
            throw new BadRequestHttpException('La date proposée est dans le passé');
        }

        // Vérifier que le prestataire est toujours actif et approuvé
        if (!$quote->getPrestataire()->isActive() || !$quote->getPrestataire()->isApproved()) {
            throw new BadRequestHttpException('Le prestataire n\'est plus disponible');
        }
    }

    /**
     * Valide la disponibilité d'un prestataire
     */
    public function validateAvailability(
        Prestataire $prestataire,
        \DateTimeInterface $date,
        \DateTimeInterface $time,
        int $duration,
        ?int $excludeBookingId = null
    ): void {
        // Vérifier que la date n'est pas dans le passé
        $scheduledDateTime = (new \DateTime($date->format('Y-m-d')))
            ->setTime((int)$time->format('H'), (int)$time->format('i'));

        if ($scheduledDateTime < new \DateTime()) {
            throw new BadRequestHttpException('La date et l\'heure sont dans le passé');
        }

        // Vérifier que le prestataire est disponible
        $isAvailable = $this->bookingRepository->isPrestataireAvailable(
            $prestataire,
            $date,
            $time,
            $duration,
            $excludeBookingId
        );

        if (!$isAvailable) {
            throw new ConflictHttpException('Le prestataire n\'est pas disponible à cette date et heure');
        }

        // Vérifier les disponibilités définies par le prestataire
        if (!$this->availabilityManager->isAvailable($prestataire, $scheduledDateTime, $duration)) {
            throw new ConflictHttpException('Cette plage horaire n\'est pas dans les disponibilités du prestataire');
        }
    }

    /**
     * Valide une transition de statut
     */
    public function validateStatusTransition(Booking $booking, string $newStatus): void
    {
        $currentStatus = $booking->getStatus();
        $allowedTransitions = $this->getAllowedStatusTransitions();

        if (!isset($allowedTransitions[$currentStatus])) {
            throw new BadRequestHttpException("Statut actuel invalide: {$currentStatus}");
        }

        if (!in_array($newStatus, $allowedTransitions[$currentStatus])) {
            throw new BadRequestHttpException(
                "Impossible de passer du statut '{$currentStatus}' au statut '{$newStatus}'"
            );
        }

        // Validations spécifiques par transition
        switch ($newStatus) {
            case 'in_progress':
                $this->validateStartTransition($booking);
                break;

            case 'completed':
                $this->validateCompleteTransition($booking);
                break;

            case 'cancelled':
                $this->validateCancelTransition($booking);
                break;
        }
    }

    /**
     * Retourne les transitions de statut autorisées
     */
    private function getAllowedStatusTransitions(): array
    {
        return [
            'scheduled' => ['confirmed', 'in_progress', 'cancelled'],
            'confirmed' => ['in_progress', 'cancelled'],
            'in_progress' => ['completed', 'cancelled'],
            'completed' => [], // Statut terminal
            'cancelled' => [], // Statut terminal
        ];
    }

    /**
     * Valide le démarrage d'une réservation
     */
    private function validateStartTransition(Booking $booking): void
    {
        $now = new \DateTime();
        $scheduledDateTime = $booking->getScheduledDateTime();

        // Ne pas démarrer trop tôt (plus de 1 heure avant)
        $tooEarly = (clone $scheduledDateTime)->modify('-1 hour');
        if ($now < $tooEarly) {
            throw new BadRequestHttpException(
                'Vous ne pouvez pas démarrer la réservation plus d\'1 heure avant l\'heure prévue'
            );
        }

        // Ne pas démarrer trop tard (plus de 2 heures après)
        $tooLate = (clone $scheduledDateTime)->modify('+2 hours');
        if ($now > $tooLate) {
            throw new BadRequestHttpException(
                'Vous ne pouvez plus démarrer cette réservation (plus de 2 heures de retard)'
            );
        }
    }

    /**
     * Valide la complétion d'une réservation
     */
    private function validateCompleteTransition(Booking $booking): void
    {
        // Vérifier qu'un temps de début a été enregistré
        if (!$booking->getActualStartTime()) {
            throw new BadRequestHttpException(
                'Vous devez d\'abord démarrer la réservation avant de la terminer'
            );
        }

        // Vérifier une durée minimale (au moins 5 minutes)
        $now = new \DateTime();
        $startTime = $booking->getActualStartTime();
        $diff = $now->getTimestamp() - $startTime->getTimestamp();
        
        if ($diff < 300) { // 5 minutes
            throw new BadRequestHttpException(
                'La réservation doit durer au moins 5 minutes'
            );
        }
    }

    /**
     * Valide l'annulation d'une réservation
     */
    private function validateCancelTransition(Booking $booking): void
    {
        // Vérifier le délai d'annulation (au moins 24h avant)
        $now = new \DateTime();
        $scheduledDateTime = $booking->getScheduledDateTime();
        $diff = $scheduledDateTime->getTimestamp() - $now->getTimestamp();
        
        // Permettre l'annulation même tardive, mais on pourrait ajouter des pénalités
        // if ($diff < 86400) { // 24 heures
        //     throw new BadRequestHttpException(
        //         'Vous ne pouvez plus annuler cette réservation (délai minimum de 24h)'
        //     );
        // }
    }

    /**
     * Valide une réservation complète
     */
    public function validateBooking(Booking $booking): array
    {
        $errors = [];

        // Valider les champs requis
        if (!$booking->getClient()) {
            $errors[] = 'Le client est requis';
        }

        if (!$booking->getPrestataire()) {
            $errors[] = 'Le prestataire est requis';
        }

        if (!$booking->getScheduledDate()) {
            $errors[] = 'La date est requise';
        }

        if (!$booking->getScheduledTime()) {
            $errors[] = 'L\'heure est requise';
        }

        if (!$booking->getDuration() || $booking->getDuration() <= 0) {
            $errors[] = 'La durée doit être supérieure à 0';
        }

        if (!$booking->getAmount() || bccomp($booking->getAmount(), '0', 2) <= 0) {
            $errors[] = 'Le montant doit être supérieur à 0';
        }

        if (!$booking->getAddress()) {
            $errors[] = 'L\'adresse est requise';
        }

        // Valider les contraintes métier
        if ($booking->getScheduledDate() && $booking->getScheduledTime()) {
            try {
                $this->validateAvailability(
                    $booking->getPrestataire(),
                    $booking->getScheduledDate(),
                    $booking->getScheduledTime(),
                    $booking->getDuration(),
                    $booking->getId()
                );
            } catch (\Exception $e) {
                $errors[] = $e->getMessage();
            }
        }

        return $errors;
    }

    /**
     * Valide les données de report
     */
    public function validateReschedule(
        Booking $booking,
        \DateTimeInterface $newDate,
        \DateTimeInterface $newTime
    ): void {
        // Vérifier que la réservation peut être reportée
        if (!$booking->canBeRescheduled()) {
            throw new BadRequestHttpException('Cette réservation ne peut plus être reportée');
        }

        // Vérifier que la nouvelle date n'est pas dans le passé
        $newDateTime = (new \DateTime($newDate->format('Y-m-d')))
            ->setTime((int)$newTime->format('H'), (int)$newTime->format('i'));

        if ($newDateTime < new \DateTime()) {
            throw new BadRequestHttpException('La nouvelle date ne peut pas être dans le passé');
        }

        // Vérifier que la nouvelle date est différente de l'ancienne
        if ($newDateTime == $booking->getScheduledDateTime()) {
            throw new BadRequestHttpException('La nouvelle date doit être différente de l\'ancienne');
        }

        // Vérifier la disponibilité pour la nouvelle date
        $this->validateAvailability(
            $booking->getPrestataire(),
            $newDate,
            $newTime,
            $booking->getDuration(),
            $booking->getId()
        );
    }

    /**
     * Valide un délai d'annulation
     */
/**
 * Valide un délai d'annulation
 */
public function validateCancellationDelay(Booking $booking): array
{
    $now = new \DateTime();
    $scheduledDateTime = $booking->getScheduledDateTime();
    $hoursUntilBooking = ($scheduledDateTime->getTimestamp() - $now->getTimestamp()) / 3600;

    $result = [
        'can_cancel' => true,
        'has_penalty' => false,
        'penalty_percentage' => 0,
        'hours_until_booking' => $hoursUntilBooking
    ];

    // Politique d'annulation
    if ($hoursUntilBooking < 2) {
        $result['can_cancel'] = false;
        $result['message'] = 'Annulation impossible moins de 2h avant le rendez-vous';

    } elseif ($hoursUntilBooking < 24) {
        $result['has_penalty'] = true;
        $result['penalty_percentage'] = 50;
        $result['message'] = 'Annulation entre 2h et 24h: pénalité de 50%';

    } elseif ($hoursUntilBooking < 48) {
        $result['has_penalty'] = true;
        $result['penalty_percentage'] = 25;
        $result['message'] = 'Annulation entre 24h et 48h: pénalité de 25%';

    } else {
        $result['message'] = 'Annulation gratuite (plus de 48h avant)';
    }

    return $result;
}

/**
 * Valide la durée d'une réservation
 */
public function validateDuration(int $duration): void
{
    if ($duration < 30) {
        throw new BadRequestHttpException('La durée minimale est de 30 minutes');
    }

    if ($duration > 480) { // 8 heures
        throw new BadRequestHttpException('La durée maximale est de 8 heures');
    }

    // La durée doit être un multiple de 15 minutes
    if ($duration % 15 !== 0) {
        throw new BadRequestHttpException('La durée doit être un multiple de 15 minutes');
    }
}

/**
 * Valide un montant
 */
public function validateAmount(string $amount): void
{
    if (bccomp($amount, '0', 2) <= 0) {
        throw new BadRequestHttpException('Le montant doit être supérieur à 0');
    }

    if (bccomp($amount, '10000', 2) > 0) {
        throw new BadRequestHttpException('Le montant ne peut pas dépasser 10 000 €');
    }
}

/**
 * Vérifie les conflits potentiels
 */
public function checkConflicts(
    Prestataire $prestataire,
    \DateTimeInterface $date,
    \DateTimeInterface $time,
    int $duration,
    ?int $excludeBookingId = null
): array {
    $scheduledDateTime = (new \DateTime($date->format('Y-m-d')))
        ->setTime((int)$time->format('H'), (int)$time->format('i'));
    
    $endDateTime = (clone $scheduledDateTime)->modify("+{$duration} minutes");

    // Récupérer toutes les réservations du jour
    $bookings = $this->bookingRepository->findByDate($date, $prestataire);

    $conflicts = [];

    foreach ($bookings as $booking) {
        if ($excludeBookingId && $booking->getId() === $excludeBookingId) {
            continue;
        }

        if (!in_array($booking->getStatus(), ['scheduled', 'confirmed', 'in_progress'])) {
            continue;
        }

        $bookingStart = $booking->getScheduledDateTime();
        $bookingEnd = (clone $bookingStart)->modify('+' . $booking->getDuration() . ' minutes');

        // Vérifier le chevauchement
        if ($scheduledDateTime < $bookingEnd && $endDateTime > $bookingStart) {
            $conflicts[] = [
                'booking' => $booking,
                'overlap_start' => max($scheduledDateTime, $bookingStart),
                'overlap_end' => min($endDateTime, $bookingEnd)
            ];
        }
    }

    return $conflicts;
}

/**
 * Vérifie si le prestataire a trop de réservations le même jour
 */
public function validateDailyBookingLimit(
    Prestataire $prestataire,
    \DateTimeInterface $date,
    ?int $excludeBookingId = null
): void {
    $bookings = $this->bookingRepository->findByDate($date, $prestataire);

    $activeBookings = array_filter($bookings, function($booking) use ($excludeBookingId) {
        if ($excludeBookingId && $booking->getId() === $excludeBookingId) {
            return false;
        }
        return in_array($booking->getStatus(), ['scheduled', 'confirmed', 'in_progress']);
    });

    $maxBookingsPerDay = 6; // Configurable

    if (count($activeBookings) >= $maxBookingsPerDay) {
        throw new ConflictHttpException(
            "Le prestataire a atteint sa limite de {$maxBookingsPerDay} réservations par jour"
        );
    }
}
}