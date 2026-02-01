# Description Complète des Entités - Plateforme de Services à Domicile

## Vue d'ensemble

Ce document décrit **toutes les entités PHP** du projet avec leurs propriétés, validations, relations et responsabilités. Les entités sont organisées par modules fonctionnels.

---

## Table des Matières

1. [Module User](#1-module-user)
2. [Module Service](#2-module-service)
3. [Module Quote](#3-module-quote)
4. [Module Booking](#4-module-booking)
5. [Module Planning](#5-module-planning)
6. [Module Rating/Review](#6-module-ratingreview)
7. [Module Notification](#7-module-notification)
8. [Module Document](#8-module-document)
9. [Module Financial](#9-module-financial)

---

## 1. Module User

### 1.1 User (Entité de Base)

**Namespace**: `App\Entity\User\User`  
**Table**: `users`  
**Type**: Classe abstraite avec héritage Single Table Inheritance (STI)

#### Propriétés Communes

```php
#[ORM\Entity]
#[ORM\Table(name: 'users')]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'dtype', type: 'string')]
#[ORM\DiscriminatorMap(['client' => Client::class, 'prestataire' => Prestataire::class, 'admin' => Admin::class])]
abstract class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column(type: 'json')]
    private array $roles = [];

    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $firstName = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $lastName = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9\s\+\-\(\)]+$/', message: 'Numéro de téléphone invalide')]
    private ?string $phone = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9]{5}$/', message: 'Code postal invalide')]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'string', length: 100)]
    private string $country = 'France';

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

**Responsabilités**:
- Authentification et gestion des rôles
- Informations de base communes à tous les types d'utilisateurs
- Vérification d'email

---

### 1.2 Client

**Namespace**: `App\Entity\User\Client`  
**Table**: `users` (dtype = 'client')  
**Hérite de**: `User`

#### Propriétés Spécifiques

```php
class Client extends User
{
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $preferredPaymentMethod = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $defaultAddress = null;

    // Relations

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ServiceRequest::class, cascade: ['persist', 'remove'])]
    private Collection $serviceRequests;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Payment::class)]
    private Collection $payments;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: PaymentMethod::class)]
    private Collection $paymentMethods;
}
```

**Responsabilités**:
- Création de demandes de service
- Réservation et paiement de services
- Évaluation des prestataires
- Gestion de ses méthodes de paiement

**Relations**:
- `OneToMany` → ServiceRequest
- `OneToMany` → Booking
- `OneToMany` → Review
- `OneToMany` → Payment (Financial)
- `OneToMany` → PaymentMethod (Financial)

---

### 1.3 Prestataire

**Namespace**: `App\Entity\User\Prestataire`  
**Table**: `users` (dtype = 'prestataire')  
**Hérite de**: `User`

#### Propriétés Spécifiques

```php
class Prestataire extends User
{
    #[ORM\Column(type: 'string', length: 14, unique: true)]
    #[Assert\Length(exactly: 14)]
    #[Assert\Regex(pattern: '/^[0-9]{14}$/', message: 'SIRET invalide')]
    private ?string $siret = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $kbisDocument = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $insuranceDocument = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $insuranceExpiryDate = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?float $hourlyRate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    #[Assert\Range(min: 1, max: 100)]
    private ?int $radius = null; // Rayon d'intervention en km

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2)]
    private float $averageRating = 0.0;

    #[ORM\Column(type: 'integer')]
    private int $totalReviews = 0;

    #[ORM\Column(type: 'boolean')]
    private bool $isApproved = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $approvedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $experienceYears = null;

    // Relations

    #[ORM\ManyToMany(targetEntity: ServiceCategory::class)]
    #[ORM\JoinTable(name: 'prestataire_service_categories')]
    private Collection $serviceCategories; // ⚠️ DEPRECATED - Utiliser $specializations

    #[ORM\ManyToMany(targetEntity: ServiceSubcategory::class)]
    #[ORM\JoinTable(name: 'prestataire_specializations')]
    private Collection $specializations; // ✅ NOUVEAU - Spécialisations détaillées

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Quote::class)]
    private Collection $quotes;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Availability::class, cascade: ['persist', 'remove'])]
    private Collection $availabilities;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Absence::class)]
    private Collection $absences;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Document::class)]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: PrestataireEarning::class)]
    private Collection $earnings;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Payout::class)]
    private Collection $payouts;
}
```

**Responsabilités**:
- Proposition de devis aux clients
- Gestion de ses spécialisations (sous-catégories)
- Gestion de ses disponibilités et absences
- Réalisation des services réservés
- Gestion de ses gains et demandes de versement
- Vérification et approbation admin

**Relations**:
- `ManyToMany` → ServiceCategory (deprecated)
- `ManyToMany` → ServiceSubcategory (✅ NOUVEAU - spécialisations)
- `OneToMany` → Quote
- `OneToMany` → Booking
- `OneToMany` → Availability
- `OneToMany` → Absence
- `OneToMany` → Document
- `OneToMany` → PrestataireEarning (Financial)
- `OneToMany` → Payout (Financial)

---

### 1.4 Admin

**Namespace**: `App\Entity\User\Admin`  
**Table**: `users` (dtype = 'admin')  
**Hérite de**: `User`

```php
class Admin extends User
{
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $department = null;

