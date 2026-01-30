<?php
// src/Entity/Planning/Schedule.php

namespace App\Planning\Entity;

use App\Repository\ScheduleRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Entity\Planning\Prestataire;
use App\Entity\Booking;
use App\Entity\User\User;   
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ScheduleRepository::class)]
#[ORM\Table(name: 'schedules')]
#[ORM\HasLifecycleCallbacks]
class Schedule
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'available'; // available, busy, unavailable, blocked

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $eventType = null; // booking, personal, break, travel, other

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Booking $booking = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'string', length: 7, nullable: true)]
    private ?string $color = null; // Code couleur hexadécimal

    #[ORM\Column(type: 'boolean')]
    private bool $isRecurring = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $recurringPattern = null; // daily, weekly, monthly

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $recurringInterval = null; // Intervalle de récurrence

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $recurringEndDate = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isAllDay = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $priority = null; // low, normal, high, urgent

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $createdBy = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): self
    {
        $this->date = $date;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function getStartDateTime(): ?\DateTime
    {
        if (!$this->date || !$this->startTime) {
            return null;
        }

        return (new \DateTime($this->date->format('Y-m-d')))
            ->setTime(
                (int)$this->startTime->format('H'),
                (int)$this->startTime->format('i'),
                (int)$this->startTime->format('s')
            );
    }

    public function getEndDateTime(): ?\DateTime
    {
        if (!$this->date || !$this->endTime) {
            return null;
        }

        return (new \DateTime($this->date->format('Y-m-d')))
            ->setTime(
                (int)$this->endTime->format('H'),
                (int)$this->endTime->format('i'),
                (int)$this->endTime->format('s')
            );
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

    public function getStatusLabel(): string
    {
        return match($this->status) {
            'available' => 'Disponible',
            'busy' => 'Occupé',
            'unavailable' => 'Indisponible',
            'blocked' => 'Bloqué',
            default => $this->status,
        };
    }

    public function getEventType(): ?string
    {
        return $this->eventType;
    }

    public function setEventType(?string $eventType): self
    {
        $this->eventType = $eventType;
        return $this;
    }

    public function getEventTypeLabel(): ?string
    {
        return match($this->eventType) {
            'booking' => 'Réservation',
            'personal' => 'Personnel',
            'break' => 'Pause',
            'travel' => 'Déplacement',
            'other' => 'Autre',
            default => $this->eventType,
        };
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;
        
        if ($booking) {
            $this->eventType = 'booking';
            $this->status = 'busy';
        }
        
        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
    }

    public function getColor(): ?string
    {
        return $this->color;
    }

    public function setColor(?string $color): self
    {
        $this->color = $color;
        return $this;
    }

    public function getDefaultColor(): string
    {
        return match($this->status) {
            'available' => '#4CAF50', // Vert
            'busy' => '#F44336', // Rouge
            'unavailable' => '#9E9E9E', // Gris
            'blocked' => '#607D8B', // Gris foncé
            default => '#2196F3', // Bleu
        };
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function getRecurringPattern(): ?string
    {
        return $this->recurringPattern;
    }

    public function setRecurringPattern(?string $recurringPattern): self
    {
        $this->recurringPattern = $recurringPattern;
        return $this;
    }

    public function getRecurringInterval(): ?int
    {
        return $this->recurringInterval;
    }

    public function setRecurringInterval(?int $recurringInterval): self
    {
        $this->recurringInterval = $recurringInterval;
        return $this;
    }

    public function getRecurringEndDate(): ?\DateTimeInterface
    {
        return $this->recurringEndDate;
    }

    public function setRecurringEndDate(?\DateTimeInterface $recurringEndDate): self
    {
        $this->recurringEndDate = $recurringEndDate;
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

    public function isAllDay(): bool
    {
        return $this->isAllDay;
    }

    public function setIsAllDay(bool $isAllDay): self
    {
        $this->isAllDay = $isAllDay;
        return $this;
    }

    public function getPriority(): ?string
    {
        return $this->priority;
    }

    public function setPriority(?string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getPriorityLabel(): ?string
    {
        return match($this->priority) {
            'low' => 'Basse',
            'normal' => 'Normale',
            'high' => 'Haute',
            'urgent' => 'Urgente',
            default => $this->priority,
        };
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getCreatedBy(): ?User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(?User $createdBy): self
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    /**
     * Calcule la durée en minutes
     */
    public function getDuration(): ?int
    {
        if (!$this->startTime || !$this->endTime) {
            return null;
        }

        $start = \DateTime::createFromFormat('H:i:s', $this->startTime->format('H:i:s'));
        $end = \DateTime::createFromFormat('H:i:s', $this->endTime->format('H:i:s'));

        $diff = $end->getTimestamp() - $start->getTimestamp();
        return (int)($diff / 60);
    }

    /**
     * Retourne la durée formatée
     */
    public function getFormattedDuration(): ?string
    {
        $duration = $this->getDuration();
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

    /**
     * Vérifie si l'événement est dans le passé
     */
    public function isPast(): bool
    {
        $endDateTime = $this->getEndDateTime();
        if (!$endDateTime) {
            return false;
        }

        return $endDateTime < new \DateTime();
    }

    /**
     * Vérifie si l'événement est aujourd'hui
     */
    public function isToday(): bool
    {
        if (!$this->date) {
            return false;
        }

        return $this->date->format('Y-m-d') === (new \DateTime())->format('Y-m-d');
    }

    /**
     * Vérifie si l'événement est dans le futur
     */
    public function isFuture(): bool
    {
        $startDateTime = $this->getStartDateTime();
        if (!$startDateTime) {
            return false;
        }

        return $startDateTime > new \DateTime();
    }

    /**
     * Vérifie si l'événement est actuellement en cours
     */
    public function isOngoing(): bool
    {
        $startDateTime = $this->getStartDateTime();
        $endDateTime = $this->getEndDateTime();
        
        if (!$startDateTime || !$endDateTime) {
            return false;
        }

        $now = new \DateTime();
        return $now >= $startDateTime && $now <= $endDateTime;
    }

    /**
     * Vérifie le chevauchement avec un autre créneau
     */
    public function overlaps(Schedule $other): bool
    {
        if ($this->date->format('Y-m-d') !== $other->getDate()->format('Y-m-d')) {
            return false;
        }

        if ($this->isAllDay || $other->isAllDay()) {
            return true;
        }

        $thisStart = $this->getStartDateTime();
        $thisEnd = $this->getEndDateTime();
        $otherStart = $other->getStartDateTime();
        $otherEnd = $other->getEndDateTime();

        return $thisStart < $otherEnd && $thisEnd > $otherStart;
    }

    /**
     * Retourne une représentation textuelle de l'événement
     */
    public function __toString(): string
    {
        if ($this->title) {
            return $this->title;
        }

        if ($this->booking) {
            return 'Réservation - ' . $this->booking->getClient()->getFullName();
        }

        return $this->getStatusLabel() . ' - ' . $this->date->format('d/m/Y');
    }

    /**
     * Convertit en format calendrier (FullCalendar compatible)
     */
    public function toCalendarEvent(): array
    {
        $event = [
            'id' => $this->id,
            'title' => $this->title ?? $this->getStatusLabel(),
            'start' => $this->getStartDateTime()?->format('Y-m-d\TH:i:s'),
            'end' => $this->getEndDateTime()?->format('Y-m-d\TH:i:s'),
            'allDay' => $this->isAllDay,
            'backgroundColor' => $this->color ?? $this->getDefaultColor(),
            'borderColor' => $this->color ?? $this->getDefaultColor(),
            'extendedProps' => [
                'status' => $this->status,
                'eventType' => $this->eventType,
                'description' => $this->description,
                'location' => $this->location,
                'priority' => $this->priority,
                'bookingId' => $this->booking?->getId(),
            ]
        ];

        return $event;
    }
}