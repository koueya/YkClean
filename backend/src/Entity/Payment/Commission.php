<?php
// src/Entity/Payment/Commission.php

namespace App\Payment\Entity;

use App\Repository\CommissionRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Booking;
use App\Entity\Prestataire;
use App\Entity\User\User;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommissionRepository::class)]
#[ORM\Table(name: 'commissions')]
#[ORM\HasLifecycleCallbacks]
class Commission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'commissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $bookingAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Range(min: 0, max: 100)]
    private string $commissionRate = '0.00'; // Taux en pourcentage

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $commissionAmount = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\PositiveOrZero]
    private string $prestataireAmount = '0.00'; // Montant revenant au prestataire

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // pending, calculated, paid, cancelled

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $type = 'standard'; // standard, promotional, special

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $dueDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $paidDate = null;

    #[ORM\Column(type: 'string', length: 100, unique: true)]
    private ?string $referenceNumber = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->generateReferenceNumber();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function prePersist(): void
    {
        if (!$this->referenceNumber) {
            $this->generateReferenceNumber();
        }
    }

    private function generateReferenceNumber(): void
    {
        $this->referenceNumber = 'COM-' . strtoupper(uniqid());
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;
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

    public function getBookingAmount(): string
    {
        return $this->bookingAmount;
    }

    public function setBookingAmount(string $bookingAmount): self
    {
        $this->bookingAmount = $bookingAmount;
        $this->calculateCommission();
        return $this;
    }

    public function getCommissionRate(): string
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(string $commissionRate): self
    {
        $this->commissionRate = $commissionRate;
        $this->calculateCommission();
        return $this;
    }

    public function getCommissionAmount(): string
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(string $commissionAmount): self
    {
        $this->commissionAmount = $commissionAmount;
        return $this;
    }

    public function getPrestataireAmount(): string
    {
        return $this->prestataireAmount;
    }

    public function setPrestataireAmount(string $prestataireAmount): self
    {
        $this->prestataireAmount = $prestataireAmount;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'pending' => 'En attente',
            'calculated' => 'Calculée',
            'paid' => 'Payée',
            'cancelled' => 'Annulée',
            default => $this->status,
        };
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getTypeLabel(): string
    {
        return match($this->type) {
            'standard' => 'Standard',
            'promotional' => 'Promotionnelle',
            'special' => 'Spéciale',
            default => $this->type ?? 'Standard',
        };
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPaidDate(): ?\DateTimeInterface
    {
        return $this->paidDate;
    }

    public function setPaidDate(?\DateTimeInterface $paidDate): self
    {
        $this->paidDate = $paidDate;
        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;
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

    public function getMetadataValue(string $key): mixed
    {
        $metadata = $this->getMetadata();
        return $metadata[$key] ?? null;
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

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Calcule automatiquement la commission et le montant prestataire
     */
    public function calculateCommission(): void
    {
        // Commission = montant réservation * taux / 100
        $rate = bcdiv($this->commissionRate, '100', 4);
        $this->commissionAmount = bcmul(
            (string)$this->bookingAmount,
            $rate,
            2
        );

        // Montant prestataire = montant réservation - commission
        $this->prestataireAmount = bcsub(
            (string)$this->bookingAmount,
            (string)$this->commissionAmount,
            2
        );
    }

    /**
     * Marque la commission comme payée
     */
    public function markAsPaid(\DateTimeInterface $paidDate = null): self
    {
        $this->status = 'paid';
        $this->paidDate = $paidDate ?? new \DateTime();
        return $this;
    }

    /**
     * Annule la commission
     */
    public function cancel(): self
    {
        $this->status = 'cancelled';
        return $this;
    }

    /**
     * Vérifie si la commission est en retard
     */
    public function isOverdue(): bool
    {
        if (!$this->dueDate || $this->status === 'paid' || $this->status === 'cancelled') {
            return false;
        }

        return $this->dueDate < new \DateTime();
    }

    /**
     * Calcule le nombre de jours de retard
     */
    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $now = new \DateTime();
        return $this->dueDate->diff($now)->days;
    }

    /**
     * Vérifie si la commission peut être modifiée
     */
    public function canBeModified(): bool
    {
        return in_array($this->status, ['pending', 'calculated']);
    }

    /**
     * Vérifie si la commission peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return $this->status !== 'cancelled' && $this->status !== 'paid';
    }

    /**
     * Retourne le taux de commission formaté
     */
    public function getFormattedRate(): string
    {
        return $this->commissionRate . '%';
    }

    /**
     * Retourne un résumé de la commission
     */
    public function getSummary(): string
    {
        return sprintf(
            'Commission %s - %s€ sur %s€ (%s%%)',
            $this->referenceNumber,
            $this->commissionAmount,
            $this->bookingAmount,
            $this->commissionRate
        );
    }

    public function __toString(): string
    {
        return $this->referenceNumber ?? 'Commission';
    }
}