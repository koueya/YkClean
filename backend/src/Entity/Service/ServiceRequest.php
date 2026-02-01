<?php
// src/Entity/Service/ServiceRequest.php

namespace App\Entity\Service;

use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\User\Client;
use App\Repository\Service\ServiceRequestRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ServiceRequestRepository::class)]
#[ORM\Table(name: 'service_requests')]
#[ORM\HasLifecycleCallbacks]
class ServiceRequest
{
    // Constantes pour les statuts
    public const STATUS_OPEN = 'open';
    public const STATUS_QUOTING = 'quoting';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED = 'expired';

    // Constantes pour les fréquences
    public const FREQUENCY_PONCTUEL = 'ponctuel';
    public const FREQUENCY_HEBDOMADAIRE = 'hebdomadaire';
    public const FREQUENCY_BI_HEBDOMADAIRE = 'bi_hebdomadaire';
    public const FREQUENCY_MENSUEL = 'mensuel';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    // Relations
    
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'serviceRequests')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le client est requis')]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class, inversedBy: 'serviceRequests')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'La catégorie de service est requise')]
    private ?ServiceCategory $category = null;

    #[ORM\OneToMany(mappedBy: 'serviceRequest', targetEntity: Quote::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'DESC'])]
    private Collection $quotes;

    #[ORM\OneToMany(mappedBy: 'serviceRequest', targetEntity: Booking::class, cascade: ['persist'])]
    #[ORM\OrderBy(['scheduledDate' => 'DESC'])]
    private Collection $bookings;

    // Informations de base

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'La description est requise')]
    #[Assert\Length(
        min: 20,
        max: 2000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    // Adresse

    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse est requise')]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'La ville est requise')]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank(message: 'Le code postal est requis')]
    #[Assert\Regex(
        pattern: '/^[0-9]{5}$/',
        message: 'Le code postal doit contenir 5 chiffres'
    )]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    // Dates

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(message: 'La date préférée est requise')]
    #[Assert\GreaterThan(
        'today',
        message: 'La date préférée doit être dans le futur'
    )]
    private ?\DateTimeImmutable $preferredDate = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $alternativeDates = null;

    // Durée et fréquence

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Assert\Range(
        min: 30,
        max: 480,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
    )]
    private ?int $estimatedDuration = null;

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'La fréquence est requise')]
    #[Assert\Choice(
        choices: [
            self::FREQUENCY_PONCTUEL,
            self::FREQUENCY_HEBDOMADAIRE,
            self::FREQUENCY_BI_HEBDOMADAIRE,
            self::FREQUENCY_MENSUEL
        ],
        message: 'La fréquence sélectionnée n\'est pas valide'
    )]
    private string $frequency = self::FREQUENCY_PONCTUEL;

    // Budget

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le budget minimum doit être positif')]
    private ?string $budgetMin = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le budget maximum doit être positif')]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'budgetMin',
        message: 'Le budget maximum doit être supérieur ou égal au budget minimum'
    )]
    private ?string $budgetMax = null;

    // Statut et gestion

    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(
        choices: [
            self::STATUS_OPEN,
            self::STATUS_QUOTING,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_EXPIRED
        ],
        message: 'Le statut sélectionné n\'est pas valide'
    )]
    private string $status = self::STATUS_OPEN;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\GreaterThan(
        'now',
        message: 'La date d\'expiration doit être dans le futur'
    )]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $closedAt = null;

    // Informations supplémentaires

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $additionalInfo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $specificRequirements = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'La surface doit être positive')]
    private ?int $surfaceArea = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'Le nombre de pièces doit être positif')]
    #[Assert\Range(
        min: 1,
        max: 50,
        notInRangeMessage: 'Le nombre de pièces doit être entre {{ min }} et {{ max }}'
    )]
    private ?int $numberOfRooms = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $hasPets = false;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $accessInstructions = null;

    // Annulation

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $cancelledBy = null; // 'client' ou 'system'

    // Statistiques

    #[ORM\Column(type: Types::INTEGER)]
    private int $viewsCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    private int $quotesReceivedCount = 0;

    // Constructeur

    public function __construct()
    {
        $this->quotes = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        
        // Date d'expiration par défaut : 7 jours
        $this->expiresAt = (new \DateTimeImmutable())->modify('+7 days');
    }

    // Getters et Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
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

    /**
     * @return Collection<int, Quote>
     */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(Quote $quote): self
    {
        if (!$this->quotes->contains($quote)) {
            $this->quotes->add($quote);
            $quote->setServiceRequest($this);
            $this->quotesReceivedCount = $this->quotes->count();
        }

        return $this;
    }

    public function removeQuote(Quote $quote): self
    {
        if ($this->quotes->removeElement($quote)) {
            if ($quote->getServiceRequest() === $this) {
                $quote->setServiceRequest(null);
            }
            $this->quotesReceivedCount = $this->quotes->count();
        }

        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setServiceRequest($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getServiceRequest() === $this) {
                $booking->setServiceRequest(null);
            }
        }

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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getPreferredDate(): ?\DateTimeImmutable
    {
        return $this->preferredDate;
    }

    public function setPreferredDate(?\DateTimeImmutable $preferredDate): self
    {
        $this->preferredDate = $preferredDate;
        return $this;
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

    public function getEstimatedDuration(): ?int
    {
        return $this->estimatedDuration;
    }

    public function setEstimatedDuration(?int $estimatedDuration): self
    {
        $this->estimatedDuration = $estimatedDuration;
        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->frequency !== self::FREQUENCY_PONCTUEL;
    }

    public function getBudgetMin(): ?string
    {
        return $this->budgetMin;
    }

    public function setBudgetMin(?string $budgetMin): self
    {
        $this->budgetMin = $budgetMin;
        return $this;
    }

    public function getBudgetMax(): ?string
    {
        return $this->budgetMax;
    }

    public function setBudgetMax(?string $budgetMax): self
    {
        $this->budgetMax = $budgetMax;
        return $this;
    }

    /**
     * Retourne le budget moyen
     */
    public function getAverageBudget(): ?float
    {
        if ($this->budgetMin === null && $this->budgetMax === null) {
            return null;
        }

        $min = (float) ($this->budgetMin ?? 0);
        $max = (float) ($this->budgetMax ?? $min);

        return ($min + $max) / 2;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $oldStatus = $this->status;
        $this->status = $status;

        // Mettre à jour closedAt si la demande est clôturée
        if (in_array($status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED])) {
            if ($this->closedAt === null) {
                $this->closedAt = new \DateTimeImmutable();
            }
        }

        // Si la demande passe en quoting, mettre à jour le statut
        if ($status === self::STATUS_QUOTING && $oldStatus === self::STATUS_OPEN) {
            $this->updatedAt = new \DateTimeImmutable();
        }

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

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getClosedAt(): ?\DateTimeImmutable
    {
        return $this->closedAt;
    }

    public function setClosedAt(?\DateTimeImmutable $closedAt): self
    {
        $this->closedAt = $closedAt;
        return $this;
    }

    public function getAdditionalInfo(): ?array
    {
        return $this->additionalInfo ?? [];
    }

    public function setAdditionalInfo(?array $additionalInfo): self
    {
        $this->additionalInfo = $additionalInfo;
        return $this;
    }

    public function addAdditionalInfo(string $key, mixed $value): self
    {
        $info = $this->getAdditionalInfo();
        $info[$key] = $value;
        $this->additionalInfo = $info;
        return $this;
    }

    public function getSpecificRequirements(): ?string
    {
        return $this->specificRequirements;
    }

    public function setSpecificRequirements(?string $specificRequirements): self
    {
        $this->specificRequirements = $specificRequirements;
        return $this;
    }

    public function getSurfaceArea(): ?int
    {
        return $this->surfaceArea;
    }

    public function setSurfaceArea(?int $surfaceArea): self
    {
        $this->surfaceArea = $surfaceArea;
        return $this;
    }

    public function getNumberOfRooms(): ?int
    {
        return $this->numberOfRooms;
    }

    public function setNumberOfRooms(?int $numberOfRooms): self
    {
        $this->numberOfRooms = $numberOfRooms;
        return $this;
    }

    public function getHasPets(): bool
    {
        return $this->hasPets;
    }

    public function setHasPets(bool $hasPets): self
    {
        $this->hasPets = $hasPets;
        return $this;
    }

    public function getAccessInstructions(): ?string
    {
        return $this->accessInstructions;
    }

    public function setAccessInstructions(?string $accessInstructions): self
    {
        $this->accessInstructions = $accessInstructions;
        return $this;
    }

    public function getCancellationReason(): ?string
    {
        return $this->cancellationReason;
    }

    public function setCancellationReason(?string $cancellationReason): self
    {
        $this->cancellationReason = $cancellationReason;
        return $this;
    }

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getCancelledBy(): ?string
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?string $cancelledBy): self
    {
        $this->cancelledBy = $cancelledBy;
        return $this;
    }

    public function getViewsCount(): int
    {
        return $this->viewsCount;
    }

    public function setViewsCount(int $viewsCount): self
    {
        $this->viewsCount = $viewsCount;
        return $this;
    }

    public function incrementViewsCount(): self
    {
        $this->viewsCount++;
        return $this;
    }

    public function getQuotesReceivedCount(): int
    {
        return $this->quotesReceivedCount;
    }

    public function setQuotesReceivedCount(int $quotesReceivedCount): self
    {
        $this->quotesReceivedCount = $quotesReceivedCount;
        return $this;
    }

    // Méthodes utilitaires

    /**
     * Vérifie si la demande est expirée
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return new \DateTimeImmutable() > $this->expiresAt;
    }

    /**
     * Vérifie si la demande est ouverte
     */
    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    /**
     * Vérifie si la demande est en cours de cotation
     */
    public function isQuoting(): bool
    {
        return $this->status === self::STATUS_QUOTING;
    }

    /**
     * Vérifie si la demande est complétée
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Vérifie si la demande est annulée
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Vérifie si la demande peut recevoir de nouveaux devis
     */
    public function canReceiveQuotes(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_QUOTING]) 
            && !$this->isExpired();
    }

    /**
     * Vérifie si la demande peut être modifiée
     */
    public function canBeUpdated(): bool
    {
        return in_array($this->status, [self::STATUS_OPEN, self::STATUS_QUOTING]);
    }

    /**
     * Vérifie si la demande peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_EXPIRED]);
    }

    /**
     * Annule la demande
     */
    public function cancel(string $reason, string $cancelledBy = 'client'): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancellationReason = $reason;
        $this->cancelledAt = new \DateTimeImmutable();
        $this->cancelledBy = $cancelledBy;
        $this->closedAt = new \DateTimeImmutable();
        
        return $this;
    }

    /**
     * Prolonge la date d'expiration
     */
    public function extendExpiration(int $days = 7): self
    {
        if ($this->expiresAt === null) {
            $this->expiresAt = new \DateTimeImmutable();
        }
        
        $this->expiresAt = $this->expiresAt->modify("+{$days} days");
        $this->updatedAt = new \DateTimeImmutable();
        
        return $this;
    }

    /**
     * Obtient le nombre de devis actifs (non rejetés, non expirés)
     */
    public function getActiveQuotesCount(): int
    {
        return $this->quotes->filter(function (Quote $quote) {
            return in_array($quote->getStatus(), ['pending', 'accepted']);
        })->count();
    }

    /**
     * Obtient le devis accepté (s'il existe)
     */
    public function getAcceptedQuote(): ?Quote
    {
        foreach ($this->quotes as $quote) {
            if ($quote->getStatus() === 'accepted') {
                return $quote;
            }
        }
        
        return null;
    }

    /**
     * Vérifie si un devis a été accepté
     */
    public function hasAcceptedQuote(): bool
    {
        return $this->getAcceptedQuote() !== null;
    }

    /**
     * Obtient l'adresse complète formatée
     */
    public function getFullAddress(): string
    {
        return sprintf(
            '%s, %s %s',
            $this->address,
            $this->postalCode,
            $this->city
        );
    }

    /**
     * Vérifie si la demande a des coordonnées GPS
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    // Callbacks Doctrine

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
        
        if ($this->expiresAt === null) {
            $this->expiresAt = (new \DateTimeImmutable())->modify('+7 days');
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function __toString(): string
    {
        return sprintf(
            'ServiceRequest #%d - %s (%s)',
            $this->id ?? 0,
            $this->category?->getName() ?? 'Unknown',
            $this->status
        );
    }
}