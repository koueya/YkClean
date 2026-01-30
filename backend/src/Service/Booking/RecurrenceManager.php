<?php
// src/Service/Booking/RecurrenceManager.php

namespace App\Service\Booking;

use App\Entity\Booking;
use App\Entity\Quote;
use App\Entity\Recurrence;
use App\Entity\Client;
use App\Entity\Prestataire;
use App\Service\Notification\NotificationService;
use App\Service\Planning\AvailabilityManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class RecurrenceManager
{
    public function __construct(
        private EntityManagerInterface $em,
        private AvailabilityManager $availabilityManager,
        private NotificationService $notificationService,
        private BookingValidator $bookingValidator
    ) {}

    /**
     * Crée une récurrence à partir d'un devis
     */
    public function createRecurrence(
        Quote $quote,
        string $frequency,
        \DateTimeInterface $startDate,
        ?\DateTimeInterface $endDate = null,
        ?int $dayOfWeek = null,
        ?int $dayOfMonth = null
    ): array {
        // Valider la fréquence
        $this->validateFrequency($frequency);

        // Valider les paramètres selon la fréquence
        if ($frequency === 'hebdomadaire' && $dayOfWeek === null) {
            throw new BadRequestHttpException('Le jour de la semaine est requis pour une récurrence hebdomadaire');
        }

        if ($frequency === 'mensuel' && $dayOfMonth === null) {
            throw new BadRequestHttpException('Le jour du mois est requis pour une récurrence mensuelle');
        }

        // Créer la récurrence
        $recurrence = new Recurrence();
        $recurrence->setClient($quote->getServiceRequest()->getClient());
        $recurrence->setPrestataire($quote->getPrestataire());
        $recurrence->setCategory($quote->getServiceRequest()->getCategory());
        $recurrence->setFrequency($frequency);
        $recurrence->setDayOfWeek($dayOfWeek);
        $recurrence->setDayOfMonth($dayOfMonth);
        $recurrence->setTime(\DateTime::createFromFormat('H:i:s', $quote->getProposedDate()->format('H:i:s')));
        $recurrence->setDuration($quote->getProposedDuration());
        $recurrence->setAddress($quote->getServiceRequest()->getAddress());
        $recurrence->setAmount($quote->getAmount());
        $recurrence->setStartDate($startDate);
        $recurrence->setEndDate($endDate);
        $recurrence->setNextOccurrence($startDate);
        $recurrence->setIsActive(true);

        $this->em->persist($recurrence);

        // Créer la première réservation
        $firstBooking = $this->createBookingFromRecurrence($recurrence, $startDate);
        $firstBooking->setQuote($quote);
        $firstBooking->setServiceRequest($quote->getServiceRequest());

        $this->em->flush();

        return [
            'recurrence' => $recurrence,
            'first_booking' => $firstBooking
        ];
    }

    /**
     * Crée une réservation à partir d'une récurrence
     */
    private function createBookingFromRecurrence(
        Recurrence $recurrence,
        \DateTimeInterface $date
    ): Booking {
        $booking = new Booking();
        $booking->setClient($recurrence->getClient());
        $booking->setPrestataire($recurrence->getPrestataire());
        $booking->setScheduledDate($date);
        $booking->setScheduledTime($recurrence->getTime());
        $booking->setDuration($recurrence->getDuration());
        $booking->setAddress($recurrence->getAddress());
        $booking->setAmount($recurrence->getAmount());
        $booking->setRecurrence($recurrence);
        $booking->setStatus('scheduled');

        $this->em->persist($booking);

        return $booking;
    }

    /**
     * Génère les prochaines occurrences d'une récurrence
     */
    public function generateNextOccurrences(Recurrence $recurrence, int $count = 1): array
    {
        if (!$recurrence->isActive()) {
            throw new BadRequestHttpException('La récurrence n\'est pas active');
        }

        $bookings = [];
        $currentDate = $recurrence->getNextOccurrence();

        for ($i = 0; $i < $count; $i++) {
            // Calculer la prochaine date
            $nextDate = $this->calculateNextOccurrence($recurrence, $currentDate);

            // Vérifier si on dépasse la date de fin
            if ($recurrence->getEndDate() && $nextDate > $recurrence->getEndDate()) {
                // Désactiver la récurrence si la date de fin est atteinte
                $recurrence->setIsActive(false);
                break;
            }

            // Vérifier la disponibilité
            try {
                $this->bookingValidator->validateAvailability(
                    $recurrence->getPrestataire(),
                    $nextDate,
                    $recurrence->getTime(),
                    $recurrence->getDuration()
                );

                // Créer la réservation
                $booking = $this->createBookingFromRecurrence($recurrence, $nextDate);
                $bookings[] = $booking;

                // Mettre à jour la prochaine occurrence
                $recurrence->setNextOccurrence($nextDate);
                $currentDate = $nextDate;

            } catch (\Exception $e) {
                // Si le prestataire n'est pas disponible, passer à la prochaine occurrence
                $currentDate = $nextDate;
                continue;
            }
        }

        $this->em->flush();

        // Envoyer les notifications
        foreach ($bookings as $booking) {
            $this->notificationService->notifyBookingCreated($booking);
        }

        return $bookings;
    }

    /**
     * Calcule la prochaine occurrence selon la fréquence
     */
    public function calculateNextOccurrence(
        Recurrence $recurrence,
        \DateTimeInterface $currentDate
    ): \DateTimeInterface {
        $nextDate = clone $currentDate;

        switch ($recurrence->getFrequency()) {
            case 'hebdomadaire':
                $nextDate->modify('+1 week');
                break;

            case 'bihebdomadaire':
                $nextDate->modify('+2 weeks');
                break;

            case 'mensuel':
                $nextDate->modify('+1 month');
                // Ajuster au bon jour du mois si nécessaire
                if ($recurrence->getDayOfMonth()) {
                    $nextDate->setDate(
                        (int)$nextDate->format('Y'),
                        (int)$nextDate->format('m'),
                        min($recurrence->getDayOfMonth(), (int)$nextDate->format('t'))
                    );
                }
                break;

            default:
                throw new BadRequestHttpException('Fréquence invalide: ' . $recurrence->getFrequency());
        }

        return $nextDate;
    }

    /**
     * Met à jour une récurrence
     */
    public function updateRecurrence(
        Recurrence $recurrence,
        array $data,
        bool $updateFutureBookings = false
    ): Recurrence {
        // Mettre à jour les champs
        if (isset($data['frequency'])) {
            $this->validateFrequency($data['frequency']);
            $recurrence->setFrequency($data['frequency']);
        }

        if (isset($data['day_of_week'])) {
            $recurrence->setDayOfWeek($data['day_of_week']);
        }

        if (isset($data['day_of_month'])) {
            $recurrence->setDayOfMonth($data['day_of_month']);
        }

        if (isset($data['time'])) {
            $recurrence->setTime($data['time']);
        }

        if (isset($data['duration'])) {
            $recurrence->setDuration($data['duration']);
        }

        if (isset($data['amount'])) {
            $recurrence->setAmount($data['amount']);
        }

        if (isset($data['address'])) {
            $recurrence->setAddress($data['address']);
        }

        if (isset($data['end_date'])) {
            $recurrence->setEndDate($data['end_date']);
        }

        $this->em->flush();

        // Mettre à jour les réservations futures si demandé
        if ($updateFutureBookings) {
            $this->updateFutureBookings($recurrence, $data);
        }

        return $recurrence;
    }

    /**
     * Met à jour toutes les réservations futures d'une récurrence
     */
    private function updateFutureBookings(Recurrence $recurrence, array $data): void
    {
        $now = new \DateTime();
        
        $futureBookings = $this->em->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->andWhere('b.recurrence = :recurrence')
            ->andWhere('b.scheduledDate > :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('recurrence', $recurrence)
            ->setParameter('now', $now)
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->getQuery()
            ->getResult();

        foreach ($futureBookings as $booking) {
            if (isset($data['time'])) {
                $booking->setScheduledTime($data['time']);
            }

            if (isset($data['duration'])) {
                $booking->setDuration($data['duration']);
            }

            if (isset($data['amount'])) {
                $booking->setAmount($data['amount']);
            }

            if (isset($data['address'])) {
                $booking->setAddress($data['address']);
            }
        }

        $this->em->flush();

        // Notifier les changements
        foreach ($futureBookings as $booking) {
            $this->notificationService->notifyBookingUpdated($booking);
        }
    }

    /**
     * Suspend une récurrence temporairement
     */
    public function suspend(
        Recurrence $recurrence,
        \DateTimeInterface $suspendUntil
    ): Recurrence {
        // Annuler toutes les réservations futures jusqu'à la date de reprise
        $bookingsToCancel = $this->em->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->andWhere('b.recurrence = :recurrence')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.scheduledDate <= :suspendUntil')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('recurrence', $recurrence)
            ->setParameter('now', new \DateTime())
            ->setParameter('suspendUntil', $suspendUntil)
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->getQuery()
            ->getResult();

        foreach ($bookingsToCancel as $booking) {
            $booking->setStatus('cancelled');
            $booking->setCancellationReason('Récurrence suspendue temporairement');
            $this->notificationService->notifyBookingCancelled($booking);
        }

        // Mettre à jour la prochaine occurrence
        $recurrence->setNextOccurrence($suspendUntil);

        $this->em->flush();

        return $recurrence;
    }

    /**
     * Désactive une récurrence et annule toutes les réservations futures
     */
    public function cancel(Recurrence $recurrence, string $reason): array
    {
        $recurrence->setIsActive(false);

        // Annuler toutes les réservations futures
        $futureBookings = $this->em->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->andWhere('b.recurrence = :recurrence')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('recurrence', $recurrence)
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->getQuery()
            ->getResult();

        foreach ($futureBookings as $booking) {
            $booking->setStatus('cancelled');
            $booking->setCancellationReason($reason);
            $this->notificationService->notifyBookingCancelled($booking);
        }

        $this->em->flush();

        return $futureBookings;
    }

    /**
     * Réactive une récurrence
     */
    public function reactivate(Recurrence $recurrence): Recurrence
    {
        $recurrence->setIsActive(true);
        
        // Recalculer la prochaine occurrence
        $now = new \DateTime();
        $nextOccurrence = $this->calculateNextOccurrence($recurrence, $now);
        $recurrence->setNextOccurrence($nextOccurrence);

        $this->em->flush();

        return $recurrence;
    }

    /**
     * Obtient toutes les récurrences actives d'un client
     */
    public function getActiveRecurrencesByClient(Client $client): array
    {
        return $this->em->getRepository(Recurrence::class)
            ->createQueryBuilder('r')
            ->andWhere('r.client = :client')
            ->andWhere('r.isActive = :active')
            ->setParameter('client', $client)
            ->setParameter('active', true)
            ->orderBy('r.nextOccurrence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtient toutes les récurrences d'un prestataire
     */
    public function getRecurrencesByPrestataire(Prestataire $prestataire): array
    {
        return $this->em->getRepository(Recurrence::class)
            ->createQueryBuilder('r')
            ->andWhere('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.isActive', 'DESC')
            ->addOrderBy('r.nextOccurrence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtient les récurrences qui nécessitent une génération de réservation
     */
    public function getRecurrencesNeedingGeneration(): array
    {
        $now = new \DateTime();
        $threshold = (clone $now)->modify('+7 days'); // Générer 7 jours à l'avance

        return $this->em->getRepository(Recurrence::class)
            ->createQueryBuilder('r')
            ->andWhere('r.isActive = :active')
            ->andWhere('r.nextOccurrence <= :threshold')
            ->andWhere('r.endDate IS NULL OR r.endDate >= :now')
            ->setParameter('active', true)
            ->setParameter('threshold', $threshold)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();
    }

    /**
     * Génère automatiquement les réservations pour toutes les récurrences actives
     */
    public function generateAllPendingOccurrences(): array
    {
        $recurrences = $this->getRecurrencesNeedingGeneration();
        $generated = [];

        foreach ($recurrences as $recurrence) {
            try {
                $bookings = $this->generateNextOccurrences($recurrence, 1);
                $generated[$recurrence->getId()] = $bookings;
            } catch (\Exception $e) {
                // Logger l'erreur mais continuer
                error_log("Erreur lors de la génération de la récurrence {$recurrence->getId()}: {$e->getMessage()}");
            }
        }

        return $generated;
    }

    /**
     * Obtient les statistiques d'une récurrence
     */
    public function getRecurrenceStatistics(Recurrence $recurrence): array
    {
        $bookings = $this->em->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->andWhere('b.recurrence = :recurrence')
            ->setParameter('recurrence', $recurrence)
            ->getQuery()
            ->getResult();

        $stats = [
            'total_bookings' => count($bookings),
            'completed' => 0,
            'scheduled' => 0,
            'cancelled' => 0,
            'total_amount' => '0.00',
            'average_duration' => 0,
        ];

        $totalDuration = 0;

        foreach ($bookings as $booking) {
            switch ($booking->getStatus()) {
                case 'completed':
                    $stats['completed']++;
                    $stats['total_amount'] = bcadd($stats['total_amount'], $booking->getAmount(), 2);
                    break;
                case 'scheduled':
                case 'confirmed':
                    $stats['scheduled']++;
                    break;
                case 'cancelled':
                    $stats['cancelled']++;
                    break;
            }

            if ($booking->getActualDuration()) {
                $totalDuration += $booking->getActualDuration();
            }
        }

        if ($stats['completed'] > 0) {
            $stats['average_duration'] = $totalDuration / $stats['completed'];
        }

        return $stats;
    }

    /**
     * Valide une fréquence
     */
    private function validateFrequency(string $frequency): void
    {
        $validFrequencies = ['hebdomadaire', 'bihebdomadaire', 'mensuel'];
        
        if (!in_array($frequency, $validFrequencies)) {
            throw new BadRequestHttpException(
                'Fréquence invalide. Valeurs acceptées: ' . implode(', ', $validFrequencies)
            );
        }
    }

    /**
     * Obtient le prochain rendez-vous d'une récurrence
     */
    public function getNextBooking(Recurrence $recurrence): ?Booking
    {
        $now = new \DateTime();

        return $this->em->getRepository(Booking::class)
            ->createQueryBuilder('b')
            ->andWhere('b.recurrence = :recurrence')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('recurrence', $recurrence)
            ->setParameter('now', $now)
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Clone une récurrence pour un nouveau prestataire
     */
    public function cloneForNewPrestataire(
        Recurrence $originalRecurrence,
        Prestataire $newPrestataire
    ): Recurrence {
        $newRecurrence = new Recurrence();
        $newRecurrence->setClient($originalRecurrence->getClient());
        $newRecurrence->setPrestataire($newPrestataire);
        $newRecurrence->setCategory($originalRecurrence->getCategory());
        $newRecurrence->setFrequency($originalRecurrence->getFrequency());
        $newRecurrence->setDayOfWeek($originalRecurrence->getDayOfWeek());
        $newRecurrence->setDayOfMonth($originalRecurrence->getDayOfMonth());
        $newRecurrence->setTime($originalRecurrence->getTime());
        $newRecurrence->setDuration($originalRecurrence->getDuration());
        $newRecurrence->setAddress($originalRecurrence->getAddress());
        $newRecurrence->setAmount($originalRecurrence->getAmount());
        $newRecurrence->setStartDate(new \DateTime());
        $newRecurrence->setNextOccurrence(new \DateTime());
        $newRecurrence->setIsActive(true);

        $this->em->persist($newRecurrence);
        $this->em->flush();

        return $newRecurrence;
    }
}