<?php

namespace App\EventListener;

use App\Event\QuoteAcceptedEvent;
use App\Service\BookingService;
use App\Service\NotificationService;
use App\Service\PaymentService;
use App\Entity\Quote\Quote;
use App\Entity\Booking\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class QuoteAcceptedListener implements EventSubscriberInterface
{
    private BookingService $bookingService;
    private NotificationService $notificationService;
    private PaymentService $paymentService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        BookingService $bookingService,
        NotificationService $notificationService,
        PaymentService $paymentService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->bookingService = $bookingService;
        $this->notificationService = $notificationService;
        $this->paymentService = $paymentService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            QuoteAcceptedEvent::class => [
                ['createBooking', 10],
                ['rejectOtherQuotes', 5],
                ['notifyPrestataire', 0],
                ['notifyClient', 0],
                ['initializePayment', -5],
                ['updateServiceRequestStatus', -10],
            ],
        ];
    }

    /**
     * Crée automatiquement une réservation à partir du devis accepté
     */
    public function createBooking(QuoteAcceptedEvent $event): void
    {
        $quote = $event->getQuote();

        try {
            $this->logger->info('Creating booking from accepted quote', [
                'quote_id' => $quote->getId(),
                'client_id' => $quote->getServiceRequest()->getClient()->getId(),
                'prestataire_id' => $quote->getPrestataire()->getId(),
            ]);

            // Créer la réservation
            $booking = $this->bookingService->createFromQuote($quote);

            // Stocker la réservation dans l'événement pour les autres listeners
            $event->setBooking($booking);

            $this->logger->info('Booking created successfully', [
                'booking_id' => $booking->getId(),
                'quote_id' => $quote->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create booking from quote', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Rejette automatiquement tous les autres devis pour cette demande
     */
    public function rejectOtherQuotes(QuoteAcceptedEvent $event): void
    {
        $quote = $event->getQuote();
        $serviceRequest = $quote->getServiceRequest();

        try {
            $this->logger->info('Rejecting other quotes for service request', [
                'service_request_id' => $serviceRequest->getId(),
                'accepted_quote_id' => $quote->getId(),
            ]);

            // Récupérer tous les autres devis en attente
            $otherQuotes = $this->entityManager
                ->getRepository(Quote::class)
                ->createQueryBuilder('q')
                ->where('q.serviceRequest = :request')
                ->andWhere('q.id != :acceptedQuoteId')
                ->andWhere('q.status = :pending')
                ->setParameter('request', $serviceRequest)
                ->setParameter('acceptedQuoteId', $quote->getId())
                ->setParameter('pending', 'pending')
                ->getQuery()
                ->getResult();

            $rejectedCount = 0;
            foreach ($otherQuotes as $otherQuote) {
                $otherQuote->setStatus('rejected');
                $otherQuote->setRejectedAt(new \DateTime());
                $otherQuote->setRejectionReason('Un autre devis a été accepté par le client');

                // Notifier le prestataire que son devis a été rejeté
                $this->notificationService->notifyQuoteRejected($otherQuote);

                $rejectedCount++;
            }

            $this->entityManager->flush();

            $this->logger->info('Other quotes rejected successfully', [
                'service_request_id' => $serviceRequest->getId(),
                'rejected_count' => $rejectedCount,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to reject other quotes', [
                'service_request_id' => $serviceRequest->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si cette étape échoue
        }
    }

    /**
     * Notifie le prestataire que son devis a été accepté
     */
    public function notifyPrestataire(QuoteAcceptedEvent $event): void
    {
        $quote = $event->getQuote();
        $booking = $event->getBooking();

        try {
            $this->logger->info('Notifying prestataire of quote acceptance', [
                'quote_id' => $quote->getId(),
                'prestataire_id' => $quote->getPrestataire()->getId(),
            ]);

            // Notification push
            $this->notificationService->sendPushNotification(
                $quote->getPrestataire(),
                'Devis accepté ! 🎉',
                sprintf(
                    'Votre devis de %s€ a été accepté. Rendez-vous le %s.',
                    $quote->getAmount(),
                    $booking->getScheduledDate()->format('d/m/Y à H:i')
                ),
                [
                    'type' => 'quote_accepted',
                    'quote_id' => $quote->getId(),
                    'booking_id' => $booking->getId(),
                ]
            );

            // Email de confirmation
            $this->notificationService->sendEmail(
                $quote->getPrestataire()->getEmail(),
                'Votre devis a été accepté',
                'emails/prestataire/quote_accepted.html.twig',
                [
                    'quote' => $quote,
                    'booking' => $booking,
                    'prestataire' => $quote->getPrestataire(),
                    'client' => $quote->getServiceRequest()->getClient(),
                ]
            );

            // SMS de rappel
            $this->notificationService->sendSms(
                $quote->getPrestataire()->getPhone(),
                sprintf(
                    'Votre devis de %s€ a été accepté. RDV le %s chez %s. Consultez l\'app pour plus de détails.',
                    $quote->getAmount(),
                    $booking->getScheduledDate()->format('d/m H:i'),
                    $booking->getClient()->getFirstName()
                )
            );

            $this->logger->info('Prestataire notified successfully', [
                'prestataire_id' => $quote->getPrestataire()->getId(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to notify prestataire', [
                'prestataire_id' => $quote->getPrestataire()->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si la notification échoue
        }
    }

    /**
     * Notifie le client de la confirmation de sa réservation
     */
    public function notifyClient(QuoteAcceptedEvent $event): void
    {
        $quote = $event->getQuote();
        $booking = $event->getBooking();
        $client = $quote->getServiceRequest()->getClient();

        try {
            $this->logger->info('Notifying client of booking confirmation', [
                'quote_id' => $quote->getId(),
                'client_id' => $client->getId(),
            ]);

            // Notification push
            $this->notificationService->sendPushNotification(
                $client,
                'Réservation confirmée ✓',
                sprintf(
                    'Votre réservation avec %s est confirmée pour le %s.',
                    $quote->getPrestataire()->getFirstName(),
                    $booking->getScheduledDate()->format('d/m/Y à H:i')
                ),
                [
                    'type' => 'booking_confirmed',
                    'booking_id' => $booking->getId(),
                ]
            );

            // Email de confirmation détaillé
            $this->notificationService->sendEmail(
                $client->getEmail(),
                'Réservation confirmée',
                'emails/client/booking_confirmed.html.twig',
                [
                    'booking' => $booking,
                    'quote' => $quote,
                    'client' => $client,
                    'prestataire' => $quote->getPrestataire(),
                ]
            );

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
     * Initialise le processus de paiement si nécessaire
     */
    public function initializePayment(QuoteAcceptedEvent $event): void
    {
        $quote = $event->getQuote();
        $booking = $event->getBooking();

        try {
            // Vérifier si un acompte est requis
            if ($quote->getRequiresDeposit() && $quote->getDepositPercentage() > 0) {
                
                $this->logger->info('Initializing deposit payment', [
                    'booking_id' => $booking->getId(),
                    'deposit_percentage' => $quote->getDepositPercentage(),
                ]);

                $depositAmount = ($quote->getAmount() * $quote->getDepositPercentage()) / 100;

                // Créer l'intention de paiement
                $paymentIntent = $this->paymentService->createPaymentIntent(
                    $booking,
                    $depositAmount,
                    'deposit',
                    [
                        'description' => sprintf(
                            'Acompte de %s%% pour réservation #%s',
                            $quote->getDepositPercentage(),
                            $booking->getId()
                        ),
                    ]
                );

                // Envoyer le lien de paiement au client
                $this->notificationService->sendPaymentLink(
                    $booking->getClient(),
                    $paymentIntent,
                    $depositAmount,
                    'deposit'
                );

                $this->logger->info('Deposit payment initialized', [
                    'booking_id' => $booking->getId(),
                    'deposit_amount' => $depositAmount,
                    'payment_intent_id' => $paymentIntent->getId(),
                ]);

            } else {
                $this->logger->info('No deposit required for this booking', [
                    'booking_id' => $booking->getId(),
                ]);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to initialize payment', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            // Ne pas bloquer le processus si le paiement échoue
            // Le client pourra être relancé plus tard
        }
    }

    /**
     * Met à jour le statut de la demande de service
     */
    public function updateServiceRequestStatus(QuoteAcceptedEvent $event): void
    {
        $quote = $event->getQuote();
        $serviceRequest = $quote->getServiceRequest();

        try {
            $this->logger->info('Updating service request status', [
                'service_request_id' => $serviceRequest->getId(),
                'old_status' => $serviceRequest->getStatus(),
            ]);

            // Passer la demande au statut "booked"
            $serviceRequest->setStatus('booked');
            $serviceRequest->setBookedAt(new \DateTime());
            $serviceRequest->setAcceptedQuote($quote);

            $this->entityManager->flush();

            $this->logger->info('Service request status updated', [
                'service_request_id' => $serviceRequest->getId(),
                'new_status' => 'booked',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update service request status', [
                'service_request_id' => $serviceRequest->getId(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}