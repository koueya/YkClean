# Architecture du Module Financial Autonome

## Vue d'ensemble

Le module Financial est un module **complètement autonome** qui centralise toute la gestion financière de la plateforme : paiements, commissions, gains des prestataires, versements, transactions, factures et rapports financiers.

---

## Structure des Dossiers

```
backend/src/Module/Financial/
│
├── Entity/
│   ├── Payment.php
│   ├── Invoice.php
│   ├── Commission.php
│   ├── PrestataireEarning.php
│   ├── Transaction.php
│   ├── Payout.php
│   ├── CommissionRule.php
│   ├── PaymentMethod.php
│   ├── RefundRequest.php
│   └── FinancialReport.php
│
├── Repository/
│   ├── PaymentRepository.php
│   ├── InvoiceRepository.php
│   ├── CommissionRepository.php
│   ├── PrestataireEarningRepository.php
│   ├── TransactionRepository.php
│   ├── PayoutRepository.php
│   ├── CommissionRuleRepository.php
│   ├── PaymentMethodRepository.php
│   ├── RefundRequestRepository.php
│   └── FinancialReportRepository.php
│
├── Service/
│   ├── PaymentService.php
│   ├── InvoiceService.php
│   ├── CommissionService.php
│   ├── EarningService.php
│   ├── PayoutService.php
│   ├── TransactionService.php
│   ├── RefundService.php
│   ├── FinancialReportService.php
│   ├── BalanceService.php
│   ├── TaxCalculationService.php
│   └── Gateway/
│       ├── PaymentGatewayInterface.php
│       ├── StripeGateway.php
│       └── MangopayGateway.php
│
├── Controller/
│   ├── Admin/
│   │   ├── CommissionController.php
│   │   ├── PayoutController.php
│   │   ├── TransactionController.php
│   │   ├── FinancialReportController.php
│   │   ├── CommissionRuleController.php
│   │   └── RefundController.php
│   │
│   ├── Prestataire/
│   │   ├── EarningController.php
│   │   ├── PayoutRequestController.php
│   │   ├── InvoiceController.php
│   │   ├── TransactionHistoryController.php
│   │   └── BalanceController.php
│   │
│   └── Client/
│       ├── PaymentController.php
│       ├── InvoiceController.php
│       ├── RefundRequestController.php
│       └── PaymentMethodController.php
│
├── EventListener/
│   ├── BookingCompletedListener.php
│   ├── PaymentSuccessListener.php
│   ├── PaymentFailedListener.php
│   ├── EarningAvailableListener.php
│   ├── PayoutProcessedListener.php
│   └── CommissionCalculationListener.php
│
├── EventSubscriber/
│   ├── TransactionLoggerSubscriber.php
│   └── FinancialNotificationSubscriber.php
│
├── DTO/
│   ├── Request/
│   │   ├── CreatePaymentRequest.php
│   │   ├── RequestPayoutDTO.php
│   │   ├── CreateRefundRequest.php
│   │   └── UpdateCommissionRuleRequest.php
│   │
│   └── Response/
│       ├── EarningStatisticsDTO.php
│       ├── PayoutDetailsDTO.php
│       ├── FinancialReportDTO.php
│       ├── TransactionDTO.php
│       └── BalanceDTO.php
│
├── Exception/
│   ├── InsufficientBalanceException.php
│   ├── PaymentGatewayException.php
│   ├── InvalidCommissionRuleException.php
│   ├── PayoutNotAllowedException.php
│   └── RefundException.php
│
├── Validator/
│   ├── Constraints/
│   │   ├── ValidPaymentAmount.php
│   │   ├── ValidIBAN.php
│   │   └── SufficientBalance.php
│   │
│   └── Validator/
│       ├── ValidPaymentAmountValidator.php
│       ├── ValidIBANValidator.php
│       └── SufficientBalanceValidator.php
│
├── Enum/
│   ├── PaymentStatus.php
│   ├── TransactionType.php
│   ├── PayoutStatus.php
│   ├── CommissionRuleType.php
│   ├── RefundStatus.php
│   └── InvoiceStatus.php
│
├── Factory/
│   ├── PaymentFactory.php
│   ├── CommissionFactory.php
│   ├── EarningFactory.php
│   ├── TransactionFactory.php
│   └── InvoiceFactory.php
│
├── Specification/
│   ├── CanRequestPayoutSpecification.php
│   ├── IsPaymentRefundableSpecification.php
│   └── CommissionRuleApplicableSpecification.php
│
├── Strategy/
│   ├── CommissionCalculation/
│   │   ├── CommissionCalculationStrategyInterface.php
│   │   ├── PercentageCommissionStrategy.php
│   │   ├── FixedCommissionStrategy.php
│   │   └── TieredCommissionStrategy.php
│   │
│   └── Payout/
│       ├── PayoutStrategyInterface.php
│       ├── InstantPayoutStrategy.php
│       └── ScheduledPayoutStrategy.php
│
├── Query/
│   ├── GetEarningsByPrestataireQuery.php
│   ├── GetTransactionHistoryQuery.php
│   ├── GetFinancialReportQuery.php
│   └── GetPendingPayoutsQuery.php
│
├── Handler/
│   ├── GetEarningsByPrestataireHandler.php
│   ├── GetTransactionHistoryHandler.php
│   ├── GetFinancialReportHandler.php
│   └── GetPendingPayoutsHandler.php
│
├── Event/
│   ├── PaymentCompletedEvent.php
│   ├── CommissionCalculatedEvent.php
│   ├── EarningCreatedEvent.php
│   ├── PayoutRequestedEvent.php
│   ├── PayoutProcessedEvent.php
│   └── RefundProcessedEvent.php
│
└── Tests/
    ├── Unit/
    │   ├── Service/
    │   ├── Validator/
    │   └── Strategy/
    │
    └── Integration/
        └── Controller/
```

