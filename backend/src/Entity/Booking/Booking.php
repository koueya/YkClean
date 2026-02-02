<?php

namespace App\Entity\Booking;

use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\User\User;
use App\Entity\ServiceRequest\ServiceRequest;
use App\Entity\Quote\Quote;
use App\Entity\Service\ServiceCategory;
use App\Entity\Payment\Payment;
use App\Entity\Review\Review;
use App\Entity\Planning\Replacement;
use App\Entity\Planning\AvailableSlot;
use App\Repository\Booking\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une réservation confirmée de service
 * Créée automatiquement lors de l'acceptation d'un devis par le client
 * Contient toutes les informations pour l'exécution du service
 */
#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
#[ORM\Index(columns: ['client_id', 'status'], name: 'idx_client_status')]
#[ORM\Index(columns: ['prestataire_id', 'status'], name: 'idx_prestataire_status')]
#[ORM\Index(columns: ['scheduled_date', 'status'], name: 'idx_date_status')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['reference_number'], name: 'idx_reference')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created')]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    // Statuts possibles
    public const STATUS_PENDING = 'pending';                     // En attente de confirmation
    public const STATUS_AWAITING_PAYMENT = 'awaiting_payment';   // En attente de paiement
    public const STATUS_SCHEDULED = 'scheduled';                 // Planifié
    public const STATUS_CONFIRMED = 'confirmed';                 // Confirmé par les deux parties
    public const STATUS_IN_PROGRESS = 'in_progress';             // En cours d'exécution
    public const STATUS_COMPLETED = 'completed';                 // Terminé avec succès
    public const STATUS_CANCELLED = 'cancelled';                 // Annulé
    public const STATUS_NO_SHOW = 'no_show';                    // Client absent
    public const STATUS_DISPUTED = 'disputed';                   // Litige en cours
    public const STATUS_REFUNDED = 'refunded';                   // Remboursée

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['booking:read', 'booking:list', 'client:read', 'prestataire:read'])]
    private ?int $id = null;

    /**
     * Numéro de référence unique pour la réservation
     * Format: BK-YYYYMMDD-XXXXX
     */
    #[ORM\Column(type: Types::STRING, length: 100, unique: true)]
    #[Groups(['booking:read', 'booking:list'])]
    private ?string $referenceNumber = null;

    /**
     * Devis à l'origine de la réservation
     */
    #[ORM\OneToOne(inversedBy: 'booking', targetEntity: Quote::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?Quote $quote = null;

    /**
     * Demande de service d'origine (peut être null si réservation directe)
     */
    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?ServiceRequest $serviceRequest = null;

    /**
     * Client qui a réservé le service
     */
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le client est obligatoire', groups: ['booking:create'])]
    #[Groups(['booking:read', 'booking:list', 'prestataire:read'])]
    private ?Client $client = null;

    /**
     * Prestataire qui effectuera le service
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire', groups: ['booking:create'])]
    #[Groups(['booking:read', 'booking:list', 'client:read'])]
    private ?Prestataire $prestataire = null;

    /**
     * Catégorie de service
     */
    #[ORM\ManyToOne(targetEntity: ServiceCategory::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['booking:read', 'booking:list'])]
    private ?ServiceCategory $serviceCategory = null;

    /**
     * Récurrence associée si service récurrent
     */
    #[ORM\ManyToOne(targetEntity: Recurrence::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?Recurrence $recurrence = null;

    /**
     * Créneau disponible utilisé pour cette réservation
     */
    #[ORM\OneToOne(targetEntity: AvailableSlot::class, mappedBy: 'booking')]
    #[Groups(['booking:admin'])]
    private ?AvailableSlot $availableSlot = null;

    /**
     * Date prévue du service
     */
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date est obligatoire', groups: ['booking:create'])]
    #[Assert\GreaterThanOrEqual(
        'today',
        message: 'La date ne peut pas être dans le passé',
        groups: ['booking:create']
    )]
    #[Groups(['booking:read', 'booking:list'])]
    private ?\DateTimeInterface $scheduledDate = null;

    /**
     * Heure prévue du service
     */
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank(message: 'L\'heure est obligatoire', groups: ['booking:create'])]
    #[Groups(['booking:read', 'booking:list'])]
    private ?\DateTimeInterface $scheduledTime = null;

    /**
     * Durée prévue en minutes
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(groups: ['booking:create'])]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Assert\Range(
        min: 30,
        max: 480,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
    )]
    #[Groups(['booking:read', 'booking:list'])]
    private ?int $duration = null;

    /**
     * Adresse complète du service
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire', groups: ['booking:create'])]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['booking:read', 'booking:list'])]
    private ?string $address = null;

    /**
     * Ville
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(groups: ['booking:create'])]
    #[Groups(['booking:read', 'booking:list'])]
    private ?string $city = null;

    /**
     * Code postal
     */
    #[ORM\Column(type: Types::STRING, length: 10)]
    #[Assert\NotBlank(groups: ['booking:create'])]
    #[Assert\Length(max: 10)]
    #[Groups(['booking:read', 'booking:list'])]
    private ?string $postalCode = null;

    /**
     * Latitude pour géolocalisation
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Groups(['booking:read', 'booking:admin'])]
    private ?string $latitude = null;

    /**
     * Longitude pour géolocalisation
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    #[Groups(['booking:read', 'booking:admin'])]
    private ?string $longitude = null;

    /**
     * Montant total de la réservation
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(groups: ['booking:create'])]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    #[Groups(['booking:read', 'booking:list'])]
    private ?string $amount = null;

    /**
     * Statut actuel de la réservation
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_AWAITING_PAYMENT,
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
            self::STATUS_DISPUTED,
            self::STATUS_REFUNDED
        ],
        message: 'Statut invalide'
    )]
    #[Groups(['booking:read', 'booking:list'])]
    private string $status = self::STATUS_PENDING;

    /**
     * Heure de début réelle du service
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?\DateTime $actualStartTime = null;

    /**
     * Heure de fin réelle du service
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?\DateTime $actualEndTime = null;

    /**
     * Notes de finalisation du service (par le prestataire)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?string $completionNotes = null;

    /**
     * Raison de l'annulation
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?string $cancellationReason = null;

    /**
     * Instructions du client pour le prestataire
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['booking:read', 'booking:detail', 'prestataire:read'])]
    private ?array $clientInstructions = [];

    /**
     * Notes du prestataire (visibles uniquement par lui)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['booking:admin', 'prestataire:read'])]
    private ?array $prestataireNotes = [];

    /**
     * Le client sera-t-il présent lors du service ?
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['booking:read', 'booking:detail'])]
    private bool $clientPresent = false;

    /**
     * Instructions d'accès au domicile (code porte, digicode, etc.)
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Les instructions ne peuvent pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['booking:read', 'booking:detail', 'prestataire:read'])]
    private ?string $accessInstructions = null;

    /**
     * Code d'accès (chiffré)
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['booking:detail', 'prestataire:read'])]
    private ?string $accessCode = null;

    /**
     * Rappel 24h envoyé
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['booking:admin'])]
    private bool $reminderSent24h = false;

    /**
     * Rappel 2h envoyé
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['booking:admin'])]
    private bool $reminderSent2h = false;

    /**
     * Paiement associé à la réservation
     */
    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Payment::class, cascade: ['persist', 'remove'])]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?Payment $payment = null;

    /**
     * Avis laissé par le client
     */
    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Review::class, cascade: ['persist', 'remove'])]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?Review $review = null;

    /**
     * Historique des remplacements de prestataire
     */
    #[ORM\OneToMany(mappedBy: 'originalBooking', targetEntity: Replacement::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['booking:detail', 'booking:admin'])]
    private Collection $replacements;

    /**
     * Utilisateur qui a annulé la réservation (si annulée)
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['booking:admin'])]
    private ?User $cancelledBy = null;

    /**
     * Date de création de la réservation
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['booking:read', 'booking:list'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de dernière mise à jour
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['booking:read'])]
    private \DateTimeImmutable $updatedAt;

    /**
     * Date de confirmation de la réservation
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?\DateTimeImmutable $confirmedAt = null;

    /**
     * Date de finalisation du service
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?\DateTimeImmutable $completedAt = null;

    /**
     * Date d'annulation
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * Date de remboursement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['booking:read', 'booking:detail'])]
    private ?\DateTimeImmutable $refundedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->replacements = new ArrayCollection();
        $this->clientInstructions = [];
        $this->prestataireNotes = [];
        $this->generateReferenceNumber();
    }

    // ==================== LIFECYCLE CALLBACKS ====================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->updatedAt === null) {
            $this->updatedAt = new \DateTimeImmutable();
        }
        if ($this->referenceNumber === null) {
            $this->generateReferenceNumber();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Génère un numéro de référence unique
     * Format: BK-YYYYMMDD-XXXXX
     */
    private function generateReferenceNumber(): void
    {
        $date = (new \DateTime())->format('Ymd');
        $random = strtoupper(substr(md5(uniqid((string)mt_rand(), true)), 0, 5));
        $this->referenceNumber = "BK-{$date}-{$random}";
    }

    // ==================== GETTERS / SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;
        return $this;
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

    public function getServiceRequest(): ?ServiceRequest
    {
        return $this->serviceRequest;
    }

    public function setServiceRequest(?ServiceRequest $serviceRequest): self
    {
        $this->serviceRequest = $serviceRequest;
        return $this;
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

    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(?Prestataire $prestataire): self
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getServiceCategory(): ?ServiceCategory
    {
        return $this->serviceCategory;
    }

    public function setServiceCategory(?ServiceCategory $serviceCategory): self
    {
        $this->serviceCategory = $serviceCategory;
        return $this;
    }

    public function getRecurrence(): ?Recurrence
    {
        return $this->recurrence;
    }

    public function setRecurrence(?Recurrence $recurrence): self
    {
        $this->recurrence = $recurrence;
        return $this;
    }

    public function getAvailableSlot(): ?AvailableSlot
    {
        return $this->availableSlot;
    }

    public function setAvailableSlot(?AvailableSlot $availableSlot): self
    {
        $this->availableSlot = $availableSlot;
        
        // Synchronisation bidirectionnelle
        if ($availableSlot !== null && $availableSlot->getBooking() !== $this) {
            $availableSlot->setBooking($this);
        }
        
        return $this;
    }

    public function getScheduledDate(): ?\DateTimeInterface
    {
        return $this->scheduledDate;
    }

    public function setScheduledDate(\DateTimeInterface $scheduledDate): self
    {
        $this->scheduledDate = $scheduledDate;
        return $this;
    }

    public function getScheduledTime(): ?\DateTimeInterface
    {
        return $this->scheduledTime;
    }

    public function setScheduledTime(\DateTimeInterface $scheduledTime): self
    {
        $this->scheduledTime = $scheduledTime;
        return $this;
    }

    /**
     * Retourne la date et l'heure combinées
     */
    public function getScheduledDateTime(): ?\DateTime
    {
        if (!$this->scheduledDate || !$this->scheduledTime) {
            return null;
        }

        $datetime = clone $this->scheduledDate;
        $datetime->setTime(
            (int) $this->scheduledTime->format('H'),
            (int) $this->scheduledTime->format('i'),
            (int) $this->scheduledTime->format('s')
        );

        return $datetime;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    /**
     * Retourne la durée formatée (ex: "2h30")
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return '0h';
        }

        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($minutes === 0) {
            return "{$hours}h";
        }

        return "{$hours}h{$minutes}";
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    /**
     * Retourne l'adresse complète formatée
     */
    public function getFullAddress(): string
    {
        return sprintf('%s, %s %s', $this->address, $this->postalCode, $this->city);
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

    /**
     * Vérifie si la réservation a des coordonnées GPS
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    /**
     * Retourne le montant formaté (ex: "45,50 €")
     */
    public function getFormattedAmount(): string
    {
        return number_format((float) $this->amount, 2, ',', ' ') . ' €';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $oldStatus = $this->status;
        $this->status = $status;

        // Mise à jour automatique des dates selon le statut
        $now = new \DateTimeImmutable();

        if ($status === self::STATUS_CONFIRMED && $this->confirmedAt === null) {
            $this->confirmedAt = $now;
        } elseif ($status === self::STATUS_COMPLETED && $this->completedAt === null) {
            $this->completedAt = $now;
            if ($this->actualEndTime === null) {
                $this->actualEndTime = new \DateTime();
            }
        } elseif ($status === self::STATUS_CANCELLED && $this->cancelledAt === null) {
            $this->cancelledAt = $now;
        } elseif ($status === self::STATUS_REFUNDED && $this->refundedAt === null) {
            $this->refundedAt = $now;
        }

        return $this;
    }

    /**
     * Retourne le libellé du statut en français
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_AWAITING_PAYMENT => 'En attente de paiement',
            self::STATUS_SCHEDULED => 'Planifié',
            self::STATUS_CONFIRMED => 'Confirmé',
            self::STATUS_IN_PROGRESS => 'En cours',
            self::STATUS_COMPLETED => 'Terminé',
            self::STATUS_CANCELLED => 'Annulé',
            self::STATUS_NO_SHOW => 'Client absent',
            self::STATUS_DISPUTED => 'Litige',
            self::STATUS_REFUNDED => 'Remboursé',
            default => $this->status
        };
    }

    public function getActualStartTime(): ?\DateTime
    {
        return $this->actualStartTime;
    }

    public function setActualStartTime(?\DateTime $actualStartTime): self
    {
        $this->actualStartTime = $actualStartTime;
        return $this;
    }

    public function getActualEndTime(): ?\DateTime
    {
        return $this->actualEndTime;
    }

    public function setActualEndTime(?\DateTime $actualEndTime): self
    {
        $this->actualEndTime = $actualEndTime;
        return $this;
    }

    /**
     * Calcule la durée réelle du service en minutes
     */
    public function getActualDuration(): ?int
    {
        if (!$this->actualStartTime || !$this->actualEndTime) {
            return null;
        }

        $diff = $this->actualEndTime->getTimestamp() - $this->actualStartTime->getTimestamp();
        return (int) round($diff / 60);
    }

    public function getCompletionNotes(): ?string
    {
        return $this->completionNotes;
    }

    public function setCompletionNotes(?string $completionNotes): self
    {
        $this->completionNotes = $completionNotes;
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

    public function getClientInstructions(): ?array
    {
        return $this->clientInstructions ?? [];
    }

    public function setClientInstructions(?array $clientInstructions): self
    {
        $this->clientInstructions = $clientInstructions;
        return $this;
    }

    public function addClientInstruction(string $key, mixed $value): self
    {
        if ($this->clientInstructions === null) {
            $this->clientInstructions = [];
        }
        $this->clientInstructions[$key] = $value;
        return $this;
    }

    public function getPrestataireNotes(): ?array
    {
        return $this->prestataireNotes ?? [];
    }

    public function setPrestataireNotes(?array $prestataireNotes): self
    {
        $this->prestataireNotes = $prestataireNotes;
        return $this;
    }

    public function addPrestataireNote(string $key, mixed $value): self
    {
        if ($this->prestataireNotes === null) {
            $this->prestataireNotes = [];
        }
        $this->prestataireNotes[$key] = $value;
        return $this;
    }

    public function isClientPresent(): bool
    {
        return $this->clientPresent;
    }

    public function setClientPresent(bool $clientPresent): self
    {
        $this->clientPresent = $clientPresent;
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

    public function getAccessCode(): ?string
    {
        return $this->accessCode;
    }

    public function setAccessCode(?string $accessCode): self
    {
        $this->accessCode = $accessCode;
        return $this;
    }

    public function isReminderSent24h(): bool
    {
        return $this->reminderSent24h;
    }

    public function setReminderSent24h(bool $reminderSent24h): self
    {
        $this->reminderSent24h = $reminderSent24h;
        return $this;
    }

    public function isReminderSent2h(): bool
    {
        return $this->reminderSent2h;
    }

    public function setReminderSent2h(bool $reminderSent2h): self
    {
        $this->reminderSent2h = $reminderSent2h;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        // Synchronisation bidirectionnelle
        if ($payment === null && $this->payment !== null) {
            $this->payment->setBooking(null);
        }

        if ($payment !== null && $payment->getBooking() !== $this) {
            $payment->setBooking($this);
        }

        $this->payment = $payment;
        return $this;
    }

    /**
     * Vérifie si la réservation est payée
     */
    public function isPaid(): bool
    {
        return $this->payment !== null && $this->payment->getStatus() === 'completed';
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): self
    {
        // Synchronisation bidirectionnelle
        if ($review === null && $this->review !== null) {
            $this->review->setBooking(null);
        }

        if ($review !== null && $review->getBooking() !== $this) {
            $review->setBooking($this);
        }

        $this->review = $review;
        return $this;
    }

    /**
     * Vérifie si la réservation a un avis
     */
    public function hasReview(): bool
    {
        return $this->review !== null;
    }

    /**
     * @return Collection<int, Replacement>
     */
    public function getReplacements(): Collection
    {
        return $this->replacements;
    }

    public function addReplacement(Replacement $replacement): self
    {
        if (!$this->replacements->contains($replacement)) {
            $this->replacements->add($replacement);
            $replacement->setOriginalBooking($this);
        }

        return $this;
    }

    public function removeReplacement(Replacement $replacement): self
    {
        if ($this->replacements->removeElement($replacement)) {
            if ($replacement->getOriginalBooking() === $this) {
                $replacement->setOriginalBooking(null);
            }
        }

        return $this;
    }

    /**
     * Vérifie si la réservation a des remplacements
     */
    public function hasReplacements(): bool
    {
        return !$this->replacements->isEmpty();
    }

    /**
     * Récupère le remplacement actif (confirmé)
     */
    public function getActiveReplacement(): ?Replacement
    {
        foreach ($this->replacements as $replacement) {
            if ($replacement->isConfirmed()) {
                return $replacement;
            }
        }
        return null;
    }

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): self
    {
        $this->cancelledBy = $cancelledBy;
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

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?\DateTimeImmutable $confirmedAt): self
    {
        $this->confirmedAt = $confirmedAt;
        return $this;
    }

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
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

    public function getRefundedAt(): ?\DateTimeImmutable
    {
        return $this->refundedAt;
    }

    public function setRefundedAt(?\DateTimeImmutable $refundedAt): self
    {
        $this->refundedAt = $refundedAt;
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifie si la réservation est en attente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Vérifie si la réservation est en attente de paiement
     */
    public function isAwaitingPayment(): bool
    {
        return $this->status === self::STATUS_AWAITING_PAYMENT;
    }

    /**
     * Vérifie si la réservation est planifiée
     */
    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    /**
     * Vérifie si la réservation est confirmée
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Vérifie si la réservation est en cours
     */
    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Vérifie si la réservation est terminée
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Vérifie si la réservation est annulée
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Vérifie si la réservation est remboursée
     */
    public function isRefunded(): bool
    {
        return $this->status === self::STATUS_REFUNDED;
    }

    /**
     * Vérifie si la réservation est en litige
     */
    public function isDisputed(): bool
    {
        return $this->status === self::STATUS_DISPUTED;
    }

    /**
     * Vérifie si la réservation peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_AWAITING_PAYMENT,
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED
        ]);
    }

    /**
     * Vérifie si la réservation peut être modifiée
     */
    public function canBeModified(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED
        ]);
    }

    /**
     * Vérifie si la réservation peut être démarrée
     */
    public function canBeStarted(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Vérifie si la réservation peut être terminée
     */
    public function canBeCompleted(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    /**
     * Vérifie si la réservation peut recevoir un avis
     */
    public function canBeReviewed(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Vérifie si la réservation nécessite un paiement
     */
    public function requiresPayment(): bool
    {
        return in_array($this->status, [
            self::STATUS_PENDING,
            self::STATUS_AWAITING_PAYMENT,
            self::STATUS_CONFIRMED,
            self::STATUS_SCHEDULED,
            self::STATUS_IN_PROGRESS
        ]);
    }

    /**
     * Vérifie si la réservation peut être remboursée
     */
    public function canBeRefunded(): bool
    {
        return in_array($this->status, [
            self::STATUS_CONFIRMED,
            self::STATUS_SCHEDULED,
            self::STATUS_COMPLETED,
            self::STATUS_NO_SHOW
        ]);
    }

    /**
     * Vérifie si la réservation est passée
     */
    public function isPast(): bool
    {
        if (!$this->scheduledDate) {
            return false;
        }

        $scheduled = $this->getScheduledDateTime();
        if (!$scheduled) {
            return false;
        }

        return $scheduled < new \DateTime();
    }

    /**
     * Vérifie si la réservation est dans les prochaines 24h
     */
    public function isUpcoming24h(): bool
    {
        if (!$this->scheduledDate) {
            return false;
        }

        $scheduled = $this->getScheduledDateTime();
        if (!$scheduled) {
            return false;
        }

        $now = new \DateTime();
        $in24h = (clone $now)->modify('+24 hours');

        return $scheduled > $now && $scheduled <= $in24h;
    }

    /**
     * Vérifie si la réservation est dans les prochaines 2h
     */
    public function isUpcoming2h(): bool
    {
        if (!$this->scheduledDate) {
            return false;
        }

        $scheduled = $this->getScheduledDateTime();
        if (!$scheduled) {
            return false;
        }

        $now = new \DateTime();
        $in2h = (clone $now)->modify('+2 hours');

        return $scheduled > $now && $scheduled <= $in2h;
    }

    /**
     * Vérifie si c'est une réservation récurrente
     */
    public function isRecurring(): bool
    {
        return $this->recurrence !== null;
    }

    /**
     * Calcule le temps restant avant la réservation
     */
    public function getTimeUntilBooking(): ?\DateInterval
    {
        $scheduled = $this->getScheduledDateTime();
        if (!$scheduled) {
            return null;
        }

        $now = new \DateTime();
        if ($scheduled > $now) {
            return $now->diff($scheduled);
        }

        return null;
    }

    /**
     * Retourne une représentation textuelle
     */
    public function __toString(): string
    {
        $date = $this->scheduledDate ? $this->scheduledDate->format('d/m/Y') : 'N/A';
        $time = $this->scheduledTime ? $this->scheduledTime->format('H:i') : 'N/A';
        
        return "Réservation #{$this->id} - {$date} à {$time}";
    }

    /**
     * Liste de tous les statuts disponibles
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_AWAITING_PAYMENT,
            self::STATUS_SCHEDULED,
            self::STATUS_CONFIRMED,
            self::STATUS_IN_PROGRESS,
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_NO_SHOW,
            self::STATUS_DISPUTED,
            self::STATUS_REFUNDED
        ];
    }
}