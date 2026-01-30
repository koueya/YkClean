<?php

namespace App\MessageHandler;

use App\Message\ProcessPaymentMessage;
use App\Message\GenerateInvoiceMessage;
use App\Message\TransferCommissionMessage;
use App\Message\SendNotificationMessage;
use App\Service\PaymentService;
use App\Service\StripeService;
use App\Repository\Booking\BookingRepository;
use App\Repository\Payment\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
class ProcessPaymentHandler
{
    private PaymentService $paymentService;
    private StripeService $stripeService;
    private BookingRepository $bookingRepository;
    private PaymentRepository $paymentRepository;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private MessageBusInterface $messageBus;

    public function __construct(
        PaymentService $paymentService,
        StripeService $stripeService,
        BookingRepository $bookingRepository,
        PaymentRepository $paymentRepository,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        MessageBusInterface $messageBus
    ) {
        $this->paymentService = $paymentService;
        $this->stripeService = $stripeService;
        $this->bookingRepository = $bookingRepository;
        $this->paymentRepository = $paymentRepository;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->messageBus = $messageBus;
    }

    /**
     * Traiter le message de paiement
     */
    public function __invoke(ProcessPaymentMessage $message): void
    {
        $bookingId = $message->getBookingId();
        $paymentIntentId = $message->getPaymentIntentId();
        $amount = $message->getAmount();
        $action = $message->getAction();

        $this->logger->info('Processing payment message', [
            'booking_id' => $bookingId,
            'payment_intent_id' => $paymentIntentId,
            'amount' => $amount,
            'action' => $action,
        ]);

        // Récupérer le booking
        $booking = $this->bookingRepository->find($bookingId);

        if (!$booking) {
            $this->logger->error('Booking not found for payment processing', [
                'booking_id' => $bookingId,
            ]);
            throw new \RuntimeException('Booking not found: ' . $bookingId);
        }

        try {
            // Traiter selon l'action
            switch ($action) {
                case 'charge':
                    $this->processCharge($booking, $paymentIntentId, $amount);
                    break;

                case 'refund':
                    $this->processRefund($booking, $paymentIntentId, $amount);
                    break;

                case 'transfer':
                    $this->processTransfer($booking, $amount);
                    break;

                case 'confirm':
                    $this->processConfirmation($booking, $paymentIntentId);
                    break;

                default:
                    throw new \InvalidArgumentException('Unknown payment action: ' . $action);
            }

        } catch (\Exception $e) {
            $this->logger->error('Payment processing failed', [
                'booking_id' => $bookingId,
                'action' => $action,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            // Mettre à jour le statut du booking en cas d'erreur
            $booking->setPaymentStatus('failed');
            $this->entityManager->flush();

            // Notifier le client de l'échec
            $this->notifyPaymentFailed($booking);

            throw $e;
        }
    }

    /**
     * Traiter un paiement (charge)
     */
    private function processCharge($booking, string $paymentIntentId, float $amount): void
    {
        $this->logger->info('Processing charge', [
            'booking_id' => $booking->getId(),
            'amount' => $amount,
        ]);

        // Récupérer le Payment Intent depuis Stripe
        $paymentIntent = $this->stripeService->retrievePaymentIntent($paymentIntentId);

        if (!$paymentIntent) {
            throw new \RuntimeException('Payment Intent not found: ' . $paymentIntentId);
        }

        // Vérifier le statut
        if ($paymentIntent['status'] !== 'succeeded') {
            $this->logger->warning('Payment Intent not succeeded', [
                'payment_intent_id' => $paymentIntentId,
                'status' => $paymentIntent['status'],
            ]);

            // Si le paiement nécessite une action (3D Secure par exemple)
            if ($paymentIntent['status'] === 'requires_action') {
                $booking->setPaymentStatus('requires_action');
                $this->entityManager->flush();

                // Notifier le client qu'une action est requise
                $this->notifyPaymentRequiresAction($booking, $paymentIntent['client_secret']);
                return;
            }

            throw new \RuntimeException('Payment Intent status is: ' . $paymentIntent['status']);
        }

        // Confirmer le paiement
        $confirmed = $this->paymentService->confirmPayment($paymentIntentId);

        if (!$confirmed) {
            throw new \RuntimeException('Failed to confirm payment');
        }

        $booking->setPaymentStatus('paid');
        $this->entityManager->flush();

        $this->logger->info('Charge processed successfully', [
            'booking_id' => $booking->getId(),
            'payment_intent_id' => $paymentIntentId,
        ]);

        // Déclencher les actions post-paiement
        $this->afterSuccessfulPayment($booking);
    }

    /**
     * Traiter un remboursement
     */
    private function processRefund($booking, string $paymentIntentId, float $amount): void
    {
        $this->logger->info('Processing refund', [
            'booking_id' => $booking->getId(),
            'amount' => $amount,
        ]);

        // Récupérer le paiement
        $payment = $this->paymentRepository->findOneBy([
            'booking' => $booking,
            'stripePaymentIntentId' => $paymentIntentId,
        ]);

        if (!$payment) {
            throw new \RuntimeException('Payment not found for booking: ' . $booking->getId());
        }

        // Vérifier que le paiement peut être remboursé
        if ($payment->getStatus() !== 'completed') {
            throw new \RuntimeException('Payment cannot be refunded, status: ' . $payment->getStatus());
        }

        // Effectuer le remboursement
        $refunded = $this->paymentService->refundPayment($payment, $amount, 'Booking cancelled');

        if (!$refunded) {
            throw new \RuntimeException('Failed to process refund');
        }

        $booking->setPaymentStatus('refunded');
        $this->entityManager->flush();

        $this->logger->info('Refund processed successfully', [
            'booking_id' => $booking->getId(),
            'amount' => $amount,
        ]);

        // Notifier le client du remboursement
        $this->notifyRefundProcessed($booking, $amount);
    }

    /**
     * Traiter un transfert vers le prestataire
     */
    private function processTransfer($booking, float $amount): void
    {
        $this->logger->info('Processing transfer to prestataire', [
            'booking_id' => $booking->getId(),
            'amount' => $amount,
        ]);

        $prestataire = $booking->getPrestataire();

        // Vérifier que le prestataire a un compte Stripe Connect
        if (!$prestataire->getStripeConnectedAccountId()) {
            throw new \RuntimeException('Prestataire has no Stripe connected account');
        }

        // Vérifier que le compte est actif
        $accountStatus = $this->stripeService->getAccountStatus($prestataire);
        if (!$accountStatus || !$accountStatus['charges_enabled']) {
            throw new \RuntimeException('Prestataire account is not ready for transfers');
        }

        // Récupérer la commission
        $payment = $this->paymentRepository->findOneBy(['booking' => $booking]);
        if (!$payment || !$payment->getCommission()) {
            throw new \RuntimeException('Commission not found for booking');
        }

        $commission = $payment->getCommission();

        // Dispatcher le message de transfert de commission
        $this->messageBus->dispatch(
            new TransferCommissionMessage(
                $commission->getId(),
                $prestataire->getId(),
                $commission->getPrestataireAmount()
            )
        );

        $this->logger->info('Transfer message dispatched', [
            'commission_id' => $commission->getId(),
            'prestataire_id' => $prestataire->getId(),
        ]);
    }

    /**
     * Traiter la confirmation d'un paiement
     */
    private function processConfirmation($booking, string $paymentIntentId): void
    {
        $this->logger->info('Processing payment confirmation', [
            'booking_id' => $booking->getId(),
            'payment_intent_id' => $paymentIntentId,
        ]);

        $confirmed = $this->paymentService->confirmPayment($paymentIntentId);

        if (!$confirmed) {
            throw new \RuntimeException('Failed to confirm payment');
        }

        $booking->setPaymentStatus('paid');
        $this->entityManager->flush();

        $this->afterSuccessfulPayment($booking);
    }

    /**
     * Actions à effectuer après un paiement réussi
     */
    private function afterSuccessfulPayment($booking): void
    {
        $payment = $this->paymentRepository->findOneBy(['booking' => $booking]);

        if (!$payment) {
            $this->logger->warning('Payment not found after successful charge', [
                'booking_id' => $booking->getId(),
            ]);
            return;
        }

        // 1. Générer la facture
        $this->messageBus->dispatch(
            new GenerateInvoiceMessage(
                $payment->getId(),
                $booking->getId(),
                true // Envoyer par email
            )
        );

        // 2. Notifier le client
        $this->messageBus->dispatch(
            new SendNotificationMessage(
                $booking->getClient()->getId(),
                'payment_confirmed',
                'Paiement confirmé',
                'Votre paiement a été traité avec succès',
                [
                    'booking_id' => $booking->getId(),
                    'amount' => $payment->getAmount(),
                ],
                ['push', 'email']
            )
        );

        // 3. Notifier le prestataire
        $this->messageBus->dispatch(
            new SendNotificationMessage(
                $booking->getPrestataire()->getId(),
                'payment_received',
                'Paiement reçu',
                sprintf('Vous avez reçu un paiement de %.2f€', $payment->getAmount()),
                [
                    'booking_id' => $booking->getId(),
                    'amount' => $payment->getAmount(),
                ],
                ['push', 'email']
            )
        );

        // 4. Planifier le transfert de la commission (après délai de sécurité)
        $transferDate = new \DateTime('+3 days'); // Délai de sécurité
        $this->scheduleCommissionTransfer($booking, $transferDate);

        $this->logger->info('Post-payment actions completed', [
            'booking_id' => $booking->getId(),
        ]);
    }

    /**
     * Planifier le transfert de commission
     */
    private function scheduleCommissionTransfer($booking, \DateTime $transferDate): void
    {
        $payment = $this->paymentRepository->findOneBy(['booking' => $booking]);
        $commission = $payment->getCommission();

        if (!$commission) {
            $this->logger->warning('No commission found for transfer scheduling', [
                'booking_id' => $booking->getId(),
            ]);
            return;
        }

        $commission->setScheduledTransferAt($transferDate);
        $this->entityManager->flush();

        $this->logger->info('Commission transfer scheduled', [
            'commission_id' => $commission->getId(),
            'transfer_date' => $transferDate->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Notifier l'échec d'un paiement
     */
    private function notifyPaymentFailed($booking): void
    {
        $this->messageBus->dispatch(
            new SendNotificationMessage(
                $booking->getClient()->getId(),
                'payment_failed',
                'Échec du paiement',
                'Le paiement de votre réservation a échoué. Veuillez réessayer.',
                [
                    'booking_id' => $booking->getId(),
                ],
                ['push', 'email'],
                10 // Priorité haute
            )
        );
    }

    /**
     * Notifier qu'une action est requise pour le paiement
     */
    private function notifyPaymentRequiresAction($booking, string $clientSecret): void
    {
        $this->messageBus->dispatch(
            new SendNotificationMessage(
                $booking->getClient()->getId(),
                'payment_requires_action',
                'Action requise',
                'Une action est requise pour finaliser votre paiement (ex: authentification 3D Secure)',
                [
                    'booking_id' => $booking->getId(),
                    'client_secret' => $clientSecret,
                ],
                ['push', 'email'],
                10 // Priorité haute
            )
        );
    }

    /**
     * Notifier qu'un remboursement a été effectué
     */
    private function notifyRefundProcessed($booking, float $amount): void
    {
        $this->messageBus->dispatch(
            new SendNotificationMessage(
                $booking->getClient()->getId(),
                'refund_processed',
                'Remboursement effectué',
                sprintf('Un remboursement de %.2f€ a été effectué sur votre compte', $amount),
                [
                    'booking_id' => $booking->getId(),
                    'amount' => $amount,
                ],
                ['push', 'email']
            )
        );
    }

    /**
     * Gérer les erreurs de paiement récurrentes
     */
    private function handleRecurringPaymentError($booking, string $errorMessage): void
    {
        $retryCount = $booking->getPaymentRetryCount() ?? 0;
        $maxRetries = 3;

        if ($retryCount >= $maxRetries) {
            // Trop de tentatives, marquer le booking comme échoué
            $booking->setPaymentStatus('failed');
            $booking->setStatus('cancelled');
            $this->entityManager->flush();

            $this->logger->warning('Payment failed after max retries', [
                'booking_id' => $booking->getId(),
                'retry_count' => $retryCount,
            ]);

            // Notifier l'échec définitif
            $this->notifyPaymentFailed($booking);
        } else {
            // Incrémenter le compteur et réessayer
            $booking->setPaymentRetryCount($retryCount + 1);
            $this->entityManager->flush();

            $this->logger->info('Payment retry scheduled', [
                'booking_id' => $booking->getId(),
                'retry_count' => $retryCount + 1,
            ]);
        }
    }

    /**
     * Vérifier le statut d'un paiement
     */
    private function checkPaymentStatus(string $paymentIntentId): array
    {
        return $this->stripeService->retrievePaymentIntent($paymentIntentId);
    }

    /**
     * Calculer les frais de transaction
     */
    private function calculateTransactionFees(float $amount): array
    {
        // Exemple: frais Stripe de 1.4% + 0.25€
        $stripeFeePercent = 0.014;
        $stripeFeeFixed = 0.25;

        $stripeFee = ($amount * $stripeFeePercent) + $stripeFeeFixed;

        return [
            'amount' => $amount,
            'stripe_fee' => round($stripeFee, 2),
            'net_amount' => round($amount - $stripeFee, 2),
        ];
    }

    /**
     * Logger les métriques de paiement
     */
    private function logPaymentMetrics($booking, string $action, bool $success, ?float $duration = null): void
    {
        $metrics = [
            'booking_id' => $booking->getId(),
            'action' => $action,
            'success' => $success,
            'amount' => $booking->getAmount(),
            'duration_ms' => $duration,
            'timestamp' => new \DateTime(),
        ];

        $this->logger->info('Payment metrics', $metrics);

        // Ici on pourrait enregistrer dans une table de métriques ou envoyer vers un système de monitoring
    }
}