---

## Configuration Symfony

### 1. Configuration des Services (`config/services.yaml`)

```yaml
# Configuration du module Financial
services:
    _defaults:
        autowire: true
        autoconfigure: true
        bind:
            # Binding des paramètres globaux
            $minimumPayoutAmount: '%financial.minimum_payout_amount%'
            $defaultCommissionRate: '%financial.default_commission_rate%'
            $payoutProcessingDelay: '%financial.payout_processing_delay%'
            $earningAvailabilityDelay: '%financial.earning_availability_delay%'
    
    # Auto-configuration du module Financial
    App\Module\Financial\:
        resource: '../src/Module/Financial/'
        exclude:
            - '../src/Module/Financial/Entity/'
            - '../src/Module/Financial/DTO/'
            - '../src/Module/Financial/Enum/'
            - '../src/Module/Financial/Exception/'
            - '../src/Module/Financial/Event/'
            - '../src/Module/Financial/Query/'
            - '../src/Module/Financial/Tests/'
    
    # Controllers avec arguments automatiques
    App\Module\Financial\Controller\:
        resource: '../src/Module/Financial/Controller/'
        tags: ['controller.service_arguments']
    
    # Event Listeners
    App\Module\Financial\EventListener\:
        resource: '../src/Module/Financial/EventListener/'
    
    # Event Subscribers
    App\Module\Financial\EventSubscriber\:
        resource: '../src/Module/Financial/EventSubscriber/'
        tags:
            - { name: 'kernel.event_subscriber' }
    
    # Repositories
    App\Module\Financial\Repository\:
        resource: '../src/Module/Financial/Repository/'
        tags: ['doctrine.repository_service']
    
    # === Services Core ===
    
    # Payment Gateway (interface avec implémentation par défaut)
    App\Module\Financial\Service\Gateway\PaymentGatewayInterface:
        alias: App\Module\Financial\Service\Gateway\StripeGateway
    
    # Stripe Gateway
    App\Module\Financial\Service\Gateway\StripeGateway:
        arguments:
            $stripeSecretKey: '%env(STRIPE_SECRET_KEY)%'
            $stripeWebhookSecret: '%env(STRIPE_WEBHOOK_SECRET)%'
    
    # Mangopay Gateway (alternative)
    App\Module\Financial\Service\Gateway\MangopayGateway:
        arguments:
            $clientId: '%env(MANGOPAY_CLIENT_ID)%'
            $apiKey: '%env(MANGOPAY_API_KEY)%'
            $environment: '%env(MANGOPAY_ENVIRONMENT)%'
    
    # === Commission Strategy (Pattern Strategy) ===
    
    # Interface avec stratégie par défaut
    App\Module\Financial\Strategy\CommissionCalculation\CommissionCalculationStrategyInterface:
        alias: App\Module\Financial\Strategy\CommissionCalculation\PercentageCommissionStrategy
    
    # Strategies individuelles
    App\Module\Financial\Strategy\CommissionCalculation\PercentageCommissionStrategy: ~
    App\Module\Financial\Strategy\CommissionCalculation\FixedCommissionStrategy: ~
    App\Module\Financial\Strategy\CommissionCalculation\TieredCommissionStrategy: ~
    
    # === Payout Strategy ===
    
    App\Module\Financial\Strategy\Payout\PayoutStrategyInterface:
        alias: App\Module\Financial\Strategy\Payout\ScheduledPayoutStrategy
    
    App\Module\Financial\Strategy\Payout\InstantPayoutStrategy: ~
    App\Module\Financial\Strategy\Payout\ScheduledPayoutStrategy: ~
    
    # === Services Métier ===
    
    App\Module\Financial\Service\PaymentService:
        arguments:
            $paymentGateway: '@App\Module\Financial\Service\Gateway\PaymentGatewayInterface'
    
    App\Module\Financial\Service\CommissionService:
        arguments:
            $commissionStrategy: '@App\Module\Financial\Strategy\CommissionCalculation\CommissionCalculationStrategyInterface'
    
    App\Module\Financial\Service\EarningService: ~
    
    App\Module\Financial\Service\PayoutService:
        arguments:
            $payoutStrategy: '@App\Module\Financial\Strategy\Payout\PayoutStrategyInterface'
    
    App\Module\Financial\Service\TransactionService: ~
    App\Module\Financial\Service\InvoiceService: ~
    App\Module\Financial\Service\RefundService: ~
    App\Module\Financial\Service\BalanceService: ~
    App\Module\Financial\Service\TaxCalculationService: ~
    App\Module\Financial\Service\FinancialReportService: ~
    
    # === Factories ===
    
    App\Module\Financial\Factory\PaymentFactory: ~
    App\Module\Financial\Factory\CommissionFactory: ~
    App\Module\Financial\Factory\EarningFactory: ~
    App\Module\Financial\Factory\TransactionFactory: ~
    App\Module\Financial\Factory\InvoiceFactory: ~
    
    # === Specifications ===
    
    App\Module\Financial\Specification\CanRequestPayoutSpecification: ~
    App\Module\Financial\Specification\IsPaymentRefundableSpecification: ~
    App\Module\Financial\Specification\CommissionRuleApplicableSpecification: ~
    
    # === Query Handlers (CQRS Pattern) ===
    
    App\Module\Financial\Handler\:
        resource: '../src/Module/Financial/Handler/'
        tags:
            - { name: 'messenger.message_handler', bus: 'query.bus' }
```