    #[ORM\Column(type: 'json')]
    private array $permissions = [];
}
```

**Responsabilités**:
- Approbation des prestataires
- Gestion des utilisateurs
- Gestion des catégories de services
- Validation des documents
- Gestion financière (approbation des versements)

---

## 2. Module Service

### 2.1 ServiceCategory (✅ MODIFIÉ - Hiérarchie)

**Namespace**: `App\Entity\Service\ServiceCategory`  
**Table**: `service_categories`

```php
#[ORM\Entity(repositoryClass: ServiceCategoryRepository::class)]
#[ORM\Table(name: 'service_categories')]
class ServiceCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $displayOrder = 0;

    // ✅ NOUVEAU - Auto-référence pour hiérarchie
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(onDelete: 'CASCADE')]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['displayOrder' => 'ASC'])]
    private Collection $children;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ServiceSubcategory::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['displayOrder' => 'ASC'])]
    private Collection $subcategories; // ✅ NOUVEAU

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ServiceType::class)]
    private Collection $serviceTypes; // ⚠️ DEPRECATED

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ServiceRequest::class)]
    private Collection $serviceRequests;

    #[ORM\ManyToMany(targetEntity: Prestataire::class, mappedBy: 'serviceCategories')]
    private Collection $prestataires; // ⚠️ DEPRECATED

    #[ORM\OneToMany(mappedBy: 'serviceCategory', targetEntity: CommissionRule::class)]
    private Collection $commissionRules;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->subcategories = new ArrayCollection();
        $this->serviceTypes = new ArrayCollection();
        $this->serviceRequests = new ArrayCollection();
        $this->prestataires = new ArrayCollection();
        $this->commissionRules = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    // ✅ Méthodes utilitaires pour hiérarchie

    public function isRootCategory(): bool
    {
        return $this->parent === null;
    }

    public function hasChildren(): bool
    {
        return $this->children->count() > 0;
    }

    public function getLevel(): int
    {
        $level = 0;
        $current = $this;
        
        while ($current->getParent()) {
            $level++;
            $current = $current->getParent();
        }
        
        return $level;
    }

    public function getAllSubcategories(): Collection
    {
        $allSubcategories = new ArrayCollection();
        
        // Sous-catégories directes
        foreach ($this->subcategories as $subcategory) {
            $allSubcategories->add($subcategory);
        }
        
        // Sous-catégories des enfants (récursif)
        foreach ($this->children as $child) {
            foreach ($child->getAllSubcategories() as $subcategory) {
                $allSubcategories->add($subcategory);
            }
        }
        
        return $allSubcategories;
    }

    // Getters/Setters...
}
```

**Responsabilités**:
- Organisation hiérarchique des services
- Regroupement de sous-catégories
- Support de l'arborescence multi-niveaux
- Navigation par catégories

**Relations**:
- `ManyToOne` → self (parent)
- `OneToMany` → self (children)
- `OneToMany` → ServiceSubcategory ✅ NOUVEAU
- `OneToMany` → ServiceType (deprecated)
- `OneToMany` → ServiceRequest
- `ManyToMany` ← Prestataire (deprecated)
- `OneToMany` → CommissionRule (Financial)

**Exemple de hiérarchie**:
```
Nettoyage (niveau 0 - racine)
├── Entretien courant (niveau 1)
│   ├── Nettoyage léger (subcategory)
│   └── Nettoyage standard (subcategory)
├── Grand ménage (niveau 1)
│   ├── Grand ménage classique (subcategory)
│   └── Grand ménage avec vitres (subcategory)
└── Nettoyage spécialisé (niveau 1)
    ├── Nettoyage après travaux (subcategory)
    └── Nettoyage de fin de bail (subcategory)
