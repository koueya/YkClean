<?php
// src/Service/Booking/BookingManager.php

namespace App\Service\Booking;

use App\Entity\Booking;
use App\Entity\Quote;
use App\Entity\Client;
use App\Entity\Prestataire;
use App\Entity\User;
use App\Service\Notification\NotificationService;
use App\Service\Planning\AvailabilityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class BookingManager
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
        $booking->setServiceRequest($quote->getServiceRequest());
        $booking->setClient($quote->getServiceRequest()->getClient());
        $booking->setPrestataire($quote->getPrestataire());
        
        $booking->setScheduledDate($proposedDate);
        $booking->setScheduledTime($proposedTime);
        $booking->setDuration($quote->getProposedDuration());
        
        // Copier l'adresse depuis la demande de service
        $serviceRequest = $quote->getServiceRequest();
        $booking->setAddress($serviceRequest->getAddress());
        $booking->setCity($serviceRequest->getCity());
        $booking->setPostalCode($serviceRequest->getPostalCode());
        $booking->setLatitude($serviceRequest->getLatitude());
        $booking->setLongitude($serviceRequest->getLongitude());
        
        $booking->setAmount($quote->getAmount());
        $booking->setStatus('scheduled');

        $this->em->persist($booking);
        $this->em->flush();

        // Enregistrer le changement de statut initial
        $this->statusManager->changeStatus(
            $booking,
            'scheduled',
            $user,
            'Réservation créée à partir du devis accepté'
        );

        // Mettre à jour le statut de la demande de service
        $serviceRequest->setStatus('in_progress');
        $this->em->flush();

        // Envoyer les notifications
        $this->notificationService->notifyBookingCreated($booking);

        return $booking;
    }

    /**
     * Confirme une réservation
     */
    public function confirm(Booking $booking, User $user, ?string $comment = null): Booking
    {
        $this->bookingValidator->validateStatusTransition($booking, 'confirmed');

        $this->statusManager->changeStatus(
            $booking,
            'confirmed',
            $user,
            'Réservation confirmée',
            $comment
        );

        $this->notificationService->notifyBookingConfirmed($booking);

        return $booking;
    }

    /**
     * Démarre une réservation (le prestataire arrive)
     */
    public function start(Booking $booking, User $user): Booking
    {
        $this->bookingValidator->validateStatusTransition($booking, 'in_progress');

        $booking->setActualStartTime(new \DateTime());
        
        $this->statusManager->changeStatus(
            $booking,
            'in_progress',
            $user,
            'Service démarré'
        );

        $this->notificationService->notifyBookingStarted($booking);

        return $booking;
    }

    /**
     * Termine une réservation
     */
    public function complete(
        Booking $booking,
        User $user,
        ?string $completionNotes = null
    ): Booking {
        $this->bookingValidator->validateStatusTransition($booking, 'completed');

        $booking->setActualEndTime(new \DateTime());
        
        if ($completionNotes) {
            $booking->setCompletionNotes($completionNotes);
        }

        $this->statusManager->changeStatus(
            $booking,
            'completed',
            $user,
            'Service terminé',
            $completionNotes
        );

        // Mettre à jour les statistiques
        $this->updateStatistics($booking);

        // Notifications
        $this->notificationService->notifyBookingCompleted($booking);
        $this->notificationService->requestReview($booking);

        return $booking;
    }

    /**
     * Annule une réservation
     */
    public function cancel(
        Booking $booking,
        User $user,
        string $reason,
        ?string $comment = null
    ): Booking {
        if (!$booking->canBeCancelled()) {
            throw new BadRequestHttpException('Cette réservation ne peut plus être annulée');
        }

        $booking->setCancellationReason($reason);
        $booking->setCancelledBy($user);

        $this->statusManager->changeStatus(
            $booking,
            'cancelled',
            $user,
            $reason,
            $comment
        );

        // Notifications
        $this->notificationService->notifyBookingCancelled($booking);

        return $booking;
    }

    /**
     * Reporte une réservation
     */
    public function reschedule(
        Booking $booking,
        \DateTimeInterface $newDate,
        \DateTimeInterface $newTime,
        User $user,
        ?string $reason = null
    ): Booking {
        if (!$booking->canBeRescheduled()) {
            throw new BadRequestHttpException('Cette réservation ne peut plus être reportée');
        }

        // Valider la nouvelle disponibilité
        $this->bookingValidator->validateAvailability(
            $booking->getPrestataire(),
            $newDate,
            $newTime,
            $booking->getDuration(),
            $booking->getId()
        );

        $oldDate = $booking->getScheduledDateTime();
        
        $booking->setScheduledDate($newDate);
        $booking->setScheduledTime($newTime);
        $booking->setReminderSent24h(false);
        $booking->setReminderSent2h(false);

        $this->em->flush();

        $this->statusManager->changeStatus(
            $booking,
            $booking->getStatus(),
            $user,
            $reason ?? 'Réservation reportée',
            null,
            [
                'old_date' => $oldDate->format('Y-m-d H:i:s'),
                'new_date' => $booking->getScheduledDateTime()->format('Y-m-d H:i:s')
            ]
        );

        $this->notificationService->notifyBookingRescheduled($booking, $oldDate);

        return $booking;
    }

    /**
     * Ajoute des instructions du client
     */
    public function addClientInstructions(
        Booking $booking,
        string $instructions,
        Client $client
    ): Booking {
        if ($booking->getClient() !== $client) {
            throw new BadRequestHttpException('Seul le client de cette réservation peut ajouter des instructions');
        }

        $booking->addClientInstruction($instructions);
        $this->em->flush();

        $this->notificationService->notifyInstructionsAdded($booking);

        return $booking;
    }

    /**
     * Ajoute des notes du prestataire
     */
    public function addPrestataireNotes(
        Booking $booking,
        string $note,
        Prestataire $prestataire
    ): Booking {
        if ($booking->getPrestataire() !== $prestataire) {
            throw new BadRequestHttpException('Seul le prestataire de cette réservation peut ajouter des notes');
        }

        $booking->addPrestataireNote($note);
        $this->em->flush();

        return $booking;
    }

    /**
     * Définit les informations d'accès
     */
    public function setAccessInformation(
        Booking $booking,
        ?string $accessInstructions = null,
        ?string $accessCode = null,
        ?bool $clientPresent = null
    ): Booking {
        if ($accessInstructions !== null) {
            $booking->setAccessInstructions($accessInstructions);
        }

        if ($accessCode !== null) {
            $booking->setAccessCode($accessCode);
        }

        if ($clientPresent !== null) {
            $booking->setClientPresent($clientPresent);
        }

        $this->em->flush();

        return $booking;
    }

    /**
     * Crée une réservation récurrente
     */
    public function createRecurrent(
        Quote $quote,
        string $frequency,
        \DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate = null,
        ?int $dayOfWeek = null,
        ?int $dayOfMonth = null,
        User $user = null
    ): array {
        // Déléguer au RecurrenceManager
        $result = $this->recurrenceManager->createRecurrence(
            $quote,
            $frequency,
            $startDate,
            $endDate,
            $dayOfWeek,
            $dayOfMonth
        );

        $firstBooking = $result['first_booking'];

        // Enregistrer le statut initial
        if ($user) {
            $this->statusManager->changeStatus(
                $firstBooking,
                'scheduled',
                $user,
                'Première réservation d\'une série récurrente'
            );
        }

        // Notifications
        $this->notificationService->notifyBookingCreated($firstBooking);
        $this->notificationService->notifyRecurrenceCreated($result['recurrence']);

        return $result;
    }

    /**
     * Met à jour les statistiques après une réservation complétée
     */
    private function updateStatistics(Booking $booking): void
    {
        $client = $booking->getClient();
        $prestataire = $booking->getPrestataire();

        // Statistiques client
        $client->incrementTotalBookings();
        $client->addToTotalSpent($booking->getAmount());

        // Statistiques prestataire
        $prestataire->incrementCompletedBookings();
        $prestataire->addToTotalEarnings($booking->getAmount());

        $this->em->flush();
    }

    /**
     * Obtient les réservations à venir d'un client
     */
    public function getUpcomingByClient(Client $client): array
    {
        return $this->em->getRepository(Booking::class)
            ->findUpcomingByClient($client);
    }

    /**
     * Obtient les réservations à venir d'un prestataire
     */
    public function getUpcomingByPrestataire(Prestataire $prestataire): array
    {
        return $this->em->getRepository(Booking::class)
            ->findUpcomingByPrestataire($prestataire);
    }

    /**
     * Obtient les réservations du jour pour un prestataire
     */
    public function getTodayByPrestataire(Prestataire $prestataire): array
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
     * Vérifie et envoie les rappels
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
     * Recherche de réservations
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
    public function getRequiringAction(Prestataire $prestataire): array
    {
        $today = new \DateTime();
        
        return [
            'to_confirm' => $this->em->getRepository(Booking::class)
                ->findByPrestataire($prestataire, 'scheduled'),
            
            'today' => $this->getTodayByPrestataire($prestataire),
            
            'in_progress' => $this->em->getRepository(Booking::class)
                ->findByPrestataire($prestataire, 'in_progress'),
            
            'to_review' => $this->em->getRepository(Booking::class)
                ->findCompletedWithoutReview($prestataire->getUser())
        ];
    }

    /**
     * Clone une réservation
     */
    public function clone(
        Booking $originalBooking,
        \DateTimeInterface $newDate,
        \DateTimeInterface $newTime,
        User $user
    ): Booking {
        // Valider la disponibilité
        $this->bookingValidator->validateAvailability(
            $originalBooking->getPrestataire(),
            $newDate,
            $newTime,
            $originalBooking->getDuration()
        );

        $newBooking = new Booking();
        $newBooking->setClient($originalBooking->getClient());
        $newBooking->setPrestataire($originalBooking->getPrestataire());
        $newBooking->setServiceRequest($originalBooking->getServiceRequest());
        $newBooking->setScheduledDate($newDate);
        $newBooking->setScheduledTime($newTime);
        $newBooking->setDuration($originalBooking->getDuration());
        $newBooking->setAddress($originalBooking->getAddress());
        $newBooking->setCity($originalBooking->getCity());
        $newBooking->setPostalCode($originalBooking->getPostalCode());
        $newBooking->setLatitude($originalBooking->getLatitude());
        $newBooking->setLongitude($originalBooking->getLongitude());
        $newBooking->setAmount($originalBooking->getAmount());
        $newBooking->setStatus('scheduled');

        $this->em->persist($newBooking);
        $this->em->flush();

        $this->statusManager->changeStatus(
            $newBooking,
            'scheduled',
            $user,
            'Réservation clonée depuis ' . $originalBooking->getReferenceNumber()
        );

        $this->notificationService->notifyBookingCreated($newBooking);

        return $newBooking;
    }

    /**
     * Exporte les réservations en CSV
     */
    public function exportToCsv(array $bookings): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes CSV
        fputcsv($handle, [
            'Référence',
            'Client',
            'Prestataire',
            'Date',
            'Heure',
            'Durée (min)',
            'Montant (€)',
            'Statut',
            'Adresse',
            'Ville',
            'Code Postal',
            'Créé le',
            'Complété le'
        ]);

        // Données
        foreach ($bookings as $booking) {
            fputcsv($handle, [
                $booking->getReferenceNumber(),
                $booking->getClient()->getFullName(),
                $booking->getPrestataire()->getFullName(),
                $booking->getScheduledDate()->format('d/m/Y'),
                $booking->getScheduledTime()->format('H:i'),
                $booking->getDuration(),
                $booking->getAmount(),
                $booking->getStatusLabel(),
                $booking->getAddress(),
                $booking->getCity(),
                $booking->getPostalCode(),
                $booking->getCreatedAt()->format('d/m/Y H:i'),
                $booking->getCompletedAt() ? $booking->getCompletedAt()->format('d/m/Y H:i') : ''
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Obtient un résumé des réservations pour un dashboard
     */
    public function getDashboardSummary(User $user): array
    {
        if ($user instanceof Client) {
            return $this->getClientDashboard($user);
        } elseif ($user instanceof Prestataire) {
            return $this->getPrestataireDashboard($user);
        }

        return [];
    }

    /**
     * Dashboard client
     */
    private function getClientDashboard(Client $client): array
    {
        $upcoming = $this->getUpcomingByClient($client);
        $stats = $this->getStatistics(null, $client);

        return [
            'upcoming_count' => count($upcoming),
            'next_booking' => !empty($upcoming) ? $upcoming[0] : null,
            'total_bookings' => $stats['total'],
            'completed_bookings' => $stats['completed'],
            'total_spent' => $stats['total_revenue'],
            'to_review' => $this->em->getRepository(Booking::class)
                ->findCompletedWithoutReview($client)
        ];
    }

    /**
     * Dashboard prestataire
     */
    private function getPrestataireDashboard(Prestataire $prestataire): array
    {
        $today = $this->getTodayByPrestataire($prestataire);
        $upcoming = $this->getUpcomingByPrestataire($prestataire);
        $stats = $this->getStatistics($prestataire, null);

        return [
            'today_count' => count($today),
            'today_bookings' => $today,
            'upcoming_count' => count($upcoming),
            'next_booking' => !empty($upcoming) ? $upcoming[0] : null,
            'total_bookings' => $stats['total'],
            'completed_bookings' => $stats['completed'],
            'total_earned' => $stats['total_revenue'],
            'to_confirm' => $this->em->getRepository(Booking::class)
                ->countByStatus('scheduled', $prestataire),
            'in_progress' => $this->em->getRepository(Booking::class)
                ->countByStatus('in_progress', $prestataire)
        ];
    }

    /**
     * Obtient les conflits potentiels pour un prestataire
     */
    public function getPotentialConflicts(
        Prestataire $prestataire,
        \DateTimeInterface $date
    ): array {
        $bookings = $this->em->getRepository(Booking::class)
            ->findByDate($date, $prestataire);

        $conflicts = [];
        
        for ($i = 0; $i < count($bookings) - 1; $i++) {
            $current = $bookings[$i];
            $next = $bookings[$i + 1];
            
            $currentEnd = (clone $current->getScheduledDateTime())
                ->modify('+' . $current->getDuration() . ' minutes');
            
            if ($currentEnd > $next->getScheduledDateTime()) {
                $conflicts[] = [
                    'booking1' => $current,
                    'booking2' => $next,
                    'overlap_minutes' => $currentEnd->diff($next->getScheduledDateTime())->i
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Calcule le temps de trajet estimé entre deux réservations
     */
    public function calculateTravelTime(Booking $booking1, Booking $booking2): ?int
    {
        if (!$booking1->getLatitude() || !$booking2->getLatitude()) {
            return null;
        }

        // Calcul de distance simple (formule de Haversine)
        $earthRadius = 6371; // km

        $latFrom = deg2rad((float)$booking1->getLatitude());
        $lonFrom = deg2rad((float)$booking1->getLongitude());
        $latTo = deg2rad((float)$booking2->getLatitude());
        $lonTo = deg2rad((float)$booking2->getLongitude());

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        $distance = $earthRadius * $c;

        // Estimation du temps (30 km/h en ville)
        $travelTimeHours = $distance / 30;
        $travelTimeMinutes = (int)($travelTimeHours * 60);

        return $travelTimeMinutes;
    }

    /**
     * Obtient les suggestions d'optimisation pour un prestataire
     */
    public function getOptimizationSuggestions(
        Prestataire $prestataire,
        \DateTimeInterface $date
    ): array {
        $bookings = $this->em->getRepository(Booking::class)
            ->findByDate($date, $prestataire);

        if (count($bookings) < 2) {
            return [];
        }

        $suggestions = [];

        // Vérifier les temps morts
        for ($i = 0; $i < count($bookings) - 1; $i++) {
            $current = $bookings[$i];
            $next = $bookings[$i + 1];
            
            $currentEnd = (clone $current->getScheduledDateTime())
                ->modify('+' . $current->getDuration() . ' minutes');
            
            $gap = $currentEnd->diff($next->getScheduledDateTime())->i;
            
            if ($gap > 60) {
                $suggestions[] = [
                    'type' => 'large_gap',
                    'message' => "Temps mort de {$gap} minutes entre deux réservations",
                    'booking1' => $current,
                    'booking2' => $next
                ];
            }
        }

        // Vérifier l'ordre géographique optimal
        // TODO: Implémenter algorithme d'optimisation de trajet

        return $suggestions;
    }
}