### 2. Configuration des Paramètres (`config/packages/financial.yaml`)

```yaml
# Configuration spécifique au module Financial
parameters:
    # Montant minimum pour demander un versement (en euros)
    financial.minimum_payout_amount: 50.0
    
    # Taux de commission par défaut (pourcentage)
    financial.default_commission_rate: 15.0
    
    # Délai avant traitement des versements (en jours)
    financial.payout_processing_delay: 2
    
    # Délai avant disponibilité des gains (en jours)
    # Permet de gérer les annulations/litiges
    financial.earning_availability_delay: 7
    
    # Commission par paliers (tiered)
    financial.tiered_commission_rates:
        - { min: 0, max: 500, rate: 15.0 }      # 0-500€: 15%
        - { min: 500, max: 1000, rate: 12.0 }   # 500-1000€: 12%
        - { min: 1000, max: null, rate: 10.0 }  # >1000€: 10%
    
    # Frais fixes par catégorie de service
    financial.fixed_commission_by_category:
        nettoyage: 5.0
        repassage: 3.0
        menage_complet: 8.0
    
    # Configuration des versements
    financial.payout:
        # Jours de traitement des versements (1 = Lundi, 7 = Dimanche)
        processing_days: [2, 5]  # Mardi et Vendredi
        # Heure de traitement (format 24h)
        processing_time: '14:00'
        # Méthodes de paiement disponibles
        available_methods:
            - bank_transfer
            - stripe_payout
            - mangopay_payout
    
    # Configuration des remboursements
    financial.refund:
        # Délai maximum pour demander un remboursement (en jours)
        max_request_days: 30
        # Remboursement automatique si service annulé avant (en heures)
        auto_refund_before_hours: 24
    
    # Configuration des factures
    financial.invoice:
        # Numéro de TVA de la plateforme
        platform_vat_number: 'FR12345678901'
        # Informations de la société
        company_name: 'Ma Plateforme Services'
        company_address: '123 Rue Example, 75001 Paris, France'
        company_siret: '12345678901234'
    
    # Configuration fiscale
    financial.tax:
        # Taux de TVA par défaut
        default_vat_rate: 20.0
        # Services à taux réduit
        reduced_vat_services:
            - nettoyage_domicile  # 10% ou 5.5%
        reduced_vat_rate: 10.0
```