```

---

### 2.2 ServiceSubcategory (✅ NOUVEAU)

**Namespace**: `App\Entity\Service\ServiceSubcategory`  
**Table**: `service_subcategories`

```php
#[ORM\Entity(repositoryClass: ServiceSubcategoryRepository::class)]
#[ORM\Table(name: 'service_subcategories')]
#[ORM\HasLifecycleCallbacks]
class ServiceSubcategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class, inversedBy: 'subcategories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull]
    private ?ServiceCategory $category = null;

    #[ORM\Column(type: 'string', length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 3, max: 150)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 150, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?float $basePrice = null; // Tarif de base (€/h)

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedDuration = null; // Durée estimée (minutes)

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $displayOrder = 0;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $requirements = null; // Équipements requis

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations

    #[ORM\OneToMany(mappedBy: 'subcategory', targetEntity: ServiceRequest::class)]
    private Collection $serviceRequests;

    #[ORM\ManyToMany(targetEntity: Prestataire::class, mappedBy: 'specializations')]
    private Collection $prestataires;

    public function __construct()
    {
        $this->serviceRequests = new ArrayCollection();
        $this->prestataires = new ArrayCollection();
        $this->requirements = [];
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters/Setters...
}
```

**Responsabilités**:
- Définition précise des services offerts
- Tarification de base par service
- Estimation de durée
- Spécification des exigences matérielles
- Support des spécialisations prestataires

**Relations**:
- `ManyToOne` → ServiceCategory
- `OneToMany` → ServiceRequest
- `ManyToMany` ← Prestataire (via specializations)

**Exemples concrets**:
```
Grand ménage classique
├── basePrice: 30.00 €/h
├── estimatedDuration: 300 minutes
├── requirements: ["aspirateur", "matériel complet", "produits ménagers"]
└── 45 prestataires spécialisés

Nettoyage de fin de bail
├── basePrice: 45.00 €/h
├── estimatedDuration: 400 minutes
├── requirements: ["matériel professionnel", "garantie état des lieux"]
└── 23 prestataires spécialisés
```

---

### 2.3 ServiceType (⚠️ DEPRECATED)

**Namespace**: `App\Entity\Service\ServiceType`  
**Table**: `service_types`

**Note**: Cette entité est **deprecated** et remplacée par `ServiceSubcategory`.  
Elle reste dans le code pour compatibilité mais ne doit plus être utilisée pour les nouvelles fonctionnalités.

```php
#[ORM\Entity]
#[ORM\Table(name: 'service_types')]
class ServiceType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class, inversedBy: 'serviceTypes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceCategory $category = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $slug = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedDuration = null; // en minutes

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?float $basePrice = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

**Responsabilités**:
- Définition des types de services spécifiques
- Estimation de durée et prix de base

**Relations**:
- `ManyToOne` → ServiceCategory

---

### 2.4 ServiceRequest (✅ MODIFIÉ)

**Namespace**: `App\Entity\Service\ServiceRequest`  
**Table**: `service_requests`

```php
#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\Table(name: 'service_requests')]
#[ORM\HasLifecycleCallbacks]
class ServiceRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'serviceRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class, inversedBy: 'serviceRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceCategory $category = null;

    // ✅ NOUVEAU - Sous-catégorie spécifique
    #[ORM\ManyToOne(targetEntity: ServiceSubcategory::class, inversedBy: 'serviceRequests')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ServiceSubcategory $subcategory = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 5, max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $preferredDate = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $alternativeDates = [];

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedDuration = null; // en minutes

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: ['once', 'weekly', 'biweekly', 'monthly'])]
    private string $frequency = 'once';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?float $budget = null;

    // ✅ NOUVEAU - Surface en m² (pour nettoyage)
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $surfaceArea = null;

    // ✅ NOUVEAU - Exigences spécifiques (JSON)
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $specificRequirements = null; // Ex: ["fenêtres", "four", "réfrigérateur"]

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(choices: ['open', 'quoted', 'in_progress', 'completed', 'cancelled'])]
    private string $status = 'open';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    // Relations

    #[ORM\OneToMany(mappedBy: 'serviceRequest', targetEntity: Quote::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $quotes;

    #[ORM\OneToMany(mappedBy: 'serviceRequest', targetEntity: Booking::class)]
    private Collection $bookings;

    public function __construct()
    {
        $this->quotes = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->alternativeDates = [];
        $this->specificRequirements = [];
        $this->createdAt = new \DateTimeImmutable();
        $this->expiresAt = new \DateTimeImmutable('+7 days');
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ✅ Méthodes utilitaires

    public function getEstimatedPrice(): ?float
    {
        if ($this->subcategory && $this->estimatedDuration) {
            return ($this->subcategory->getBasePrice() * $this->estimatedDuration) / 60;
        }
        return null;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt < new \DateTimeImmutable();
    }

    // Getters/Setters...
}
```

**Responsabilités**:
- Création de demandes de service par les clients
- Spécification précise via sous-catégorie
- Spécification des besoins (localisation, date, budget, surface, exigences)
- Gestion du cycle de vie de la demande
- Calcul automatique du prix estimé

**Relations**:
- `ManyToOne` → Client
- `ManyToOne` → ServiceCategory
- `ManyToOne` → ServiceSubcategory ✅ NOUVEAU
- `OneToMany` → Quote
- `OneToMany` → Booking

