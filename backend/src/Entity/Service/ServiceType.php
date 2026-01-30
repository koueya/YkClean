<?php
// src/Entity/Service/ServiceType.php

namespace App\Service\Entity;

use App\Repository\ServiceTypeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\ServiceCategory;
use Doctrine\ORM\Mapping\PreUpdate;
#[ORM\Entity(repositoryClass: ServiceTypeRepository::class)]
#[ORM\Table(name: 'service_types')]
class ServiceType
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceCategory $category = null;

    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    private ?string $name = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $icon = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?string $basePrice = null; // Prix de base suggéré

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive]
    private ?int $estimatedDuration = null; // Durée estimée en minutes

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $unit = null; // heure, m², pièce, etc.

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'boolean')]
    private bool $requiresEquipment = false;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $requiredEquipment = []; // Liste du matériel nécessaire

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $additionalOptions = []; // Options supplémentaires possibles

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $specificInstructions = null; // Instructions spécifiques

    #[ORM\Column(type: 'integer')]
    private int $displayOrder = 0;

    #[ORM\Column(type: 'integer')]
    private int $popularityScore = 0; // Score de popularité

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters and Setters

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

    public function setName(string $name): self
    {
        $this->name = $name;
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

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getBasePrice(): ?string
    {
        return $this->basePrice;
    }

    public function setBasePrice(?string $basePrice): self
    {
        $this->basePrice = $basePrice;
        return $this;
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

    public function getUnit(): ?string
    {
        return $this->unit;
    }

    public function setUnit(?string $unit): self
    {
        $this->unit = $unit;
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

    public function requiresEquipment(): bool
    {
        return $this->requiresEquipment;
    }

    public function setRequiresEquipment(bool $requiresEquipment): self
    {
        $this->requiresEquipment = $requiresEquipment;
        return $this;
    }

    public function getRequiredEquipment(): ?array
    {
        return $this->requiredEquipment ?? [];
    }

    public function setRequiredEquipment(?array $requiredEquipment): self
    {
        $this->requiredEquipment = $requiredEquipment;
        return $this;
    }

    public function addRequiredEquipment(string $equipment): self
    {
        $equipments = $this->getRequiredEquipment();
        if (!in_array($equipment, $equipments, true)) {
            $equipments[] = $equipment;
            $this->requiredEquipment = $equipments;
        }
        return $this;
    }

    public function removeRequiredEquipment(string $equipment): self
    {
        $equipments = $this->getRequiredEquipment();
        $key = array_search($equipment, $equipments, true);
        if ($key !== false) {
            unset($equipments[$key]);
            $this->requiredEquipment = array_values($equipments);
        }
        return $this;
    }

    public function getAdditionalOptions(): ?array
    {
        return $this->additionalOptions ?? [];
    }

    public function setAdditionalOptions(?array $additionalOptions): self
    {
        $this->additionalOptions = $additionalOptions;
        return $this;
    }

    public function addAdditionalOption(array $option): self
    {
        $options = $this->getAdditionalOptions();
        $options[] = $option;
        $this->additionalOptions = $options;
        return $this;
    }

    /**
     * Exemple de structure pour une option:
     * [
     *     'name' => 'Repassage délicat',
     *     'description' => 'Pour tissus délicats',
     *     'price' => '5.00',
     *     'unit' => 'par pièce'
     * ]
     */
    public function getAdditionalOption(string $name): ?array
    {
        $options = $this->getAdditionalOptions();
        foreach ($options as $option) {
            if (isset($option['name']) && $option['name'] === $name) {
                return $option;
            }
        }
        return null;
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

    public function getDisplayOrder(): int
    {
        return $this->displayOrder;
    }

    public function setDisplayOrder(int $displayOrder): self
    {
        $this->displayOrder = $displayOrder;
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

    public function getCreatedAt(): ?\DateTimeImmutable
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Retourne le nom complet avec la catégorie
     */
    public function getFullName(): string
    {
        if ($this->category) {
            return $this->category->getName() . ' - ' . $this->name;
        }
        return $this->name ?? '';
    }

    /**
     * Vérifie si le type de service nécessite un équipement spécifique
     */
    public function hasSpecificEquipment(string $equipment): bool
    {
        return in_array($equipment, $this->getRequiredEquipment(), true);
    }

    /**
     * Calcule le prix estimé avec les options
     */
    public function calculatePriceWithOptions(array $selectedOptions = []): string
    {
        $totalPrice = $this->basePrice ?? '0.00';

        foreach ($selectedOptions as $optionName) {
            $option = $this->getAdditionalOption($optionName);
            if ($option && isset($option['price'])) {
                $totalPrice = bcadd($totalPrice, $option['price'], 2);
            }
        }

        return $totalPrice;
    }
}