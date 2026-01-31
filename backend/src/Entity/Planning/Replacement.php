<?php
// src/Entity/Planning/Replacement.php

namespace App\Entity\Planning;

use App\Repository\ReplacementRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Booking;
use App\Entity\Planning\Prestataire;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReplacementRepository::class)]
#[ORM\Table(name: 'replacements')]
class Replacement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'replacements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $originalBooking = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'replacementsAsOriginal')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $originalPrestataire = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'replacementsAsReplacement')]
    #[ORM\JoinColumn(nullable: true)]
    private ?Prestataire $replacementPrestataire = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $reason = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // pending, confirmed, rejected, cancelled

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $requestedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    public function __construct()
    {
        $this->requestedAt = new \DateTimeImmutable();
    }

    // Getters and Setters

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
        
        if ($status === 'confirmed') {
            $this->confirmedAt = new \DateTimeImmutable();
        } elseif ($status === 'rejected') {
            $this->rejectedAt = new \DateTimeImmutable();
        }
        
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

    public function getRequestedAt(): ?\DateTimeImmutable
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

    public function getRejectedAt(): ?\DateTimeImmutable
    {
        return $this->rejectedAt;
    }

    public function setRejectedAt(?\DateTimeImmutable $rejectedAt): self
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
}