**Exemple de données**:
```php
ServiceRequest {
    title: "Grand ménage appartement 80m²"
    category: Nettoyage (id: 1)
    subcategory: Grand ménage classique (id: 3)
    surfaceArea: 80
    specificRequirements: ["fenêtres", "four", "réfrigérateur"]
    estimatedDuration: 300 // minutes
    budget: 150.00
    // Prix estimé calculé automatiquement: (30€/h * 300min) / 60 = 150€
}
```

---

## 3. Module Quote

### 3.1 Quote

**Namespace**: `App\Entity\Quote\Quote`  
**Table**: `quotes`

```php
#[ORM\Entity]
#[ORM\Table(name: 'quotes')]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?float $amount = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank]
    private ?\DateTimeImmutable $proposedDate = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?int $proposedDuration = null; // en minutes

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // 'pending', 'accepted', 'rejected', 'expired'

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotBlank]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    // Relations

    #[ORM\OneToOne(mappedBy: 'quote', targetEntity: Booking::class, cascade: ['persist'])]
    private ?Booking $booking = null;

    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuoteItem::class, cascade: ['persist', 'remove'])]
    private Collection $items;
}
```

**Responsabilités**:
- Proposition de prix et conditions par le prestataire
- Validation de la disponibilité pour la date proposée
- Gestion du cycle de vie du devis (accepté/rejeté/expiré)

**Relations**:
- `ManyToOne` → ServiceRequest
- `ManyToOne` → Prestataire
- `OneToOne` → Booking
- `OneToMany` → QuoteItem

---

### 3.2 QuoteItem

**Namespace**: `App\Entity\Quote\QuoteItem`  
**Table**: `quote_items`

```php
#[ORM\Entity]
#[ORM\Table(name: 'quote_items')]
class QuoteItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Quote::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Quote $quote = null;

    #[ORM\ManyToOne(targetEntity: ServiceType::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ServiceType $serviceType = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private int $quantity = 1;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $unit = null; // heure, m², pièce, etc.

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?float $unitPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?float $totalPrice = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedDuration = null; // en minutes

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isOptional = false;

    #[ORM\Column(type: 'integer')]
    private int $displayOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

**Responsabilités**:
- Détail des prestations incluses dans le devis
- Calcul des sous-totaux

**Relations**:
- `ManyToOne` → Quote
- `ManyToOne` → ServiceType

---

## 4. Module Booking

### 4.1 Booking

**Namespace**: `App\Entity\Booking\Booking`  
**Table**: `bookings`

```php
#[ORM\Entity]
#[ORM\Table(name: 'bookings')]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'booking', targetEntity: Quote::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Quote $quote = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceCategory $serviceCategory = null;

    #[ORM\ManyToOne(targetEntity: Recurrence::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Recurrence $recurrence = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $scheduledDate = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $scheduledTime = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private ?int $duration = null; // en minutes

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?float $amount = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'scheduled'; // 'scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled'

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $actualStartTime = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $actualEndTime = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $completionNotes = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $cancelledBy = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Payment::class, cascade: ['persist'])]
    private ?Payment $payment = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Commission::class)]
    private ?Commission $commission = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: PrestataireEarning::class)]
    private ?PrestataireEarning $earning = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Review::class)]
    private ?Review $review = null;

    #[ORM\OneToMany(mappedBy: 'originalBooking', targetEntity: Replacement::class)]
    private Collection $replacements;
}
```

**Responsabilités**:
- Représentation d'une réservation confirmée
- Gestion du cycle de vie (programmé → confirmé → en cours → complété)
- Suivi du temps réel d'exécution
- Gestion des annulations

**Relations**:
- `OneToOne` → Quote
- `ManyToOne` → Client
- `ManyToOne` → Prestataire
- `ManyToOne` → ServiceCategory
- `ManyToOne` → Recurrence
- `OneToOne` → Payment (Financial)
- `OneToOne` → Commission (Financial)
- `OneToOne` → PrestataireEarning (Financial)
- `OneToOne` → Review
- `OneToMany` → Replacement

---

### 4.2 Recurrence

**Namespace**: `App\Entity\Booking\Recurrence`  
**Table**: `recurrences`

```php
#[ORM\Entity]
#[ORM\Table(name: 'recurrences')]
class Recurrence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['weekly', 'biweekly', 'monthly'])]
    private ?string $frequency = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private int $intervalValue = 1;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 0, max: 6)]
    private ?int $dayOfWeek = null; // 0=Dimanche, 6=Samedi

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 31)]
    private ?int $dayOfMonth = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    // Relations

    #[ORM\OneToMany(mappedBy: 'recurrence', targetEntity: Booking::class)]
    private Collection $bookings;
}
```

**Responsabilités**:
- Définition de la récurrence des réservations
- Gestion des services hebdomadaires/mensuels

**Relations**:
- `OneToMany` → Booking

---

## 5. Module Planning

### 5.1 Availability

**Namespace**: `App\Entity\Planning\Availability`  
**Table**: `availabilities`

```php
#[ORM\Entity]
#[ORM\Table(name: 'availabilities')]
#[ORM\HasLifecycleCallbacks]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 0, max: 6)]
    private ?int $dayOfWeek = null; // 0-6 (NULL si date spécifique)

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $specificDate = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRecurring = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

