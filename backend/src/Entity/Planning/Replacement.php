<?php

namespace App\Entity\Planning;

use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: 'App\Repository\Planning\ReplacementRepository')]
#[ORM\Table(name: 'replacements')]
class Replacement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['replacement:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'replacements')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['replacement:read'])]
    private Booking $booking;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['replacement:read'])]
    private Prestataire $originalPrestataire;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['replacement:read'])]
    private ?Prestataire $replacementPrestataire = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Groups(['replacement:read'])]
    private string $reason;

    #[ORM\Column(type: 'string', length: 20)]
    #[Groups(['replacement:read'])]
    private string $status; // pending, accepted, declined, cancelled

    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['replacement:read'])]
    private \DateTimeImmutable $requestedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['replacement:read'])]
    private ?\DateTimeImmutable $proposedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['replacement:read'])]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['replacement:read'])]
    private ?\DateTimeImmutable $declinedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['replacement:read'])]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['replacement:read'])]
    private ?string $declineReason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['replacement:read'])]
    private ?string $notes = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
        $this->status = 'pending';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getBooking(): Booking
    {
        return $this->booking;
    }

    public function setBooking(Booking $booking): self
    {
        $this->booking = $booking;
        return $this;
    }

    public function getOriginalPrestataire(): Prestataire
    {
        return $this->originalPrestataire;
    }

    public function setOriginalPrestataire(Prestataire $originalPrestataire): self
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

    public function getReason(): string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
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

    public function getRequestedAt(): \DateTimeImmutable
    {
        return $this->requestedAt;
    }

    public function setRequestedAt(\DateTimeImmutable $requestedAt): self
    {
        $this->requestedAt = $requestedAt;
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

    public function getDeclinedAt(): ?\DateTimeImmutable
    {
        return $this->declinedAt;
    }

    public function setDeclinedAt(?\DateTimeImmutable $declinedAt): self
    {
        $this->declinedAt = $declinedAt;
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

    public function getDeclineReason(): ?string
    {
        return $this->declineReason;
    }

    public function setDeclineReason(?string $declineReason): self
    {
        $this->declineReason = $declineReason;
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

    // Méthodes utilitaires

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isDeclined(): bool
    {
        return $this->status === 'declined';
    }

    public function isCancelled(): bool
    {
        return $this->status === 'cancelled';
    }

    public function hasReplacementPrestataire(): bool
    {
        return $this->replacementPrestataire !== null;
    }
}