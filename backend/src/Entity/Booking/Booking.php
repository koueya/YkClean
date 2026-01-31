<?php
// src/Entity/Booking/Booking.php

namespace App\Entity\Booking;

use App\Repository\BookingRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\Client\Client;
use App\Entity\Prestataire\Prestataire;
use App\Entity\ServiceRequest\ServiceRequest;
use App\Entity\Quote\Quote;
use App\Entity\User\User;
use App\Entity\Payment\Payment;
use App\Entity\Review\Review;
use App\Entity\Booking\Recurrence;
use App\Entity\Booking\Replacement;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BookingRepository::class)]
#[ORM\Table(name: 'bookings')]
#[ORM\HasLifecycleCallbacks]
class Booking
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'booking', targetEntity: Quote::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Quote $quote = null;

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceRequest $serviceRequest = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'bookings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $scheduledDate = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $scheduledTime = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private ?int $duration = null; // en minutes

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $address = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $city = null;

    #[ORM\Column(type: 'string', length: 10)]
    private ?string $postalCode = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\Positive]
    private ?string $amount = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'scheduled'; // scheduled, confirmed, in_progress, completed, cancelled

    #[ORM\ManyToOne(targetEntity: Recurrence::class, inversedBy: 'bookings')]
    private ?Recurrence $recurrence = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $actualStartTime = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $actualEndTime = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $completionNotes = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $cancellationReason = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $clientInstructions = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $prestataireNotes = [];

    #[ORM\Column(type: 'boolean')]
    private bool $clientPresent = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $accessInstructions = null; // Instructions d'accès (code porte, etc.)

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $accessCode = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $confirmedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $completedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $cancelledAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $cancelledBy = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Payment::class, cascade: ['persist'])]
    private ?Payment $payment = null;

    #[ORM\OneToOne(mappedBy: 'booking', targetEntity: Review::class, cascade: ['persist'])]
    private ?Review $review = null;

    #[ORM\OneToMany(mappedBy: 'originalBooking', targetEntity: Replacement::class, cascade: ['persist'])]
    private Collection $replacements;

    #[ORM\Column(type: 'boolean')]
    private bool $reminderSent24h = false;

    #[ORM\Column(type: 'boolean')]
    private bool $reminderSent2h = false;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $referenceNumber = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->replacements = new ArrayCollection();
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

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuote(): ?Quote
    {
        return $this->quote;
    }

    public function setQuote(?Quote $quote): self
    {
        $this->quote = $quote;
        return $this;
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

    public function getScheduledDate(): ?\DateTimeInterface
    {
        return $this->scheduledDate;
    }

    public function setScheduledDate(\DateTimeInterface $scheduledDate): self
    {
        $this->scheduledDate = $scheduledDate;
        return $this;
    }

    public function getScheduledTime(): ?\DateTimeInterface
    {
        return $this->scheduledTime;
    }

    public function setScheduledTime(\DateTimeInterface $scheduledTime): self
    {
        $this->scheduledTime = $scheduledTime;
        return $this;
    }

    public function getScheduledDateTime(): \DateTime
    {
        $date = clone $this->scheduledDate;
        $time = $this->scheduledTime;
        
        return (new \DateTime($date->format('Y-m-d')))
            ->setTime(
                (int)$time->format('H'),
                (int)$time->format('i'),
                (int)$time->format('s')
            );
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function getDurationFormatted(): string
    {
        if (!$this->duration) {
            return '0 min';
        }

        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh%02d', $hours, $minutes);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        } else {
            return sprintf('%d min', $minutes);
        }
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getFullAddress(): string
    {
        return sprintf('%s, %s %s', $this->address, $this->postalCode, $this->city);
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;
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

    public function getFormattedAmount(): string
    {
        return number_format((float)$this->amount, 2, ',', ' ') . ' €';
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
        } elseif ($status === 'completed') {
            $this->completedAt = new \DateTimeImmutable();
        } elseif ($status === 'cancelled') {
            $this->cancelledAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'scheduled' => 'Planifié',
            'confirmed' => 'Confirmé',
            'in_progress' => 'En cours',
            'completed' => 'Terminé',
            'cancelled' => 'Annulé',
            default => $this->status,
        };
    }

    public function getRecurrence(): ?Recurrence
    {
        return $this->recurrence;
    }

    public function setRecurrence(?Recurrence $recurrence): self
    {
        $this->recurrence = $recurrence;
        return $this;
    }

    public function isRecurrent(): bool
    {
        return $this->recurrence !== null;
    }

    public function getActualStartTime(): ?\DateTime
    {
        return $this->actualStartTime;
    }

    public function setActualStartTime(?\DateTime $actualStartTime): self
    {
        $this->actualStartTime = $actualStartTime;
        return $this;
    }

    public function getActualEndTime(): ?\DateTime
    {
        return $this->actualEndTime;
    }

    public function setActualEndTime(?\DateTime $actualEndTime): self
    {
        $this->actualEndTime = $actualEndTime;
        return $this;
    }

    public function getActualDuration(): ?int
    {
        if (!$this->actualStartTime || !$this->actualEndTime) {
            return null;
        }
        
        $diff = $this->actualEndTime->getTimestamp() - $this->actualStartTime->getTimestamp();
        return (int) ($diff / 60); // en minutes
    }

    public function getActualDurationFormatted(): ?string
    {
        $duration = $this->getActualDuration();
        if (!$duration) {
            return null;
        }

        $hours = floor($duration / 60);
        $minutes = $duration % 60;

        if ($hours > 0 && $minutes > 0) {
            return sprintf('%dh%02d', $hours, $minutes);
        } elseif ($hours > 0) {
            return sprintf('%dh', $hours);
        } else {
            return sprintf('%d min', $minutes);
        }
    }

    public function getCompletionNotes(): ?string
    {
        return $this->completionNotes;
    }

    public function setCompletionNotes(?string $completionNotes): self
    {
        $this->completionNotes = $completionNotes;
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

    public function getClientInstructions(): ?array
    {
        return $this->clientInstructions ?? [];
    }

    public function setClientInstructions(?array $clientInstructions): self
    {
        $this->clientInstructions = $clientInstructions;
        return $this;
    }

    public function addClientInstruction(string $instruction): self
    {
        $instructions = $this->getClientInstructions();
        $instructions[] = $instruction;
        $this->clientInstructions = $instructions;
        return $this;
    }

    public function getPrestataireNotes(): ?array
    {
        return $this->prestataireNotes ?? [];
    }

    public function setPrestataireNotes(?array $prestataireNotes): self
    {
        $this->prestataireNotes = $prestataireNotes;
        return $this;
    }

    public function addPrestataireNote(string $note): self
    {
        $notes = $this->getPrestataireNotes();
        $notes[] = [
            'note' => $note,
            'created_at' => (new \DateTime())->format('Y-m-d H:i:s')
        ];
        $this->prestataireNotes = $notes;
        return $this;
    }

    public function isClientPresent(): bool
    {
        return $this->clientPresent;
    }

    public function setClientPresent(bool $clientPresent): self
    {
        $this->clientPresent = $clientPresent;
        return $this;
    }

    public function getAccessInstructions(): ?string
    {
        return $this->accessInstructions;
    }

    public function setAccessInstructions(?string $accessInstructions): self
    {
        $this->accessInstructions = $accessInstructions;
        return $this;
    }

    public function getAccessCode(): ?string
    {
        return $this->accessCode;
    }

    public function setAccessCode(?string $accessCode): self
    {
        $this->accessCode = $accessCode;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
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

    public function getCompletedAt(): ?\DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function setCompletedAt(?\DateTimeImmutable $completedAt): self
    {
        $this->completedAt = $completedAt;
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

    public function getCancelledBy(): ?User
    {
        return $this->cancelledBy;
    }

    public function setCancelledBy(?User $cancelledBy): self
    {
        $this->cancelledBy = $cancelledBy;
        return $this;
    }

    public function getPayment(): ?Payment
    {
        return $this->payment;
    }

    public function setPayment(?Payment $payment): self
    {
        // unset the owning side of the relation if necessary
        if ($payment === null && $this->payment !== null) {
            $this->payment->setBooking(null);
        }

        // set the owning side of the relation if necessary
        if ($payment !== null && $payment->getBooking() !== $this) {
            $payment->setBooking($this);
        }

        $this->payment = $payment;

        return $this;
    }

    public function isPaid(): bool
    {
        return $this->payment && $this->payment->getStatus() === 'completed';
    }

    public function getReview(): ?Review
    {
        return $this->review;
    }

    public function setReview(?Review $review): self
    {
        // unset the owning side of the relation if necessary
        if ($review === null && $this->review !== null) {
            $this->review->setBooking(null);
        }

        // set the owning side of the relation if necessary
        if ($review !== null && $review->getBooking() !== $this) {
            $review->setBooking($this);
        }

        $this->review = $review;

        return $this;
    }

    public function hasReview(): bool
    {
        return $this->review !== null;
    }

    /**
     * @return Collection<int, Replacement>
     */
    public function getReplacements(): Collection
    {
        return $this->replacements;
    }

    public function addReplacement(Replacement $replacement): self
    {
        if (!$this->replacements->contains($replacement)) {
            $this->replacements->add($replacement);
            $replacement->setOriginalBooking($this);
        }

        return $this;
    }

    public function removeReplacement(Replacement $replacement): self
    {
        if ($this->replacements->removeElement($replacement)) {
            if ($replacement->getOriginalBooking() === $this) {
                $replacement->setOriginalBooking(null);
            }
        }

        return $this;
    }

    public function hasActiveReplacement(): bool
    {
        foreach ($this->replacements as $replacement) {
            if ($replacement->getStatus() === 'confirmed') {
                return true;
            }
        }
        return false;
    }

    public function getActiveReplacement(): ?Replacement
    {
        foreach ($this->replacements as $replacement) {
            if ($replacement->getStatus() === 'confirmed') {
                return $replacement;
            }
        }
        return null;
    }

    public function isReminderSent24h(): bool
    {
        return $this->reminderSent24h;
    }

    public function setReminderSent24h(bool $reminderSent24h): self
    {
        $this->reminderSent24h = $reminderSent24h;
        return $this;
    }

    public function isReminderSent2h(): bool
    {
        return $this->reminderSent2h;
    }

    public function setReminderSent2h(bool $reminderSent2h): self
    {
        $this->reminderSent2h = $reminderSent2h;
        return $this;
    }

    public function getReferenceNumber(): ?string
    {
        return $this->referenceNumber;
    }

    public function setReferenceNumber(?string $referenceNumber): self
    {
        $this->referenceNumber = $referenceNumber;
        return $this;
    }

    private function generateReferenceNumber(): void
    {
        $this->referenceNumber = 'BKG' . strtoupper(uniqid());
    }

    // Méthodes utilitaires

    public function isUpcoming(): bool
    {
        return $this->getScheduledDateTime() > new \DateTime();
    }

    public function isPast(): bool
    {
        return $this->getScheduledDateTime() < new \DateTime();
    }

    public function isToday(): bool
    {
        $scheduled = $this->getScheduledDateTime();
        $today = new \DateTime();
        return $scheduled->format('Y-m-d') === $today->format('Y-m-d');
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->status, ['scheduled', 'confirmed']) && $this->isUpcoming();
    }

    public function canBeRescheduled(): bool
    {
        return in_array($this->status, ['scheduled', 'confirmed']) && $this->isUpcoming();
    }

    public function canBeCompleted(): bool
    {
        return $this->status === 'in_progress';
    }

    public function canBeReviewed(): bool
    {
        return $this->status === 'completed' && !$this->hasReview();
    }

    public function getTimeUntilStart(): ?\DateInterval
    {
        if (!$this->isUpcoming()) {
            return null;
        }

        $now = new \DateTime();
        return $now->diff($this->getScheduledDateTime());
    }

    public function getHoursUntilStart(): ?int
    {
        $interval = $this->getTimeUntilStart();
        if (!$interval) {
            return null;
        }

        return ($interval->days * 24) + $interval->h;
    }

    public function needsReminder24h(): bool
    {
        if ($this->reminderSent24h || !$this->isUpcoming()) {
            return false;
        }

        $hours = $this->getHoursUntilStart();
        return $hours !== null && $hours <= 24 && $hours > 2;
    }

    public function needsReminder2h(): bool
    {
        if ($this->reminderSent2h || !$this->isUpcoming()) {
            return false;
        }

        $hours = $this->getHoursUntilStart();
        return $hours !== null && $hours <= 2;
    }
}