**Responsabilités**:
- Définition des créneaux de disponibilité du prestataire
- Gestion des disponibilités récurrentes et ponctuelles

**Relations**:
- `ManyToOne` → Prestataire

---

### 5.2 Absence

**Namespace**: `App\Entity\Planning\Absence`  
**Table**: `absences`

```php
#[ORM\Entity]
#[ORM\Table(name: 'absences')]
#[ORM\HasLifecycleCallbacks]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'absences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // 'pending', 'approved', 'rejected'

    #[ORM\Column(type: 'boolean')]
    private bool $requiresReplacement = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

**Responsabilités**:
- Déclaration des périodes d'indisponibilité
- Gestion des remplacements si nécessaire

**Relations**:
- `ManyToOne` → Prestataire

---

### 5.3 Replacement

**Namespace**: `App\Entity\Planning\Replacement`  
**Table**: `replacements`

```php
#[ORM\Entity]
#[ORM\Table(name: 'replacements')]
#[ORM\HasLifecycleCallbacks]
class Replacement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'replacements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $originalBooking = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $originalPrestataire = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Prestataire $replacementPrestataire = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // 'pending', 'confirmed', 'rejected'

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;
}
```

**Responsabilités**:
- Gestion des remplacements de prestataires
- Notification au client du changement

**Relations**:
- `ManyToOne` → Booking
- `ManyToOne` → Prestataire (original)
- `ManyToOne` → Prestataire (remplacement)

---

## 6. Module Rating/Review

### 6.1 Review

**Namespace**: `App\Entity\Review\Review`  
**Table**: `reviews`

```php
#[ORM\Entity]
#[ORM\Table(name: 'reviews')]
#[ORM\HasLifecycleCallbacks]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'review', targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $rating = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $comment = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $qualityRating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $punctualityRating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $professionalismRating = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isVisible = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

**Responsabilités**:
- Évaluation du prestataire après un service
- Calcul de la moyenne des notes
- Modération des avis

**Relations**:
- `OneToOne` → Booking
- `ManyToOne` → Client
- `ManyToOne` → Prestataire

---

## 7. Module Notification

### 7.1 Notification

**Namespace**: `App\Entity\Notification\Notification`  
**Table**: `notifications`

```php
#[ORM\Entity]
#[ORM\Table(name: 'notifications')]
class Notification
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['email', 'sms', 'push', 'in_app'])]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $channel = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $subject = null;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $message = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $data = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isRead = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $readAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

**Responsabilités**:
- Envoi de notifications aux utilisateurs
- Gestion multi-canal (email, SMS, push, in-app)
- Suivi de lecture

**Relations**:
- `ManyToOne` → User

---

## 8. Module Document

### 8.1 Document

**Namespace**: `App\Entity\Document\Document`  
**Table**: `documents`

```php
#[ORM\Entity]
#[ORM\Table(name: 'documents')]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'documents')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    #[Assert\Choice(choices: ['kbis', 'insurance', 'id_card', 'certificate'])]
    private ?string $type = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $filePath = null;

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $fileName = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $fileSize = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // 'pending', 'approved', 'rejected'

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $expiryDate = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $verifiedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

**Responsabilités**:
- Stockage des documents administratifs
- Validation par les admins
- Gestion des dates d'expiration

**Relations**:
- `ManyToOne` → Prestataire
- `ManyToOne` → Admin (verified by)

---

## 9. Module Financial (Autonome) 🏦

### 9.1 Payment

**Namespace**: `App\Module\Financial\Entity\Payment`  
**Table**: `financial_payment`

```php
#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'financial_payment')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Booking $booking;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private float $amount;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank]
    private string $paymentMethod; // 'card', 'bank_transfer', 'stripe', 'mangopay'

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $paymentGateway = null; // 'stripe', 'mangopay'

    #[ORM\Column(type: 'string', length: 255, unique: true, nullable: true)]
    private ?string $gatewayTransactionId = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // 'pending', 'processing', 'completed', 'failed', 'refunded'

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $failedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // Relations

    #[ORM\OneToOne(mappedBy: 'payment', targetEntity: Commission::class)]
    private ?Commission $commission = null;

    #[ORM\OneToMany(mappedBy: 'payment', targetEntity: RefundRequest::class)]
    private Collection $refundRequests;
}
```

