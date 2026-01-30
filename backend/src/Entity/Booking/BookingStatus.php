<?php
// src/Entity/Booking/BookingStatus.php

namespace App\Entity\Booking;

use App\Repository\BookingStatusRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BookingStatusRepository::class)]
#[ORM\Table(name: 'booking_statuses')]
class BookingStatus
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $oldStatus = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $newStatus = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $changedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $reason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $comment = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $changedAt = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $userAgent = null;

    public function __construct()
    {
        $this->changedAt = new \DateTimeImmutable();
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

    public function getOldStatus(): ?string
    {
        return $this->oldStatus;
    }

    public function setOldStatus(?string $oldStatus): self
    {
        $this->oldStatus = $oldStatus;
        return $this;
    }

    public function getOldStatusLabel(): string
    {
        return $this->getStatusLabel($this->oldStatus);
    }

    public function getNewStatus(): ?string
    {
        return $this->newStatus;
    }

    public function setNewStatus(string $newStatus): self
    {
        $this->newStatus = $newStatus;
        return $this;
    }

    public function getNewStatusLabel(): string
    {
        return $this->getStatusLabel($this->newStatus);
    }

    private function getStatusLabel(?string $status): string
    {
        return match($status) {
            'scheduled' => 'Planifié',
            'confirmed' => 'Confirmé',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            'pending' => 'En attente',
            'rescheduled' => 'Reporté',
            default => $status ?? 'Inconnu',
        };
    }

    public function getChangedBy(): ?User
    {
        return $this->changedBy;
    }

    public function setChangedBy(?User $changedBy): self
    {
        $this->changedBy = $changedBy;
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

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
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

    public function getChangedAt(): ?\DateTimeImmutable
    {
        return $this->changedAt;
    }

    public function setChangedAt(\DateTimeImmutable $changedAt): self
    {
        $this->changedAt = $changedAt;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): self
    {
        $this->userAgent = $userAgent;
        return $this;
    }

    /**
     * Retourne une description complète du changement
     */
    public function getChangeDescription(): string
    {
        $description = sprintf(
            'Changement de statut de "%s" à "%s"',
            $this->getOldStatusLabel(),
            $this->getNewStatusLabel()
        );

        if ($this->changedBy) {
            $description .= sprintf(
                ' par %s',
                $this->changedBy->getFullName()
            );
        }

        if ($this->reason) {
            $description .= sprintf(' - Raison: %s', $this->reason);
        }

        return $description;
    }

    /**
     * Vérifie si le changement est une annulation
     */
    public function isCancellation(): bool
    {
        return $this->newStatus === 'cancelled';
    }

    /**
     * Vérifie si le changement est une confirmation
     */
    public function isConfirmation(): bool
    {
        return $this->newStatus === 'confirmed';
    }

    /**
     * Vérifie si le changement est une complétion
     */
    public function isCompletion(): bool
    {
        return $this->newStatus === 'completed';
    }

    /**
     * Vérifie si le changement est un report
     */
    public function isRescheduling(): bool
    {
        return $this->newStatus === 'rescheduled';
    }

    /**
     * Retourne le temps écoulé depuis le changement
     */
    public function getTimeSinceChange(): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->changedAt);

        if ($diff->y > 0) {
            return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        } elseif ($diff->m > 0) {
            return $diff->m . ' mois';
        } elseif ($diff->d > 0) {
            return $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
        } elseif ($diff->h > 0) {
            return $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        } elseif ($diff->i > 0) {
            return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '');
        } else {
            return 'À l\'instant';
        }
    }
}