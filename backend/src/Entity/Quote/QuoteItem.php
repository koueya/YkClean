<?php
// src/Entity/Quote/QuoteItem.php

namespace App\Entity\Quote;

use App\Repository\QuoteItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Quote\Quote;
use App\Entity\ServiceType;
#[ORM\Entity(repositoryClass: QuoteItemRepository::class)]
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
    private ?int $quantity = 1;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $unit = null; // heure, m², pièce, etc.

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $unitPrice = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?string $totalPrice = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedDuration = null; // en minutes

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $options = []; // Options supplémentaires sélectionnées

    #[ORM\Column(type: 'boolean')]
    private bool $isOptional = false;

    #[ORM\Column(type: 'integer')]
    private int $displayOrder = 0;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and Setters

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

    public function getServiceType(): ?ServiceType
    {
        return $this->serviceType;
    }

    public function setServiceType(?ServiceType $serviceType): self
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        $this->calculateTotalPrice();
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

    public function getUnitPrice(): ?string
    {
        return $this->unitPrice;
    }

    public function setUnitPrice(string $unitPrice): self
    {
        $this->unitPrice = $unitPrice;
        $this->calculateTotalPrice();
        return $this;
    }

    public function getTotalPrice(): ?string
    {
        return $this->totalPrice;
    }

    public function setTotalPrice(string $totalPrice): self
    {
        $this->totalPrice = $totalPrice;
        return $this;
    }

    /**
     * Calcule automatiquement le prix total
     */
    public function calculateTotalPrice(): void
    {
        if ($this->unitPrice && $this->quantity) {
            $this->totalPrice = bcmul($this->unitPrice, (string)$this->quantity, 2);
        }
    }

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): self
    {
        $this->estimatedDuration = $estimatedDuration;
        return $this;
    }

    public function getEstimatedDurationFormatted(): ?string
    {
        if (!$this->estimatedDuration) {
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

    public function getTotalEstimatedDuration(): ?int
    {
        if (!$this->estimatedDuration) {
            return null;
        }

        return $this->estimatedDuration * $this->quantity;
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

    public function getOptions(): ?array
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
        $options = $this->getOptions();
        return isset($options[$key]);
    }

    public function getOption(string $key): mixed
    {
        $options = $this->getOptions();
        return $options[$key] ?? null;
    }

    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    public function setIsOptional(bool $isOptional): self
    {
        $this->isOptional = $isOptional;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Retourne une description détaillée de l'item
     */
    public function getDetailedDescription(): string
    {
        $details = [$this->description];

        if ($this->quantity > 1) {
            $unitText = $this->unit ? " {$this->unit}" : '';
            $details[] = "Quantité: {$this->quantity}{$unitText}";
        }

        if ($this->estimatedDuration) {
            $details[] = "Durée estimée: " . $this->getEstimatedDurationFormatted();
        }

        if (!empty($this->getOptions())) {
            $optionsText = implode(', ', array_keys($this->getOptions()));
            $details[] = "Options: {$optionsText}";
        }

        return implode(' | ', $details);
    }

    /**
     * Retourne le prix unitaire formatté
     */
    public function getFormattedUnitPrice(): string
    {
        $price = number_format((float)$this->unitPrice, 2, ',', ' ');
        $unit = $this->unit ? "/{$this->unit}" : '';
        return "{$price} €{$unit}";
    }

    /**
     * Retourne le prix total formatté
     */
    public function getFormattedTotalPrice(): string
    {
        $price = number_format((float)$this->totalPrice, 2, ',', ' ');
        return "{$price} €";
    }

    /**
     * Clone l'item pour un autre devis
     */
    public function cloneForQuote(Quote $newQuote): self
    {
        $clone = new self();
        $clone->setQuote($newQuote);
        $clone->setServiceType($this->serviceType);
        $clone->setDescription($this->description);
        $clone->setQuantity($this->quantity);
        $clone->setUnit($this->unit);
        $clone->setUnitPrice($this->unitPrice);
        $clone->setTotalPrice($this->totalPrice);
        $clone->setEstimatedDuration($this->estimatedDuration);
        $clone->setNotes($this->notes);
        $clone->setOptions($this->options);
        $clone->setIsOptional($this->isOptional);
        $clone->setDisplayOrder($this->displayOrder);

        return $clone;
    }
}