<?php

namespace App\Service\Booking;

use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\User\User;
use App\Service\Notification\NotificationService;
use App\Service\Planning\AvailabilityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Service de gestion des réservations (Bookings)
 * 
 * Ce service orchestre toutes les opérations liées aux réservations :
 * - Création à partir de devis
 * - Gestion du cycle de vie (start, complete, cancel)
 * - Gestion des services récurrents
 * - Rappels et notifications
 * - Statistiques
 */
class BookingService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookingStatusManager $statusManager,
        private BookingValidator $bookingValidator,
        private RecurrenceManager $recurrenceManager,
        private NotificationService $notificationService,
        private AvailabilityManager $availabilityManager
    ) {}

    /**
     * Crée une réservation à partir d'un devis accepté
     */
    public function createFromQuote(Quote $quote, User $user): Booking
    {
        // Valider le devis
        $this->bookingValidator->validateQuote($quote);

        // Vérifier qu'il n'existe pas déjà une réservation
        if ($quote->getBooking()) {
            throw new ConflictHttpException('Une réservation existe déjà pour ce devis');
        }

        // Vérifier la disponibilité du prestataire
        $proposedDate = $quote->getProposedDate();
        $proposedTime = \DateTime::createFromFormat('H:i:s', $proposedDate->format('H:i:s'));
        
        $this->bookingValidator->validateAvailability(
            $quote->getPrestataire(),
            $proposedDate,
            $proposedTime,
            $quote->getProposedDuration()
        );

        // Créer la réservation
        $booking = new Booking();
        $booking->setQuote($quote);
        $booking->setClient($quote->getServiceRequest()->getClient());
        $booking->setPrestataire($quote->getPrestataire());
        $booking->setServiceRequest($quote->getServiceRequest());
        $booking->setScheduledDate($quote->getProposedDate());
        $booking->setScheduledTime($proposedTime);
        $booking->setDuration($quote->getProposedDuration());
        $booking->setAddress($quote->getServiceRequest()->getAddress());
        $booking->setAmount($quote->getAmount());
        $booking->setStatus('scheduled');

        $this->em->persist($booking);

        // Gérer la récurrence si nécessaire
        if ($quote->getServiceRequest()->getFrequency() !== 'ponctuel') {
            $this->recurrenceManager->createRecurringBookings($booking, $quote->getServiceRequest());
        }

        $this->em->flush();

        // Notifier les parties
        $this->notificationService->notifyBookingCreated($booking);

        return $booking;
    }

    /**
     * Démarre une réservation (check-in)
     */
    public function startBooking(Booking $booking): void
    {
        if ($booking->getStatus() !== 'confirmed' && $booking->getStatus() !== 'scheduled') {
            throw new BadRequestHttpException(
                'Cette réservation ne peut pas être démarrée. Statut actuel : ' . $booking->getStatus()
            );
        }

        $this->statusManager->updateStatus($booking, 'in_progress');
        $booking->setActualStartTime(new \DateTime());
        
        $this->em->flush();
        
        // Notifier le client
        $this->notificationService->notifyBookingStarted($booking);
    }

    /**
     * Termine une réservation (check-out)
     */
    public function completeBooking(
        Booking $booking, 
        ?string $completionNotes = null, 
        ?int $actualDuration = null
    ): void {
        if ($booking->getStatus() !== 'in_progress') {
            throw new BadRequestHttpException(
                'Cette réservation ne peut pas être terminée. Statut actuel : ' . $booking->getStatus()
            );
        }

        $this->statusManager->updateStatus($booking, 'completed');
        $booking->setActualEndTime(new \DateTime());
        
        if ($completionNotes) {
            $booking->setCompletionNotes($completionNotes);
        }
        
        if ($actualDuration) {
            $booking->setActualDuration($actualDuration);
        } else {
            // Calculer la durée réelle si non fournie
            $start = $booking->getActualStartTime();
            $end = $booking->getActualEndTime();
            if ($start && $end) {
                $diff = $end->diff($start);
                $actualDuration = ($diff->h * 60) + $diff->i;
                $booking->setActualDuration($actualDuration);
            }
        }
        
        $this->em->flush();
        
        // Notifier le client de la complétion
        $this->notificationService->notifyBookingCompleted($booking);
    }

    /**
     * Annule une réservation
     */
    public function cancelBooking(
        Booking $booking, 
        string $reason, 
        User $canceledBy
    ): void {
        // Vérifier que la réservation peut être annulée
        if (in_array($booking->getStatus(), ['completed', 'cancelled'])) {
            throw new BadRequestHttpException(
                'Cette réservation ne peut pas être annulée. Statut actuel : ' . $booking->getStatus()
            );
        }

        $this->statusManager->updateStatus($booking, 'cancelled');
        $booking->setCancellationReason($reason);
        $booking->setCanceledBy($canceledBy);
        $booking->setCanceledAt(new \DateTime());
        
        // Libérer le créneau du prestataire
        $this->availabilityManager->releaseSlot($booking);
        
        $this->em->flush();
        
        // Notifier les parties concernées
        $this->notificationService->notifyBookingCancelled($booking);
    }

    /**
     * Confirme une réservation
     */
    public function confirmBooking(Booking $booking): void
    {
        if ($booking->getStatus() !== 'scheduled') {
            throw new BadRequestHttpException('Cette réservation ne peut pas être confirmée');
        }

        $this->statusManager->updateStatus($booking, 'confirmed');
        $this->em->flush();

        $this->notificationService->notifyBookingConfirmed($booking);
    }

    /**
     * Met à jour une réservation
     */
    public function updateBooking(Booking $booking, array $data): Booking
    {
        // Vérifier que la réservation peut être modifiée
        if (!in_array($booking->getStatus(), ['scheduled', 'confirmed'])) {
            throw new BadRequestHttpException('Cette réservation ne peut plus être modifiée');
        }

        if (isset($data['scheduled_date'])) {
            $newDate = new \DateTime($data['scheduled_date']);
            
            // Vérifier la disponibilité pour la nouvelle date
            $this->bookingValidator->validateAvailability(
                $booking->getPrestataire(),
                $newDate,
                $booking->getScheduledTime(),
                $booking->getDuration(),
                $booking // Exclure la réservation actuelle de la vérification
            );

            $booking->setScheduledDate($newDate);
        }

        if (isset($data['scheduled_time'])) {
            $booking->setScheduledTime(new \DateTime($data['scheduled_time']));
        }

        if (isset($data['notes'])) {
            $booking->setNotes($data['notes']);
        }

        $this->em->flush();

        // Notifier les parties du changement
        $this->notificationService->notifyBookingUpdated($booking);

        return $booking;
    }

    /**
     * Obtient les réservations à venir d'un prestataire
     */
    public function getUpcomingBookings(Prestataire $prestataire, int $days = 30): array
    {
        $endDate = new \DateTime("+{$days} days");
        
        return $this->em->getRepository(Booking::class)
            ->findUpcomingByPrestataire($prestataire, $endDate);
    }

    /**
     * Obtient les réservations du jour pour un prestataire
     */
    public function getTodayBookings(Prestataire $prestataire): array
    {
        return $this->em->getRepository(Booking::class)
            ->findTodayByPrestataire($prestataire);
    }

    /**
     * Obtient les réservations entre deux dates
     */
    public function getBookingsBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null,
        ?Client $client = null
    ): array {
        return $this->em->getRepository(Booking::class)
            ->findBetweenDates($startDate, $endDate, $prestataire, $client);
    }

    /**
     * Vérifie et envoie les rappels automatiques
     */
    public function checkAndSendReminders(): array
    {
        $sent = [];

        // Rappels 24h avant
        $bookings24h = $this->em->getRepository(Booking::class)
            ->findBookingsIn24Hours();

        foreach ($bookings24h as $booking) {
            $this->notificationService->sendBookingReminder($booking, '24h');
            $booking->setReminderSent24h(true);
            $sent[] = ['booking' => $booking, 'type' => '24h'];
        }

        // Rappels 2h avant
        $bookings2h = $this->em->getRepository(Booking::class)
            ->findBookingsIn2Hours();

        foreach ($bookings2h as $booking) {
            $this->notificationService->sendBookingReminder($booking, '2h');
            $booking->setReminderSent2h(true);
            $sent[] = ['booking' => $booking, 'type' => '2h'];
        }

        $this->em->flush();

        return $sent;
    }

    /**
     * Obtient les statistiques de réservation
     */
    public function getStatistics(
        ?Prestataire $prestataire = null,
        ?Client $client = null
    ): array {
        return $this->em->getRepository(Booking::class)
            ->getBookingStatistics($prestataire, $client);
    }

    /**
     * Recherche de réservations avec critères
     */
    public function search(array $criteria): array
    {
        return $this->em->getRepository(Booking::class)
            ->search($criteria);
    }

    /**
     * Obtient l'historique des changements de statut
     */
    public function getHistory(Booking $booking): array
    {
        return $this->statusManager->getHistory($booking);
    }

    /**
     * Obtient les réservations nécessitant une action
     */
    public function getBookingsRequiringAction(?Prestataire $prestataire = null): array
    {
        $repository = $this->em->getRepository(Booking::class);
        
        if ($prestataire) {
            return $repository->findRequiringActionByPrestataire($prestataire);
        }
        
        return $repository->findRequiringAction();
    }

    /**
     * Vérifie si une réservation peut être modifiée
     */
    public function canBeModified(Booking $booking): bool
    {
        return in_array($booking->getStatus(), ['scheduled', 'confirmed']);
    }

    /**
     * Vérifie si une réservation peut être annulée
     */
    public function canBeCancelled(Booking $booking): bool
    {
        return !in_array($booking->getStatus(), ['completed', 'cancelled']);
    }

    /**
     * Vérifie si une réservation peut être démarrée
     */
    public function canBeStarted(Booking $booking): bool
    {
        return in_array($booking->getStatus(), ['scheduled', 'confirmed']);
    }

    /**
     * Vérifie si une réservation peut être terminée
     */
    public function canBeCompleted(Booking $booking): bool
    {
        return $booking->getStatus() === 'in_progress';
    }
}