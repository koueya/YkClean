<?php
// src/Entity/Planning/Absence.php

namespace App\Entity\Planning;

use App\Entity\User\Admin;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use App\Repository\Planning\AbsenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une période d'absence/indisponibilité d'un prestataire
 * Peut nécessiter des remplacements pour les réservations existantes
 */
#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Table(name: 'absences')]
#[ORM\Index(columns: ['prestataire_id', 'start_date', 'end_date'], name: 'idx_prestataire_dates')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['type'], name: 'idx_type')]
#[ORM\Index(columns: ['requires_replacement'], name: 'idx_requires_replacement')]
#[ORM\HasLifecycleCallbacks]
class Absence
{
    // ===== CONSTANTES - TYPES D'ABSENCE =====
    
    public const TYPE_CONGES = 'congés';
    public const TYPE_MALADIE = 'maladie';
    public const TYPE_URGENCE = 'urgence';
    public const TYPE_FORMATION = 'formation';
    public const TYPE_PERSONNEL = 'personnel';
    public const TYPE_AUTRE = 'autre';
    
    public const TYPES = [
        self::TYPE_CONGES,
        self::TYPE_MALADIE,
        self::TYPE_URGENCE,
        self::TYPE_FORMATION,
        self::TYPE_PERSONNEL,
        self::TYPE_AUTRE,
    ];

    // ===== CONSTANTES - STATUTS =====
    
    public const STATUS_PENDING = 'pending';
    public const STATUS_APPROVED = 'approved';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ACTIVE = 'active';
    
