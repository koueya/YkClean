<?php

namespace App\EventListener;

use App\Event\BookingCompletedEvent;
use App\Service\NotificationService;
use App\Service\PaymentService;
use App\Service\RatingService;
use App\Service\CommissionService;
use App\Entity\Payment\Payment;
use App\Entity\Payment\Commission;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class BookingCompletedListener implements EventSubscriberInterface
{
    private NotificationService $notificationService;
    private PaymentService $paymentService;
    private RatingService $ratingService;
    private CommissionService $commissionService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        NotificationService $notificationService,
        PaymentService $paymentService,
        RatingService $ratingService,
        CommissionService $commissionService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->paymentService = $paymentService;
        $this->ratingService = $ratingService;
        $this->commissionService = $commissionService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BookingCompletedEvent::class => [
                ['processPayment', 10],
                ['calculateCommission', 5],
                ['notifyClient', 0],
                ['notifyPrestataire', 0],
                ['requestClientReview', -5],
                ['updatePrestataireStatistics', -10],
                ['checkNextRecurringBooking', -15],
            ],
        ];
    }

    /**
     * Traite le paiement final du service
     */
    public function processPayment(BookingCompletedEvent $event): void
    {
        $booking = $event->getBooking();

        try {
            $this->logger->info('Processing payment for completed booking', [
                'booking_id' => $booking->getId(),
                'amount' => $booking->getAmount(),
            ]);

            // Vérifier si un paiement existe déjà
            $existingPayment = $this->entityManager
                ->getRepository(Payment::class)
                ->findOneBy([
                    'booking' => $booking,
                    'status' => 'completed',
                ]);

            if ($existingPayment) {
                $this->logger->info('Payment already processed for this booking', [
                    'booking_id' => $booking->getId(),
                    'payment_id' => $existingPayment->getId(),
                ]);
                return;
            }

            // Calculer le montant restant à payer (si un acompte a été versé)
            $depositAmount = $booking->getDepositAmount() ?? 0;
            $remainingAmount = $booking->getAmount() - $depositAmount;

            if ($remainingAmount > 0) {
                // Créer le paiement final
                $payment = $this->paymentService->createPayment(
                    $booking,
                    $remainingAmount,
                    'service_completion',
                    [
                        'description' => sprintf(
                            'Paiement pour service #%d - %s',
                            $booking->getId(),
                            $booking->getServiceCategory()
                        ),
                        'auto_capture' => true,
                    ]
                );

                // Capturer le paiement automatiquement
                $this->paymentService->capturePayment($payment);

                $booking->setPayment($payment);
                $this->entityManager->flush();

                $this->logger->info('Payment processed successfully', [
                    'booking_id' => $booking->getId(),
                    'payment_id' => $payment->getId(),
                    'amount' => $remainingAmount,
                ]);
            } else {
                $this->logger->info('No remaining amount to pay (deposit covered full amount)', [
                    'booking_id' => $booking->getId(),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to process payment for completed booking', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Notifier l'équipe en cas d'échec de paiement
            $this->notificationService->notifyAdmins(
                'Échec de paiement automatique',
                sprintf(
                    'Le paiement pour la réservation #%d a échoué. Intervention manuelle requise.',
                    $booking->getId()
                ),
                ['booking_id' => $booking->getId(), 'error' => $e->getMessage()]
            );

            throw $e;
        }
    }

    /**
     * Calcule et enregistre la commission de la plateforme
     */
    public function calculateCommission(BookingCompletedEvent $event): void
    {
        $booking = $event->getBooking();

        try {
            $this->logger->info('Calculating commission for completed booking', [
                'booking_id' => $booking->getId(),
            ]);

            // Calculer la commission (ex: 15% du montant total)
            $commissionRate = $this->commissionService->getCommissionRate($booking->getPrestataire());
            $commissionAmount = ($booking->getAmount() * $commissionRate) / 100;

            // Créer l'enregistrement de commission
            $commission = new Commission();
            $commission->setBooking($booking);
            $commission->setPrestataire($booking->getPrestataire());
            $commission->setAmount($commissionAmount);
            $commission->setRate($commissionRate);
            $commission->setCalculatedAt(new \DateTime());
            $commission->setStatus('pending');
            $commission->setDueDate((new \DateTime())->modify('+30 days'));

            $this->entityManager->persist($commission);
            $this->entityManager->flush();

            $this->logger->info('Commission calculated and recorded', [
                'booking_id' => $booking->getId(),
                'commission_id' => $commission->getId(),
                'amount' => $commissionAmount,
                'rate' => $commissionRate,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate commission', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si le calcul de commission échoue
        }
    }

    /**
     * Notifie le client de la complétion du service
     */
    public function notifyClient(BookingCompletedEvent $event): void
    {
        $booking = $event->getBooking();
        $client = $booking->getClient();

        try {
            $this->logger->info('Notifying client of booking completion', [
                'booking_id' => $booking->getId(),
                'client_id' => $client->getId(),
            ]);

            // Notification push
            $this->notificationService->sendPushNotification(
                $client,
                'Service terminé ✓',
                sprintf(
                    'Votre service avec %s est terminé. Merci de noter votre expérience !',
                    $booking->getPrestataire()->getFirstName()
                ),
                [
                    'type' => 'booking_completed',
                    'booking_id' => $booking->getId(),
                    'action' => 'rate_service',
                ]
            );

            // Email de remerciement
            $this->notificationService->sendEmail(
                $client->getEmail(),
                'Merci pour votre confiance',
                'emails/client/booking_completed.html.twig',
                [
                    'booking' => $booking,
                    'client' => $client,
                    'prestataire' => $booking->getPrestataire(),
                ]
            );

            // Envoyer la facture
            $invoice = $this->paymentService->generateInvoice($booking);
            $this->notificationService->sendInvoiceEmail($client, $invoice);

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
     * Notifie le prestataire de la complétion et du paiement
     */
    public function notifyPrestataire(BookingCompletedEvent $event): void
    {
        $booking = $event->getBooking();
        $prestataire = $booking->getPrestataire();

        try {
            $this->logger->info('Notifying prestataire of booking completion', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            // Calculer le montant net (après commission)
            $commissionRate = $this->commissionService->getCommissionRate($prestataire);
            $commissionAmount = ($booking->getAmount() * $commissionRate) / 100;
            $netAmount = $booking->getAmount() - $commissionAmount;

            // Notification push
            $this->notificationService->sendPushNotification(
                $prestataire,
                'Service complété ! 💰',
                sprintf(
                    'Service terminé. Vous allez recevoir %s€ (montant net).',
                    number_format($netAmount, 2, ',', ' ')
                ),
                [
                    'type' => 'booking_completed',
                    'booking_id' => $booking->getId(),
                    'net_amount' => $netAmount,
                ]
            );

            // Email récapitulatif
            $this->notificationService->sendEmail(
                $prestataire->getEmail(),
                'Service terminé - Récapitulatif de paiement',
                'emails/prestataire/booking_completed.html.twig',
                [
                    'booking' => $booking,
                    'prestataire' => $prestataire,
                    'client' => $booking->getClient(),
                    'grossAmount' => $booking->getAmount(),
                    'commissionRate' => $commissionRate,
                    'commissionAmount' => $commissionAmount,
                    'netAmount' => $netAmount,
                ]
            );

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
     * Demande au client de noter le service
     */
    public function requestClientReview(BookingCompletedEvent $event): void
    {
        $booking = $event->getBooking();
        $client = $booking->getClient();

        try {
            $this->logger->info('Requesting client review', [
                'booking_id' => $booking->getId(),
                'client_id' => $client->getId(),
            ]);

            // Créer une demande d'avis
            $this->ratingService->createReviewRequest($booking);

            // Notification programmée pour demander l'avis (2h après la fin du service)
            $reviewRequestTime = (new \DateTime())->modify('+2 hours');
            
            $this->notificationService->scheduleNotification(
                $client,
                'Comment s\'est passé votre service ? ⭐',
                sprintf(
                    'Partagez votre expérience avec %s et aidez d\'autres clients.',
                    $booking->getPrestataire()->getFirstName()
                ),
                $reviewRequestTime,
                [
                    'type' => 'review_request',
                    'booking_id' => $booking->getId(),
                    'action' => 'leave_review',
                ]
            );

            // Email de demande d'avis (programmé pour le lendemain)
            $emailTime = (new \DateTime())->modify('+1 day');
            $this->notificationService->scheduleEmail(
                $client->getEmail(),
                'Votre avis nous intéresse',
                'emails/client/review_request.html.twig',
                [
                    'booking' => $booking,
                    'client' => $client,
                    'prestataire' => $booking->getPrestataire(),
                ],
                $emailTime
            );

            $this->logger->info('Review request scheduled successfully', [
                'booking_id' => $booking->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to request client review', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la demande d'avis échoue
        }
    }

    /**
     * Met à jour les statistiques du prestataire
     */
    public function updatePrestataireStatistics(BookingCompletedEvent $event): void
    {
        $booking = $event->getBooking();
        $prestataire = $booking->getPrestataire();

        try {
            $this->logger->info('Updating prestataire statistics', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            // Incrémenter le nombre de services complétés
            $prestataire->incrementCompletedBookings();

            // Ajouter au chiffre d'affaires total
            $prestataire->addTotalRevenue($booking->getAmount());

            // Calculer le taux de complétion
            $completionRate = ($prestataire->getCompletedBookings() / $prestataire->getTotalBookings()) * 100;
            $prestataire->setCompletionRate($completionRate);

            // Mettre à jour le dernier service complété
            $prestataire->setLastCompletedBookingAt(new \DateTime());

            // Calculer le temps moyen de service
            $this->updateAverageServiceDuration($prestataire);

            $this->entityManager->flush();

            $this->logger->info('Prestataire statistics updated successfully', [
                'prestataire_id' => $prestataire->getId(),
                'completed_bookings' => $prestataire->getCompletedBookings(),
                'total_revenue' => $prestataire->getTotalRevenue(),
                'completion_rate' => $completionRate,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update prestataire statistics', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la mise à jour des stats échoue
        }
    }

    /**
     * Vérifie et confirme la prochaine réservation récurrente
     */
    public function checkNextRecurringBooking(BookingCompletedEvent $event): void
    {
        $booking = $event->getBooking();

        // Si ce n'est pas une réservation récurrente, on passe
        if (!$booking->getRecurrence()) {
            return;
        }

        try {
            $this->logger->info('Checking next recurring booking', [
                'booking_id' => $booking->getId(),
                'recurrence_id' => $booking->getRecurrence()->getId(),
            ]);

            // Trouver la prochaine occurrence
            $nextBooking = $this->entityManager
                ->getRepository(\App\Entity\Booking\Booking::class)
                ->findNextRecurringBooking($booking->getRecurrence(), $booking->getId());

            if ($nextBooking) {
                // Vérifier la disponibilité du prestataire
                $isAvailable = $this->paymentService->checkPrestataireAvailability(
                    $nextBooking->getPrestataire(),
                    $nextBooking->getScheduledDate(),
                    $nextBooking->getDuration()
                );

                if ($isAvailable) {
                    // Confirmer automatiquement la prochaine réservation
                    $nextBooking->setStatus('confirmed');
                    $this->entityManager->flush();

                    // Notifier le client
                    $this->notificationService->sendPushNotification(
                        $booking->getClient(),
                        'Prochaine réservation confirmée',
                        sprintf(
                            'Votre prochain service avec %s est confirmé pour le %s.',
                            $nextBooking->getPrestataire()->getFirstName(),
                            $nextBooking->getScheduledDate()->format('d/m/Y à H:i')
                        ),
                        [
                            'type' => 'recurring_booking_confirmed',
                            'booking_id' => $nextBooking->getId(),
                        ]
                    );

                    $this->logger->info('Next recurring booking confirmed', [
                        'next_booking_id' => $nextBooking->getId(),
                    ]);
                } else {
                    // Prestataire non disponible, notifier le client
                    $this->notificationService->sendPushNotification(
                        $booking->getClient(),
                        'Attention : Prochaine réservation',
                        sprintf(
                            'Votre prestataire n\'est pas disponible pour le %s. Veuillez choisir une nouvelle date.',
                            $nextBooking->getScheduledDate()->format('d/m/Y')
                        ),
                        [
                            'type' => 'recurring_booking_unavailable',
                            'booking_id' => $nextBooking->getId(),
                            'action' => 'reschedule',
                        ]
                    );

                    $this->logger->warning('Next recurring booking - prestataire unavailable', [
                        'next_booking_id' => $nextBooking->getId(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to check next recurring booking', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus
        }
    }

    /**
     * Calcule le temps moyen de service du prestataire
     */
    private function updateAverageServiceDuration($prestataire): void
    {
        $completedBookings = $this->entityManager
            ->getRepository(\App\Entity\Booking\Booking::class)
            ->findBy([
                'prestataire' => $prestataire,
                'status' => 'completed',
            ]);

        if (empty($completedBookings)) {
            return;
        }

        $totalDuration = 0;
        foreach ($completedBookings as $booking) {
            if ($booking->getActualStartTime() && $booking->getActualEndTime()) {
                $duration = $booking->getActualEndTime()->diff($booking->getActualStartTime());
                $totalDuration += ($duration->h * 60) + $duration->i; // en minutes
            }
        }

        $averageDuration = $totalDuration / count($completedBookings);
        $prestataire->setAverageServiceDuration($averageDuration);
    }
}