### 3. Configuration des Routes (`config/routes/financial.yaml`)

```yaml
# Routes du module Financial

# === Admin - Gestion financière ===
financial_admin:
    resource: '../src/Module/Financial/Controller/Admin/'
    type: attribute
    prefix: /api/admin/financial
    name_prefix: financial_admin_
    defaults:
        _format: json

# === Prestataire - Mes finances ===
financial_prestataire:
    resource: '../src/Module/Financial/Controller/Prestataire/'
    type: attribute
    prefix: /api/prestataire/financial
    name_prefix: financial_prestataire_
    defaults:
        _format: json

# === Client - Paiements ===
financial_client:
    resource: '../src/Module/Financial/Controller/Client/'
    type: attribute
    prefix: /api/client/financial
    name_prefix: financial_client_
    defaults:
        _format: json

# === Webhooks (paiements externes) ===
financial_webhooks:
    path: /api/webhooks/financial/{provider}
    controller: App\Module\Financial\Controller\WebhookController::handle
    methods: [POST]
    requirements:
        provider: stripe|mangopay
```

### 4. Configuration Doctrine (`config/packages/doctrine.yaml`)

```yaml
doctrine:
    dbal:
        # ... configuration existante
        
    orm:
        auto_generate_proxy_classes: true
        enable_lazy_ghost_objects: true
        
        naming_strategy: doctrine.orm.naming_strategy.underscore_number_aware
        auto_mapping: true
        
        mappings:
            # Mapping du module Financial
            Financial:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/src/Module/Financial/Entity'
                prefix: 'App\Module\Financial\Entity'
                alias: Financial
            
            # Autres mappings...
            App:
                is_bundle: false
                type: attribute
                dir: '%kernel.project_dir%/src/Entity'
                prefix: 'App\Entity'
                alias: App
        
        # Filters pour le module Financial
        filters:
            financial_soft_delete:
                class: App\Module\Financial\Filter\SoftDeleteFilter
                enabled: true
```

### 5. Configuration Messenger (`config/packages/messenger.yaml`)

```yaml
framework:
    messenger:
        failure_transport: failed
        
        transports:
            # Transport async pour les tâches financières
            async_financial:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
                options:
                    use_notify: true
                    check_delayed_interval: 60000
                retry_strategy:
                    max_retries: 3
                    multiplier: 2
                    delay: 1000
            
            # Transport pour les notifications financières
            financial_notifications:
                dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
            
            failed: 'doctrine://default?queue_name=failed'
        
        routing:
            # Events financiers asynchrones
            'App\Module\Financial\Event\PaymentCompletedEvent': async_financial
            'App\Module\Financial\Event\CommissionCalculatedEvent': async_financial
            'App\Module\Financial\Event\EarningCreatedEvent': async_financial
            'App\Module\Financial\Event\PayoutRequestedEvent': async_financial
            'App\Module\Financial\Event\PayoutProcessedEvent': async_financial
            'App\Module\Financial\Event\RefundProcessedEvent': async_financial
            
            # Notifications
            'App\Module\Financial\Message\SendPaymentConfirmationMessage': financial_notifications
            'App\Module\Financial\Message\SendPayoutNotificationMessage': financial_notifications
```

### 6. Configuration des Variables d'Environnement (`.env`)