    public const STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_APPROVED,
        self::STATUS_REJECTED,
        self::STATUS_CANCELLED,
        self::STATUS_ACTIVE,
    ];

    // ===== PROPRIÉTÉS DE BASE =====
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['absence:read', 'absence:list'])]
    private ?int $id = null;

    /**
     * Prestataire concerné par l'absence
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'absences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire')]
    #[Groups(['absence:read'])]
    private ?Prestataire $prestataire = null;

    /**
     * Date de début de l'absence
     */
    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'La date de début est obligatoire')]
    #[Groups(['absence:read', 'absence:list'])]
    private ?\DateTimeInterface $startDate = null;

    /**
     * Date de fin de l'absence
     */
    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire')]
    #[Assert\GreaterThanOrEqual(
        propertyPath: 'startDate',
        message: 'La date de fin doit être après ou égale à la date de début'
    )]
    #[Groups(['absence:read', 'absence:list'])]
    private ?\DateTimeInterface $endDate = null;

    // ===== TYPE ET RAISON =====
    
    /**
     * Type d'absence (congés, maladie, urgence, etc.)
     */
    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le type d\'absence est obligatoire')]
    #[Assert\Choice(
        choices: self::TYPES,
        message: 'Type d\'absence invalide'
    )]
    #[Groups(['absence:read', 'absence:list'])]
    private string $type = self::TYPE_CONGES;

    /**
     * Raison courte de l'absence
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['absence:read', 'absence:list'])]
    private ?string $reason = null;

    /**
     * Description détaillée de l'absence
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['absence:read'])]
    private ?string $description = null;

    // ===== STATUT ET WORKFLOW =====
    
    /**
     * Statut de l'absence
     */
    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\Choice(
        choices: self::STATUSES,
        message: 'Statut invalide'
    )]
    #[Groups(['absence:read', 'absence:list'])]
    private string $status = self::STATUS_PENDING;

    /**
     * Indique si cette absence nécessite de trouver des remplacements
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups(['absence:read', 'absence:list'])]
    private bool $requiresReplacement = false;

    /**
     * Indique si c'est un congé payé
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups(['absence:read'])]
    private bool $isPaid = false;

    // ===== APPROBATION =====
    
    /**
     * Admin qui a approuvé/rejeté l'absence
     */
    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['absence:read'])]
    private ?Admin $approvedBy = null;

    /**
     * Date d'approbation
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['absence:read'])]
    private ?\DateTimeInterface $approvedAt = null;

    /**
     * Date de rejet
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['absence:read'])]
    private ?\DateTimeInterface $rejectedAt = null;

    /**
     * Raison du rejet
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['absence:read'])]
    private ?string $rejectionReason = null;

    // ===== NOTES INTERNES =====
    
    /**
     * Notes internes (visibles uniquement par les admins)
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['absence:admin'])]
    private ?string $internalNotes = null;

    // ===== PIÈCES JUSTIFICATIVES =====
    
    /**
     * Documents justificatifs (arrêt maladie, etc.)
     * Stockés sous forme de tableau de chemins de fichiers
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['absence:read'])]
    private ?array $attachments = null;

    // ===== IMPACT SUR LES RÉSERVATIONS =====
    
    /**
     * Nombre de réservations affectées par cette absence
     */
    #[ORM\Column(type: 'integer')]
    #[Groups(['absence:read'])]
    private int $affectedBookingsCount = 0;

    /**
     * Nombre de remplacements trouvés
     */
    #[ORM\Column(type: 'integer')]
    #[Groups(['absence:read'])]
    private int $replacementsFoundCount = 0;

    // ===== DATES DE SUIVI =====
    
    /**
     * Date de création de l'absence
     */
    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['absence:read'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de dernière modification
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['absence:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Date d'annulation
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['absence:read'])]
    private ?\DateTimeImmutable $cancelledAt = null;

    /**
     * Raison de l'annulation
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['absence:read'])]
    private ?string $cancellationReason = null;

    // ===== NOTIFICATIONS =====
    
    /**
     * Indique si le prestataire a été notifié de l'approbation
     */
    #[ORM\Column(type: 'boolean')]
    private bool $prestataireNotified = false;

    /**
     * Indique si les clients concernés ont été notifiés
     */
    #[ORM\Column(type: 'boolean')]
    private bool $clientsNotified = false;

    // ===== RELATIONS =====
    
    /**
     * Réservations affectées par cette absence
     * @var Collection<int, Booking>
     */
    #[ORM\ManyToMany(targetEntity: Booking::class)]
    #[ORM\JoinTable(name: 'absence_affected_bookings')]
    private Collection $affectedBookings;

    // ===== CONSTRUCTEUR =====
    
    public function __construct()
    {
        $this->affectedBookings = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->status = self::STATUS_PENDING;
        $this->type = self::TYPE_CONGES;
        $this->requiresReplacement = false;
        $this->isPaid = false;
        $this->affectedBookingsCount = 0;
        $this->replacementsFoundCount = 0;
        $this->prestataireNotified = false;
        $this->clientsNotified = false;
        $this->attachments = [];
    }

    // ===== LIFECYCLE CALLBACKS =====
    
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ===== GETTERS & SETTERS - BASE =====

    public function getId(): ?int
    {
        return $this->id;
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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    // ===== GETTERS & SETTERS - TYPE ET RAISON =====

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        if (!in_array($type, self::TYPES, true)) {
            throw new \InvalidArgumentException('Type d\'absence invalide');
        }
        
        $this->type = $type;
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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    // ===== GETTERS & SETTERS - STATUT =====

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        if (!in_array($status, self::STATUSES, true)) {
            throw new \InvalidArgumentException('Statut invalide');
        }
        
        $this->status = $status;
        return $this;
    }

    public function requiresReplacement(): bool
    {
        return $this->requiresReplacement;
    }

    public function setRequiresReplacement(bool $requiresReplacement): self
    {
        $this->requiresReplacement = $requiresReplacement;
        return $this;
    }

    public function isPaid(): bool
    {
        return $this->isPaid;
    }

    public function setIsPaid(bool $isPaid): self
    {
        $this->isPaid = $isPaid;
        return $this;
    }

    // ===== GETTERS & SETTERS - APPROBATION =====

    public function getApprovedBy(): ?Admin
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?Admin $approvedBy): self
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeInterface
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeInterface $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getRejectedAt(): ?\DateTimeInterface
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeInterface $rejectedAt): self
    {
        $this->rejectedAt = $rejectedAt;
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

    // ===== GETTERS & SETTERS - NOTES =====

    public function getInternalNotes(): ?string
    {
        return $this->internalNotes;
    }

    public function setInternalNotes(?string $internalNotes): self
    {
        $this->internalNotes = $internalNotes;
        return $this;
    }

    // ===== GETTERS & SETTERS - PIÈCES JUSTIFICATIVES =====

    public function getAttachments(): ?array
    {
        return $this->attachments ?? [];
    }

    public function setAttachments(?array $attachments): self
    {
        $this->attachments = $attachments;
        return $this;
    }

    public function addAttachment(string $filePath): self
    {
        if (!in_array($filePath, $this->getAttachments(), true)) {
            $attachments = $this->getAttachments();
            $attachments[] = $filePath;
            $this->attachments = $attachments;
        }
        return $this;
    }

    public function removeAttachment(string $filePath): self
    {
        $attachments = $this->getAttachments();
        $key = array_search($filePath, $attachments, true);
        if ($key !== false) {
            unset($attachments[$key]);
            $this->attachments = array_values($attachments);
        }
        return $this;
    }

    public function hasAttachments(): bool
    {
        return !empty($this->getAttachments());
    }

    // ===== GETTERS & SETTERS - IMPACT =====

    public function getAffectedBookingsCount(): int
    {
        return $this->affectedBookingsCount;
    }

    public function setAffectedBookingsCount(int $affectedBookingsCount): self
    {
        $this->affectedBookingsCount = $affectedBookingsCount;
        return $this;
    }

    public function getReplacementsFoundCount(): int
    {
        return $this->replacementsFoundCount;
    }

    public function setReplacementsFoundCount(int $replacementsFoundCount): self
    {
        $this->replacementsFoundCount = $replacementsFoundCount;
        return $this;
    }

    public function incrementReplacementsFoundCount(): self
    {
        $this->replacementsFoundCount++;
        return $this;
    }

    // ===== GETTERS & SETTERS - DATES =====

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

    public function getCancelledAt(): ?\DateTimeImmutable
    {
        return $this->cancelledAt;
    }

    public function setCancelledAt(?\DateTimeImmutable $cancelledAt): self
    {
        $this->cancelledAt = $cancelledAt;
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

    // ===== GETTERS & SETTERS - NOTIFICATIONS =====

    public function isPrestataireNotified(): bool
    {
        return $this->prestataireNotified;
    }

    public function setPrestataireNotified(bool $prestataireNotified): self
    {
        $this->prestataireNotified = $prestataireNotified;
        return $this;
    }

    public function areClientsNotified(): bool
    {
        return $this->clientsNotified;
    }

    public function setClientsNotified(bool $clientsNotified): self
    {
        $this->clientsNotified = $clientsNotified;
        return $this;
    }

    // ===== RELATIONS - AFFECTED BOOKINGS =====

    /**
     * @return Collection<int, Booking>
     */
    public function getAffectedBookings(): Collection
    {
        return $this->affectedBookings;
    }

    public function addAffectedBooking(Booking $booking): self
    {
        if (!$this->affectedBookings->contains($booking)) {
            $this->affectedBookings->add($booking);
            $this->affectedBookingsCount = $this->affectedBookings->count();
        }
        return $this;
    }

    public function removeAffectedBooking(Booking $booking): self
    {
        if ($this->affectedBookings->removeElement($booking)) {
            $this->affectedBookingsCount = $this->affectedBookings->count();
        }
        return $this;
    }

    // ===== MÉTHODES DE WORKFLOW =====

    /**
     * Approuve l'absence
     */
    public function approve(Admin $admin): self
    {
        $this->status = self::STATUS_APPROVED;
        $this->approvedBy = $admin;
        $this->approvedAt = new \DateTime();
        $this->rejectedAt = null;
        $this->rejectionReason = null;
        return $this;
    }

    /**
     * Rejette l'absence
     */
    public function reject(Admin $admin, ?string $reason = null): self
    {
        $this->status = self::STATUS_REJECTED;
        $this->approvedBy = $admin;
        $this->rejectedAt = new \DateTime();
        $this->rejectionReason = $reason;
        $this->approvedAt = null;
        return $this;
    }

    /**
     * Annule l'absence
     */
    public function cancel(?string $reason = null): self
    {
        $this->status = self::STATUS_CANCELLED;
        $this->cancelledAt = new \DateTimeImmutable();
        $this->cancellationReason = $reason;
        return $this;
    }

    /**
     * Active l'absence (après approbation)
     */
    public function activate(): self
    {
        if ($this->status !== self::STATUS_APPROVED) {
            throw new \LogicException('Seules les absences approuvées peuvent être activées');
        }
        
        $this->status = self::STATUS_ACTIVE;
        return $this;
    }

    // ===== MÉTHODES DE VÉRIFICATION D'ÉTAT =====

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isApproved(): bool
    {
        return $this->status === self::STATUS_APPROVED;
    }

    public function isRejected(): bool
    {
        return $this->status === self::STATUS_REJECTED;
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    // ===== MÉTHODES UTILITAIRES =====

    /**
     * Calcule la durée de l'absence en jours
     */
    public function getDurationInDays(): int
    {
        if (!$this->startDate || !$this->endDate) {
            return 0;
        }

        $diff = $this->startDate->diff($this->endDate);
        return $diff->days + 1; // +1 pour inclure le dernier jour
    }

    /**
     * Vérifie si l'absence est en cours
     */
    public function isOngoing(): bool
    {
        if (!$this->isActive() && !$this->isApproved()) {
            return false;
        }

        $now = new \DateTime();
        return $this->startDate <= $now && $this->endDate >= $now;
    }

    /**
     * Vérifie si l'absence est future
     */
    public function isFuture(): bool
    {
        $now = new \DateTime();
        return $this->startDate > $now;
    }

    /**
     * Vérifie si l'absence est passée
     */
    public function isPast(): bool
    {
        $now = new \DateTime();
        return $this->endDate < $now;
    }

    /**
     * Vérifie si une date donnée est couverte par cette absence
     */
    public function coversDate(\DateTimeInterface $date): bool
    {
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    /**
     * Vérifie si cette absence chevauche une autre période
     */
    public function overlaps(\DateTimeInterface $otherStart, \DateTimeInterface $otherEnd): bool
    {
        return $this->startDate <= $otherEnd && $this->endDate >= $otherStart;
    }

    /**
     * Retourne le pourcentage de remplacements trouvés
     */
    public function getReplacementRate(): float
    {
        if ($this->affectedBookingsCount === 0) {
            return 100.0;
        }

        return ($this->replacementsFoundCount / $this->affectedBookingsCount) * 100;
    }

    /**
     * Vérifie si tous les remplacements ont été trouvés
     */
    public function hasAllReplacements(): bool
    {
        return $this->requiresReplacement 
            && $this->affectedBookingsCount > 0
            && $this->replacementsFoundCount === $this->affectedBookingsCount;
    }

    /**
     * Retourne une description lisible de l'absence
     */
    public function getDescriptionAbscence(): string
    {
        $duration = $this->getDurationInDays();
        $durationText = $duration === 1 ? '1 jour' : "{$duration} jours";
        
        return sprintf(
            '%s - %s (%s) - %s',
            $this->startDate->format('d/m/Y'),
            $this->endDate->format('d/m/Y'),
            $durationText,
            $this->getTypeLabel()
        );
    }

    /**
     * Retourne le libellé du type d'absence
     */
    public function getTypeLabel(): string
    {
        $labels = [
            self::TYPE_CONGES => 'Congés',
            self::TYPE_MALADIE => 'Maladie',
            self::TYPE_URGENCE => 'Urgence',
            self::TYPE_FORMATION => 'Formation',
            self::TYPE_PERSONNEL => 'Personnel',
            self::TYPE_AUTRE => 'Autre',
        ];

        return $labels[$this->type] ?? $this->type;
    }

    /**
     * Retourne le libellé du statut
     */
    public function getStatusLabel(): string
    {
        $labels = [
            self::STATUS_PENDING => 'En attente',
            self::STATUS_APPROVED => 'Approuvée',
            self::STATUS_REJECTED => 'Rejetée',
            self::STATUS_CANCELLED => 'Annulée',
            self::STATUS_ACTIVE => 'Active',
        ];

        return $labels[$this->status] ?? $this->status;
    }

    public function __toString(): string
    {
        return $this->getDescription();
    }
}