**Responsabilités**:
- Traitement des paiements clients via Stripe/Mangopay
- Suivi du statut de paiement
- Gestion des échecs et remboursements

**Relations**:
- `OneToOne` → Booking
- `ManyToOne` → Client
- `OneToOne` → Commission
- `OneToMany` → RefundRequest

---

### 9.2 Commission

**Namespace**: `App\Module\Financial\Entity\Commission`  
**Table**: `financial_commission`

```php
#[ORM\Entity(repositoryClass: CommissionRepository::class)]
#[ORM\Table(name: 'financial_commission')]
class Commission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Booking $booking;

    #[ORM\OneToOne(inversedBy: 'commission', targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Payment $payment;

    #[ORM\ManyToOne(targetEntity: CommissionRule::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?CommissionRule $commissionRule = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private float $baseAmount; // Montant de base

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Positive]
    private float $commissionRate; // Taux en %

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private float $commissionAmount; // Montant de la commission

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $calculationMethod = null; // 'percentage', 'fixed', 'tiered'

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $calculationDetails = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

**Responsabilités**:
- Calcul automatique de la commission selon les règles
- Traçabilité du calcul

**Relations**:
- `OneToOne` → Booking
- `OneToOne` → Payment
- `ManyToOne` → CommissionRule

---

### 9.3 PrestataireEarning

**Namespace**: `App\Module\Financial\Entity\PrestataireEarning`  
**Table**: `financial_prestataire_earning`

```php
#[ORM\Entity(repositoryClass: PrestataireEarningRepository::class)]
#[ORM\Table(name: 'financial_prestataire_earning')]
class PrestataireEarning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'earnings')]
    #[ORM\JoinColumn(nullable: false)]
    private Prestataire $prestataire;

    #[ORM\OneToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true)]
    private Booking $booking;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private float $totalAmount; // Montant total du service

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private float $commissionAmount; // Commission plateforme

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    private float $netAmount; // Montant net prestataire

    #[ORM\ManyToOne(targetEntity: Payout::class, inversedBy: 'earnings')]
    #[ORM\JoinColumn(nullable: true)]
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
}
```

**Responsabilités**:
- Calcul du gain net du prestataire (total - commission)
- Gestion de la disponibilité du gain (délai de sécurité)
- Traçabilité des versements

**Relations**:
- `ManyToOne` → Prestataire
- `OneToOne` → Booking
- `ManyToOne` → Payout

---

### 9.4 Payout

**Namespace**: `App\Module\Financial\Entity\Payout`  
**Table**: `financial_payout`

```php
#[ORM\Entity(repositoryClass: PayoutRepository::class)]
#[ORM\Table(name: 'financial_payout')]
class Payout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'payouts')]
    #[ORM\JoinColumn(nullable: false)]
    private Prestataire $prestataire;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private float $amount;

    #[ORM\Column(type: 'string', length: 20, enumType: PayoutStatus::class)]
    private PayoutStatus $status = PayoutStatus::PENDING;

    #[ORM\Column(type: 'string', length: 50)]
    private string $paymentMethod; // 'bank_transfer', 'stripe_payout'

    #[ORM\OneToMany(mappedBy: 'payout', targetEntity: PrestataireEarning::class)]
    private Collection $earnings;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: 'string', length: 100, unique: true, nullable: true)]
    private ?string $transactionReference = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'json')]
    private array $bankDetails = [];

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $approvedBy = null;
}
```

**Responsabilités**:
- Regroupement des gains pour versement
- Approbation par admin
- Traitement du virement bancaire

**Relations**:
- `ManyToOne` → Prestataire
- `OneToMany` → PrestataireEarning
- `ManyToOne` → Admin (approved by)

---

### 9.5 Transaction

**Namespace**: `App\Module\Financial\Entity\Transaction`  
**Table**: `financial_transaction`

```php
#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'financial_transaction')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 30, enumType: TransactionType::class)]
    private TransactionType $type;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $amount;

    #[ORM\Column(type: 'string', length: 3)]
    private string $currency = 'EUR';

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    #[ORM\ManyToOne(targetEntity: Commission::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Commission $commission = null;

    #[ORM\ManyToOne(targetEntity: PrestataireEarning::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?PrestataireEarning $earning = null;

    #[ORM\ManyToOne(targetEntity: Payout::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payout $payout = null;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private string $reference;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;
}
```

**Responsabilités**:
- Journal de toutes les transactions financières
- Traçabilité complète
- Audit et reporting

**Relations**:
- `ManyToOne` → User
- `ManyToOne` → Booking
- `ManyToOne` → Payment
- `ManyToOne` → Commission
- `ManyToOne` → PrestataireEarning
- `ManyToOne` → Payout

---

### 9.6 CommissionRule

**Namespace**: `App\Module\Financial\Entity\CommissionRule`  
**Table**: `financial_commission_rule`

```php
#[ORM\Entity(repositoryClass: CommissionRuleRepository::class)]
#[ORM\Table(name: 'financial_commission_rule')]
class CommissionRule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(type: 'string', length: 20, enumType: CommissionRuleType::class)]
    private CommissionRuleType $type;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Positive]
    private float $value;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $minAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $maxAmount = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class, inversedBy: 'commissionRules')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ServiceCategory $category = null;

    #[ORM\Column(type: 'json')]
    private array $conditions = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'integer')]
    private int $priority = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $validFrom;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $validUntil = null;
}
```

**Responsabilités**:
- Définition des règles de calcul de commission
- Gestion des taux par catégorie ou montant
- Priorités et périodes de validité

**Relations**:
- `ManyToOne` → ServiceCategory
- `OneToMany` ← Commission

---

### 9.7 Invoice

**Namespace**: `App\Module\Financial\Entity\Invoice`  
**Table**: `financial_invoice`

```php
#[ORM\Entity(repositoryClass: InvoiceRepository::class)]
#[ORM\Table(name: 'financial_invoice')]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50, unique: true)]
    private string $invoiceNumber;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type = 'standard'; // 'standard', 'advance', 'credit_note'

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Prestataire $prestataire;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Payment::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Payment $payment = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $amount;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $taxAmount = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private float $totalAmount;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'draft'; // 'draft', 'sent', 'paid', 'overdue'

    #[ORM\Column(type: 'date')]
    private \DateTimeInterface $issueDate;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $paidDate = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $pdfPath = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

