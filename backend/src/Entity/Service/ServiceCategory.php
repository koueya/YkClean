<?php
// src/Entity/Service/ServiceCategory.php

namespace App\Entity\Service;

use App\Entity\User\Prestataire;
use App\Module\Financial\Entity\CommissionRule;
use App\Repository\Service\ServiceCategoryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une catégorie de service avec support hiérarchique
 * 
 * Exemples de hiérarchie :
 * - Nettoyage (niveau 0 - racine)
 *   ├── Entretien courant (niveau 1)
 *   │   ├── Nettoyage léger (subcategory)
 *   │   └── Nettoyage standard (subcategory)
 *   ├── Grand ménage (niveau 1)
 *   │   ├── Grand ménage classique (subcategory)
 *   │   └── Grand ménage avec vitres (subcategory)
 *   └── Nettoyage spécialisé (niveau 1)
 *       ├── Nettoyage après travaux (subcategory)
 *       └── Nettoyage de fin de bail (subcategory)
 */
#[ORM\Entity(repositoryClass: ServiceCategoryRepository::class)]
#[ORM\Table(name: 'service_categories')]
#[ORM\Index(columns: ['slug'], name: 'idx_category_slug')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_category_parent')]
#[ORM\Index(columns: ['is_active'], name: 'idx_category_active')]
#[ORM\Index(columns: ['level'], name: 'idx_category_level')]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug existe déjà')]
#[ORM\HasLifecycleCallbacks]
class ServiceCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['category:read', 'category:list', 'category:detail'])]
    private ?int $id = null;

    // ============================================
    // INFORMATIONS DE BASE
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['category:read', 'category:list', 'category:detail'])]
    private ?string $name = null;

    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Groups(['category:read', 'category:list', 'category:detail'])]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['category:read', 'category:detail'])]
    private ?string $description = null;

    // ============================================
    // AFFICHAGE ET VISIBILITÉ
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['category:read', 'category:list'])]
    private ?string $icon = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['category:read', 'category:list'])]
    private ?string $image = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Regex(pattern: '/^#[0-9A-Fa-f]{6}$/', message: 'Le format de couleur doit être hexadécimal (#RRGGBB)')]
    #[Groups(['category:read', 'category:list'])]
    private ?string $color = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['category:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['category:read'])]
    private int $position = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isFeatured = false;

    // ============================================
    // HIÉRARCHIE (Auto-référence)
    // ============================================

    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(name: 'parent_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
    #[Groups(['category:detail'])]
    private ?self $parent = null;

    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    #[Groups(['category:detail'])]
    private Collection $children;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['category:read'])]
    private int $level = 0;

    // ============================================
    // RELATIONS AVEC LES AUTRES ENTITÉS
    // ============================================

    /**
     * ✅ NOUVEAU - Sous-catégories détaillées avec tarification
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ServiceSubcategory::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['displayOrder' => 'ASC', 'name' => 'ASC'])]
    private Collection $subcategories;

    /**
     * ⚠️ DEPRECATED - Remplacé par ServiceSubcategory
     * Conservé pour compatibilité avec l'ancien système
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ServiceType::class)]
    private Collection $serviceTypes;

    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ServiceRequest::class)]
    private Collection $serviceRequests;

    /**
     * ⚠️ DEPRECATED - Les prestataires sont maintenant liés via ServiceSubcategory
     * Relation conservée temporairement pour la migration
     */
    #[ORM\ManyToMany(targetEntity: Prestataire::class, mappedBy: 'serviceCategories')]
    private Collection $prestataires;

    /**
     * Règles de commission spécifiques à cette catégorie
     */
    #[ORM\OneToMany(mappedBy: 'serviceCategory', targetEntity: CommissionRule::class)]
    private Collection $commissionRules;

    // ============================================
    // STATISTIQUES ET MÉTRIQUES
    // ============================================

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $requestCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $prestataireCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $viewCount = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 5)]
    private ?string $averageRating = null;

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
    #[Groups(['category:detail'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['category:detail'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // ============================================
    // CONSTRUCTEUR
    // ============================================

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

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
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
    // HIÉRARCHIE - PARENT/CHILDREN
    // ============================================

    public function getParent(): ?self
    {
        return $this->parent;
    }

    public function setParent(?self $parent): self
    {
        $this->parent = $parent;
        $this->updateLevel();
        return $this;
    }

    /**
     * @return Collection<int, self>
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    public function addChild(self $child): self
    {
        if (!$this->children->contains($child)) {
            $this->children->add($child);
            $child->setParent($this);
        }

        return $this;
    }

    public function removeChild(self $child): self
    {
        if ($this->children->removeElement($child)) {
            if ($child->getParent() === $this) {
                $child->setParent(null);
            }
        }

        return $this;
    }

    public function getLevel(): int
    {
        return $this->level;
    }

    public function setLevel(int $level): self
    {
        $this->level = $level;
        return $this;
    }

    /**
     * Met à jour automatiquement le niveau basé sur la hiérarchie
     */
    private function updateLevel(): void
    {
        $this->level = $this->calculateLevel();
        
        // Mettre à jour récursivement les niveaux des enfants
        foreach ($this->children as $child) {
            $child->updateLevel();
        }
    }

    /**
     * Calcule le niveau dans la hiérarchie
     */
    private function calculateLevel(): int
    {
        $level = 0;
        $current = $this;
        
        while ($current->getParent() !== null) {
            $level++;
            $current = $current->getParent();
        }
        
        return $level;
    }

    // ============================================
    // RELATIONS - SUBCATEGORIES
    // ============================================

    /**
     * @return Collection<int, ServiceSubcategory>
     */
    public function getSubcategories(): Collection
    {
        return $this->subcategories;
    }

    public function addSubcategory(ServiceSubcategory $subcategory): self
    {
        if (!$this->subcategories->contains($subcategory)) {
            $this->subcategories->add($subcategory);
            $subcategory->setCategory($this);
        }

        return $this;
    }

    public function removeSubcategory(ServiceSubcategory $subcategory): self
    {
        if ($this->subcategories->removeElement($subcategory)) {
            if ($subcategory->getCategory() === $this) {
                $subcategory->setCategory(null);
            }
        }

        return $this;
    }

    /**
     * Obtient toutes les sous-catégories (y compris celles des enfants)
     */
    public function getAllSubcategories(): Collection
    {
        $allSubcategories = new ArrayCollection();
        
        // Sous-catégories directes
        foreach ($this->subcategories as $subcategory) {
            if (!$allSubcategories->contains($subcategory)) {
                $allSubcategories->add($subcategory);
            }
        }
        
        // Sous-catégories des enfants (récursif)
        foreach ($this->children as $child) {
            foreach ($child->getAllSubcategories() as $subcategory) {
                if (!$allSubcategories->contains($subcategory)) {
                    $allSubcategories->add($subcategory);
                }
            }
        }
        
        return $allSubcategories;
    }

    /**
     * Obtient les sous-catégories actives uniquement
     */
    public function getActiveSubcategories(): Collection
    {
        return $this->subcategories->filter(function (ServiceSubcategory $subcategory) {
            return $subcategory->isActive();
        });
    }

    // ============================================
    // RELATIONS - SERVICE TYPES (DEPRECATED)
    // ============================================

    /**
     * @return Collection<int, ServiceType>
     * @deprecated Utiliser getSubcategories() à la place
     */
    public function getServiceTypes(): Collection
    {
        return $this->serviceTypes;
    }

    /**
     * @deprecated
     */
    public function addServiceType(ServiceType $serviceType): self
    {
        if (!$this->serviceTypes->contains($serviceType)) {
            $this->serviceTypes->add($serviceType);
            $serviceType->setCategory($this);
        }

        return $this;
    }

    /**
     * @deprecated
     */
    public function removeServiceType(ServiceType $serviceType): self
    {
        if ($this->serviceTypes->removeElement($serviceType)) {
            if ($serviceType->getCategory() === $this) {
                $serviceType->setCategory(null);
            }
        }

        return $this;
    }

    // ============================================
    // RELATIONS - SERVICE REQUESTS
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
            $serviceRequest->setCategory($this);
        }

        return $this;
    }

    public function removeServiceRequest(ServiceRequest $serviceRequest): self
    {
        if ($this->serviceRequests->removeElement($serviceRequest)) {
            if ($serviceRequest->getCategory() === $this) {
                $serviceRequest->setCategory(null);
            }
        }

        return $this;
    }

    // ============================================
    // RELATIONS - PRESTATAIRES (DEPRECATED)
    // ============================================

    /**
     * @return Collection<int, Prestataire>
     * @deprecated Les prestataires sont maintenant liés via ServiceSubcategory->specializations
     */
    public function getPrestataires(): Collection
    {
        return $this->prestataires;
    }

    /**
     * @deprecated
     */
    public function addPrestataire(Prestataire $prestataire): self
    {
        if (!$this->prestataires->contains($prestataire)) {
            $this->prestataires->add($prestataire);
        }

        return $this;
    }

    /**
     * @deprecated
     */
    public function removePrestataire(Prestataire $prestataire): self
    {
        $this->prestataires->removeElement($prestataire);
        return $this;
    }

    // ============================================
    // RELATIONS - COMMISSION RULES
    // ============================================

    /**
     * @return Collection<int, CommissionRule>
     */
    public function getCommissionRules(): Collection
    {
        return $this->commissionRules;
    }

    public function addCommissionRule(CommissionRule $commissionRule): self
    {
        if (!$this->commissionRules->contains($commissionRule)) {
            $this->commissionRules->add($commissionRule);
            $commissionRule->setServiceCategory($this);
        }

        return $this;
    }

    public function removeCommissionRule(CommissionRule $commissionRule): self
    {
        if ($this->commissionRules->removeElement($commissionRule)) {
            if ($commissionRule->getServiceCategory() === $this) {
                $commissionRule->setServiceCategory(null);
            }
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

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): self
    {
        $this->averageRating = $averageRating;
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
     * Vérifie si c'est une catégorie racine (sans parent)
     */
    public function isRootCategory(): bool
    {
        return $this->parent === null;
    }

    /**
     * Vérifie si la catégorie a des enfants
     */
    public function hasChildren(): bool
    {
        return $this->children->count() > 0;
    }

    /**
     * Vérifie si la catégorie a des sous-catégories
     */
    public function hasSubcategories(): bool
    {
        return $this->subcategories->count() > 0;
    }

    /**
     * Obtient le chemin complet de la catégorie (breadcrumb)
     * Retourne un tableau de catégories du plus haut niveau au niveau actuel
     */
    public function getPath(): array
    {
        $path = [$this];
        $current = $this;

        while ($current->getParent() !== null) {
            $parent = $current->getParent();
            array_unshift($path, $parent);
            $current = $parent;
        }

        return $path;
    }

    /**
     * Obtient le chemin formaté en string (ex: "Nettoyage > Grand ménage > Après travaux")
     */
    public function getPathString(string $separator = ' > '): string
    {
        $path = $this->getPath();
        $names = array_map(fn(self $category) => $category->getName(), $path);
        return implode($separator, $names);
    }

    /**
     * Obtient la catégorie racine
     */
    public function getRootCategory(): self
    {
        $current = $this;
        
        while ($current->getParent() !== null) {
            $current = $current->getParent();
        }
        
        return $current;
    }

    /**
     * Obtient tous les descendants (enfants, petits-enfants, etc.)
     */
    public function getAllDescendants(): array
    {
        $descendants = [];
        
        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getAllDescendants());
        }
        
        return $descendants;
    }

    /**
     * Vérifie si une catégorie est un ancêtre de cette catégorie
     */
    public function isDescendantOf(self $category): bool
    {
        $current = $this->parent;
        
        while ($current !== null) {
            if ($current->getId() === $category->getId()) {
                return true;
            }
            $current = $current->getParent();
        }
        
        return false;
    }

    /**
     * Obtient la profondeur maximale de l'arborescence à partir de cette catégorie
     */
    public function getMaxDepth(): int
    {
        if (!$this->hasChildren()) {
            return 0;
        }

        $maxChildDepth = 0;
        foreach ($this->children as $child) {
            $childDepth = $child->getMaxDepth();
            if ($childDepth > $maxChildDepth) {
                $maxChildDepth = $childDepth;
            }
        }

        return $maxChildDepth + 1;
    }

    /**
     * Compte le nombre total de demandes de service (incluant les enfants)
     */
    public function getTotalRequestCount(): int
    {
        $total = $this->requestCount;
        
        foreach ($this->children as $child) {
            $total += $child->getTotalRequestCount();
        }
        
        return $total;
    }

    /**
     * Vérifie si la catégorie est visible (active et avec parent actif si existe)
     */
    public function isVisible(): bool
    {
        if (!$this->isActive) {
            return false;
        }

        $current = $this->parent;
        while ($current !== null) {
            if (!$current->isActive()) {
                return false;
            }
            $current = $current->getParent();
        }

        return true;
    }

    // ============================================
    // LIFECYCLE CALLBACKS
    // ============================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updateLevel();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->updateLevel();
    }

    // ============================================
    // MÉTHODES SPÉCIALES
    // ============================================

    public function __toString(): string
    {
        return $this->name ?? '';
    }

    /**
     * Retourne une représentation JSON-friendly de la catégorie
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'image' => $this->image,
            'color' => $this->color,
            'level' => $this->level,
            'position' => $this->position,
            'isActive' => $this->isActive,
            'isFeatured' => $this->isFeatured,
            'parentId' => $this->parent?->getId(),
            'childrenCount' => $this->children->count(),
            'subcategoriesCount' => $this->subcategories->count(),
            'requestCount' => $this->requestCount,
            'prestataireCount' => $this->prestataireCount,
            'viewCount' => $this->viewCount,
            'averageRating' => $this->averageRating,
        ];
    }
}