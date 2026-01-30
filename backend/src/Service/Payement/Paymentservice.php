<?php

namespace App\Service;

use App\Entity\Booking\Booking;
use App\Entity\Payment\Payment;
use App\Entity\Payment\Invoice;
use App\Entity\Payment\Commission;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class PaymentService
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private StripeClient $stripe;
    private float $platformCommissionRate;
    private string $currency;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $stripeSecretKey,
        float $platformCommissionRate = 0.15, // 15% de commission par défaut
        string $currency = 'eur'
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->stripe = new StripeClient($stripeSecretKey);
        $this->platformCommissionRate = $platformCommissionRate;
        $this->currency = $currency;
    }

    /**
     * Créer un Payment Intent pour un booking
     */
    public function createPaymentIntent(Booking $booking): array
    {
        try {
            $amount = $booking->getAmount();
            $client = $booking->getClient();

            // Créer le Payment Intent Stripe
            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $this->convertToStripeAmount($amount),
                'currency' => $this->currency,
                'customer' => $client->getStripeCustomerId(),
                'metadata' => [
                    'booking_id' => $booking->getId(),
                    'client_id' => $client->getId(),
                    'prestataire_id' => $booking->getPrestataire()->getId(),
                ],
                'description' => sprintf(
                    'Service %s - Booking #%d',
                    $booking->getServiceRequest()?->getCategory()->getName() ?? 'Service',
                    $booking->getId()
                ),
                'automatic_payment_methods' => [
                    'enabled' => true,
                ],
            ]);

            // Enregistrer le paiement en base
            $payment = new Payment();
            $payment->setBooking($booking);
            $payment->setAmount($amount);
            $payment->setStatus('pending');
            $payment->setStripePaymentIntentId($paymentIntent->id);
            $payment->setCreatedAt(new \DateTime());

            $this->entityManager->persist($payment);
            $this->entityManager->flush();

            $this->logger->info('Payment intent created', [
                'booking_id' => $booking->getId(),
                'payment_intent_id' => $paymentIntent->id,
                'amount' => $amount,
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'client_secret' => $paymentIntent->client_secret,
                'amount' => $amount,
            ];

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error while creating payment intent', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        } catch (\Exception $e) {
            $this->logger->error('Error while creating payment intent', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Une erreur est survenue lors de la création du paiement',
            ];
        }
    }

    /**
     * Confirmer un paiement après succès
     */
    public function confirmPayment(string $paymentIntentId): bool
    {
        try {
            // Récupérer le Payment Intent depuis Stripe
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            // Trouver le paiement en base
            $payment = $this->entityManager
                ->getRepository(Payment::class)
                ->findOneBy(['stripePaymentIntentId' => $paymentIntentId]);

            if (!$payment) {
                $this->logger->error('Payment not found', [
                    'payment_intent_id' => $paymentIntentId,
                ]);
                return false;
            }

            // Vérifier le statut du paiement
            if ($paymentIntent->status === 'succeeded') {
                $payment->setStatus('completed');
                $payment->setPaidAt(new \DateTime());
                
                $booking = $payment->getBooking();
                $booking->setPaymentStatus('paid');

                // Calculer et enregistrer la commission
                $this->createCommission($payment);

                // Générer la facture
                $this->generateInvoice($payment);

                $this->entityManager->flush();

                $this->logger->info('Payment confirmed', [
                    'payment_id' => $payment->getId(),
                    'booking_id' => $booking->getId(),
                    'amount' => $payment->getAmount(),
                ]);

                return true;
            }

            return false;

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error while confirming payment', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error while confirming payment', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Créer la commission pour la plateforme
     */
    private function createCommission(Payment $payment): Commission
    {
        $booking = $payment->getBooking();
        $amount = $payment->getAmount();
        
        $commissionAmount = $amount * $this->platformCommissionRate;
        $prestataireAmount = $amount - $commissionAmount;

        $commission = new Commission();
        $commission->setPayment($payment);
        $commission->setBooking($booking);
        $commission->setPrestataire($booking->getPrestataire());
        $commission->setTotalAmount($amount);
        $commission->setCommissionRate($this->platformCommissionRate);
        $commission->setCommissionAmount($commissionAmount);
        $commission->setPrestataireAmount($prestataireAmount);
        $commission->setStatus('pending_transfer');
        $commission->setCreatedAt(new \DateTime());

        $this->entityManager->persist($commission);

        $this->logger->info('Commission created', [
            'payment_id' => $payment->getId(),
            'commission_amount' => $commissionAmount,
            'prestataire_amount' => $prestataireAmount,
        ]);

        return $commission;
    }

    /**
     * Générer une facture
     */
    private function generateInvoice(Payment $payment): Invoice
    {
        $booking = $payment->getBooking();
        $client = $booking->getClient();

        $invoice = new Invoice();
        $invoice->setPayment($payment);
        $invoice->setBooking($booking);
        $invoice->setClient($client);
        $invoice->setInvoiceNumber($this->generateInvoiceNumber());
        $invoice->setAmount($payment->getAmount());
        $invoice->setIssuedAt(new \DateTime());
        $invoice->setDueAt(new \DateTime()); // Payé immédiatement
        $invoice->setStatus('paid');
        $invoice->setPaidAt(new \DateTime());

        // Détails de la facture
        $details = [
            'service' => $booking->getServiceRequest()?->getCategory()->getName(),
            'date' => $booking->getScheduledDate()->format('d/m/Y'),
            'duration' => $booking->getDuration() . ' heures',
            'prestataire' => $booking->getPrestataire()->getFullName(),
            'address' => $booking->getAddress(),
        ];
        $invoice->setDetails($details);

        $this->entityManager->persist($invoice);

        $this->logger->info('Invoice generated', [
            'invoice_number' => $invoice->getInvoiceNumber(),
            'payment_id' => $payment->getId(),
        ]);

        return $invoice;
    }

    /**
     * Rembourser un paiement
     */
    public function refundPayment(Payment $payment, ?float $amount = null, string $reason = ''): bool
    {
        try {
            $refundAmount = $amount ?? $payment->getAmount();

            // Créer le remboursement sur Stripe
            $refund = $this->stripe->refunds->create([
                'payment_intent' => $payment->getStripePaymentIntentId(),
                'amount' => $this->convertToStripeAmount($refundAmount),
                'reason' => $reason ?: 'requested_by_customer',
                'metadata' => [
                    'payment_id' => $payment->getId(),
                    'booking_id' => $payment->getBooking()->getId(),
                ],
            ]);

            // Mettre à jour le statut du paiement
            $payment->setStatus('refunded');
            $payment->setRefundedAt(new \DateTime());
            $payment->setRefundAmount($refundAmount);
            $payment->setRefundReason($reason);

            // Mettre à jour le booking
            $booking = $payment->getBooking();
            $booking->setPaymentStatus('refunded');

            // Mettre à jour la commission si elle existe
            $commission = $payment->getCommission();
            if ($commission) {
                $commission->setStatus('refunded');
                $commission->setRefundedAt(new \DateTime());
            }

            $this->entityManager->flush();

            $this->logger->info('Payment refunded', [
                'payment_id' => $payment->getId(),
                'refund_amount' => $refundAmount,
                'refund_id' => $refund->id,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error while refunding payment', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error while refunding payment', [
                'payment_id' => $payment->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Transférer les fonds au prestataire
     */
    public function transferToPrestataire(Commission $commission): bool
    {
        try {
            $prestataire = $commission->getPrestataire();
            
            if (!$prestataire->getStripeConnectedAccountId()) {
                $this->logger->error('Prestataire has no Stripe connected account', [
                    'prestataire_id' => $prestataire->getId(),
                ]);
                return false;
            }

            // Créer le transfert
            $transfer = $this->stripe->transfers->create([
                'amount' => $this->convertToStripeAmount($commission->getPrestataireAmount()),
                'currency' => $this->currency,
                'destination' => $prestataire->getStripeConnectedAccountId(),
                'metadata' => [
                    'commission_id' => $commission->getId(),
                    'booking_id' => $commission->getBooking()->getId(),
                    'prestataire_id' => $prestataire->getId(),
                ],
            ]);

            $commission->setStatus('transferred');
            $commission->setTransferredAt(new \DateTime());
            $commission->setStripeTransferId($transfer->id);

            $this->entityManager->flush();

            $this->logger->info('Funds transferred to prestataire', [
                'commission_id' => $commission->getId(),
                'prestataire_id' => $prestataire->getId(),
                'amount' => $commission->getPrestataireAmount(),
                'transfer_id' => $transfer->id,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error while transferring to prestataire', [
                'commission_id' => $commission->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        } catch (\Exception $e) {
            $this->logger->error('Error while transferring to prestataire', [
                'commission_id' => $commission->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Créer un compte Stripe Connect pour un prestataire
     */
    public function createConnectedAccount(Prestataire $prestataire): ?string
    {
        try {
            $account = $this->stripe->accounts->create([
                'type' => 'express',
                'country' => 'FR',
                'email' => $prestataire->getEmail(),
                'capabilities' => [
                    'transfers' => ['requested' => true],
                ],
                'business_type' => 'individual',
                'individual' => [
                    'first_name' => $prestataire->getFirstName(),
                    'last_name' => $prestataire->getLastName(),
                    'email' => $prestataire->getEmail(),
                ],
                'metadata' => [
                    'prestataire_id' => $prestataire->getId(),
                ],
            ]);

            $prestataire->setStripeConnectedAccountId($account->id);
            $this->entityManager->flush();

            $this->logger->info('Stripe connected account created', [
                'prestataire_id' => $prestataire->getId(),
                'account_id' => $account->id,
            ]);

            return $account->id;

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error while creating connected account', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Créer un lien d'onboarding pour le prestataire
     */
    public function createAccountLink(Prestataire $prestataire, string $returnUrl, string $refreshUrl): ?string
    {
        try {
            if (!$prestataire->getStripeConnectedAccountId()) {
                $this->createConnectedAccount($prestataire);
            }

            $accountLink = $this->stripe->accountLinks->create([
                'account' => $prestataire->getStripeConnectedAccountId(),
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            return $accountLink->url;

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error while creating account link', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Créer un client Stripe
     */
    public function createStripeCustomer(Client $client): ?string
    {
        try {
            $customer = $this->stripe->customers->create([
                'email' => $client->getEmail(),
                'name' => $client->getFullName(),
                'phone' => $client->getPhone(),
                'metadata' => [
                    'client_id' => $client->getId(),
                ],
            ]);

            $client->setStripeCustomerId($customer->id);
            $this->entityManager->flush();

            $this->logger->info('Stripe customer created', [
                'client_id' => $client->getId(),
                'customer_id' => $customer->id,
            ]);

            return $customer->id;

        } catch (ApiErrorException $e) {
            $this->logger->error('Stripe API error while creating customer', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Obtenir les gains d'un prestataire
     */
    public function getPrestataireEarnings(Prestataire $prestataire, ?\DateTime $startDate = null, ?\DateTime $endDate = null): array
    {
        $qb = $this->entityManager
            ->getRepository(Commission::class)
            ->createQueryBuilder('c')
            ->where('c.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($startDate) {
            $qb->andWhere('c.createdAt >= :startDate')
                ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('c.createdAt <= :endDate')
                ->setParameter('endDate', $endDate);
        }

        $commissions = $qb->getQuery()->getResult();

        $totalEarnings = 0;
        $totalCommissions = 0;
        $pendingTransfers = 0;
        $transferred = 0;

        foreach ($commissions as $commission) {
            $totalEarnings += $commission->getTotalAmount();
            $totalCommissions += $commission->getCommissionAmount();

            if ($commission->getStatus() === 'pending_transfer') {
                $pendingTransfers += $commission->getPrestataireAmount();
            } elseif ($commission->getStatus() === 'transferred') {
                $transferred += $commission->getPrestataireAmount();
            }
        }

        return [
            'total_earnings' => $totalEarnings,
            'platform_commissions' => $totalCommissions,
            'net_earnings' => $totalEarnings - $totalCommissions,
            'pending_transfers' => $pendingTransfers,
            'transferred' => $transferred,
            'commission_rate' => $this->platformCommissionRate * 100,
            'bookings_count' => count($commissions),
        ];
    }

    /**
     * Obtenir l'historique des paiements d'un client
     */
    public function getClientPaymentHistory(Client $client, int $limit = 50): array
    {
        return $this->entityManager
            ->getRepository(Payment::class)
            ->createQueryBuilder('p')
            ->join('p.booking', 'b')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Convertir un montant en centimes pour Stripe
     */
    private function convertToStripeAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Générer un numéro de facture unique
     */
    private function generateInvoiceNumber(): string
    {
        $year = date('Y');
        $month = date('m');
        
        $lastInvoice = $this->entityManager
            ->getRepository(Invoice::class)
            ->createQueryBuilder('i')
            ->where('i.invoiceNumber LIKE :prefix')
            ->setParameter('prefix', "INV-{$year}{$month}%")
            ->orderBy('i.invoiceNumber', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->getInvoiceNumber(), -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return sprintf('INV-%s%s-%05d', $year, $month, $newNumber);
    }

    /**
     * Webhook handler pour les événements Stripe
     */
    public function handleStripeWebhook(string $payload, string $signature, string $webhookSecret): bool
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $webhookSecret
            );

            switch ($event->type) {
                case 'payment_intent.succeeded':
                    $paymentIntent = $event->data->object;
                    $this->confirmPayment($paymentIntent->id);
                    break;

                case 'payment_intent.payment_failed':
                    $paymentIntent = $event->data->object;
                    $this->handleFailedPayment($paymentIntent->id);
                    break;

                case 'account.updated':
                    $account = $event->data->object;
                    $this->updateConnectedAccountStatus($account->id);
                    break;

                default:
                    $this->logger->info('Unhandled webhook event', ['type' => $event->type]);
            }

            return true;

        } catch (\Exception $e) {
            $this->logger->error('Error handling Stripe webhook', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Gérer un paiement échoué
     */
    private function handleFailedPayment(string $paymentIntentId): void
    {
        $payment = $this->entityManager
            ->getRepository(Payment::class)
            ->findOneBy(['stripePaymentIntentId' => $paymentIntentId]);

        if ($payment) {
            $payment->setStatus('failed');
            $payment->getBooking()->setPaymentStatus('failed');
            $this->entityManager->flush();

            $this->logger->warning('Payment failed', [
                'payment_id' => $payment->getId(),
                'payment_intent_id' => $paymentIntentId,
            ]);
        }
    }

    /**
     * Mettre à jour le statut d'un compte connecté
     */
    private function updateConnectedAccountStatus(string $accountId): void
    {
        try {
            $account = $this->stripe->accounts->retrieve($accountId);
            
            $prestataire = $this->entityManager
                ->getRepository(Prestataire::class)
                ->findOneBy(['stripeConnectedAccountId' => $accountId]);

            if ($prestataire) {
                $prestataire->setStripeAccountStatus($account->charges_enabled ? 'active' : 'pending');
                $this->entityManager->flush();

                $this->logger->info('Connected account status updated', [
                    'prestataire_id' => $prestataire->getId(),
                    'account_id' => $accountId,
                    'charges_enabled' => $account->charges_enabled,
                ]);
            }

        } catch (ApiErrorException $e) {
            $this->logger->error('Error updating connected account status', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}