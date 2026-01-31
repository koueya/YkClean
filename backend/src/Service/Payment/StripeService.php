<?php

namespace App\Service\Payment;

use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Payment\Payment;
use App\Entity\Booking\Booking;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Stripe\StripeClient;
use Stripe\Exception\ApiErrorException;

class StripeService
{
    private StripeClient $stripe;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private string $currency;
    private string $webhookSecret;

    public function __construct(
        string $stripeSecretKey,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $webhookSecret = '',
        string $currency = 'eur'
    ) {
        $this->stripe = new StripeClient($stripeSecretKey);
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->webhookSecret = $webhookSecret;
        $this->currency = $currency;
    }

    /**
     * Créer ou récupérer un client Stripe
     */
    public function getOrCreateCustomer(Client $client): ?string
    {
        // Si le client a déjà un ID Stripe, le retourner
        if ($client->getStripeCustomerId()) {
            return $client->getStripeCustomerId();
        }

        try {
            $customer = $this->stripe->customers->create([
                'email' => $client->getEmail(),
                'name' => $client->getFullName(),
                'phone' => $client->getPhone(),
                'address' => [
                    'line1' => $client->getAddress(),
                    'city' => $client->getCity(),
                    'postal_code' => $client->getPostalCode(),
                    'country' => 'FR',
                ],
                'metadata' => [
                    'client_id' => $client->getId(),
                    'user_type' => 'client',
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
            $this->logger->error('Failed to create Stripe customer', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Créer un Setup Intent pour enregistrer une carte
     */
    public function createSetupIntent(Client $client): ?array
    {
        try {
            $customerId = $this->getOrCreateCustomer($client);

            if (!$customerId) {
                return null;
            }

            $setupIntent = $this->stripe->setupIntents->create([
                'customer' => $customerId,
                'payment_method_types' => ['card'],
                'usage' => 'off_session',
                'metadata' => [
                    'client_id' => $client->getId(),
                ],
            ]);

            $this->logger->info('Setup intent created', [
                'client_id' => $client->getId(),
                'setup_intent_id' => $setupIntent->id,
            ]);

            return [
                'setup_intent_id' => $setupIntent->id,
                'client_secret' => $setupIntent->client_secret,
            ];

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create setup intent', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Attacher une méthode de paiement à un client
     */
    public function attachPaymentMethod(Client $client, string $paymentMethodId): bool
    {
        try {
            $customerId = $this->getOrCreateCustomer($client);

            if (!$customerId) {
                return false;
            }

            // Attacher la méthode de paiement
            $this->stripe->paymentMethods->attach($paymentMethodId, [
                'customer' => $customerId,
            ]);

            // Définir comme méthode de paiement par défaut
            $this->stripe->customers->update($customerId, [
                'invoice_settings' => [
                    'default_payment_method' => $paymentMethodId,
                ],
            ]);

            $client->setDefaultPaymentMethodId($paymentMethodId);
            $this->entityManager->flush();

            $this->logger->info('Payment method attached', [
                'client_id' => $client->getId(),
                'payment_method_id' => $paymentMethodId,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to attach payment method', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Récupérer les méthodes de paiement d'un client
     */
    public function getPaymentMethods(Client $client): array
    {
        try {
            $customerId = $client->getStripeCustomerId();

            if (!$customerId) {
                return [];
            }

            $paymentMethods = $this->stripe->paymentMethods->all([
                'customer' => $customerId,
                'type' => 'card',
            ]);

            $methods = [];
            foreach ($paymentMethods->data as $pm) {
                $methods[] = [
                    'id' => $pm->id,
                    'brand' => $pm->card->brand,
                    'last4' => $pm->card->last4,
                    'exp_month' => $pm->card->exp_month,
                    'exp_year' => $pm->card->exp_year,
                    'is_default' => $pm->id === $client->getDefaultPaymentMethodId(),
                ];
            }

            return $methods;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to retrieve payment methods', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Supprimer une méthode de paiement
     */
    public function detachPaymentMethod(string $paymentMethodId): bool
    {
        try {
            $this->stripe->paymentMethods->detach($paymentMethodId);

            $this->logger->info('Payment method detached', [
                'payment_method_id' => $paymentMethodId,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to detach payment method', [
                'payment_method_id' => $paymentMethodId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Créer un paiement avec la méthode par défaut
     */
    public function createPaymentWithDefaultMethod(Booking $booking): ?array
    {
        try {
            $client = $booking->getClient();
            $customerId = $client->getStripeCustomerId();
            $paymentMethodId = $client->getDefaultPaymentMethodId();

            if (!$customerId || !$paymentMethodId) {
                $this->logger->warning('No default payment method', [
                    'client_id' => $client->getId(),
                ]);
                return null;
            }

            $paymentIntent = $this->stripe->paymentIntents->create([
                'amount' => $this->convertToStripeAmount($booking->getAmount()),
                'currency' => $this->currency,
                'customer' => $customerId,
                'payment_method' => $paymentMethodId,
                'off_session' => true,
                'confirm' => true,
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
            ]);

            return [
                'success' => true,
                'payment_intent_id' => $paymentIntent->id,
                'status' => $paymentIntent->status,
            ];

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create payment with default method', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Créer un compte Stripe Connect Express pour un prestataire
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
                    'phone' => $prestataire->getPhone(),
                ],
                'business_profile' => [
                    'mcc' => '8111', // Service providers
                    'url' => 'https://www.votreplateforme.com',
                ],
                'metadata' => [
                    'prestataire_id' => $prestataire->getId(),
                    'siret' => $prestataire->getSiret(),
                ],
            ]);

            $prestataire->setStripeConnectedAccountId($account->id);
            $prestataire->setStripeAccountStatus('pending');
            $this->entityManager->flush();

            $this->logger->info('Stripe connected account created', [
                'prestataire_id' => $prestataire->getId(),
                'account_id' => $account->id,
            ]);

            return $account->id;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create connected account', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Créer un lien d'onboarding Stripe Connect
     */
    public function createAccountOnboardingLink(
        Prestataire $prestataire,
        string $returnUrl,
        string $refreshUrl
    ): ?string {
        try {
            $accountId = $prestataire->getStripeConnectedAccountId();

            if (!$accountId) {
                $accountId = $this->createConnectedAccount($prestataire);
            }

            if (!$accountId) {
                return null;
            }

            $accountLink = $this->stripe->accountLinks->create([
                'account' => $accountId,
                'refresh_url' => $refreshUrl,
                'return_url' => $returnUrl,
                'type' => 'account_onboarding',
            ]);

            $this->logger->info('Account onboarding link created', [
                'prestataire_id' => $prestataire->getId(),
                'account_id' => $accountId,
            ]);

            return $accountLink->url;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create account onboarding link', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Créer un lien pour le dashboard Stripe Connect
     */
    public function createLoginLink(Prestataire $prestataire): ?string
    {
        try {
            $accountId = $prestataire->getStripeConnectedAccountId();

            if (!$accountId) {
                return null;
            }

            $loginLink = $this->stripe->accounts->createLoginLink($accountId);

            return $loginLink->url;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create login link', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Vérifier le statut d'un compte connecté
     */
    public function getAccountStatus(Prestataire $prestataire): ?array
    {
        try {
            $accountId = $prestataire->getStripeConnectedAccountId();

            if (!$accountId) {
                return null;
            }

            $account = $this->stripe->accounts->retrieve($accountId);

            $status = [
                'id' => $account->id,
                'charges_enabled' => $account->charges_enabled,
                'payouts_enabled' => $account->payouts_enabled,
                'details_submitted' => $account->details_submitted,
                'requirements' => [
                    'currently_due' => $account->requirements->currently_due ?? [],
                    'eventually_due' => $account->requirements->eventually_due ?? [],
                    'past_due' => $account->requirements->past_due ?? [],
                ],
            ];

            // Mettre à jour le statut local
            if ($account->charges_enabled && $account->payouts_enabled) {
                $prestataire->setStripeAccountStatus('active');
            } elseif ($account->details_submitted) {
                $prestataire->setStripeAccountStatus('pending_verification');
            } else {
                $prestataire->setStripeAccountStatus('pending');
            }

            $this->entityManager->flush();

            return $status;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to retrieve account status', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Créer un transfert vers un compte connecté
     */
    public function createTransfer(
        Prestataire $prestataire,
        float $amount,
        array $metadata = []
    ): ?string {
        try {
            $accountId = $prestataire->getStripeConnectedAccountId();

            if (!$accountId) {
                $this->logger->error('No connected account for prestataire', [
                    'prestataire_id' => $prestataire->getId(),
                ]);
                return null;
            }

            $transfer = $this->stripe->transfers->create([
                'amount' => $this->convertToStripeAmount($amount),
                'currency' => $this->currency,
                'destination' => $accountId,
                'metadata' => array_merge($metadata, [
                    'prestataire_id' => $prestataire->getId(),
                ]),
            ]);

            $this->logger->info('Transfer created', [
                'prestataire_id' => $prestataire->getId(),
                'transfer_id' => $transfer->id,
                'amount' => $amount,
            ]);

            return $transfer->id;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create transfer', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Créer un payout (virement bancaire) pour un prestataire
     */
    public function createPayout(Prestataire $prestataire, float $amount): ?string
    {
        try {
            $accountId = $prestataire->getStripeConnectedAccountId();

            if (!$accountId) {
                return null;
            }

            $payout = $this->stripe->payouts->create([
                'amount' => $this->convertToStripeAmount($amount),
                'currency' => $this->currency,
            ], [
                'stripe_account' => $accountId,
            ]);

            $this->logger->info('Payout created', [
                'prestataire_id' => $prestataire->getId(),
                'payout_id' => $payout->id,
                'amount' => $amount,
            ]);

            return $payout->id;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create payout', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Récupérer le solde d'un compte connecté
     */
    public function getAccountBalance(Prestataire $prestataire): ?array
    {
        try {
            $accountId = $prestataire->getStripeConnectedAccountId();

            if (!$accountId) {
                return null;
            }

            $balance = $this->stripe->balance->retrieve([], [
                'stripe_account' => $accountId,
            ]);

            $available = 0;
            $pending = 0;

            foreach ($balance->available as $amount) {
                if ($amount->currency === $this->currency) {
                    $available = $amount->amount / 100;
                }
            }

            foreach ($balance->pending as $amount) {
                if ($amount->currency === $this->currency) {
                    $pending = $amount->amount / 100;
                }
            }

            return [
                'available' => $available,
                'pending' => $pending,
                'currency' => $this->currency,
            ];

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to retrieve account balance', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Annuler un Payment Intent
     */
    public function cancelPaymentIntent(string $paymentIntentId): bool
    {
        try {
            $this->stripe->paymentIntents->cancel($paymentIntentId);

            $this->logger->info('Payment intent cancelled', [
                'payment_intent_id' => $paymentIntentId,
            ]);

            return true;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to cancel payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Créer un remboursement
     */
    public function createRefund(
        string $paymentIntentId,
        ?float $amount = null,
        string $reason = 'requested_by_customer'
    ): ?string {
        try {
            $refundData = [
                'payment_intent' => $paymentIntentId,
                'reason' => $reason,
            ];

            if ($amount !== null) {
                $refundData['amount'] = $this->convertToStripeAmount($amount);
            }

            $refund = $this->stripe->refunds->create($refundData);

            $this->logger->info('Refund created', [
                'payment_intent_id' => $paymentIntentId,
                'refund_id' => $refund->id,
                'amount' => $amount,
            ]);

            return $refund->id;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create refund', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Récupérer un Payment Intent
     */
    public function retrievePaymentIntent(string $paymentIntentId): ?array
    {
        try {
            $paymentIntent = $this->stripe->paymentIntents->retrieve($paymentIntentId);

            return [
                'id' => $paymentIntent->id,
                'amount' => $paymentIntent->amount / 100,
                'currency' => $paymentIntent->currency,
                'status' => $paymentIntent->status,
                'client_secret' => $paymentIntent->client_secret,
                'created' => $paymentIntent->created,
            ];

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to retrieve payment intent', [
                'payment_intent_id' => $paymentIntentId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Vérifier et traiter un webhook Stripe
     */
    public function verifyWebhook(string $payload, string $signature): ?\Stripe\Event
    {
        try {
            $event = \Stripe\Webhook::constructEvent(
                $payload,
                $signature,
                $this->webhookSecret
            );

            $this->logger->info('Webhook verified', [
                'event_type' => $event->type,
                'event_id' => $event->id,
            ]);

            return $event;

        } catch (\UnexpectedValueException $e) {
            $this->logger->error('Invalid webhook payload', [
                'error' => $e->getMessage(),
            ]);
            return null;
        } catch (\Stripe\Exception\SignatureVerificationException $e) {
            $this->logger->error('Invalid webhook signature', [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Obtenir les transactions d'un client
     */
    public function getCustomerTransactions(Client $client, int $limit = 10): array
    {
        try {
            $customerId = $client->getStripeCustomerId();

            if (!$customerId) {
                return [];
            }

            $charges = $this->stripe->charges->all([
                'customer' => $customerId,
                'limit' => $limit,
            ]);

            $transactions = [];
            foreach ($charges->data as $charge) {
                $transactions[] = [
                    'id' => $charge->id,
                    'amount' => $charge->amount / 100,
                    'currency' => $charge->currency,
                    'status' => $charge->status,
                    'description' => $charge->description,
                    'created' => date('Y-m-d H:i:s', $charge->created),
                    'receipt_url' => $charge->receipt_url,
                ];
            }

            return $transactions;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to retrieve customer transactions', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * Créer une facture Stripe
     */
    public function createInvoice(Client $client, array $items, array $metadata = []): ?string
    {
        try {
            $customerId = $this->getOrCreateCustomer($client);

            if (!$customerId) {
                return null;
            }

            // Créer les invoice items
            foreach ($items as $item) {
                $this->stripe->invoiceItems->create([
                    'customer' => $customerId,
                    'amount' => $this->convertToStripeAmount($item['amount']),
                    'currency' => $this->currency,
                    'description' => $item['description'],
                ]);
            }

            // Créer la facture
            $invoice = $this->stripe->invoices->create([
                'customer' => $customerId,
                'auto_advance' => true,
                'metadata' => $metadata,
            ]);

            // Finaliser la facture
            $this->stripe->invoices->finalizeInvoice($invoice->id);

            $this->logger->info('Invoice created', [
                'client_id' => $client->getId(),
                'invoice_id' => $invoice->id,
            ]);

            return $invoice->id;

        } catch (ApiErrorException $e) {
            $this->logger->error('Failed to create invoice', [
                'client_id' => $client->getId(),
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Convertir un montant en centimes pour Stripe
     */
    private function convertToStripeAmount(float $amount): int
    {
        return (int) round($amount * 100);
    }

    /**
     * Convertir un montant Stripe en euros
     */
    private function convertFromStripeAmount(int $amount): float
    {
        return $amount / 100;
    }
}