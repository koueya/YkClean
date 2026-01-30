<?php

namespace App\Entity\Service;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use App\Entity\ServiceRequest\ServiceRequest;

/**
 * Représente une catégorie de service avec support des sous-catégories
 * 
 * Exemples de hiérarchie :
 * - Ménage (parent)
 *   ├── Ménage courant (enfant)
 *   ├── Grand ménage (enfant)
 *   └── Ménage après travaux (enfant)
 * 
 * - Repassage (parent)
 *   ├── Repassage standard (enfant)
 *   └── Repassage délicat (enfant)
 */
#[ORM\Entity(repositoryClass: 'App\Repository\Service\ServiceCategoryRepository')]
#[ORM\Table(name: 'service_categories')]
#[ORM\Index(columns: ['slug'], name: 'idx_slug')]
#[ORM\Index(columns: ['parent_id'], name: 'idx_parent')]
#[ORM\Index(columns: ['is_active'], name: 'idx_active')]
#[UniqueEntity(fields: ['slug'], message: 'Ce slug existe déjà')]
#[ORM\HasLifecycleCallbacks]
class ServiceCategory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['category:read', 'category:list'])]
    private ?int $id = null;

    /**
     * Nom de la catégorie
     */
    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['category:read', 'category:list'])]
    private ?string $name = null;

    /**
     * Slug pour l'URL (généré automatiquement depuis le nom)
     */
    #[ORM\Column(type: 'string', length: 100, unique: true)]
    #[Groups(['category:read', 'category:list'])]
    private ?string $slug = null;

    /**
     * Description de la catégorie
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['category:read'])]
    private ?string $description = null;

    /**
     * Icône de la catégorie (nom du fichier ou classe CSS)
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['category:read', 'category:list'])]
    private ?string $icon = null;

    /**
     * Image de la catégorie
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['category:read', 'category:list'])]
    private ?string $image = null;

    /**
     * Couleur associée à la catégorie (hex)
     */
    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    #[Assert\Regex(
        pattern: '/^#[0-9A-Fa-f]{6}$/',
        message: 'La couleur doit être au format hexadécimal (#RRGGBB)'
    )]
    #[Groups(['category:read', 'category:list'])]
    private ?string $color = null;

    /**
     * Catégorie parente (null si catégorie de niveau 1)
     */
    #[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    #[Groups(['category:read'])]
    private ?self $parent = null;

    /**
     * Sous-catégories (enfants)
     */
    #[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class, cascade: ['persist', 'remove'])]
    #[ORM\OrderBy(['position' => 'ASC', 'name' => 'ASC'])]
    #[Groups(['category:read', 'category:tree'])]
    private Collection $children;

    /**
     * Niveau de profondeur dans l'arborescence (0 = racine)
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['category:read'])]
    private int $level = 0;

    /**
     * Position/ordre d'affichage
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['category:read', 'category:list'])]
    private int $position = 0;

    /**
     * Indique si la catégorie est active
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['category:read', 'category:list'])]
    private bool $isActive = true;

    /**
     * Indique si la catégorie est visible dans le menu
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['category:read'])]
    private bool $isVisibleInMenu = true;

    /**
     * Indique si c'est une catégorie populaire (mise en avant)
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['category:read', 'category:list'])]
    private bool $isPopular = false;

    /**
     * Tarif horaire minimum suggéré
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le tarif doit être positif')]
    #[Groups(['category:read'])]
    private ?string $minHourlyRate = null;

    /**
     * Tarif horaire maximum suggéré
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le tarif doit être positif')]
    #[Groups(['category:read'])]
    private ?string $maxHourlyRate = null;

    /**
     * Durée minimale en minutes pour cette catégorie
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Groups(['category:read'])]
    private ?int $minDuration = null;

    /**
     * Durée standard en minutes pour cette catégorie
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Groups(['category:read'])]
    private ?int $defaultDuration = null;

    /**
     * Métadonnées SEO - Title
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $metaTitle = null;

    /**
     * Métadonnées SEO - Description
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $metaDescription = null;

    /**
     * Métadonnées SEO - Keywords
     */
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metaKeywords = [];

    /**
     * Demandes de service associées
     */
    #[ORM\OneToMany(mappedBy: 'category', targetEntity: ServiceRequest::class)]
    private Collection $serviceRequests;

    /**
     * Nombre de demandes de service
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['category:read', 'category:list'])]
    private int $requestCount = 0;

    /**
     * Date de création
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['category:read'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Date de dernière modification
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['category:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->children = new ArrayCollection();
        $this->serviceRequests = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function updateLevel(): void
    {
        if ($this->parent) {
            $this->level = $this->parent->getLevel() + 1;
        } else {
            $this->level = 0;
        }
    }

    #[ORM\PrePersist]
    public function generateSlug(): void
    {
        if (!$this->slug && $this->name) {
            $this->slug = $this->slugify($this->name);
        }
    }

    // Getters et Setters

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
        
        // Régénérer le slug si le nom change
        if ($name) {
            $this->slug = $this->slugify($name);
        }
        
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

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;
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

    public function isVisibleInMenu(): bool
    {
        return $this->isVisibleInMenu;
    }

    public function setIsVisibleInMenu(bool $isVisibleInMenu): self
    {
        $this->isVisibleInMenu = $isVisibleInMenu;
        return $this;
    }

    public function isPopular(): bool
    {
        return $this->isPopular;
    }

    public function setIsPopular(bool $isPopular): self
    {
        $this->isPopular = $isPopular;
        return $this;
    }

    public function getMinHourlyRate(): ?string
    {
        return $this->minHourlyRate;
    }

    public function setMinHourlyRate(?string $minHourlyRate): self
    {
        $this->minHourlyRate = $minHourlyRate;
        return $this;
    }

    public function getMaxHourlyRate(): ?string
    {
        return $this->maxHourlyRate;
    }

    public function setMaxHourlyRate(?string $maxHourlyRate): self
    {
        $this->maxHourlyRate = $maxHourlyRate;
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

    public function getDefaultDuration(): ?int
    {
        return $this->defaultDuration;
    }

    public function setDefaultDuration(?int $defaultDuration): self
    {
        $this->defaultDuration = $defaultDuration;
        return $this;
    }

    public function getMetaTitle(): ?string
    {
        return $this->metaTitle;
    }

    public function setMetaTitle(?string $metaTitle): self
    {
        $this->metaTitle = $metaTitle;
        return $this;
    }

    public function getMetaDescription(): ?string
    {
        return $this->metaDescription;
    }

    public function setMetaDescription(?string $metaDescription): self
    {
        $this->metaDescription = $metaDescription;
        return $this;
    }

    public function getMetaKeywords(): ?array
    {
        return $this->metaKeywords ?? [];
    }

    public function setMetaKeywords(?array $metaKeywords): self
    {
        $this->metaKeywords = $metaKeywords;
        return $this;
    }

    /**
     * @return Collection<int, ServiceRequest>
     */
    public function getServiceRequests(): Collection
    {
        return $this->serviceRequests;
    }

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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Méthodes utilitaires

    /**
     * Vérifie si c'est une catégorie racine (niveau 0)
     */
    #[Groups(['category:read'])]
    public function isRoot(): bool
    {
        return $this->parent === null && $this->level === 0;
    }

    /**
     * Vérifie si c'est une feuille (pas d'enfants)
     */
    #[Groups(['category:read'])]
    public function isLeaf(): bool
    {
        return $this->children->isEmpty();
    }

    /**
     * Vérifie si la catégorie a des enfants
     */
    #[Groups(['category:read'])]
    public function hasChildren(): bool
    {
        return !$this->children->isEmpty();
    }

    /**
     * Obtient tous les enfants actifs
     */
    public function getActiveChildren(): Collection
    {
        return $this->children->filter(fn(self $child) => $child->isActive());
    }

    /**
     * Obtient le chemin complet (breadcrumb)
     * Ex: "Ménage > Ménage courant"
     */
    #[Groups(['category:read'])]
    public function getPath(string $separator = ' > '): string
    {
        $path = [$this->name];
        $current = $this->parent;

        while ($current !== null) {
            array_unshift($path, $current->getName());
            $current = $current->getParent();
        }

        return implode($separator, $path);
    }

    /**
     * Obtient tous les ancêtres (du plus proche au plus éloigné)
     */
    public function getAncestors(): array
    {
        $ancestors = [];
        $current = $this->parent;

        while ($current !== null) {
            $ancestors[] = $current;
            $current = $current->getParent();
        }

        return $ancestors;
    }

    /**
     * Obtient tous les descendants (récursif)
     */
    public function getDescendants(): array
    {
        $descendants = [];

        foreach ($this->children as $child) {
            $descendants[] = $child;
            $descendants = array_merge($descendants, $child->getDescendants());
        }

        return $descendants;
    }

    /**
     * Obtient la catégorie racine
     */
    public function getRoot(): self
    {
        $current = $this;

        while ($current->getParent() !== null) {
            $current = $current->getParent();
        }

        return $current;
    }

    /**
     * Génère un slug à partir d'un texte
     */
    private function slugify(string $text): string
    {
        // Remplacer les caractères non-ASCII
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        
        // Convertir en minuscules
        $text = strtolower($text);
        
        // Remplacer les caractères spéciaux par des tirets
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        
        // Supprimer les tirets en début et fin
        $text = trim($text, '-');
        
        return $text;
    }

    /**
     * Retourne un tableau pour l'arborescence
     */
    public function toTree(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'icon' => $this->icon,
            'color' => $this->color,
            'level' => $this->level,
            'isActive' => $this->isActive,
            'isPopular' => $this->isPopular,
            'requestCount' => $this->requestCount,
            'children' => array_map(fn(self $child) => $child->toTree(), $this->children->toArray()),
        ];
    }

    /**
     * Retourne un tableau simple
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'icon' => $this->icon,
            'color' => $this->color,
            'level' => $this->level,
            'position' => $this->position,
            'isActive' => $this->isActive,
            'isPopular' => $this->isPopular,
            'isRoot' => $this->isRoot(),
            'isLeaf' => $this->isLeaf(),
            'hasChildren' => $this->hasChildren(),
            'childrenCount' => $this->children->count(),
            'requestCount' => $this->requestCount,
            'path' => $this->getPath(),
            'parentId' => $this->parent?->getId(),
        ];
    }

    /**
     * Représentation textuelle
     */
    public function __toString(): string
    {
        return $this->getPath();
    }
}