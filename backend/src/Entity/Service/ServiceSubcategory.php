<?php
// src/Entity/Service/ServiceSubcategory.php

namespace App\Entity\Service;

use App\Entity\User\Prestataire;
use App\Repository\Service\ServiceSubcategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Sous-catégorie de service avec tarification détaillée
 * 
 * Exemples :
 * - Grand ménage classique (basePrice: 30€/h, duration: 300min)
 * - Nettoyage de fin de bail (basePrice: 45€/h, duration: 400min)
 * - Repassage standard (basePrice: 20€/h, duration: 120min)
 * 
 * Cette entité remplace ServiceType (deprecated) avec des fonctionnalités étendues
 */
#[ORM\Entity(repositoryClass: ServiceSubcategoryRepository::class)]
#[ORM\Table(name: 'service_subcategories')]
#[ORM\Index(columns: ['slug'], name: 'idx_subcategory_slug')]
#[ORM\Index(columns: ['category_id'], name: 'idx_subcategory_category')]
#[ORM\Index(columns: ['is_active'], name: 'idx_subcategory_active')]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug existe déjà')]
#[ORM\HasLifecycleCallbacks]
class ServiceSubcategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['subcategory:read', 'subcategory:list', 'subcategory:detail'])]
    private ?int $id = null;

    // ============================================
    // RELATION AVEC LA CATÉGORIE PARENTE
    // ============================================

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class, inversedBy: 'subcategories')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La catégorie parente est obligatoire')]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?ServiceCategory $category = null;

    // ============================================
    // INFORMATIONS DE BASE
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 150)]
    #[Assert\NotBlank(message: 'Le nom de la sous-catégorie est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 150,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['subcategory:read', 'subcategory:list', 'subcategory:detail'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 150, unique: true)]
    #[Groups(['subcategory:read', 'subcategory:list', 'subcategory:detail'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?string $description = null;

    // ============================================
    // TARIFICATION ET DURÉE
    // ============================================

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le prix de base doit être positif')]
    #[Assert\Range(
        min: 5,
        max: 200,
        notInRangeMessage: 'Le prix de base doit être entre {{ min }}€ et {{ max }}€'
    )]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?string $basePrice = null; // Tarif de base (€/h)

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le prix minimum doit être positif')]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?string $minPrice = null; // Prix minimum suggéré

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le prix maximum doit être positif')]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'minPrice',
        message: 'Le prix maximum doit être supérieur ou égal au prix minimum'
    )]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?string $maxPrice = null; // Prix maximum suggéré

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'La durée estimée doit être positive')]
    #[Assert\Range(
        min: 15,
        max: 960,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
    )]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?int $estimatedDuration = null; // Durée estimée (minutes)

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?int $minDuration = null; // Durée minimum (minutes)

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'minDuration',
        message: 'La durée maximum doit être supérieure ou égale à la durée minimum'
    )]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?int $maxDuration = null; // Durée maximum (minutes)

    // ============================================
    // AFFICHAGE
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['subcategory:read', 'subcategory:list'])]
    private ?string $icon = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['subcategory:read', 'subcategory:list'])]
    private ?string $image = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Le format de couleur doit être hexadécimal (#RRGGBB)')]
    #[Groups(['subcategory:read', 'subcategory:list'])]
    private ?string $color = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['subcategory:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['subcategory:read'])]
    private int $displayOrder = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isFeatured = false;

    // ============================================
    // EXIGENCES ET SPÉCIFICATIONS
    // ============================================

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private ?array $requirements = null; // Équipements requis

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['subcategory:detail'])]
    private ?array $skillsRequired = null; // Compétences requises

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['subcategory:read', 'subcategory:detail'])]
    private bool $requiresEquipment = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['subcategory:detail'])]
    private bool $requiresCertification = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['subcategory:detail'])]
    private ?string $specificInstructions = null;

    // ============================================
    // OPTIONS ET COMPLÉMENTS
    // ============================================

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['subcategory:detail'])]
    private ?array $additionalOptions = null; // Options supplémentaires possibles

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['subcategory:detail'])]
    private ?array $includedServices = null; // Services inclus dans la prestation

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['subcategory:detail'])]
    private ?array $excludedServices = null; // Services non inclus

    // ============================================
    // RELATIONS
    // ============================================

    #[ORM\OneToMany(mappedBy: 'subcategory', targetEntity: ServiceRequest::class)]
    private Collection $serviceRequests;

    /**
     * Prestataires ayant cette sous-catégorie dans leurs spécialisations
     */
    #[ORM\ManyToMany(targetEntity: Prestataire::class, mappedBy: 'specializations')]
    private Collection $prestataires;

    // ============================================
    // STATISTIQUES ET MÉTRIQUES
    // ============================================

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['subcategory:detail'])]
    private int $requestCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['subcategory:detail'])]
    private int $prestataireCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $viewCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $bookingCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    #[Groups(['subcategory:detail'])]
    private ?string $averagePrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 5)]
    #[Groups(['subcategory:detail'])]
    private ?string $averageRating = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $popularityScore = 0;

    // ============================================
    // INFORMATIONS COMPLÉMENTAIRES
    // ============================================

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoTitle = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $seoDescription = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $seoKeywords = null;

    // ============================================
    // DATES
    // ============================================

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['subcategory:detail'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['subcategory:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // ============================================
    // CONSTRUCTEUR
    // ============================================

    public function __construct()
    {
        $this->serviceRequests = new ArrayCollection();
        $this->prestataires = new ArrayCollection();
        $this->requirements = [];
        $this->skillsRequired = [];
        $this->additionalOptions = [];
        $this->includedServices = [];
        $this->excludedServices = [];
        $this->createdAt = new \DateTimeImmutable();
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCategory(): ?ServiceCategory
    {
        return $this->category;
    }

    public function setCategory(?ServiceCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    // ============================================
    // TARIFICATION
    // ============================================

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(?string $basePrice): self
    {
        $this->basePrice = $basePrice;
        return $this;
    }

    public function getBasePriceFloat(): ?float
    {
        return $this->basePrice !== null ? (float) $this->basePrice : null;
    }

    public function getMinPrice(): ?string
    {
        return $this->minPrice;
    }

    public function setMinPrice(?string $minPrice): self
    {
        $this->minPrice = $minPrice;
        return $this;
    }

    public function getMaxPrice(): ?string
    {
        return $this->maxPrice;
    }

    public function setMaxPrice(?string $maxPrice): self
    {
        $this->maxPrice = $maxPrice;
        return $this;
    }

    /**
     * Retourne la fourchette de prix formatée
     */
    public function getPriceRange(): ?string
    {
        if ($this->minPrice !== null && $this->maxPrice !== null) {
            return sprintf('%.2f€ - %.2f€/h', (float) $this->minPrice, (float) $this->maxPrice);
        } elseif ($this->basePrice !== null) {
            return sprintf('%.2f€/h', (float) $this->basePrice);
        }
        return null;
    }

    // ============================================
    // DURÉE
    // ============================================

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): self
    {
        $this->estimatedDuration = $estimatedDuration;
        return $this;
    }

    public function getMinDuration(): ?int
    {
        return $this->minDuration;
    }

    public function setMinDuration(?int $minDuration): self
    {
        $this->minDuration = $minDuration;
        return $this;
    }

    public function getMaxDuration(): ?int
    {
        return $this->maxDuration;
    }

    public function setMaxDuration(?int $maxDuration): self
    {
        $this->maxDuration = $maxDuration;
        return $this;
    }

    /**
     * Retourne la durée formatée (ex: "2h30")
     */
    public function getFormattedDuration(): ?string
    {
        if ($this->estimatedDuration === null) {
            return null;
        }

        $hours = floor($this->estimatedDuration / 60);
        $minutes = $this->estimatedDuration % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh%02d', $hours, $minutes);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        } else {
            return sprintf('%d min', $minutes);
        }
    }

    /**
     * Retourne la fourchette de durée formatée
     */
    public function getDurationRange(): ?string
    {
        if ($this->minDuration !== null && $this->maxDuration !== null) {
            $minFormatted = $this->formatMinutes($this->minDuration);
            $maxFormatted = $this->formatMinutes($this->maxDuration);
            return sprintf('%s - %s', $minFormatted, $maxFormatted);
        }
        return $this->getFormattedDuration();
    }

    private function formatMinutes(int $minutes): string
    {
        $hours = floor($minutes / 60);
        $mins = $minutes % 60;

        if ($hours > 0 && $mins > 0) {
            return sprintf('%dh%02d', $hours, $mins);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        }
        return sprintf('%d min', $mins);
    }

    // ============================================
    // AFFICHAGE
    // ============================================

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }

    public function isFeatured(): bool
    {
        return $this->isFeatured;
    }

    public function setIsFeatured(bool $isFeatured): self
    {
        $this->isFeatured = $isFeatured;
        return $this;
    }

    // ============================================
    // EXIGENCES
    // ============================================

    public function getRequirements(): ?array
    {
        return $this->requirements ?? [];
    }

    public function setRequirements(?array $requirements): self
    {
        $this->requirements = $requirements;
        return $this;
    }

    public function addRequirement(string $requirement): self
    {
        $requirements = $this->getRequirements();
        if (!in_array($requirement, $requirements, true)) {
            $requirements[] = $requirement;
            $this->requirements = $requirements;
        }
        return $this;
    }

    public function removeRequirement(string $requirement): self
    {
        $requirements = $this->getRequirements();
        $key = array_search($requirement, $requirements, true);
        if ($key !== false) {
            unset($requirements[$key]);
            $this->requirements = array_values($requirements);
        }
        return $this;
    }

    public function getSkillsRequired(): ?array
    {
        return $this->skillsRequired ?? [];
    }

    public function setSkillsRequired(?array $skillsRequired): self
    {
        $this->skillsRequired = $skillsRequired;
        return $this;
    }

    public function requiresEquipment(): bool
    {
        return $this->requiresEquipment;
    }

    public function setRequiresEquipment(bool $requiresEquipment): self
    {
        $this->requiresEquipment = $requiresEquipment;
        return $this;
    }

    public function requiresCertification(): bool
    {
        return $this->requiresCertification;
    }

    public function setRequiresCertification(bool $requiresCertification): self
    {
        $this->requiresCertification = $requiresCertification;
        return $this;
    }

    public function getSpecificInstructions(): ?string
    {
        return $this->specificInstructions;
    }

    public function setSpecificInstructions(?string $specificInstructions): self
    {
        $this->specificInstructions = $specificInstructions;
        return $this;
    }

    // ============================================
    // OPTIONS
    // ============================================

    public function getAdditionalOptions(): ?array
    {
        return $this->additionalOptions ?? [];
    }

    public function setAdditionalOptions(?array $additionalOptions): self
    {
        $this->additionalOptions = $additionalOptions;
        return $this;
    }

    public function getIncludedServices(): ?array
    {
        return $this->includedServices ?? [];
    }

    public function setIncludedServices(?array $includedServices): self
    {
        $this->includedServices = $includedServices;
        return $this;
    }

    public function getExcludedServices(): ?array
    {
        return $this->excludedServices ?? [];
    }

    public function setExcludedServices(?array $excludedServices): self
    {
        $this->excludedServices = $excludedServices;
        return $this;
    }

    // ============================================
    // RELATIONS
    // ============================================

    /**
     * @return Collection<int, ServiceRequest>
     */
    public function getServiceRequests(): Collection
    {
        return $this->serviceRequests;
    }

    public function addServiceRequest(ServiceRequest $serviceRequest): self
    {
        if (!$this->serviceRequests->contains($serviceRequest)) {
            $this->serviceRequests->add($serviceRequest);
            $serviceRequest->setSubcategory($this);
        }

        return $this;
    }

    public function removeServiceRequest(ServiceRequest $serviceRequest): self
    {
        if ($this->serviceRequests->removeElement($serviceRequest)) {
            if ($serviceRequest->getSubcategory() === $this) {
                $serviceRequest->setSubcategory(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Prestataire>
     */
    public function getPrestataires(): Collection
    {
        return $this->prestataires;
    }

    public function addPrestataire(Prestataire $prestataire): self
    {
        if (!$this->prestataires->contains($prestataire)) {
            $this->prestataires->add($prestataire);
            $prestataire->addSpecialization($this);
        }

        return $this;
    }

    public function removePrestataire(Prestataire $prestataire): self
    {
        if ($this->prestataires->removeElement($prestataire)) {
            $prestataire->removeSpecialization($this);
        }

        return $this;
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    public function getRequestCount(): int
    {
        return $this->requestCount;
    }

    public function setRequestCount(int $requestCount): self
    {
        $this->requestCount = $requestCount;
        return $this;
    }

    public function incrementRequestCount(): self
    {
        $this->requestCount++;
        return $this;
    }

    public function getPrestataireCount(): int
    {
        return $this->prestataireCount;
    }

    public function setPrestataireCount(int $prestataireCount): self
    {
        $this->prestataireCount = $prestataireCount;
        return $this;
    }

    public function getViewCount(): int
    {
        return $this->viewCount;
    }

    public function setViewCount(int $viewCount): self
    {
        $this->viewCount = $viewCount;
        return $this;
    }

    public function incrementViewCount(): self
    {
        $this->viewCount++;
        return $this;
    }

    public function getBookingCount(): int
    {
        return $this->bookingCount;
    }

    public function setBookingCount(int $bookingCount): self
    {
        $this->bookingCount = $bookingCount;
        return $this;
    }

    public function incrementBookingCount(): self
    {
        $this->bookingCount++;
        return $this;
    }

    public function getAveragePrice(): ?string
    {
        return $this->averagePrice;
    }

    public function setAveragePrice(?string $averagePrice): self
    {
        $this->averagePrice = $averagePrice;
        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): self
    {
        $this->averageRating = $averageRating;
        return $this;
    }

    public function getPopularityScore(): int
    {
        return $this->popularityScore;
    }

    public function setPopularityScore(int $popularityScore): self
    {
        $this->popularityScore = $popularityScore;
        return $this;
    }

    public function incrementPopularityScore(int $increment = 1): self
    {
        $this->popularityScore += $increment;
        return $this;
    }

    // ============================================
    // MÉTADONNÉES ET SEO
    // ============================================

    public function getMetadata(): ?array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        $metadata = $this->getMetadata();
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    public function getSeoTitle(): ?string
    {
        return $this->seoTitle;
    }

    public function setSeoTitle(?string $seoTitle): self
    {
        $this->seoTitle = $seoTitle;
        return $this;
    }

    public function getSeoDescription(): ?string
    {
        return $this->seoDescription;
    }

    public function setSeoDescription(?string $seoDescription): self
    {
        $this->seoDescription = $seoDescription;
        return $this;
    }

    public function getSeoKeywords(): ?array
    {
        return $this->seoKeywords ?? [];
    }

    public function setSeoKeywords(?array $seoKeywords): self
    {
        $this->seoKeywords = $seoKeywords;
        return $this;
    }

    // ============================================
    // DATES
    // ============================================

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Calcule le prix estimé pour une durée donnée
     */
    public function calculateEstimatedPrice(?int $durationInMinutes = null): ?float
    {
        $duration = $durationInMinutes ?? $this->estimatedDuration;
        
        if ($this->basePrice === null || $duration === null) {
            return null;
        }

        return ((float) $this->basePrice * $duration) / 60;
    }

    /**
     * Obtient le nom complet avec la catégorie
     */
    public function getFullName(): string
    {
        if ($this->category !== null) {
            return $this->category->getName() . ' - ' . $this->name;
        }
        return $this->name ?? '';
    }

    /**
     * Vérifie si la sous-catégorie est visible (active et catégorie active)
     */
    public function isVisible(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        if ($this->category !== null && !$this->category->isActive()) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie si la sous-catégorie est populaire
     */
    public function isPopular(): bool
    {
        return $this->popularityScore >= 100 || $this->bookingCount >= 50;
    }

    /**
     * Vérifie si un prestataire est spécialisé dans cette sous-catégorie
     */
    public function hasPrestataire(Prestataire $prestataire): bool
    {
        return $this->prestataires->contains($prestataire);
    }

    // ============================================
    // LIFECYCLE CALLBACKS
    // ============================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ============================================
    // MÉTHODES SPÉCIALES
    // ============================================

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Retourne une représentation JSON-friendly de la sous-catégorie
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'categoryId' => $this->category?->getId(),
            'categoryName' => $this->category?->getName(),
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'basePrice' => $this->basePrice !== null ? (float) $this->basePrice : null,
            'priceRange' => $this->getPriceRange(),
            'estimatedDuration' => $this->estimatedDuration,
            'formattedDuration' => $this->getFormattedDuration(),
            'durationRange' => $this->getDurationRange(),
            'icon' => $this->icon,
            'image' => $this->image,
            'color' => $this->color,
            'displayOrder' => $this->displayOrder,
            'isActive' => $this->isActive,
            'isFeatured' => $this->isFeatured,
            'requiresEquipment' => $this->requiresEquipment,
            'requiresCertification' => $this->requiresCertification,
            'requirements' => $this->getRequirements(),
            'requestCount' => $this->requestCount,
            'prestataireCount' => $this->prestataireCount,
            'bookingCount' => $this->bookingCount,
            'averagePrice' => $this->averagePrice !== null ? (float) $this->averagePrice : null,
            'averageRating' => $this->averageRating !== null ? (float) $this->averageRating : null,
            'popularityScore' => $this->popularityScore,
            'isPopular' => $this->isPopular(),
        ];
    }
}