```env
###> Financial Module Configuration ###

# Stripe
STRIPE_SECRET_KEY=sk_test_xxxxxxxxxxxxxxxxxxxxx
STRIPE_PUBLISHABLE_KEY=pk_test_xxxxxxxxxxxxxxxxxxxxx
STRIPE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxxxxxxxxxx

# Mangopay (alternative)
MANGOPAY_CLIENT_ID=your_client_id
MANGOPAY_API_KEY=your_api_key
MANGOPAY_ENVIRONMENT=sandbox # or production

# Messenger
MESSENGER_TRANSPORT_DSN=doctrine://default

###< Financial Module Configuration ###
```

---

## API Endpoints Complets

### **Admin - Gestion Financière**

```
# Dashboard financier
GET    /api/admin/financial/dashboard
GET    /api/admin/financial/statistics

# Commissions
GET    /api/admin/financial/commissions
GET    /api/admin/financial/commissions/{id}
PUT    /api/admin/financial/commissions/{id}
GET    /api/admin/financial/commissions/by-prestataire/{prestataireId}
GET    /api/admin/financial/commissions/by-period

# Règles de commission
GET    /api/admin/financial/commission-rules
POST   /api/admin/financial/commission-rules
GET    /api/admin/financial/commission-rules/{id}
PUT    /api/admin/financial/commission-rules/{id}
DELETE /api/admin/financial/commission-rules/{id}
PATCH  /api/admin/financial/commission-rules/{id}/toggle-status

# Gains des prestataires
GET    /api/admin/financial/earnings
GET    /api/admin/financial/earnings/{id}
GET    /api/admin/financial/earnings/pending
GET    /api/admin/financial/earnings/by-prestataire/{prestataireId}

# Versements (Payouts)
GET    /api/admin/financial/payouts
GET    /api/admin/financial/payouts/{id}
GET    /api/admin/financial/payouts/pending
POST   /api/admin/financial/payouts/{id}/approve
POST   /api/admin/financial/payouts/{id}/reject
POST   /api/admin/financial/payouts/{id}/process
POST   /api/admin/financial/payouts/batch-process

# Transactions
GET    /api/admin/financial/transactions
GET    /api/admin/financial/transactions/{id}
GET    /api/admin/financial/transactions/by-user/{userId}
GET    /api/admin/financial/transactions/by-type/{type}
POST   /api/admin/financial/transactions/{id}/adjust

# Remboursements
GET    /api/admin/financial/refunds
GET    /api/admin/financial/refunds/{id}
POST   /api/admin/financial/refunds/{id}/approve
POST   /api/admin/financial/refunds/{id}/reject
POST   /api/admin/financial/refunds/{id}/process

# Rapports financiers
GET    /api/admin/financial/reports/monthly
GET    /api/admin/financial/reports/annual
GET    /api/admin/financial/reports/revenue
GET    /api/admin/financial/reports/top-earners
POST   /api/admin/financial/reports/export
```

### **Prestataire - Mes Finances**

```
# Gains (Earnings)
GET    /api/prestataire/financial/earnings
GET    /api/prestataire/financial/earnings/statistics
GET    /api/prestataire/financial/earnings/{id}
GET    /api/prestataire/financial/earnings/available
GET    /api/prestataire/financial/earnings/pending
GET    /api/prestataire/financial/earnings/monthly/{year}/{month}

# Balance / Solde
GET    /api/prestataire/financial/balance
GET    /api/prestataire/financial/balance/available
GET    /api/prestataire/financial/balance/pending

# Demandes de versement
POST   /api/prestataire/financial/payouts/request
GET    /api/prestataire/financial/payouts
GET    /api/prestataire/financial/payouts/{id}
DELETE /api/prestataire/financial/payouts/{id}/cancel

# Factures
GET    /api/prestataire/financial/invoices
GET    /api/prestataire/financial/invoices/{id}
GET    /api/prestataire/financial/invoices/{id}/download

# Historique des transactions
GET    /api/prestataire/financial/transactions
GET    /api/prestataire/financial/transactions/{id}
GET    /api/prestataire/financial/transactions/by-period
```

### **Client - Paiements**

```
# Paiements
POST   /api/client/financial/payments
GET    /api/client/financial/payments
GET    /api/client/financial/payments/{id}
GET    /api/client/financial/payments/{id}/receipt

# Méthodes de paiement
GET    /api/client/financial/payment-methods
POST   /api/client/financial/payment-methods
GET    /api/client/financial/payment-methods/{id}
DELETE /api/client/financial/payment-methods/{id}
PATCH  /api/client/financial/payment-methods/{id}/set-default

# Factures
GET    /api/client/financial/invoices
GET    /api/client/financial/invoices/{id}
GET    /api/client/financial/invoices/{id}/download

# Remboursements
POST   /api/client/financial/refunds/request
GET    /api/client/financial/refunds
GET    /api/client/financial/refunds/{id}
```

