<?php
// src/Entity/Quote/Quote.php

namespace App\Entity\Quote;

use App\Entity\Booking\Booking;
use App\Entity\Service\ServiceRequest;
use App\Entity\User\Prestataire;
use App\Repository\Quote\QuoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Devis proposé par un prestataire pour une demande de service
 */
#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[ORM\Table(name: 'quotes')]
#[ORM\Index(columns: ['status'], name: 'idx_quote_status')]
#[ORM\Index(columns: ['service_request_id'], name: 'idx_quote_service_request')]
#[ORM\Index(columns: ['prestataire_id'], name: 'idx_quote_prestataire')]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    // Constantes pour les statuts
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACCEPTED = 'accepted';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_WITHDRAWN = 'withdrawn';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    // ============================================
    // RELATIONS PRINCIPALES
    // ============================================

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La demande de service est obligatoire')]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire')]
    private ?Prestataire $prestataire = null;

    #[ORM\OneToOne(mappedBy: 'quote', targetEntity: Booking::class, cascade: ['persist'])]
    private ?Booking $booking = null;

    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuoteItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['displayOrder' => 'ASC'])]
    private Collection $items;

    // ============================================
    // PROPOSITION FINANCIÈRE
    // ============================================

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    #[Assert\Range(
        min: 10,
        max: 10000,
        notInRangeMessage: 'Le montant doit être entre {{ min }}€ et {{ max }}€'
    )]
    private ?string $amount = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive]
    private ?string $depositAmount = null; // Acompte demandé

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $depositPercentage = null; // Pourcentage d'acompte

    // ============================================
    // PROPOSITION DE PLANNING
    // ============================================

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotBlank(message: 'La date proposée est obligatoire')]
    #[Assert\GreaterThan('today', message: 'La date proposée doit être dans le futur')]
    private ?\DateTimeImmutable $proposedDate = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $proposedTime = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'La durée proposée est obligatoire')]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Assert\Range(
        min: 30,
        max: 960,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
    )]
    private ?int $proposedDuration = null; // en minutes

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $alternativeDates = null; // Dates alternatives proposées

    // ============================================
    // DESCRIPTION ET CONDITIONS
    // ============================================

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(
        min: 20,
        max: 2000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les conditions ne peuvent pas dépasser {{ limit }} caractères')]
    private ?string $conditions = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $includedServices = null; // Services inclus

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $excludedServices = null; // Services non inclus

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specialNotes = null; // Notes spéciales

    // ============================================
    // STATUT ET CYCLE DE VIE
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_ACCEPTED,
            self::STATUS_REJECTED,
            self::STATUS_EXPIRED,
            self::STATUS_WITHDRAWN
        ],
        message: 'Le statut sélectionné n\'est pas valide'
    )]
    private string $status = self::STATUS_PENDING;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotBlank(message: 'La date de validité est obligatoire')]
    #[Assert\GreaterThan('now', message: 'La date de validité doit être dans le futur')]
    private ?\DateTimeImmutable $validUntil = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $viewedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $withdrawnAt = null;

    // ============================================
    // GESTION DU REJET
    // ============================================

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $rejectionCategory = null; // prix, date, autre

    // ============================================
    // GESTION DES MODIFICATIONS
    // ============================================

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $modificationRequested = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $modificationMessage = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $modificationRequestedAt = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $revisionNumber = 0;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $previousQuoteId = null; // Si c'est une révision

    // ============================================
    // MÉTADONNÉES ET TRAÇABILITÉ
    // ============================================

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isViewed = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $viewCount = 0;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $clientIp = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $clientUserAgent = null;

    // ============================================
    // CONSTRUCTEUR
    // ============================================

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        
        // Validité par défaut : 7 jours
        $this->validUntil = (new \DateTimeImmutable())->modify('+7 days');
        
        $this->includedServices = [];
        $this->excludedServices = [];
        $this->alternativeDates = [];
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceRequest(): ?ServiceRequest
    {
        return $this->serviceRequest;
    }

    public function setServiceRequest(?ServiceRequest $serviceRequest): self
    {
        $this->serviceRequest = $serviceRequest;
        return $this;
    }

    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(?Prestataire $prestataire): self
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        // Unset the owning side if necessary
        if ($booking === null && $this->booking !== null) {
            $this->booking->setQuote(null);
        }

        // Set the owning side if necessary
        if ($booking !== null && $booking->getQuote() !== $this) {
            $booking->setQuote($this);
        }

        $this->booking = $booking;
        return $this;
    }

    /**
     * @return Collection<int, QuoteItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(QuoteItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setQuote($this);
        }

        return $this;
    }

    public function removeItem(QuoteItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getQuote() === $this) {
                $item->setQuote(null);
            }
        }

        return $this;
    }

    // ============================================
    // MONTANTS
    // ============================================

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function getAmountFloat(): ?float
    {
        return $this->amount !== null ? (float) $this->amount : null;
    }

    public function getDepositAmount(): ?string
    {
        return $this->depositAmount;
    }

    public function setDepositAmount(?string $depositAmount): self
    {
        $this->depositAmount = $depositAmount;
        return $this;
    }

    public function getDepositPercentage(): ?string
    {
        return $this->depositPercentage;
    }

    public function setDepositPercentage(?string $depositPercentage): self
    {
        $this->depositPercentage = $depositPercentage;
        return $this;
    }

    /**
     * Calcule le montant de l'acompte selon le pourcentage
     */
    public function calculateDepositFromPercentage(): void
    {
        if ($this->amount !== null && $this->depositPercentage !== null) {
            $amount = (float) $this->amount;
            $percentage = (float) $this->depositPercentage;
            $this->depositAmount = (string) round(($amount * $percentage) / 100, 2);
        }
    }

    // ============================================
    // PLANNING
    // ============================================

    public function getProposedDate(): ?\DateTimeImmutable
    {
        return $this->proposedDate;
    }

    public function setProposedDate(?\DateTimeImmutable $proposedDate): self
    {
        $this->proposedDate = $proposedDate;
        return $this;
    }

    public function getProposedTime(): ?\DateTimeImmutable
    {
        return $this->proposedTime;
    }

    public function setProposedTime(?\DateTimeImmutable $proposedTime): self
    {
        $this->proposedTime = $proposedTime;
        return $this;
    }

    public function getProposedDuration(): ?int
    {
        return $this->proposedDuration;
    }

    public function setProposedDuration(?int $proposedDuration): self
    {
        $this->proposedDuration = $proposedDuration;
        return $this;
    }

    /**
     * Retourne la durée formatée (ex: "2h30")
     */
    public function getFormattedDuration(): ?string
    {
        if ($this->proposedDuration === null) {
            return null;
        }

        $hours = floor($this->proposedDuration / 60);
        $minutes = $this->proposedDuration % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh%02d', $hours, $minutes);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        }
        return sprintf('%d min', $minutes);
    }

    public function getAlternativeDates(): ?array
    {
        return $this->alternativeDates ?? [];
    }

    public function setAlternativeDates(?array $alternativeDates): self
    {
        $this->alternativeDates = $alternativeDates;
        return $this;
    }

    public function addAlternativeDate(\DateTimeImmutable $date): self
    {
        $dates = $this->getAlternativeDates();
        $dates[] = $date->format('Y-m-d H:i:s');
        $this->alternativeDates = array_unique($dates);
        return $this;
    }

    // ============================================
    // DESCRIPTION ET CONDITIONS
    // ============================================

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): self
    {
        $this->conditions = $conditions;
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

    public function getSpecialNotes(): ?string
    {
        return $this->specialNotes;
    }

    public function setSpecialNotes(?string $specialNotes): self
    {
        $this->specialNotes = $specialNotes;
        return $this;
    }

    // ============================================
    // STATUT
    // ============================================

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $oldStatus = $this->status;
        $this->status = $status;

        // Mettre à jour les dates selon le statut
        if ($status === self::STATUS_ACCEPTED && $oldStatus !== self::STATUS_ACCEPTED) {
            $this->acceptedAt = new \DateTimeImmutable();
        } elseif ($status === self::STATUS_REJECTED && $oldStatus !== self::STATUS_REJECTED) {
            $this->rejectedAt = new \DateTimeImmutable();
        } elseif ($status === self::STATUS_WITHDRAWN && $oldStatus !== self::STATUS_WITHDRAWN) {
            $this->withdrawnAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getValidUntil(): ?\DateTimeImmutable
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeImmutable $validUntil): self
    {
        $this->validUntil = $validUntil;
        return $this;
    }

    /**
     * Définit la validité en nombre de jours à partir de maintenant
     */
    public function setValidityInDays(int $days): self
    {
        $this->validUntil = (new \DateTimeImmutable())->modify("+{$days} days");
        return $this;
    }

    /**
     * Prolonge la validité de X jours
     */
    public function extendValidity(int $days): self
    {
        if ($this->validUntil !== null) {
            $this->validUntil = $this->validUntil->modify("+{$days} days");
        } else {
            $this->setValidityInDays($days);
        }
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

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getViewedAt(): ?\DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function setViewedAt(?\DateTimeImmutable $viewedAt): self
    {
        $this->viewedAt = $viewedAt;
        return $this;
    }

    public function getAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->acceptedAt;
    }

    public function setAcceptedAt(?\DateTimeImmutable $acceptedAt): self
    {
        $this->acceptedAt = $acceptedAt;
        return $this;
    }

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): self
    {
        $this->rejectedAt = $rejectedAt;
        return $this;
    }

    public function getWithdrawnAt(): ?\DateTimeImmutable
    {
        return $this->withdrawnAt;
    }

    public function setWithdrawnAt(?\DateTimeImmutable $withdrawnAt): self
    {
        $this->withdrawnAt = $withdrawnAt;
        return $this;
    }

    // ============================================
    // REJET
    // ============================================

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): self
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getRejectionCategory(): ?string
    {
        return $this->rejectionCategory;
    }

    public function setRejectionCategory(?string $rejectionCategory): self
    {
        $this->rejectionCategory = $rejectionCategory;
        return $this;
    }

    /**
     * Rejette le devis avec raison
     */
    public function reject(string $reason, ?string $category = null): self
    {
        $this->status = self::STATUS_REJECTED;
        $this->rejectedAt = new \DateTimeImmutable();
        $this->rejectionReason = $reason;
        $this->rejectionCategory = $category;
        return $this;
    }

    // ============================================
    // MODIFICATIONS
    // ============================================

    public function isModificationRequested(): bool
    {
        return $this->modificationRequested;
    }

    public function setModificationRequested(bool $modificationRequested): self
    {
        $this->modificationRequested = $modificationRequested;
        return $this;
    }

    public function getModificationMessage(): ?string
    {
        return $this->modificationMessage;
    }

    public function setModificationMessage(?string $modificationMessage): self
    {
        $this->modificationMessage = $modificationMessage;
        return $this;
    }

    public function getModificationRequestedAt(): ?\DateTimeImmutable
    {
        return $this->modificationRequestedAt;
    }

    public function setModificationRequestedAt(?\DateTimeImmutable $modificationRequestedAt): self
    {
        $this->modificationRequestedAt = $modificationRequestedAt;
        return $this;
    }

    public function getRevisionNumber(): int
    {
        return $this->revisionNumber;
    }

    public function setRevisionNumber(int $revisionNumber): self
    {
        $this->revisionNumber = $revisionNumber;
        return $this;
    }

    public function incrementRevisionNumber(): self
    {
        $this->revisionNumber++;
        return $this;
    }

    public function getPreviousQuoteId(): ?int
    {
        return $this->previousQuoteId;
    }

    public function setPreviousQuoteId(?int $previousQuoteId): self
    {
        $this->previousQuoteId = $previousQuoteId;
        return $this;
    }

    // ============================================
    // MÉTADONNÉES
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

    public function isViewed(): bool
    {
        return $this->isViewed;
    }

    public function setIsViewed(bool $isViewed): self
    {
        $this->isViewed = $isViewed;
        
        if ($isViewed && $this->viewedAt === null) {
            $this->viewedAt = new \DateTimeImmutable();
        }
        
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
        $this->isViewed = true;
        
        if ($this->viewedAt === null) {
            $this->viewedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getClientIp(): ?string
    {
        return $this->clientIp;
    }

    public function setClientIp(?string $clientIp): self
    {
        $this->clientIp = $clientIp;
        return $this;
    }

    public function getClientUserAgent(): ?string
    {
        return $this->clientUserAgent;
    }

    public function setClientUserAgent(?string $clientUserAgent): self
    {
        $this->clientUserAgent = $clientUserAgent;
        return $this;
    }

    // ============================================
    // MÉTHODES UTILITAIRES - ITEMS
    // ============================================

    /**
     * Calcule le montant total du devis à partir des items
     */
    public function calculateTotalFromItems(bool $includeOptional = false): void
    {
        $total = 0;
        
        foreach ($this->items as $item) {
            if (!$includeOptional && $item->isOptional()) {
                continue;
            }
            
            if ($item->getTotalPrice() !== null) {
                $total += (float) $item->getTotalPrice();
            }
        }

        $this->amount = (string) round($total, 2);
    }

    /**
     * Calcule la durée totale du devis à partir des items
     */
    public function calculateTotalDurationFromItems(): int
    {
        $totalDuration = 0;

        foreach ($this->items as $item) {
            if (!$item->isOptional() && $item->getEstimatedDuration() !== null) {
                $totalDuration += $item->getEstimatedDuration() * $item->getQuantity();
            }
        }

        return $totalDuration;
    }

    /**
     * Retourne les items obligatoires
     */
    public function getMandatoryItems(): Collection
    {
        return $this->items->filter(fn(QuoteItem $item) => !$item->isOptional());
    }

    /**
     * Retourne les items optionnels
     */
    public function getOptionalItems(): Collection
    {
        return $this->items->filter(fn(QuoteItem $item) => $item->isOptional());
    }

    /**
     * Calcule le montant total avec les options
     */
    public function getTotalWithOptions(): float
    {
        $total = 0;
        
        foreach ($this->items as $item) {
            if ($item->getTotalPrice() !== null) {
                $total += (float) $item->getTotalPrice();
            }
        }

        return round($total, 2);
    }

    // ============================================
    // MÉTHODES UTILITAIRES - STATUTS
    // ============================================

    /**
     * Vérifie si le devis est expiré
     */
    public function isExpired(): bool
    {
        if ($this->validUntil === null) {
            return false;
        }

        return new \DateTimeImmutable() > $this->validUntil;
    }

    /**
     * Vérifie si le devis est en attente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Vérifie si le devis est accepté
     */
    public function isAccepted(): bool
    {
        return $this->status === self::STATUS_ACCEPTED;
    }

    /**
     * Vérifie si le devis est rejeté
     */
    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    /**
     * Vérifie si le devis est retiré
     */
    public function isWithdrawn(): bool
    {
        return $this->status === self::STATUS_WITHDRAWN;
    }

    /**
     * Vérifie si le devis peut être accepté
     */
    public function canBeAccepted(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isExpired();
    }

    /**
     * Vérifie si le devis peut être rejeté
     */
    public function canBeRejected(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isExpired();
    }

    /**
     * Vérifie si le devis peut être modifié
     */
    public function canBeModified(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Vérifie si le devis peut être retiré par le prestataire
     */
    public function canBeWithdrawn(): bool
    {
        return $this->status === self::STATUS_PENDING && !$this->isAccepted();
    }

    /**
     * Accepte le devis
     */
    public function accept(): self
    {
        $this->status = self::STATUS_ACCEPTED;
        $this->acceptedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Retire le devis
     */
    public function withdraw(): self
    {
        $this->status = self::STATUS_WITHDRAWN;
        $this->withdrawnAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Marque le devis comme expiré
     */
    public function markAsExpired(): self
    {
        if ($this->status === self::STATUS_PENDING) {
            $this->status = self::STATUS_EXPIRED;
        }
        return $this;
    }

    /**
     * Obtient le délai restant avant expiration (en jours)
     */
    public function getDaysUntilExpiration(): ?int
    {
        if ($this->validUntil === null) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->validUntil);
        
        return $diff->invert ? 0 : $diff->days;
    }

    /**
     * Vérifie si une réservation a été créée
     */
    public function hasBooking(): bool
    {
        return $this->booking !== null;
    }

    // ============================================
    // LIFECYCLE CALLBACKS
    // ============================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        
        if ($this->validUntil === null) {
            $this->validUntil = (new \DateTimeImmutable())->modify('+7 days');
        }
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
            'Quote #%d - %s (%.2f€)',
            $this->id ?? 0,
            $this->status,
            $this->getAmountFloat() ?? 0
        );
    }

    /**
     * Retourne une représentation JSON-friendly du devis
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'serviceRequestId' => $this->serviceRequest?->getId(),
            'prestataireId' => $this->prestataire?->getId(),
            'amount' => $this->getAmountFloat(),
            'depositAmount' => $this->depositAmount !== null ? (float) $this->depositAmount : null,
            'depositPercentage' => $this->depositPercentage !== null ? (float) $this->depositPercentage : null,
            'proposedDate' => $this->proposedDate?->format('Y-m-d'),
            'proposedTime' => $this->proposedTime?->format('H:i:s'),
            'proposedDuration' => $this->proposedDuration,
            'formattedDuration' => $this->getFormattedDuration(),
            'description' => $this->description,
            'conditions' => $this->conditions,
            'status' => $this->status,
            'validUntil' => $this->validUntil?->format('Y-m-d H:i:s'),
            'daysUntilExpiration' => $this->getDaysUntilExpiration(),
            'isExpired' => $this->isExpired(),
            'isViewed' => $this->isViewed,
            'viewCount' => $this->viewCount,
            'itemsCount' => $this->items->count(),
            'hasBooking' => $this->hasBooking(),
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}