**Responsabilités**:
- Génération automatique de factures
- Gestion des statuts (brouillon, envoyée, payée)
- Stockage PDF

**Relations**:
- `ManyToOne` → Client
- `ManyToOne` → Prestataire
- `ManyToOne` → Booking
- `ManyToOne` → Payment

---

### 9.8 RefundRequest

**Namespace**: `App\Module\Financial\Entity\RefundRequest`  
**Table**: `financial_refund_request`

```php
#[ORM\Entity(repositoryClass: RefundRequestRepository::class)]
#[ORM\Table(name: 'financial_refund_request')]
class RefundRequest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Payment::class, inversedBy: 'refundRequests')]
    #[ORM\JoinColumn(nullable: false)]
    private Payment $payment;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private float $amount;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private string $reason;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'requested'; // 'requested', 'approved', 'processing', 'completed', 'rejected'

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $adminNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $approvedBy = null;
}
```

**Responsabilités**:
- Gestion des demandes de remboursement
- Approbation admin
- Traitement des remboursements

**Relations**:
- `ManyToOne` → Payment
- `ManyToOne` → Client
- `ManyToOne` → Admin (approved by)

---

### 9.9 PaymentMethod

**Namespace**: `App\Module\Financial\Entity\PaymentMethod`  
**Table**: `financial_payment_method`

```php
#[ORM\Entity(repositoryClass: PaymentMethodRepository::class)]
#[ORM\Table(name: 'financial_payment_method')]
class PaymentMethod
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'paymentMethods')]
    #[ORM\JoinColumn(nullable: false)]
    private Client $client;

    #[ORM\Column(type: 'string', length: 50)]
    private string $type; // 'card', 'bank_account'

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $provider = null; // 'stripe', 'mangopay'

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $providerPaymentMethodId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    #[ORM\Column(type: 'string', length: 4, nullable: true)]
    private ?string $cardLast4 = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $cardBrand = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $cardExpMonth = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $cardExpYear = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $bankName = null;

    #[ORM\Column(type: 'string', length: 4, nullable: true)]
    private ?string $bankAccountLast4 = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;
}
```

**Responsabilités**:
- Stockage sécurisé des méthodes de paiement
- Gestion des cartes et comptes bancaires enregistrés
- Méthode par défaut

**Relations**:
- `ManyToOne` → Client

---

## Énumérations (Enums)

### TransactionType
```php
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
```

### EarningStatus
```php
enum EarningStatus: string
{
    case PENDING = 'pending';
    case AVAILABLE = 'available';
    case PAID = 'paid';
    case DISPUTED = 'disputed';
    case CANCELLED = 'cancelled';
}
```

### PayoutStatus
```php
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
```

### CommissionRuleType
```php
enum CommissionRuleType: string
{
    case PERCENTAGE = 'percentage';
    case FIXED = 'fixed';
    case TIERED = 'tiered';
}
```

---

## Résumé des Relations Globales