---

## Entités Principales

### 1. **PrestataireEarning**

```php
<?php

namespace App\Module\Financial\Entity;

use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use App\Module\Financial\Enum\EarningStatus;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrestataireEarningRepository::class)]
#[ORM\Table(name: 'financial_prestataire_earning')]
#[ORM\Index(columns: ['prestataire_id', 'status'])]
#[ORM\Index(columns: ['earned_at'])]
class PrestataireEarning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Prestataire $prestataire;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Booking $booking;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $totalAmount; // Montant total du service

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $commissionAmount; // Commission plateforme

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $netAmount; // Montant net pour le prestataire

    #[ORM\ManyToOne(targetEntity: Payout::class, inversedBy: 'earnings')]
    private ?Payout $payout = null;

    #[ORM\Column(type: 'string', length: 20, enumType: EarningStatus::class)]
    private EarningStatus $status = EarningStatus::PENDING;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $earnedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $availableAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    public function __construct()
    {
        $this->earnedAt = new \DateTimeImmutable();
    }

    // Getters and Setters...
}
```

### 2. **Transaction**

```php
<?php

namespace App\Module\Financial\Entity;

use App\Entity\User\User;
use App\Entity\Booking\Booking;
use App\Module\Financial\Enum\TransactionType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'financial_transaction')]
#[ORM\Index(columns: ['user_id', 'created_at'])]
#[ORM\Index(columns: ['type', 'created_at'])]
#[ORM\Index(columns: ['reference'], unique: true)]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 30, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $amount;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    private ?Payment $payment = null;

    #[ORM\ManyToOne(targetEntity: Commission::class)]
    private ?Commission $commission = null;

    #[ORM\ManyToOne(targetEntity: PrestataireEarning::class)]
    private ?PrestataireEarning $earning = null;

    #[ORM\ManyToOne(targetEntity: Payout::class)]
    private ?Payout $payout = null;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $reference;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->reference = $this->generateReference();
    }

    private function generateReference(): string
    {
        return 'TXN-' . strtoupper(uniqid());
    }

    // Getters and Setters...
}
```

### 3. **Payout**

```php
<?php

namespace App\Module\Financial\Entity;

use App\Entity\User\Prestataire;
use App\Module\Financial\Enum\PayoutStatus;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PayoutRepository::class)]
#[ORM\Table(name: 'financial_payout')]
#[ORM\Index(columns: ['prestataire_id', 'status'])]
class Payout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Prestataire $prestataire;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $amount;

    #[ORM\Column(type: 'string', length: 20, enumType: PayoutStatus::class)]
    private PayoutStatus $status = PayoutStatus::PENDING;

    #[ORM\Column(type: 'string', length: 50)]
    private string $paymentMethod; // bank_transfer, stripe, mangopay

    #[ORM\OneToMany(targetEntity: PrestataireEarning::class, mappedBy: 'payout')]
    private Collection $earnings;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $transactionReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'json')]
    private array $bankDetails = [];

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
        $this->earnings = new ArrayCollection();
    }

    public function addEarning(PrestataireEarning $earning): self
    {
        if (!$this->earnings->contains($earning)) {
            $this->earnings->add($earning);
            $earning->setPayout($this);
        }
        return $this;
    }

    // Getters and Setters...
}
```

### 4. **CommissionRule**

```php
<?php

namespace App\Module\Financial\Entity;

use App\Entity\Service\ServiceCategory;
use App\Module\Financial\Enum\CommissionRuleType;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CommissionRuleRepository::class)]
#[ORM\Table(name: 'financial_commission_rule')]
class CommissionRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 20, enumType: CommissionRuleType::class)]
    private CommissionRuleType $type;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private float $value; // Pourcentage ou montant fixe

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $minAmount = null; // Seuil minimum

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $maxAmount = null; // Seuil maximum

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class)]
    private ?ServiceCategory $category = null; // Règle par catégorie

    #[ORM\Column(type: 'json')]
    private array $conditions = []; // Conditions supplémentaires

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $priority = 0; // Priorité d'application

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;

    // Getters and Setters...
}
```

