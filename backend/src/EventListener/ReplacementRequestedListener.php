<?php

namespace App\EventListener;

use App\Event\ReplacementRequestedEvent;
use App\Service\NotificationService;
use App\Service\MatchingService;
use App\Service\PlanningService;
use App\Entity\Planning\Replacement;
use App\Entity\Notification\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ReplacementRequestedListener implements EventSubscriberInterface
{
    private NotificationService $notificationService;
    private MatchingService $matchingService;
    private PlanningService $planningService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        NotificationService $notificationService,
        MatchingService $matchingService,
        PlanningService $planningService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->matchingService = $matchingService;
        $this->planningService = $planningService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ReplacementRequestedEvent::class => [
                ['findAvailablePrestataires', 10],
                ['notifyClient', 5],
                ['notifySuitablePrestataires', 0],
                ['updateOriginalPrestataireSchedule', -5],
                ['createReplacementRecord', -10],
                ['scheduleAutoCancellation', -15],
            ],
        ];
    }

    /**
     * Trouve les prestataires disponibles pour le remplacement
     */
    public function findAvailablePrestataires(ReplacementRequestedEvent $event): void
    {
        $replacement = $event->getReplacement();
        $booking = $replacement->getOriginalBooking();

        try {
            $this->logger->info('Finding available prestataires for replacement', [
                'replacement_id' => $replacement->getId(),
                'booking_id' => $booking->getId(),
                'scheduled_date' => $booking->getScheduledDate()->format('Y-m-d H:i'),
            ]);

            // Utiliser l'algorithme de matching pour trouver des prestataires
            $suitablePrestataires = $this->matchingService->findReplacementPrestataires(
                $booking->getServiceCategory(),
                $booking->getAddress(),
                $booking->getScheduledDate(),
                $booking->getDuration(),
                [
                    'exclude_prestataire_id' => $replacement->getOriginalPrestataire()->getId(),
                    'min_rating' => 4.0,
                    'max_distance_km' => 15,
                    'same_category_experience' => true,
                ]
            );

            $this->logger->info('Found suitable prestataires for replacement', [
                'replacement_id' => $replacement->getId(),
                'count' => count($suitablePrestataires),
            ]);

            // Stocker les prestataires trouvés dans l'événement
            $event->setSuitablePrestataires($suitablePrestataires);

            // Si aucun prestataire disponible, notifier immédiatement
            if (empty($suitablePrestataires)) {
                $this->logger->warning('No suitable prestataires found for replacement', [
                    'replacement_id' => $replacement->getId(),
                ]);

                $replacement->setStatus('no_replacement_found');
                $this->entityManager->flush();

                // Notifier le client qu'aucun remplaçant n'est disponible
                $this->notifyClientNoReplacementFound($booking);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to find available prestataires', [
                'replacement_id' => $replacement->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Notifie le client du besoin de remplacement
     */
    public function notifyClient(ReplacementRequestedEvent $event): void
    {
        $replacement = $event->getReplacement();
        $booking = $replacement->getOriginalBooking();
        $client = $booking->getClient();

        try {
            $this->logger->info('Notifying client of replacement need', [
                'replacement_id' => $replacement->getId(),
                'client_id' => $client->getId(),
            ]);

            // Notification push
            $this->notificationService->sendPushNotification(
                $client,
                'Changement de prestataire',
                sprintf(
                    '%s ne peut plus assurer votre service du %s. Nous recherchons un remplaçant.',
                    $replacement->getOriginalPrestataire()->getFirstName(),
                    $booking->getScheduledDate()->format('d/m/Y à H:i')
                ),
                [
                    'type' => 'replacement_requested',
                    'replacement_id' => $replacement->getId(),
                    'booking_id' => $booking->getId(),
                    'reason' => $replacement->getReason(),
                ]
            );

            // Email détaillé
            $this->notificationService->sendEmail(
                $client->getEmail(),
                'Changement de prestataire pour votre réservation',
                'emails/client/replacement_requested.html.twig',
                [
                    'replacement' => $replacement,
                    'booking' => $booking,
                    'client' => $client,
                    'originalPrestataire' => $replacement->getOriginalPrestataire(),
                    'reason' => $replacement->getReason(),
                ]
            );

            // SMS d'alerte
            if ($client->getPhone() && $client->getSmsNotificationsEnabled()) {
                $this->notificationService->sendSms(
                    $client->getPhone(),
                    sprintf(
                        'Important: %s ne peut plus assurer votre service du %s. Nous recherchons un remplaçant. Consultez l\'app pour plus d\'infos.',
                        $replacement->getOriginalPrestataire()->getFirstName(),
                        $booking->getScheduledDate()->format('d/m H:i')
                    )
                );
            }

            // Créer une notification persistante
            $notification = new Notification();
            $notification->setUser($client);
            $notification->setType('replacement_requested');
            $notification->setTitle('Changement de prestataire');
            $notification->setMessage(sprintf(
                'Votre prestataire ne peut plus assurer le service du %s. Nous recherchons un remplaçant.',
                $booking->getScheduledDate()->format('d/m/Y à H:i')
            ));
            $notification->setRelatedEntityType('replacement');
            $notification->setRelatedEntityId($replacement->getId());
            $notification->setPriority('high');
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
     * Notifie les prestataires disponibles pour le remplacement
     */
    public function notifySuitablePrestataires(ReplacementRequestedEvent $event): void
    {
        $replacement = $event->getReplacement();
        $booking = $replacement->getOriginalBooking();
        $suitablePrestataires = $event->getSuitablePrestataires();

        if (empty($suitablePrestataires)) {
            $this->logger->info('No suitable prestataires to notify', [
                'replacement_id' => $replacement->getId(),
            ]);
            return;
        }

        try {
            $this->logger->info('Notifying suitable prestataires', [
                'replacement_id' => $replacement->getId(),
                'prestataires_count' => count($suitablePrestataires),
            ]);

            $notifiedCount = 0;

            foreach ($suitablePrestataires as $prestataire) {
                try {
                    // Calculer le montant (peut être légèrement majoré pour le remplacement)
                    $replacementAmount = $booking->getAmount() * 1.1; // +10% bonus remplacement

                    // Notification push
                    $this->notificationService->sendPushNotification(
                        $prestataire,
                        'Opportunité de remplacement 🔄',
                        sprintf(
                            'Remplacement urgent le %s - %s€. %s heures de service.',
                            $booking->getScheduledDate()->format('d/m à H:i'),
                            number_format($replacementAmount, 2, ',', ' '),
                            $booking->getDuration()
                        ),
                        [
                            'type' => 'replacement_opportunity',
                            'replacement_id' => $replacement->getId(),
                            'booking_id' => $booking->getId(),
                            'amount' => $replacementAmount,
                            'scheduled_date' => $booking->getScheduledDate()->format('c'),
                        ]
                    );

                    // Email détaillé
                    $this->notificationService->sendEmail(
                        $prestataire->getEmail(),
                        'Nouvelle opportunité de remplacement',
                        'emails/prestataire/replacement_opportunity.html.twig',
                        [
                            'replacement' => $replacement,
                            'booking' => $booking,
                            'prestataire' => $prestataire,
                            'replacementAmount' => $replacementAmount,
                            'distance' => $this->calculateDistance(
                                $prestataire->getAddress(),
                                $booking->getAddress()
                            ),
                        ]
                    );

                    // Créer notification persistante
                    $notification = new Notification();
                    $notification->setUser($prestataire);
                    $notification->setType('replacement_opportunity');
                    $notification->setTitle('Opportunité de remplacement');
                    $notification->setMessage(sprintf(
                        'Remplacement disponible le %s - %s€',
                        $booking->getScheduledDate()->format('d/m/Y à H:i'),
                        number_format($replacementAmount, 2, ',', ' ')
                    ));
                    $notification->setRelatedEntityType('replacement');
                    $notification->setRelatedEntityId($replacement->getId());
                    $notification->setPriority('high');
                    $notification->setCreatedAt(new \DateTime());
                    $notification->setExpiresAt((new \DateTime())->modify('+24 hours'));

                    $this->entityManager->persist($notification);

                    $notifiedCount++;

                } catch (\Exception $e) {
                    $this->logger->error('Failed to notify individual prestataire', [
                        'prestataire_id' => $prestataire->getId(),
                        'error' => $e->getMessage(),
                    ]);
                    // Continue avec les autres prestataires
                }
            }

            $this->entityManager->flush();

            $this->logger->info('Prestataires notified successfully', [
                'replacement_id' => $replacement->getId(),
                'notified_count' => $notifiedCount,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify suitable prestataires', [
                'replacement_id' => $replacement->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus
        }
    }

    /**
     * Met à jour le planning du prestataire original
     */
    public function updateOriginalPrestataireSchedule(ReplacementRequestedEvent $event): void
    {
        $replacement = $event->getReplacement();
        $booking = $replacement->getOriginalBooking();
        $originalPrestataire = $replacement->getOriginalPrestataire();

        try {
            $this->logger->info('Updating original prestataire schedule', [
                'replacement_id' => $replacement->getId(),
                'prestataire_id' => $originalPrestataire->getId(),
            ]);

            // Libérer le créneau dans le planning du prestataire original
            $this->planningService->unblockTimeSlot(
                $originalPrestataire,
                $booking->getScheduledDate(),
                $booking->getDuration()
            );

            // Marquer une absence/indisponibilité si c'est une raison de santé ou urgence
            if (in_array($replacement->getReason(), ['health', 'emergency', 'personal'])) {
                $this->planningService->markUnavailability(
                    $originalPrestataire,
                    $booking->getScheduledDate(),
                    $replacement->getReason()
                );
            }

            $this->logger->info('Original prestataire schedule updated', [
                'prestataire_id' => $originalPrestataire->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update original prestataire schedule', [
                'prestataire_id' => $originalPrestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus
        }
    }

    /**
     * Crée l'enregistrement de remplacement dans la base
     */
    public function createReplacementRecord(ReplacementRequestedEvent $event): void
    {
        $replacement = $event->getReplacement();

        try {
            $this->logger->info('Creating replacement record', [
                'replacement_id' => $replacement->getId(),
            ]);

            // Le remplacement a déjà été créé, on met juste à jour le statut
            $replacement->setStatus('pending');
            $replacement->setRequestedAt(new \DateTime());
            
            // Définir une date limite de recherche (24h avant le service)
            $deadline = (clone $replacement->getOriginalBooking()->getScheduledDate())
                ->modify('-24 hours');
            $replacement->setDeadline($deadline);

            $this->entityManager->flush();

            $this->logger->info('Replacement record created successfully', [
                'replacement_id' => $replacement->getId(),
                'status' => 'pending',
                'deadline' => $deadline->format('Y-m-d H:i'),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create replacement record', [
                'replacement_id' => $replacement->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Programme l'annulation automatique si aucun remplaçant trouvé
     */
    public function scheduleAutoCancellation(ReplacementRequestedEvent $event): void
    {
        $replacement = $event->getReplacement();
        $booking = $replacement->getOriginalBooking();

        try {
            $this->logger->info('Scheduling auto-cancellation for replacement', [
                'replacement_id' => $replacement->getId(),
                'booking_id' => $booking->getId(),
            ]);

            // Si aucun remplaçant n'est trouvé 24h avant le service, annuler automatiquement
            $cancellationTime = (clone $booking->getScheduledDate())->modify('-24 hours');

            // Vérifier que la date n'est pas déjà passée
            if ($cancellationTime <= new \DateTime()) {
                $this->logger->warning('Cancellation time is in the past, booking should be cancelled immediately', [
                    'replacement_id' => $replacement->getId(),
                ]);

                // Proposer l'annulation immédiate au client
                $this->notificationService->sendPushNotification(
                    $booking->getClient(),
                    'Action requise - Annulation de réservation',
                    'Aucun remplaçant disponible pour votre service. Souhaitez-vous annuler ou reprogrammer ?',
                    [
                        'type' => 'replacement_urgent_decision',
                        'replacement_id' => $replacement->getId(),
                        'booking_id' => $booking->getId(),
                        'actions' => ['cancel', 'reschedule'],
                    ]
                );

                return;
            }

            // Programmer une notification de vérification
            $this->notificationService->scheduleSystemTask(
                'check_replacement_status',
                [
                    'replacement_id' => $replacement->getId(),
                    'booking_id' => $booking->getId(),
                ],
                $cancellationTime
            );

            $this->logger->info('Auto-cancellation check scheduled', [
                'replacement_id' => $replacement->getId(),
                'check_time' => $cancellationTime->format('Y-m-d H:i'),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to schedule auto-cancellation', [
                'replacement_id' => $replacement->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus
        }
    }

    /**
     * Notifie le client qu'aucun remplaçant n'a été trouvé
     */
    private function notifyClientNoReplacementFound($booking): void
    {
        try {
            $client = $booking->getClient();

            $this->notificationService->sendPushNotification(
                $client,
                'Aucun remplaçant disponible',
                sprintf(
                    'Nous n\'avons pas trouvé de remplaçant pour votre service du %s. Que souhaitez-vous faire ?',
                    $booking->getScheduledDate()->format('d/m/Y')
                ),
                [
                    'type' => 'no_replacement_found',
                    'booking_id' => $booking->getId(),
                    'actions' => ['cancel', 'reschedule'],
                ]
            );

            $this->notificationService->sendEmail(
                $client->getEmail(),
                'Aucun remplaçant disponible - Action requise',
                'emails/client/no_replacement_found.html.twig',
                [
                    'booking' => $booking,
                    'client' => $client,
                ]
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify client of no replacement', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Calcule la distance entre deux adresses
     */
    private function calculateDistance(string $address1, string $address2): float
    {
        try {
            // Utiliser un service de géolocalisation pour calculer la distance
            // Ici, simplification avec un service fictif
            return $this->matchingService->calculateDistance($address1, $address2);
        } catch (\Exception $e) {
            $this->logger->warning('Failed to calculate distance', [
                'error' => $e->getMessage(),
            ]);
            return 0.0;
        }
    }
}