<?php

namespace App\Entity\Planning;

use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Repository\Planning\ReplacementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un remplacement de prestataire pour une réservation
 * Permet de gérer les absences et les changements de prestataires
 * tout en maintenant la continuité du service pour le client
 */
#[ORM\Entity(repositoryClass: ReplacementRepository::class)]
#[ORM\Table(name: 'replacements')]
#[ORM\Index(columns: ['original_booking_id', 'status'], name: 'idx_booking_status')]
#[ORM\Index(columns: ['original_prestataire_id', 'status'], name: 'idx_original_prestataire')]
#[ORM\Index(columns: ['replacement_prestataire_id', 'status'], name: 'idx_replacement_prestataire')]
#[ORM\Index(columns: ['requested_at'], name: 'idx_requested_at')]
#[ORM\Index(columns: ['confirmed_at'], name: 'idx_confirmed_at')]
#[ORM\HasLifecycleCallbacks]
class Replacement
{
    // Statuts possibles
    public const STATUS_PENDING = 'pending';           // En attente de validation
    public const STATUS_SEARCHING = 'searching';       // Recherche de remplaçant
    public const STATUS_PROPOSED = 'proposed';         // Remplaçant proposé au client
    public const STATUS_ACCEPTED = 'accepted';         // Accepté par le client
    public const STATUS_CONFIRMED = 'confirmed';       // Confirmé par le remplaçant
    public const STATUS_REJECTED = 'rejected';         // Rejeté par le client
    public const STATUS_DECLINED = 'declined';         // Refusé par le remplaçant
    public const STATUS_CANCELLED = 'cancelled';       // Annulé
    public const STATUS_COMPLETED = 'completed';       // Service effectué par le remplaçant
    
    // Types de remplacement
    public const TYPE_ABSENCE = 'absence';             // Absence du prestataire
    public const TYPE_EMERGENCY = 'emergency';         // Urgence
    public const TYPE_UNAVAILABILITY = 'unavailability'; // Indisponibilité planifiée
    public const TYPE_QUALITY = 'quality';             // Problème de qualité
    public const TYPE_CLIENT_REQUEST = 'client_request'; // Demande du client
    
