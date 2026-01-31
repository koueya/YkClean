<?php
// src/Financial/Entity/Commission.php

namespace App\Financial\Entity;

use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Financial\Repository\CommissionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommissionRepository::class)]
#[ORM\Table(name: 'commissions')]
class Commission
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?string $bookingAmount = null; // Montant total de la réservation

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    #[Assert\Positive]
    #[Assert\Range(min: 0, max: 100)]
    private ?string $commissionRate = null; // Taux de commission en %

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?string $commissionAmount = null; // Montant de la commission

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?string $prestataireAmount = null; // Montant pour le prestataire

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // pending, collected, cancelled

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $commissionType = 'percentage'; // percentage, fixed

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $collectedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
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

    public function getBookingAmount(): ?string
    {
        return $this->bookingAmount;
    }

    public function setBookingAmount(string $bookingAmount): self
    {
        $this->bookingAmount = $bookingAmount;
        return $this;
    }

    public function getCommissionRate(): ?string
    {
        return $this->commissionRate;
    }

    public function setCommissionRate(string $commissionRate): self
    {
        $this->commissionRate = $commissionRate;
        return $this;
    }

    public function getCommissionAmount(): ?string
    {
        return $this->commissionAmount;
    }

    public function setCommissionAmount(string $commissionAmount): self
    {
        $this->commissionAmount = $commissionAmount;
        return $this;
    }

    public function getPrestataireAmount(): ?string
    {
        return $this->prestataireAmount;
    }

    public function setPrestataireAmount(string $prestataireAmount): self
    {
        $this->prestataireAmount = $prestataireAmount;
        return $this;
    }

    /**
     * Calcule automatiquement les montants de commission
     */
    public function calculateCommission(): void
    {
        if (!$this->bookingAmount || !$this->commissionRate) {
            return;
        }

        if ($this->commissionType === 'percentage') {
            // Calcul en pourcentage
            $this->commissionAmount = bcmul(
                $this->bookingAmount,
                bcdiv($this->commissionRate, '100', 4),
                2
            );
        } elseif ($this->commissionType === 'fixed') {
            // Commission fixe
            $this->commissionAmount = $this->commissionRate;
        }

        // Montant pour le prestataire = Montant total - Commission
        $this->prestataireAmount = bcsub($this->bookingAmount, $this->commissionAmount, 2);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        
        if ($status === 'collected' && !$this->collectedAt) {
            $this->collectedAt = new \DateTimeImmutable();
        } elseif ($status === 'cancelled' && !$this->cancelledAt) {
            $this->cancelledAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getCommissionType(): ?string
    {
        return $this->commissionType;
    }

    public function setCommissionType(?string $commissionType): self
    {
        $this->commissionType = $commissionType;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCollectedAt(): ?\DateTimeImmutable
    {
        return $this->collectedAt;
    }

    public function setCollectedAt(?\DateTimeImmutable $collectedAt): self
    {
        $this->collectedAt = $collectedAt;
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

    /**
     * Retourne le pourcentage de commission effectif
     */
    public function getEffectiveCommissionPercentage(): string
    {
        if (!$this->bookingAmount || $this->bookingAmount === '0.00') {
            return '0.00';
        }

        return bcmul(
            bcdiv($this->commissionAmount, $this->bookingAmount, 4),
            '100',
            2
        );
    }
}