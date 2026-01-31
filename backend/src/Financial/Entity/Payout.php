<?php
namespace App\Entity\Financial;

use Doctrine\ORM\Mapping as ORM;
use App\Entity\User\Prestataire;
use App\Financial\Repository\PayoutRepository;
#[ORM\Entity(repositoryClass: PayoutRepository::class)]
#[ORM\Table(name: 'payouts')]
class Payout
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
   private ?int $id = null;

#[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'payouts')]
#[ORM\JoinColumn(nullable: false)]
private ?Prestataire $prestataire = null;

#[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
private ?string $amount = null;

#[ORM\Column(type: 'string', length: 50)]
private string $status = 'pending'; // pending, processing, completed, failed

#[ORM\Column(type: 'string', length: 255, nullable: true)]
private ?string $stripePayoutId = null;

#[ORM\Column(type: 'date')]
private ?\DateTimeInterface $periodStart = null;

#[ORM\Column(type: 'date')]
private ?\DateTimeInterface $periodEnd = null;

#[ORM\Column(type: 'integer')]
private int $bookingsCount = 0;

#[ORM\Column(type: 'datetime_immutable')]
private ?\DateTimeImmutable $requestedAt = null;

#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $processedAt = null;

#[ORM\Column(type: 'datetime_immutable', nullable: true)]
private ?\DateTimeImmutable $completedAt = null;

#[ORM\Column(type: 'text', nullable: true)]
private ?string $failureReason = null;

public function __construct()
{
    $this->requestedAt = new \DateTimeImmutable();
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

public function getAmount(): ?string
{
    return $this->amount;
}

public function setAmount(string $amount): self
{
    $this->amount = $amount;
    return $this;
}

public function getStatus(): string
{
    return $this->status;
}

public function setStatus(string $status): self
{
    $this->status = $status;
    
    if ($status === 'completed' && !$this->completedAt) {
        $this->completedAt = new \DateTimeImmutable();
    }
    
    return $this;
}

public function getStripePayoutId(): ?string
{
    return $this->stripePayoutId;
}

public function setStripePayoutId(?string $stripePayoutId): self
{
    $this->stripePayoutId = $stripePayoutId;
    return $this;
}

public function getPeriodStart(): ?\DateTimeInterface
{
    return $this->periodStart;
}

public function setPeriodStart(\DateTimeInterface $periodStart): self
{
    $this->periodStart = $periodStart;
    return $this;
}

public function getPeriodEnd(): ?\DateTimeInterface
{
    return $this->periodEnd;
}

public function setPeriodEnd(\DateTimeInterface $periodEnd): self
{
    $this->periodEnd = $periodEnd;
    return $this;
}

public function getBookingsCount(): int
{
    return $this->bookingsCount;
}

public function setBookingsCount(int $bookingsCount): self
{
    $this->bookingsCount = $bookingsCount;
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

public function getProcessedAt(): ?\DateTimeImmutable
{
    return $this->processedAt;
}

public function setProcessedAt(?\DateTimeImmutable $processedAt): self
{
    $this->processedAt = $processedAt;
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

public function getFailureReason(): ?string
{
    return $this->failureReason;
}

public function setFailureReason(?string $failureReason): self
{
    $this->failureReason = $failureReason;
    return $this;
}
}