<?php
// src/Entity/Quote/Quote.php

namespace App\Quote\Entity;

use App\Repository\QuoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Booking\Booking;
use App\Entity\Planning\Prestataire;
use App\Entity\ServiceRequest\ServiceRequest;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection; 
#[ORM\Entity(repositoryClass: QuoteRepository::class)]
#[ORM\Table(name: 'quotes')]
#[ORM\HasLifecycleCallbacks]
class Quote
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'quotes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank]
    #[Assert\Positive]
    private ?string $amount = null;

    #[ORM\Column(type: 'datetime')]
    #[Assert\NotBlank]
    private ?\DateTime $proposedDate = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private ?int $proposedDuration = null; // en minutes

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    private ?string $description = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $conditions = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'pending'; // pending, accepted, rejected, expired

    #[ORM\Column(type: 'datetime')]
    private ?\DateTime $validUntil = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $rejectedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $rejectionReason = null;

    #[ORM\OneToOne(mappedBy: 'quote', targetEntity: Booking::class, cascade: ['persist'])]
    private ?Booking $booking = null;
    
    #[ORM\OneToMany(mappedBy: 'quote', targetEntity: QuoteItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['displayOrder' => 'ASC'])]
    private Collection $items; 

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->setValidUntil(3); // Valide 3 jours par défaut
        $this->items = new ArrayCollection();

    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceRequest(): ?ServiceRequest
    {
        return $this->serviceRequest;
    }

    public function setServiceRequest(?ServiceRequest $serviceRequest): self
    {
        $this->serviceRequest = $serviceRequest;
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

    public function getProposedDate(): ?\DateTime
    {
        return $this->proposedDate;
    }

    public function setProposedDate(\DateTime $proposedDate): self
    {
        $this->proposedDate = $proposedDate;
        return $this;
    }

    public function getProposedDuration(): ?int
    {
        return $this->proposedDuration;
    }

    public function setProposedDuration(int $proposedDuration): self
    {
        $this->proposedDuration = $proposedDuration;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function setConditions(?string $conditions): self
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        
        if ($status === 'accepted') {
            $this->acceptedAt = new \DateTimeImmutable();
        } elseif ($status === 'rejected') {
            $this->rejectedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getValidUntil(): ?\DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(int $days): self
    {
        $this->validUntil = (new \DateTime())->modify("+{$days} days");
        return $this;
    }

    public function isExpired(): bool
    {
        return $this->validUntil && $this->validUntil < new \DateTime();
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

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): self
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        // unset the owning side of the relation if necessary
        if ($booking === null && $this->booking !== null) {
            $this->booking->setQuote(null);
        }

        // set the owning side of the relation if necessary
        if ($booking !== null && $booking->getQuote() !== $this) {
            $booking->setQuote($this);
        }

        $this->booking = $booking;

        return $this;
    }
     /**
     * @return Collection<int, QuoteItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(QuoteItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setQuote($this);
        }

        return $this;
    }

    public function removeItem(QuoteItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getQuote() === $this) {
                $item->setQuote(null);
            }
        }

        return $this;
    }

    /**
     * Calcule le montant total du devis à partir des items
     */
    public function calculateTotalFromItems(): void
    {
        $total = '0.00';
        
        foreach ($this->items as $item) {
            if (!$item->isOptional()) {
                $total = bcadd($total, $item->getTotalPrice(), 2);
            }
        }

        $this->amount = $total;
    }

    /**
     * Calcule la durée totale du devis
     */
    public function calculateTotalDuration(): int
    {
        $totalDuration = 0;

        foreach ($this->items as $item) {
            if (!$item->isOptional() && $item->getTotalEstimatedDuration()) {
                $totalDuration += $item->getTotalEstimatedDuration();
            }
        }

        return $totalDuration;
    }

    /**
     * Retourne les items obligatoires
     */
    public function getMandatoryItems(): Collection
    {
        return $this->items->filter(fn(QuoteItem $item) => !$item->isOptional());
    }

    /**
     * Retourne les items optionnels
     */
    public function getOptionalItems(): Collection
    {
        return $this->items->filter(fn(QuoteItem $item) => $item->isOptional());
    }

    /**
     * Calcule le montant total avec les options
     */
    public function getTotalWithOptions(): string
    {
        $total = '0.00';
        
        foreach ($this->items as $item) {
            $total = bcadd($total, $item->getTotalPrice(), 2);
        }

        return $total;
    }
}