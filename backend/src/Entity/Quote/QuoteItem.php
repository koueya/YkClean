<?php
// src/Entity/Quote/QuoteItem.php

namespace App\Entity\Quote;

use App\Entity\Service\ServiceSubcategory;
use App\Repository\Quote\QuoteItemRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Item détaillé d'un devis
 * Représente une ligne de prestation dans un devis
 */
#[ORM\Entity(repositoryClass: QuoteItemRepository::class)]
#[ORM\Table(name: 'quote_items')]
#[ORM\Index(columns: ['quote_id'], name: 'idx_quote_item_quote')]
#[ORM\HasLifecycleCallbacks]
class QuoteItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    // ============================================
    // RELATIONS
    // ============================================

    #[ORM\ManyToOne(targetEntity: Quote::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le devis est obligatoire')]
    private ?Quote $quote = null;

    /**
     * ✅ Référence à la sous-catégorie de service
     */
    #[ORM\ManyToOne(targetEntity: ServiceSubcategory::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?ServiceSubcategory $subcategory = null;

    // ============================================
    // DESCRIPTION ET IDENTIFICATION
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères')]
    private ?string $notes = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $internalReference = null; // Référence interne du prestataire

    // ============================================
    // QUANTITÉ ET UNITÉ
    // ============================================

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'La quantité est obligatoire')]
    #[Assert\Positive(message: 'La quantité doit être positive')]
    #[Assert\Range(
        min: 1,
        max: 1000,
        notInRangeMessage: 'La quantité doit être entre {{ min }} et {{ max }}'
    )]
    private int $quantity = 1;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $unit = null; // heure, m², pièce, forfait, etc.

    // ============================================
    // TARIFICATION
    // ============================================

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix unitaire est obligatoire')]
    #[Assert\Positive(message: 'Le prix unitaire doit être positif')]
    #[Assert\Range(
        min: 0.01,
        max: 5000,
        notInRangeMessage: 'Le prix unitaire doit être entre {{ min }}€ et {{ max }}€'
    )]
    private ?string $unitPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\Positive(message: 'Le prix total doit être positif')]
    private ?string $totalPrice = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $discountPercentage = null; // Remise en %

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $discountAmount = null; // Montant de la remise

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $taxRate = null; // Taux de TVA en %

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero]
    private ?string $taxAmount = null; // Montant de TVA

    // ============================================
    // DURÉE
    // ============================================

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'La durée estimée doit être positive')]
    #[Assert\Range(
        min: 5,
        max: 960,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
    )]
    private ?int $estimatedDuration = null; // Durée unitaire en minutes

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive]
    private ?int $totalEstimatedDuration = null; // Durée totale (quantité * durée unitaire)

    // ============================================
    // OPTIONS ET PERSONNALISATION
    // ============================================

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null; // Options supplémentaires sélectionnées

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $specifications = null; // Spécifications détaillées

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isOptional = false; // Est-ce un item optionnel ?

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isIncluded = true; // Est-ce inclus dans le prix de base ?

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $requiresClientApproval = false; // Nécessite l'approbation du client

    // ============================================
    // AFFICHAGE ET ORDRE
    // ============================================

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $displayOrder = 0;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $category = null; // Catégorie d'affichage (ex: "Nettoyage", "Options")

    // ============================================
    // MÉTADONNÉES
    // ============================================

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    // ============================================
    // CONSTRUCTEUR
    // ============================================

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->options = [];
        $this->specifications = [];
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): self
    {
        $this->quote = $quote;
        return $this;
    }

    public function getSubcategory(): ?ServiceSubcategory
    {
        return $this->subcategory;
    }

    public function setSubcategory(?ServiceSubcategory $subcategory): self
    {
        $this->subcategory = $subcategory;
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

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getInternalReference(): ?string
    {
        return $this->internalReference;
    }

    public function setInternalReference(?string $internalReference): self
    {
        $this->internalReference = $internalReference;
        return $this;
    }

    // ============================================
    // QUANTITÉ ET UNITÉ
    // ============================================

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        $this->recalculateTotals();
        return $this;
    }

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
        return $this;
    }

    // ============================================
    // TARIFICATION
    // ============================================

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(?string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        $this->recalculateTotals();
        return $this;
    }

    public function getUnitPriceFloat(): ?float
    {
        return $this->unitPrice !== null ? (float) $this->unitPrice : null;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(?string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    public function getTotalPriceFloat(): ?float
    {
        return $this->totalPrice !== null ? (float) $this->totalPrice : null;
    }

    public function getDiscountPercentage(): ?string
    {
        return $this->discountPercentage;
    }

    public function setDiscountPercentage(?string $discountPercentage): self
    {
        $this->discountPercentage = $discountPercentage;
        $this->recalculateTotals();
        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): self
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    public function getTaxRate(): ?string
    {
        return $this->taxRate;
    }

    public function setTaxRate(?string $taxRate): self
    {
        $this->taxRate = $taxRate;
        $this->recalculateTotals();
        return $this;
    }

    public function getTaxAmount(): ?string
    {
        return $this->taxAmount;
    }

    public function setTaxAmount(?string $taxAmount): self
    {
        $this->taxAmount = $taxAmount;
        return $this;
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
        $this->recalculateTotalDuration();
        return $this;
    }

    public function getTotalEstimatedDuration(): ?int
    {
        return $this->totalEstimatedDuration;
    }

    public function setTotalEstimatedDuration(?int $totalEstimatedDuration): self
    {
        $this->totalEstimatedDuration = $totalEstimatedDuration;
        return $this;
    }

    /**
     * Retourne la durée unitaire formatée (ex: "1h30")
     */
    public function getFormattedDuration(): ?string
    {
        if ($this->estimatedDuration === null) {
            return null;
        }

        return $this->formatMinutes($this->estimatedDuration);
    }

    /**
     * Retourne la durée totale formatée
     */
    public function getFormattedTotalDuration(): ?string
    {
        if ($this->totalEstimatedDuration === null) {
            return null;
        }

        return $this->formatMinutes($this->totalEstimatedDuration);
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
    // OPTIONS
    // ============================================

    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    public function setOptions(?array $options): self
    {
        $this->options = $options;
        return $this;
    }

    public function addOption(string $key, mixed $value): self
    {
        $options = $this->getOptions();
        $options[$key] = $value;
        $this->options = $options;
        return $this;
    }

    public function removeOption(string $key): self
    {
        $options = $this->getOptions();
        unset($options[$key]);
        $this->options = $options;
        return $this;
    }

    public function hasOption(string $key): bool
    {
        return isset($this->getOptions()[$key]);
    }

    public function getOption(string $key): mixed
    {
        return $this->getOptions()[$key] ?? null;
    }

    public function getSpecifications(): array
    {
        return $this->specifications ?? [];
    }

    public function setSpecifications(?array $specifications): self
    {
        $this->specifications = $specifications;
        return $this;
    }

    // ============================================
    // PROPRIÉTÉS BOOLÉENNES
    // ============================================

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $isOptional): self
    {
        $this->isOptional = $isOptional;
        return $this;
    }

    public function isIncluded(): bool
    {
        return $this->isIncluded;
    }

    public function setIsIncluded(bool $isIncluded): self
    {
        $this->isIncluded = $isIncluded;
        return $this;
    }

    public function requiresClientApproval(): bool
    {
        return $this->requiresClientApproval;
    }

    public function setRequiresClientApproval(bool $requiresClientApproval): self
    {
        $this->requiresClientApproval = $requiresClientApproval;
        return $this;
    }

    // ============================================
    // AFFICHAGE
    // ============================================

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    // ============================================
    // MÉTADONNÉES
    // ============================================

    public function getMetadata(): array
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
    // CALCULS AUTOMATIQUES
    // ============================================

    /**
     * Recalcule tous les totaux (prix et durée)
     */
    public function recalculateTotals(): void
    {
        $this->calculateTotalPrice();
        $this->calculateDiscount();
        $this->calculateTax();
        $this->recalculateTotalDuration();
    }

    /**
     * Calcule le prix total (quantité × prix unitaire)
     */
    public function calculateTotalPrice(): void
    {
        if ($this->unitPrice === null) {
            return;
        }

        $unitPrice = (float) $this->unitPrice;
        $subtotal = $unitPrice * $this->quantity;

        $this->totalPrice = (string) round($subtotal, 2);
    }

    /**
     * Calcule la remise
     */
    public function calculateDiscount(): void
    {
        if ($this->totalPrice === null) {
            return;
        }

        $total = (float) $this->totalPrice;

        if ($this->discountPercentage !== null) {
            $percentage = (float) $this->discountPercentage;
            $this->discountAmount = (string) round(($total * $percentage) / 100, 2);
        }

        if ($this->discountAmount !== null) {
            $discount = (float) $this->discountAmount;
            $total -= $discount;
            $this->totalPrice = (string) round($total, 2);
        }
    }

    /**
     * Calcule la TVA
     */
    public function calculateTax(): void
    {
        if ($this->totalPrice === null || $this->taxRate === null) {
            return;
        }

        $total = (float) $this->totalPrice;
        $rate = (float) $this->taxRate;

        $this->taxAmount = (string) round(($total * $rate) / 100, 2);
    }

    /**
     * Calcule la durée totale (quantité × durée unitaire)
     */
    public function recalculateTotalDuration(): void
    {
        if ($this->estimatedDuration === null) {
            $this->totalEstimatedDuration = null;
            return;
        }

        $this->totalEstimatedDuration = $this->estimatedDuration * $this->quantity;
    }

    /**
     * Obtient le prix total incluant la TVA
     */
    public function getTotalPriceWithTax(): float
    {
        $total = $this->getTotalPriceFloat() ?? 0;
        $tax = $this->taxAmount !== null ? (float) $this->taxAmount : 0;

        return round($total + $tax, 2);
    }

    /**
     * Obtient le prix unitaire après remise
     */
    public function getDiscountedUnitPrice(): ?float
    {
        if ($this->unitPrice === null) {
            return null;
        }

        $unitPrice = (float) $this->unitPrice;

        if ($this->discountPercentage !== null) {
            $percentage = (float) $this->discountPercentage;
            $unitPrice -= ($unitPrice * $percentage) / 100;
        }

        return round($unitPrice, 2);
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Retourne une description détaillée de l'item
     */
    public function getDetailedDescription(): string
    {
        $parts = [$this->description];

        if ($this->quantity > 1) {
            $unitText = $this->unit ? " {$this->unit}" : '';
            $parts[] = "Quantité: {$this->quantity}{$unitText}";
        }

        if ($this->estimatedDuration !== null) {
            $parts[] = "Durée: " . $this->getFormattedDuration();
        }

        if ($this->isOptional) {
            $parts[] = "(Optionnel)";
        }

        if (!empty($this->getOptions())) {
            $optionsText = implode(', ', array_keys($this->getOptions()));
            $parts[] = "Options: {$optionsText}";
        }

        return implode(' | ', $parts);
    }

    /**
     * Retourne le prix unitaire formaté
     */
    public function getFormattedUnitPrice(): string
    {
        if ($this->unitPrice === null) {
            return 'N/A';
        }

        $price = number_format((float) $this->unitPrice, 2, ',', ' ');
        $unit = $this->unit ? "/{$this->unit}" : '';
        
        return "{$price} €{$unit}";
    }

    /**
     * Retourne le prix total formaté
     */
    public function getFormattedTotalPrice(): string
    {
        if ($this->totalPrice === null) {
            return 'N/A';
        }

        $price = number_format((float) $this->totalPrice, 2, ',', ' ');
        return "{$price} €";
    }

    /**
     * Retourne le prix total avec TVA formaté
     */
    public function getFormattedTotalPriceWithTax(): string
    {
        $price = number_format($this->getTotalPriceWithTax(), 2, ',', ' ');
        return "{$price} €";
    }

    /**
     * Clone l'item pour un autre devis
     */
    public function cloneForQuote(Quote $newQuote): self
    {
        $clone = new self();
        $clone->setQuote($newQuote);
        $clone->setSubcategory($this->subcategory);
        $clone->setDescription($this->description);
        $clone->setQuantity($this->quantity);
        $clone->setUnit($this->unit);
        $clone->setUnitPrice($this->unitPrice);
        $clone->setEstimatedDuration($this->estimatedDuration);
        $clone->setNotes($this->notes);
        $clone->setOptions($this->options);
        $clone->setSpecifications($this->specifications);
        $clone->setIsOptional($this->isOptional);
        $clone->setIsIncluded($this->isIncluded);
        $clone->setRequiresClientApproval($this->requiresClientApproval);
        $clone->setDisplayOrder($this->displayOrder);
        $clone->setCategory($this->category);
        $clone->setInternalReference($this->internalReference);
        $clone->setDiscountPercentage($this->discountPercentage);
        $clone->setTaxRate($this->taxRate);
        
        $clone->recalculateTotals();

        return $clone;
    }

    /**
     * Vérifie si l'item a une remise
     */
    public function hasDiscount(): bool
    {
        return $this->discountPercentage !== null || $this->discountAmount !== null;
    }

    /**
     * Vérifie si l'item a de la TVA
     */
    public function hasTax(): bool
    {
        return $this->taxRate !== null && (float) $this->taxRate > 0;
    }

    // ============================================
    // LIFECYCLE CALLBACKS
    // ============================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->recalculateTotals();
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
        return sprintf(
            '%s (x%d) - %.2f€',
            $this->description ?? 'Item',
            $this->quantity,
            $this->getTotalPriceFloat() ?? 0
        );
    }

    /**
     * Retourne une représentation JSON-friendly de l'item
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'quoteId' => $this->quote?->getId(),
            'subcategoryId' => $this->subcategory?->getId(),
            'description' => $this->description,
            'notes' => $this->notes,
            'quantity' => $this->quantity,
            'unit' => $this->unit,
            'unitPrice' => $this->getUnitPriceFloat(),
            'totalPrice' => $this->getTotalPriceFloat(),
            'formattedUnitPrice' => $this->getFormattedUnitPrice(),
            'formattedTotalPrice' => $this->getFormattedTotalPrice(),
            'discountPercentage' => $this->discountPercentage !== null ? (float) $this->discountPercentage : null,
            'discountAmount' => $this->discountAmount !== null ? (float) $this->discountAmount : null,
            'taxRate' => $this->taxRate !== null ? (float) $this->taxRate : null,
            'taxAmount' => $this->taxAmount !== null ? (float) $this->taxAmount : null,
            'totalWithTax' => $this->getTotalPriceWithTax(),
            'estimatedDuration' => $this->estimatedDuration,
            'totalEstimatedDuration' => $this->totalEstimatedDuration,
            'formattedDuration' => $this->getFormattedDuration(),
            'formattedTotalDuration' => $this->getFormattedTotalDuration(),
            'isOptional' => $this->isOptional,
            'isIncluded' => $this->isIncluded,
            'requiresClientApproval' => $this->requiresClientApproval,
            'displayOrder' => $this->displayOrder,
            'category' => $this->category,
            'options' => $this->getOptions(),
        ];
    }
}