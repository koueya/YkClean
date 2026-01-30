<?php

namespace App\EventListener;

use App\Event\BookingCreatedEvent;
use App\Service\NotificationService;
use App\Service\PlanningService;
use App\Service\CalendarService;
use App\Entity\Notification\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BookingCreatedListener implements EventSubscriberInterface
{
    private NotificationService $notificationService;
    private PlanningService $planningService;
    private CalendarService $calendarService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        NotificationService $notificationService,
        PlanningService $planningService,
        CalendarService $calendarService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->planningService = $planningService;
        $this->calendarService = $calendarService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BookingCreatedEvent::class => [
                ['blockPrestataireSchedule', 10],
                ['createRecurringBookings', 5],
                ['notifyPrestataire', 0],
                ['notifyClient', 0],
                ['createCalendarEvent', -5],
                ['scheduleReminders', -10],
                ['updatePrestataireStatistics', -15],
            ],
        ];
    }

    /**
     * Bloque le créneau dans le planning du prestataire
     */
    public function blockPrestataireSchedule(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();

        try {
            $this->logger->info('Blocking prestataire schedule for booking', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $booking->getPrestataire()->getId(),
                'scheduled_date' => $booking->getScheduledDate()->format('Y-m-d H:i'),
                'duration' => $booking->getDuration(),
            ]);

            // Bloquer le créneau dans le planning
            $this->planningService->blockTimeSlot(
                $booking->getPrestataire(),
                $booking->getScheduledDate(),
                $booking->getDuration(),
                $booking
            );

            $this->logger->info('Schedule blocked successfully', [
                'booking_id' => $booking->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to block prestataire schedule', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Crée automatiquement les réservations récurrentes si applicable
     */
    public function createRecurringBookings(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();
        $recurrence = $booking->getRecurrence();

        // Si ce n'est pas une réservation récurrente, on passe
        if (!$recurrence) {
            $this->logger->info('No recurrence for this booking', [
                'booking_id' => $booking->getId(),
            ]);
            return;
        }

        try {
            $this->logger->info('Creating recurring bookings', [
                'booking_id' => $booking->getId(),
                'frequency' => $recurrence->getFrequency(),
                'occurrences' => $recurrence->getOccurrences(),
            ]);

            // Générer les prochaines occurrences
            $futureBookings = $this->planningService->createRecurringBookings(
                $booking,
                $recurrence
            );

            $this->logger->info('Recurring bookings created successfully', [
                'parent_booking_id' => $booking->getId(),
                'created_count' => count($futureBookings),
            ]);

            // Stocker les réservations futures dans l'événement
            $event->setFutureBookings($futureBookings);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create recurring bookings', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la création des récurrences échoue
        }
    }

    /**
     * Notifie le prestataire de la nouvelle réservation
     */
    public function notifyPrestataire(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();
        $prestataire = $booking->getPrestataire();

        try {
            $this->logger->info('Notifying prestataire of new booking', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            // Notification push
            $this->notificationService->sendPushNotification(
                $prestataire,
                'Nouvelle réservation 📅',
                sprintf(
                    'Vous avez une nouvelle réservation le %s à %s pour %s.',
                    $booking->getScheduledDate()->format('d/m/Y'),
                    $booking->getScheduledDate()->format('H:i'),
                    $booking->getClient()->getFirstName()
                ),
                [
                    'type' => 'new_booking',
                    'booking_id' => $booking->getId(),
                    'client_name' => $booking->getClient()->getFullName(),
                    'scheduled_date' => $booking->getScheduledDate()->format('c'),
                ]
            );

            // Email de confirmation
            $this->notificationService->sendEmail(
                $prestataire->getEmail(),
                'Nouvelle réservation confirmée',
                'emails/prestataire/booking_created.html.twig',
                [
                    'booking' => $booking,
                    'prestataire' => $prestataire,
                    'client' => $booking->getClient(),
                ]
            );

            // Créer une notification persistante dans la base
            $notification = new Notification();
            $notification->setUser($prestataire);
            $notification->setType('booking_created');
            $notification->setTitle('Nouvelle réservation');
            $notification->setMessage(sprintf(
                'Réservation #%d confirmée pour le %s',
                $booking->getId(),
                $booking->getScheduledDate()->format('d/m/Y à H:i')
            ));
            $notification->setRelatedEntityType('booking');
            $notification->setRelatedEntityId($booking->getId());
            $notification->setCreatedAt(new \DateTime());

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            $this->logger->info('Prestataire notified successfully', [
                'prestataire_id' => $prestataire->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify prestataire', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la notification échoue
        }
    }

    /**
     * Notifie le client de la confirmation de sa réservation
     */
    public function notifyClient(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();
        $client = $booking->getClient();

        try {
            $this->logger->info('Notifying client of booking confirmation', [
                'booking_id' => $booking->getId(),
                'client_id' => $client->getId(),
            ]);

            // Notification push
            $this->notificationService->sendPushNotification(
                $client,
                'Réservation confirmée ✓',
                sprintf(
                    'Votre réservation avec %s est confirmée pour le %s à %s.',
                    $booking->getPrestataire()->getFirstName(),
                    $booking->getScheduledDate()->format('d/m/Y'),
                    $booking->getScheduledDate()->format('H:i')
                ),
                [
                    'type' => 'booking_confirmed',
                    'booking_id' => $booking->getId(),
                    'prestataire_name' => $booking->getPrestataire()->getFullName(),
                    'scheduled_date' => $booking->getScheduledDate()->format('c'),
                ]
            );

            // Email récapitulatif
            $this->notificationService->sendEmail(
                $client->getEmail(),
                'Réservation confirmée - Récapitulatif',
                'emails/client/booking_confirmation.html.twig',
                [
                    'booking' => $booking,
                    'client' => $client,
                    'prestataire' => $booking->getPrestataire(),
                ]
            );

            // SMS de confirmation
            if ($client->getPhone() && $client->getSmsNotificationsEnabled()) {
                $this->notificationService->sendSms(
                    $client->getPhone(),
                    sprintf(
                        'Réservation confirmée avec %s le %s à %s. Montant: %s€. Réf: #%s',
                        $booking->getPrestataire()->getFirstName(),
                        $booking->getScheduledDate()->format('d/m'),
                        $booking->getScheduledDate()->format('H:i'),
                        $booking->getAmount(),
                        $booking->getId()
                    )
                );
            }

            // Créer une notification persistante
            $notification = new Notification();
            $notification->setUser($client);
            $notification->setType('booking_confirmed');
            $notification->setTitle('Réservation confirmée');
            $notification->setMessage(sprintf(
                'Votre réservation #%d est confirmée pour le %s',
                $booking->getId(),
                $booking->getScheduledDate()->format('d/m/Y à H:i')
            ));
            $notification->setRelatedEntityType('booking');
            $notification->setRelatedEntityId($booking->getId());
            $notification->setCreatedAt(new \DateTime());

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            $this->logger->info('Client notified successfully', [
                'client_id' => $client->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify client', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la notification échoue
        }
    }

    /**
     * Crée un événement dans le calendrier (intégration Google Calendar, iCal, etc.)
     */
    public function createCalendarEvent(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();

        try {
            $this->logger->info('Creating calendar event for booking', [
                'booking_id' => $booking->getId(),
            ]);

            // Créer l'événement pour le prestataire
            $prestataireCalendarEvent = $this->calendarService->createEvent(
                $booking->getPrestataire(),
                sprintf('Service chez %s', $booking->getClient()->getFirstName()),
                sprintf(
                    '%s - %s\nAdresse: %s\nMontant: %s€',
                    $booking->getServiceCategory(),
                    $booking->getDescription() ?? '',
                    $booking->getAddress(),
                    $booking->getAmount()
                ),
                $booking->getScheduledDate(),
                $booking->getDuration(),
                $booking->getAddress()
            );

            // Créer l'événement pour le client
            $clientCalendarEvent = $this->calendarService->createEvent(
                $booking->getClient(),
                sprintf('Service avec %s', $booking->getPrestataire()->getFirstName()),
                sprintf(
                    '%s - %s heures\nPrestataire: %s\nMontant: %s€',
                    $booking->getServiceCategory(),
                    $booking->getDuration(),
                    $booking->getPrestataire()->getFullName(),
                    $booking->getAmount()
                ),
                $booking->getScheduledDate(),
                $booking->getDuration(),
                $booking->getAddress()
            );

            $this->logger->info('Calendar events created successfully', [
                'booking_id' => $booking->getId(),
                'prestataire_event_id' => $prestataireCalendarEvent?->getId(),
                'client_event_id' => $clientCalendarEvent?->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create calendar events', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la création de calendrier échoue
        }
    }

    /**
     * Programme les rappels automatiques (24h avant, 2h avant)
     */
    public function scheduleReminders(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();

        try {
            $this->logger->info('Scheduling reminders for booking', [
                'booking_id' => $booking->getId(),
                'scheduled_date' => $booking->getScheduledDate()->format('Y-m-d H:i'),
            ]);

            // Rappel 24h avant pour le client
            $reminder24h = (clone $booking->getScheduledDate())->modify('-24 hours');
            if ($reminder24h > new \DateTime()) {
                $this->notificationService->scheduleNotification(
                    $booking->getClient(),
                    'Rappel : Service demain',
                    sprintf(
                        'Votre service avec %s est prévu demain à %s.',
                        $booking->getPrestataire()->getFirstName(),
                        $booking->getScheduledDate()->format('H:i')
                    ),
                    $reminder24h,
                    [
                        'type' => 'booking_reminder_24h',
                        'booking_id' => $booking->getId(),
                    ]
                );
            }

            // Rappel 2h avant pour le prestataire
            $reminder2h = (clone $booking->getScheduledDate())->modify('-2 hours');
            if ($reminder2h > new \DateTime()) {
                $this->notificationService->scheduleNotification(
                    $booking->getPrestataire(),
                    'Rappel : Service dans 2h',
                    sprintf(
                        'Service chez %s dans 2 heures (%s).',
                        $booking->getClient()->getFirstName(),
                        $booking->getScheduledDate()->format('H:i')
                    ),
                    $reminder2h,
                    [
                        'type' => 'booking_reminder_2h',
                        'booking_id' => $booking->getId(),
                    ]
                );
            }

            // Rappel 2h avant pour le client aussi
            if ($reminder2h > new \DateTime()) {
                $this->notificationService->scheduleNotification(
                    $booking->getClient(),
                    'Rappel : Service dans 2h',
                    sprintf(
                        '%s arrive dans 2 heures pour votre service.',
                        $booking->getPrestataire()->getFirstName()
                    ),
                    $reminder2h,
                    [
                        'type' => 'booking_reminder_2h',
                        'booking_id' => $booking->getId(),
                    ]
                );
            }

            $this->logger->info('Reminders scheduled successfully', [
                'booking_id' => $booking->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule reminders', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la programmation des rappels échoue
        }
    }

    /**
     * Met à jour les statistiques du prestataire
     */
    public function updatePrestataireStatistics(BookingCreatedEvent $event): void
    {
        $booking = $event->getBooking();
        $prestataire = $booking->getPrestataire();

        try {
            $this->logger->info('Updating prestataire statistics', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            // Incrémenter le nombre de réservations
            $prestataire->incrementTotalBookings();

            // Calculer le chiffre d'affaires prévisionnel
            $prestataire->addProjectedRevenue($booking->getAmount());

            // Mettre à jour la dernière réservation
            $prestataire->setLastBookingAt(new \DateTime());

            $this->entityManager->flush();

            $this->logger->info('Prestataire statistics updated successfully', [
                'prestataire_id' => $prestataire->getId(),
                'total_bookings' => $prestataire->getTotalBookings(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update prestataire statistics', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la mise à jour des stats échoue
        }
    }
}