```
User (STI)
  ├─ Client
  │   ├─→ ServiceRequest (OneToMany)
  │   ├─→ Booking (OneToMany)
  │   ├─→ Review (OneToMany)
  │   ├─→ Payment (OneToMany) [Financial]
  │   └─→ PaymentMethod (OneToMany) [Financial]
  │
  ├─ Prestataire
  │   ├─→ ServiceCategory (ManyToMany) ⚠️ DEPRECATED
  │   ├─→ ServiceSubcategory (ManyToMany) ✅ NOUVEAU - Spécialisations
  │   ├─→ Quote (OneToMany)
  │   ├─→ Booking (OneToMany)
  │   ├─→ Availability (OneToMany)
  │   ├─→ Absence (OneToMany)
  │   ├─→ Document (OneToMany)
  │   ├─→ PrestataireEarning (OneToMany) [Financial]
  │   └─→ Payout (OneToMany) [Financial]
  │
  └─ Admin

ServiceCategory (✅ MODIFIÉ - Hiérarchie)
  ├─→ ServiceCategory (OneToMany - children)
  ├─→ ServiceSubcategory (OneToMany) ✅ NOUVEAU
  ├─→ ServiceType (OneToMany) ⚠️ DEPRECATED
  ├─→ ServiceRequest (OneToMany)
  └─→ CommissionRule (OneToMany) [Financial]

ServiceSubcategory (✅ NOUVEAU)
  ├─→ ServiceRequest (OneToMany)
  └─← Prestataire (ManyToMany - specializations)

ServiceRequest (✅ MODIFIÉ)
  ├─→ ServiceCategory (ManyToOne)
  ├─→ ServiceSubcategory (ManyToOne) ✅ NOUVEAU
  ├─→ Quote (OneToMany)
  └─→ Booking (OneToMany)

Quote
  ├─→ QuoteItem (OneToMany)
  └─→ Booking (OneToOne)

Booking
  ├─→ Payment (OneToOne) [Financial]
  ├─→ Commission (OneToOne) [Financial]
  ├─→ PrestataireEarning (OneToOne) [Financial]
  ├─→ Review (OneToOne)
  └─→ Replacement (OneToMany)

Financial Module:
  Payment
    ├─→ Commission (OneToOne)
    └─→ RefundRequest (OneToMany)
  
  Commission
    └─→ CommissionRule (ManyToOne)
  
  PrestataireEarning
    └─→ Payout (ManyToOne)
  
  Payout
    └─→ PrestataireEarning (OneToMany)
  
  Transaction (traçabilité de tout)
```

---

## 📝 Modifications Clés - Système de Catégorisation

### ✅ Entités Nouvelles
1. **ServiceSubcategory** - Sous-catégories avec tarification détaillée
2. **Hiérarchie ServiceCategory** - Auto-référence parent/children

### ✅ Entités Modifiées
1. **Prestataire**
   - Ajout: `specializations` (ManyToMany → ServiceSubcategory)
   - Deprecated: `serviceCategories` (à migrer)

2. **ServiceRequest**
   - Ajout: `subcategory` (ManyToOne → ServiceSubcategory)
   - Ajout: `surfaceArea` (int)
   - Ajout: `specificRequirements` (json)
   - Méthode: `getEstimatedPrice()` basée sur subcategory

3. **ServiceCategory**
   - Ajout: `parent` (ManyToOne → self)
   - Ajout: `children` (OneToMany → self)
   - Ajout: `subcategories` (OneToMany → ServiceSubcategory)
   - Méthodes: `isRootCategory()`, `hasChildren()`, `getLevel()`, `getAllSubcategories()`

### ⚠️ Entités Deprecated
- **ServiceType** - Remplacé par ServiceSubcategory

### 🔄 Migration Recommandée
```sql
-- 1. Créer la nouvelle table service_subcategories
CREATE TABLE service_subcategories (...);

-- 2. Créer la table de liaison prestataire_specializations
CREATE TABLE prestataire_specializations (...);

-- 3. Migrer les données de service_types vers service_subcategories
INSERT INTO service_subcategories (category_id, name, slug, ...)
SELECT category_id, name, slug, ... FROM service_types;

-- 4. Migrer les relations prestataire_service_categories
INSERT INTO prestataire_specializations (prestataire_id, subcategory_id)
SELECT psc.prestataire_id, s.id
FROM prestataire_service_categories psc
JOIN service_subcategories s ON s.category_id = psc.category_id;

-- 5. Ajouter les colonnes à service_requests
ALTER TABLE service_requests 
ADD COLUMN subcategory_id INT,
ADD COLUMN surface_area INT,
ADD COLUMN specific_requirements JSON;

-- 6. Ajouter les colonnes à service_categories pour hiérarchie
ALTER TABLE service_categories 
ADD COLUMN parent_id INT,
ADD FOREIGN KEY (parent_id) REFERENCES service_categories(id);
```

---

**Total: 36 Entités** (35 originales + ServiceSubcategory) **complètement décrites avec propriétés, validations et relations.**