---

## Énumérations

```php
<?php

namespace App\Module\Financial\Enum;

enum TransactionType: string
{
    case PAYMENT = 'payment';
    case COMMISSION = 'commission';
    case EARNING = 'earning';
    case PAYOUT = 'payout';
    case REFUND = 'refund';
    case ADJUSTMENT = 'adjustment';
}

enum EarningStatus: string
{
    case PENDING = 'pending';
    case AVAILABLE = 'available';
    case PAID = 'paid';
    case DISPUTED = 'disputed';
    case CANCELLED = 'cancelled';
}

enum PayoutStatus: string
{
    case PENDING = 'pending';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}

enum CommissionRuleType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
    case TIERED = 'tiered';
}

enum PaymentStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case FAILED = 'failed';
    case REFUNDED = 'refunded';
    case PARTIALLY_REFUNDED = 'partially_refunded';
}

enum RefundStatus: string
{
    case REQUESTED = 'requested';
    case APPROVED = 'approved';
    case PROCESSING = 'processing';
    case COMPLETED = 'completed';
    case REJECTED = 'rejected';
    case CANCELLED = 'cancelled';
}

enum InvoiceStatus: string
{
    case DRAFT = 'draft';
    case SENT = 'sent';
    case PAID = 'paid';
    case OVERDUE = 'overdue';
    case CANCELLED = 'cancelled';
}
```

---

## Workflow Financier Complet

```
1. Client effectue une réservation
   ↓
2. [PaymentService] → Crée Payment
   ↓
3. [Event: PaymentCompletedEvent]
   ↓
4. [TransactionService] → Enregistre Transaction (type: PAYMENT)
   ↓
5. [CommissionService] → Calcule commission selon CommissionRule
   ↓
6. [Event: CommissionCalculatedEvent]
   ↓
7. [TransactionService] → Enregistre Transaction (type: COMMISSION)
   ↓
8. [EarningService] → Calcule gain net du prestataire
   ↓
9. [Event: EarningCreatedEvent]
   ↓
10. [TransactionService] → Enregistre Transaction (type: EARNING)
    ↓
11. [EarningService] → Marque earning "AVAILABLE" après délai (7 jours)
    ↓
12. [Event: EarningAvailableEvent]
    ↓
13. Prestataire demande un versement (Payout)
    ↓
14. [PayoutService] → Groupe les earnings disponibles
    ↓
15. [Event: PayoutRequestedEvent]
    ↓
16. Admin approuve le versement
    ↓
17. [PayoutService] → Process le paiement (Stripe/Mangopay)
    ↓
18. [Event: PayoutProcessedEvent]
    ↓
19. [TransactionService] → Enregistre Transaction (type: PAYOUT)
    ↓
20. [NotificationService] → Notifie le prestataire
```

---

## Points Clés de l'Architecture

### ✅ Avantages

1. **Module Autonome** : Toute la logique financière est isolée dans `Module/Financial/`
2. **Configuration Centralisée** : Tous les paramètres dans `financial.yaml`
3. **Traçabilité Totale** : Toutes les opérations enregistrées dans `Transaction`
4. **Flexibilité** : Pattern Strategy pour commissions et versements
5. **Évolutivité** : Facile d'ajouter de nouveaux gateways de paiement
6. **Testabilité** : Structure claire pour tests unitaires et intégration
7. **CQRS** : Séparation des requêtes (Query) et commandes (Service)
8. **Event-Driven** : Communication asynchrone via événements

### 🎯 Responsabilités Claires

- **Entity** : Modèles de données
- **Repository** : Accès aux données
- **Service** : Logique métier
- **Controller** : Points d'entrée API
- **EventListener** : Réactions aux événements
- **Factory** : Création d'objets complexes
- **Strategy** : Algorithmes interchangeables
- **Specification** : Règles métier réutilisables
- **DTO** : Transfert de données

---

## Prochaines Étapes

1. Créer les Entités
2. Créer les Repositories
3. Créer les Services
4. Créer les Controllers
5. Créer les EventListeners
6. Créer les Tests
7. Configurer les migrations Doctrine
8. Implémenter les webhooks Stripe/Mangopay

---

**Ce module est conçu pour être complètement autonome et facilement maintenable.**