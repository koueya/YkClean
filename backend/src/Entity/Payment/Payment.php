<?php
// src/Entity/Payment/Payment.php

namespace App\Payment\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Booking;
use App\Entity\Client;
use App\Entity\Prestataire;
use App\Entity\Payment\Refund;
use App\Entity\Payment\Commission;
use App\Entity\User\User;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
#[ORM\Table(name: 'payments')]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'payment', targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?string $amount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $commissionAmount = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $netAmount = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $paymentMethod = null; // card, sepa, wallet

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // pending, processing, completed, failed, refunded

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripePaymentId = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $stripePaymentIntentId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $failureReason = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\OneToOne(mappedBy: 'payment', targetEntity: Refund::class, cascade: ['persist'])]
    private ?Refund $refund = null;

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

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): self
    {
        $this->amount = $amount;
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

    public function calculateCommission(float $commissionRate): void
    {
        $this->commissionAmount = bcmul($this->amount, (string)($commissionRate / 100), 2);
        $this->netAmount = bcsub($this->amount, $this->commissionAmount, 2);
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        
        if ($status === 'completed' && !$this->paidAt) {
            $this->paidAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getStripePaymentId(): ?string
    {
        return $this->stripePaymentId;
    }

    public function setStripePaymentId(?string $stripePaymentId): self
    {
        $this->stripePaymentId = $stripePaymentId;
        return $this;
    }

    public function getStripePaymentIntentId(): ?string
    {
        return $this->stripePaymentIntentId;
    }

    public function setStripePaymentIntentId(?string $stripePaymentIntentId): self
    {
        $this->stripePaymentIntentId = $stripePaymentIntentId;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;
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

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): self
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getRefund(): ?Refund
    {
        return $this->refund;
    }

    public function setRefund(?Refund $refund): self
    {
        // unset the owning side of the relation if necessary
        if ($refund === null && $this->refund !== null) {
            $this->refund->setPayment(null);
        }

        // set the owning side of the relation if necessary
        if ($refund !== null && $refund->getPayment() !== $this) {
            $refund->setPayment($this);
        }

        $this->refund = $refund;

        return $this;
    }
}