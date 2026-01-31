<?php
// src/Finance/Entity/PrestataireEarning.php

namespace App\Finance\Entity;

use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Finance\Entity\Payout;
use App\Financial\Repository\PrestataireEarningRepository;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PrestataireEarningRepository::class)]
#[ORM\Table(name: 'prestataire_earnings')]
class PrestataireEarning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'earnings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $grossAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $commissionAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $netAmount = null;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private ?string $commissionRate = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // pending, available, paid

    #[ORM\ManyToOne(targetEntity: Payout::class)]
    private ?Payout $payout = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $earnedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $availableAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    public function __construct()
    {
        $this->earnedAt = new \DateTimeImmutable();
        // Disponible après 5 jours par défaut
        $this->availableAt = (new \DateTimeImmutable())->modify('+5 days');
    }

    // Getters and Setters

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

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;
        return $this;
    }

    public function getGrossAmount(): ?string
    {
        return $this->grossAmount;
    }

    public function setGrossAmount(string $grossAmount): self
    {
        $this->grossAmount = $grossAmount;
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

    public function getNetAmount(): ?string
    {
        return $this->netAmount;
    }

    public function setNetAmount(string $netAmount): self
    {
        $this->netAmount = $netAmount;
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

    public function calculateAmounts(string $grossAmount, string $commissionRate): void
    {
        $this->grossAmount = $grossAmount;
        $this->commissionRate = $commissionRate;
        $this->commissionAmount = bcmul($grossAmount, bcdiv($commissionRate, '100', 4), 2);
        $this->netAmount = bcsub($grossAmount, $this->commissionAmount, 2);
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        
        if ($status === 'paid' && !$this->paidAt) {
            $this->paidAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getPayout(): ?Payout
    {
        return $this->payout;
    }

    public function setPayout(?Payout $payout): self
    {
        $this->payout = $payout;
        return $this;
    }

    public function getEarnedAt(): ?\DateTimeImmutable
    {
        return $this->earnedAt;
    }

    public function setEarnedAt(\DateTimeImmutable $earnedAt): self
    {
        $this->earnedAt = $earnedAt;
        return $this;
    }

    public function getAvailableAt(): ?\DateTimeImmutable
    {
        return $this->availableAt;
    }

    public function setAvailableAt(?\DateTimeImmutable $availableAt): self
    {
        $this->availableAt = $availableAt;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->availableAt && $this->availableAt <= new \DateTimeImmutable();
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
        return $this;
    }
}