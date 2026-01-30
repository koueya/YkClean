<?php
// src/Entity/Booking/Recurrence.php

namespace App\Entity\Booking;
use App\Repository\RecurrenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RecurrenceRepository::class)]
#[ORM\Table(name: 'recurrences')]
class Recurrence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Client::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\ManyToOne(targetEntity: ServiceCategory::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?ServiceCategory $category = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $frequency; // hebdomadaire, bihebdomadaire, mensuel

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $dayOfWeek = null; // 0 = Dimanche, 6 = Samedi

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $dayOfMonth = null; // 1-31 pour récurrence mensuelle

    #[ORM\Column(type: 'time')]
    private ?\DateTimeInterface $time = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\Positive]
    private ?int $duration = null; // en minutes

    #[ORM\Column(type: 'string', length: 255)]
    private ?string $address = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private ?string $amount = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $nextOccurrence = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isActive = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'recurrence', targetEntity: Booking::class)]
    private Collection $bookings;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->bookings = new ArrayCollection();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getCategory(): ?ServiceCategory
    {
        return $this->category;
    }

    public function setCategory(?ServiceCategory $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getFrequency(): string
    {
        return $this->frequency;
    }

    public function setFrequency(string $frequency): self
    {
        $this->frequency = $frequency;
        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getDayOfMonth(): ?int
    {
        return $this->dayOfMonth;
    }

    public function setDayOfMonth(?int $dayOfMonth): self
    {
        $this->dayOfMonth = $dayOfMonth;
        return $this;
    }

    public function getTime(): ?\DateTimeInterface
    {
        return $this->time;
    }

    public function setTime(\DateTimeInterface $time): self
    {
        $this->time = $time;
        return $this;
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

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(string $address): self
    {
        $this->address = $address;
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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getNextOccurrence(): ?\DateTimeInterface
    {
        return $this->nextOccurrence;
    }

    public function setNextOccurrence(?\DateTimeInterface $nextOccurrence): self
    {
        $this->nextOccurrence = $nextOccurrence;
        return $this;
    }

    public function calculateNextOccurrence(): \DateTimeInterface
    {
        $current = $this->nextOccurrence ?? $this->startDate;
        $next = clone $current;

        switch ($this->frequency) {
            case 'hebdomadaire':
                $next->modify('+1 week');
                break;
            case 'bihebdomadaire':
                $next->modify('+2 weeks');
                break;
            case 'mensuel':
                $next->modify('+1 month');
                break;
        }

        return $next;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
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

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }
}