    // Priorités
    public const PRIORITY_LOW = 1;
    public const PRIORITY_NORMAL = 2;
    public const PRIORITY_HIGH = 3;
    public const PRIORITY_URGENT = 4;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['replacement:read', 'replacement:list', 'booking:read'])]
    private ?int $id = null;

    /**
     * Réservation concernée par le remplacement
     */
    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'replacements')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La réservation est obligatoire', groups: ['replacement:create'])]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?Booking $originalBooking = null;

    /**
     * Prestataire original (celui qui ne peut pas assurer le service)
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire original est obligatoire', groups: ['replacement:create'])]
    #[Groups(['replacement:read', 'replacement:list', 'replacement:detail'])]
    private ?Prestataire $originalPrestataire = null;

    /**
     * Prestataire de remplacement
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['replacement:read', 'replacement:list', 'replacement:detail'])]
    private ?Prestataire $replacementPrestataire = null;

    /**
     * Raison du remplacement
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['replacement:read', 'replacement:detail', 'replacement:create', 'replacement:update'])]
    private ?string $reason = null;

    /**
     * Type de remplacement
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le type de remplacement est obligatoire', groups: ['replacement:create'])]
    #[Assert\Choice(
        choices: [
            self::TYPE_ABSENCE,
            self::TYPE_EMERGENCY,
            self::TYPE_UNAVAILABILITY,
            self::TYPE_QUALITY,
            self::TYPE_CLIENT_REQUEST
        ],
        message: 'Type de remplacement invalide'
    )]
    #[Groups(['replacement:read', 'replacement:list', 'replacement:create'])]
    private string $type = self::TYPE_ABSENCE;

    /**
     * Statut actuel du remplacement
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_SEARCHING,
            self::STATUS_PROPOSED,
            self::STATUS_ACCEPTED,
            self::STATUS_CONFIRMED,
            self::STATUS_REJECTED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_COMPLETED
        ],
        message: 'Statut invalide'
    )]
    #[Groups(['replacement:read', 'replacement:list'])]
    private string $status = self::STATUS_PENDING;

    /**
     * Priorité du remplacement
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(groups: ['replacement:create'])]
    #[Assert\Choice(
        choices: [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT
        ],
        message: 'Priorité invalide'
    )]
    #[Groups(['replacement:read', 'replacement:list', 'replacement:create'])]
    private int $priority = self::PRIORITY_NORMAL;

    /**
     * Date et heure de la demande de remplacement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Assert\NotNull(groups: ['replacement:create'])]
    #[Groups(['replacement:read', 'replacement:list'])]
    private \DateTimeImmutable $requestedAt;

    /**
     * Date et heure de confirmation du remplacement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?\DateTimeImmutable $confirmedAt = null;

    /**
     * Date et heure de proposition au client
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?\DateTimeImmutable $proposedAt = null;

    /**
     * Date et heure d'acceptation par le client
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?\DateTimeImmutable $acceptedAt = null;

    /**
     * Date et heure de rejet
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?\DateTimeImmutable $rejectedAt = null;

    /**
     * Date et heure d'annulation
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * Raison du rejet/refus/annulation
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La raison du rejet ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['replacement:read', 'replacement:detail', 'replacement:update'])]
    private ?string $rejectionReason = null;

    /**
     * Notes internes (visibles uniquement par les admins)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['replacement:admin'])]
    private ?string $internalNotes = null;

    /**
     * Notes pour le client
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les notes client ne peuvent pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?string $clientNotes = null;

    /**
     * Montant du service (peut être différent si remplaçant avec tarif différent)
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?string $amount = null;

    /**
     * Différence de prix par rapport au prestataire original
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    #[Groups(['replacement:read', 'replacement:detail'])]
    private ?string $priceDifference = null;

    /**
     * Indique si le client a été notifié
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['replacement:read', 'replacement:admin'])]
    private bool $clientNotified = false;

    /**
     * Date de notification du client
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read', 'replacement:admin'])]
    private ?\DateTimeImmutable $clientNotifiedAt = null;

    /**
     * Indique si le remplaçant a été notifié
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['replacement:read', 'replacement:admin'])]
    private bool $replacementNotified = false;

    /**
     * Date de notification du remplaçant
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read', 'replacement:admin'])]
    private ?\DateTimeImmutable $replacementNotifiedAt = null;

    /**
     * Score de compatibilité du remplaçant (0-100)
     * Calculé par l'algorithme de matching
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le score doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['replacement:read', 'replacement:admin'])]
    private ?int $matchingScore = null;

    /**
     * Métadonnées supplémentaires (historique, logs, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['replacement:admin'])]
    private ?array $metadata = [];

    /**
     * Indique si c'est un remplacement automatique
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['replacement:read', 'replacement:admin'])]
    private bool $isAutomatic = false;

    /**
     * Nombre de tentatives de recherche de remplaçant
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['replacement:admin'])]
    private int $searchAttempts = 0;

    /**
     * Date de dernière tentative de recherche
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:admin'])]
    private ?\DateTimeImmutable $lastSearchAt = null;

    /**
     * Date de création
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['replacement:read', 'replacement:list'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de dernière mise à jour
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['replacement:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
        $this->metadata = [];
    }

    // ==================== LIFECYCLE CALLBACKS ====================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        if ($this->requestedAt === null) {
            $this->requestedAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ==================== GETTERS / SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalBooking(): ?Booking
    {
        return $this->originalBooking;
    }

    public function setOriginalBooking(?Booking $originalBooking): self
    {
        $this->originalBooking = $originalBooking;
        return $this;
    }

    public function getOriginalPrestataire(): ?Prestataire
    {
        return $this->originalPrestataire;
    }

    public function setOriginalPrestataire(?Prestataire $originalPrestataire): self
    {
        $this->originalPrestataire = $originalPrestataire;
        return $this;
    }

    public function getReplacementPrestataire(): ?Prestataire
    {
        return $this->replacementPrestataire;
    }

    public function setReplacementPrestataire(?Prestataire $replacementPrestataire): self
    {
        $this->replacementPrestataire = $replacementPrestataire;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
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
        
        match ($status) {
            self::STATUS_PROPOSED => $this->proposedAt = $this->proposedAt ?? $now,
            self::STATUS_ACCEPTED => $this->acceptedAt = $this->acceptedAt ?? $now,
            self::STATUS_CONFIRMED => $this->confirmedAt = $this->confirmedAt ?? $now,
            self::STATUS_REJECTED, self::STATUS_DECLINED => $this->rejectedAt = $this->rejectedAt ?? $now,
            self::STATUS_CANCELLED => $this->cancelledAt = $this->cancelledAt ?? $now,
            default => null
        };
        
        // Ajout dans les métadonnées
        $this->addMetadata('status_change', [
            'from' => $oldStatus,
            'to' => $status,
            'at' => $now->format('Y-m-d H:i:s')
        ]);
        
        return $this;
    }

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): self
    {
        $this->requestedAt = $requestedAt;
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

    public function getProposedAt(): ?\DateTimeImmutable
    {
        return $this->proposedAt;
    }

    public function setProposedAt(?\DateTimeImmutable $proposedAt): self
    {
        $this->proposedAt = $proposedAt;
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

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): self
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): self
    {
        $this->internalNotes = $internalNotes;
        return $this;
    }

    public function getClientNotes(): ?string
    {
        return $this->clientNotes;
    }

    public function setClientNotes(?string $clientNotes): self
    {
        $this->clientNotes = $clientNotes;
        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(?string $amount): self
    {
        $this->amount = $amount;
        
        // Calcul automatique de la différence de prix
        if ($this->originalBooking && $amount !== null) {
            $originalAmount = (float) $this->originalBooking->getAmount();
            $newAmount = (float) $amount;
            $this->priceDifference = (string) ($newAmount - $originalAmount);
        }
        
        return $this;
    }

    public function getPriceDifference(): ?string
    {
        return $this->priceDifference;
    }

    public function setPriceDifference(?string $priceDifference): self
    {
        $this->priceDifference = $priceDifference;
        return $this;
    }

    public function isClientNotified(): bool
    {
        return $this->clientNotified;
    }

    public function setClientNotified(bool $clientNotified): self
    {
        $this->clientNotified = $clientNotified;
        
        if ($clientNotified && $this->clientNotifiedAt === null) {
            $this->clientNotifiedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getClientNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->clientNotifiedAt;
    }

    public function setClientNotifiedAt(?\DateTimeImmutable $clientNotifiedAt): self
    {
        $this->clientNotifiedAt = $clientNotifiedAt;
        return $this;
    }

    public function isReplacementNotified(): bool
    {
        return $this->replacementNotified;
    }

    public function setReplacementNotified(bool $replacementNotified): self
    {
        $this->replacementNotified = $replacementNotified;
        
        if ($replacementNotified && $this->replacementNotifiedAt === null) {
            $this->replacementNotifiedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getReplacementNotifiedAt(): ?\DateTimeImmutable
    {
        return $this->replacementNotifiedAt;
    }

    public function setReplacementNotifiedAt(?\DateTimeImmutable $replacementNotifiedAt): self
    {
        $this->replacementNotifiedAt = $replacementNotifiedAt;
        return $this;
    }

    public function getMatchingScore(): ?int
    {
        return $this->matchingScore;
    }

    public function setMatchingScore(?int $matchingScore): self
    {
        $this->matchingScore = $matchingScore;
        return $this;
    }

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
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function isAutomatic(): bool
    {
        return $this->isAutomatic;
    }

    public function setIsAutomatic(bool $isAutomatic): self
    {
        $this->isAutomatic = $isAutomatic;
        return $this;
    }

    public function getSearchAttempts(): int
    {
        return $this->searchAttempts;
    }

    public function setSearchAttempts(int $searchAttempts): self
    {
        $this->searchAttempts = $searchAttempts;
        return $this;
    }

    public function incrementSearchAttempts(): self
    {
        $this->searchAttempts++;
        $this->lastSearchAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastSearchAt(): ?\DateTimeImmutable
    {
        return $this->lastSearchAt;
    }

    public function setLastSearchAt(?\DateTimeImmutable $lastSearchAt): self
    {
        $this->lastSearchAt = $lastSearchAt;
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

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifie si le remplacement est en attente
     */
    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    /**
     * Vérifie si le remplacement est confirmé
     */
    public function isConfirmed(): bool
    {
        return $this->status === self::STATUS_CONFIRMED;
    }

    /**
     * Vérifie si le remplacement est rejeté/refusé
     */
    public function isRejected(): bool
    {
        return in_array($this->status, [self::STATUS_REJECTED, self::STATUS_DECLINED]);
    }

    /**
     * Vérifie si le remplacement est annulé
     */
    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    /**
     * Vérifie si le remplacement est terminé
     */
    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /**
     * Vérifie si le remplacement peut être modifié
     */
    public function canBeModified(): bool
    {
        return !in_array($this->status, [
            self::STATUS_COMPLETED,
            self::STATUS_CANCELLED,
            self::STATUS_REJECTED,
            self::STATUS_DECLINED
        ]);
    }

    /**
     * Vérifie si le remplacement est urgent
     */
    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    /**
     * Vérifie si c'est une urgence (combinaison type + priorité)
     */
    public function isEmergency(): bool
    {
        return $this->type === self::TYPE_EMERGENCY || $this->priority === self::PRIORITY_URGENT;
    }

    /**
     * Calcule le temps restant avant la réservation
     */
    public function getTimeUntilBooking(): ?\DateInterval
    {
        if (!$this->originalBooking) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $bookingDateTime = $this->originalBooking->getScheduledDate();
        
        if ($bookingDateTime > $now) {
            return $now->diff($bookingDateTime);
        }
        
        return null;
    }

    /**
     * Vérifie si le remplacement a un remplaçant assigné
     */
    public function hasReplacement(): bool
    {
        return $this->replacementPrestataire !== null;
    }

    /**
     * Obtient le statut formaté en français
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'En attente',
            self::STATUS_SEARCHING => 'Recherche en cours',
            self::STATUS_PROPOSED => 'Remplaçant proposé',
            self::STATUS_ACCEPTED => 'Accepté par le client',
            self::STATUS_CONFIRMED => 'Confirmé',
            self::STATUS_REJECTED => 'Rejeté',
            self::STATUS_DECLINED => 'Refusé par le remplaçant',
            self::STATUS_CANCELLED => 'Annulé',
            self::STATUS_COMPLETED => 'Terminé',
            default => 'Inconnu'
        };
    }

    /**
     * Obtient le type formaté en français
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_ABSENCE => 'Absence',
            self::TYPE_EMERGENCY => 'Urgence',
            self::TYPE_UNAVAILABILITY => 'Indisponibilité',
            self::TYPE_QUALITY => 'Problème de qualité',
            self::TYPE_CLIENT_REQUEST => 'Demande client',
            default => 'Autre'
        };
    }

    /**
     * Obtient la priorité formatée en français
     */
    public function getPriorityLabel(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'Basse',
            self::PRIORITY_NORMAL => 'Normale',
            self::PRIORITY_HIGH => 'Haute',
            self::PRIORITY_URGENT => 'Urgente',
            default => 'Inconnue'
        };
    }

    /**
     * Obtient une représentation textuelle
     */
    public function __toString(): string
    {
        $booking = $this->originalBooking ? ' #' . $this->originalBooking->getId() : '';
        $replacement = $this->replacementPrestataire 
            ? ' → ' . $this->replacementPrestataire->getFullName() 
            : ' (en attente)';
        
        return "Remplacement{$booking}{$replacement}";
    }

    /**
     * Liste de tous les statuts disponibles
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_SEARCHING,
            self::STATUS_PROPOSED,
            self::STATUS_ACCEPTED,
            self::STATUS_CONFIRMED,
            self::STATUS_REJECTED,
            self::STATUS_DECLINED,
            self::STATUS_CANCELLED,
            self::STATUS_COMPLETED
        ];
    }

    /**
     * Liste de tous les types disponibles
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_ABSENCE,
            self::TYPE_EMERGENCY,
            self::TYPE_UNAVAILABILITY,
            self::TYPE_QUALITY,
            self::TYPE_CLIENT_REQUEST
        ];
    }

    /**
     * Liste de toutes les priorités disponibles
     */
    public static function getAvailablePriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT
        ];
    }
}