<?php

namespace App\Entity\Planning;

use App\Entity\User\Prestataire;
use App\Validator\AvailableSlotConstraint;
use App\Entity\Planning\Availability;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un créneau horaire disponible pour un prestataire
 * Peut être généré automatiquement à partir des disponibilités ou créé manuellement
 */
#[ORM\Entity(repositoryClass: 'App\Repository\Planning\AvailableSlotRepository')]
#[ORM\Table(name: 'available_slots')]
#[ORM\Index(columns: ['prestataire_id', 'date', 'start_time'], name: 'idx_prestataire_datetime')]
#[ORM\Index(columns: ['date', 'is_booked'], name: 'idx_date_booked')]
#[ORM\Index(columns: ['prestataire_id', 'is_booked', 'date'], name: 'idx_search_available')]
#[ORM\Index(columns: ['service_type'], name: 'idx_service_type')]
#[ORM\Index(columns: ['latitude', 'longitude'], name: 'idx_location')]
#[ORM\HasLifecycleCallbacks]
#[AvailableSlotConstraint(
    checkOverlap: true,
    checkBusinessHours: true,
    checkPriorityLimits: true,
    checkServiceType: true,
    groups: ['slot:create', 'slot:update']
)]
class AvailableSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['slot:read', 'slot:list'])]
    private ?int $id = null;

    /**
     * Prestataire concerné par ce créneau
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'availableSlots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire', groups: ['slot:create'])]
    #[Groups(['slot:read'])]
    private ?Prestataire $prestataire = null;

    /**
     * Date du créneau
     */
    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'La date est obligatoire', groups: ['slot:create'])]
    #[Assert\GreaterThanOrEqual(
        'today',
        message: 'La date ne peut pas être dans le passé',
        groups: ['slot:create']
    )]
    #[Groups(['slot:read', 'slot:list'])]
    private ?\DateTimeInterface $date = null;

    /**
     * Heure de début du créneau
     */
    #[ORM\Column(type: 'time')]
    #[Assert\NotNull(message: 'L\'heure de début est obligatoire', groups: ['slot:create'])]
    #[Groups(['slot:read', 'slot:list'])]
    private ?\DateTimeInterface $startTime = null;

    /**
     * Heure de fin du créneau
     */
    #[ORM\Column(type: 'time')]
    #[Assert\NotNull(message: 'L\'heure de fin est obligatoire', groups: ['slot:create'])]
    #[Groups(['slot:read', 'slot:list'])]
    private ?\DateTimeInterface $endTime = null;

    /**
     * Durée du créneau en minutes
     */
    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull(groups: ['slot:create'])]
    #[Assert\Range(
        min: 30,
        max: 480,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes',
        groups: ['slot:create', 'slot:update']
    )]
    #[Groups(['slot:read', 'slot:list'])]
    private ?int $duration = null;

    /**
     * Indique si le créneau est déjà réservé
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['slot:read', 'slot:list'])]
    private bool $isBooked = false;

    /**
     * Référence à la réservation si le créneau est réservé
     */
    #[ORM\OneToOne(targetEntity: 'App\Entity\Booking\Booking', mappedBy: 'availableSlot')]
    #[Groups(['slot:read'])]
    private mixed $booking = null;

    /**
     * Indique si le créneau est bloqué (indisponible pour réservation)
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['slot:read', 'slot:list'])]
    private bool $isBlocked = false;

    /**
     * Raison du blocage si applicable
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['slot:read'])]
    private ?string $blockReason = null;

    /**
     * Référence à la disponibilité source si généré automatiquement
     */
    #[ORM\ManyToOne(targetEntity: Availability::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['slot:read'])]
    private ?Availability $sourceAvailability = null;
    /**
     * Indique si le créneau a été créé manuellement
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['slot:read'])]
    private bool $isManual = false;

    /**
     * Prix spécifique pour ce créneau
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le prix doit être positif')]
    #[Assert\LessThanOrEqual(
        value: 500,
        message: 'Le prix ne peut pas dépasser {{ compared_value }}€'
    )]
    #[Groups(['slot:read', 'slot:list'])]
    private ?string $customPrice = null;

    /**
     * Type de service proposé pour ce créneau
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['slot:read', 'slot:list'])]
    private ?string $serviceType = null;

    /**
     * Capacité maximale de réservations pour ce créneau
     */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'La capacité doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['slot:read', 'slot:list'])]
    private int $capacity = 1;

    /**
     * Nombre de réservations actuelles pour ce créneau
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre ne peut pas être négatif')]
    #[Groups(['slot:read', 'slot:list'])]
    private int $bookedCount = 0;

    /**
     * Notes internes pour le prestataire
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['slot:read'])]
    private ?string $notes = null;

    /**
     * Adresse ou zone géographique spécifique pour ce créneau
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['slot:read', 'slot:list'])]
    private ?string $location = null;

    /**
     * Latitude de la localisation
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(
        min: -90,
        max: 90,
        notInRangeMessage: 'La latitude doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['slot:read'])]
    private ?string $latitude = null;

    /**
     * Longitude de la localisation
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(
        min: -180,
        max: 180,
        notInRangeMessage: 'La longitude doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['slot:read'])]
    private ?string $longitude = null;

    /**
     * Indique si ce créneau est prioritaire
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['slot:read', 'slot:list'])]
    private bool $isPriority = false;

    /**
     * Date de création du créneau
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['slot:read'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Date de dernière modification
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['slot:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Date d'expiration du créneau
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Assert\GreaterThan(
        'now',
        message: 'La date d\'expiration doit être dans le futur',
        groups: ['slot:create']
    )]
    #[Groups(['slot:read'])]
    private ?\DateTimeInterface $expiresAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateDurationOnSave(): void
    {
        if ($this->startTime && $this->endTime && !$this->duration) {
            $this->calculateDuration();
        }
    }

    // Getters et Setters

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

    public function setDate(?\DateTimeInterface $date): self
    {
        $this->date = $date;
        $this->calculateDuration();
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(?\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        $this->calculateDuration();
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(?\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        $this->calculateDuration();
        return $this;
    }

    public function getDuration(): ?int
    {
        return $this->duration;
    }

    public function setDuration(?int $duration): self
    {
        $this->duration = $duration;
        return $this;
    }

    public function isBooked(): bool
    {
        return $this->isBooked;
    }

    public function setIsBooked(bool $isBooked): self
    {
        $this->isBooked = $isBooked;
        return $this;
    }

    public function getBooking(): mixed
    {
        return $this->booking;
    }

    public function setBooking(mixed $booking): self
    {
        $this->booking = $booking;
        return $this;
    }

    public function isBlocked(): bool
    {
        return $this->isBlocked;
    }

    public function setIsBlocked(bool $isBlocked): self
    {
        $this->isBlocked = $isBlocked;
        return $this;
    }

    public function getBlockReason(): ?string
    {
        return $this->blockReason;
    }

    public function setBlockReason(?string $blockReason): self
    {
        $this->blockReason = $blockReason;
        return $this;
    }

    public function getSourceAvailability(): ?Availability
    {
        return $this->sourceAvailability;
    }

    public function setSourceAvailability(?Availability $sourceAvailability): self
    {
        $this->sourceAvailability = $sourceAvailability;
        return $this;
    }

    public function isManual(): bool
    {
        return $this->isManual;
    }

    public function setIsManual(bool $isManual): self
    {
        $this->isManual = $isManual;
        return $this;
    }

    public function getCustomPrice(): ?string
    {
        return $this->customPrice;
    }

    public function setCustomPrice(?string $customPrice): self
    {
        $this->customPrice = $customPrice;
        return $this;
    }

    public function getServiceType(): ?string
    {
        return $this->serviceType;
    }

    public function setServiceType(?string $serviceType): self
    {
        $this->serviceType = $serviceType;
        return $this;
    }

    public function getCapacity(): int
    {
        return $this->capacity;
    }

    public function setCapacity(int $capacity): self
    {
        $this->capacity = $capacity;
        return $this;
    }

    public function getBookedCount(): int
    {
        return $this->bookedCount;
    }

    public function setBookedCount(int $bookedCount): self
    {
        $this->bookedCount = $bookedCount;
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

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;
        return $this;
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

    public function isPriority(): bool
    {
        return $this->isPriority;
    }

    public function setIsPriority(bool $isPriority): self
    {
        $this->isPriority = $isPriority;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeInterface
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeInterface $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    // Méthodes utilitaires

    /**
     * Calcule automatiquement la durée en minutes entre startTime et endTime
     */
    private function calculateDuration(): void
    {
        if ($this->startTime && $this->endTime) {
            $start = new \DateTime($this->startTime->format('H:i:s'));
            $end = new \DateTime($this->endTime->format('H:i:s'));
            
            $diff = $end->getTimestamp() - $start->getTimestamp();
            $this->duration = (int) ($diff / 60);
        }
    }

    /**
     * Vérifie si le créneau est disponible pour réservation
     */
    public function isAvailable(): bool
    {
        return !$this->isBooked 
            && !$this->isBlocked 
            && $this->bookedCount < $this->capacity
            && (!$this->expiresAt || $this->expiresAt > new \DateTime())
            && !$this->isPast();
    }

    /**
     * Vérifie si le créneau peut encore accepter des réservations
     */
    public function hasAvailableCapacity(): bool
    {
        return $this->bookedCount < $this->capacity;
    }

    /**
     * Incrémente le compteur de réservations
     */
    public function incrementBookedCount(): self
    {
        $this->bookedCount++;
        
        if ($this->bookedCount >= $this->capacity) {
            $this->isBooked = true;
        }
        
        return $this;
    }

    /**
     * Décrémente le compteur de réservations (en cas d'annulation)
     */
    public function decrementBookedCount(): self
    {
        if ($this->bookedCount > 0) {
            $this->bookedCount--;
            
            if ($this->bookedCount < $this->capacity) {
                $this->isBooked = false;
            }
        }
        
        return $this;
    }

    /**
     * Obtient le prix effectif du créneau
     */
    #[Groups(['slot:read', 'slot:list'])]
    public function getEffectivePrice(): ?float
    {
        if ($this->customPrice !== null) {
            return (float) $this->customPrice;
        }

        if ($this->prestataire && $this->duration) {
            $hourlyRate = $this->prestataire->getHourlyRate();
            return ($hourlyRate * $this->duration) / 60;
        }

        return null;
    }

    /**
     * Combine date et heure de début en un seul DateTime
     */
    public function getStartDateTime(): ?\DateTime
    {
        if (!$this->date || !$this->startTime) {
            return null;
        }

        return new \DateTime(
            $this->date->format('Y-m-d') . ' ' . $this->startTime->format('H:i:s')
        );
    }

    /**
     * Combine date et heure de fin en un seul DateTime
     */
    public function getEndDateTime(): ?\DateTime
    {
        if (!$this->date || !$this->endTime) {
            return null;
        }

        return new \DateTime(
            $this->date->format('Y-m-d') . ' ' . $this->endTime->format('H:i:s')
        );
    }

    /**
     * Vérifie si le créneau est dans le passé
     */
    public function isPast(): bool
    {
        $startDateTime = $this->getStartDateTime();
        return $startDateTime && $startDateTime < new \DateTime();
    }

    /**
     * Vérifie si le créneau est aujourd'hui
     */
    public function isToday(): bool
    {
        if (!$this->date) {
            return false;
        }

        $today = new \DateTime();
        return $this->date->format('Y-m-d') === $today->format('Y-m-d');
    }

    /**
     * Vérifie si le créneau est demain
     */
    public function isTomorrow(): bool
    {
        if (!$this->date) {
            return false;
        }

        $tomorrow = new \DateTime('+1 day');
        return $this->date->format('Y-m-d') === $tomorrow->format('Y-m-d');
    }

    /**
     * Obtient le nombre de places restantes
     */
    #[Groups(['slot:read', 'slot:list'])]
    public function getRemainingCapacity(): int
    {
        return max(0, $this->capacity - $this->bookedCount);
    }

    /**
     * Vérifie si deux créneaux se chevauchent
     */
    public function overlapsWith(AvailableSlot $other): bool
    {
        // Doivent être le même jour
        if (!$this->date || !$other->getDate() || 
            $this->date->format('Y-m-d') !== $other->getDate()->format('Y-m-d')) {
            return false;
        }

        // Doivent être le même prestataire
        if (!$this->prestataire || !$other->getPrestataire() ||
            $this->prestataire->getId() !== $other->getPrestataire()->getId()) {
            return false;
        }

        $thisStart = $this->getStartDateTime();
        $thisEnd = $this->getEndDateTime();
        $otherStart = $other->getStartDateTime();
        $otherEnd = $other->getEndDateTime();

        if (!$thisStart || !$thisEnd || !$otherStart || !$otherEnd) {
            return false;
        }

        // Vérifie le chevauchement
        return $thisStart < $otherEnd && $otherStart < $thisEnd;
    }

    /**
     * Formate le créneau pour l'affichage
     */
    public function getFormattedSlot(string $locale = 'fr'): string
    {
        if (!$this->date || !$this->startTime || !$this->endTime) {
            return '';
        }

        $formatter = new \IntlDateFormatter(
            $locale,
            \IntlDateFormatter::FULL,
            \IntlDateFormatter::NONE,
            'Europe/Paris',
            \IntlDateFormatter::GREGORIAN,
            'EEEE d MMMM yyyy'
        );

        $dateStr = $formatter->format($this->date);
        $timeStr = sprintf(
            '%s - %s',
            $this->startTime->format('H:i'),
            $this->endTime->format('H:i')
        );

        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;
        $durationStr = $hours > 0 ? "{$hours}h" : '';
        $durationStr .= $minutes > 0 ? "{$minutes}min" : '';

        return ucfirst("{$dateStr}, {$timeStr} ({$durationStr})");
    }

    /**
     * Clone le créneau pour une autre date
     */
    public function cloneForDate(\DateTimeInterface $newDate): self
    {
        $clone = new self();
        $clone->setPrestataire($this->prestataire);
        $clone->setDate($newDate);
        $clone->setStartTime($this->startTime);
        $clone->setEndTime($this->endTime);
        $clone->setDuration($this->duration);
        $clone->setCapacity($this->capacity);
        $clone->setServiceType($this->serviceType);
        $clone->setCustomPrice($this->customPrice);
        $clone->setLocation($this->location);
        $clone->setLatitude($this->latitude);
        $clone->setLongitude($this->longitude);
        $clone->setSourceAvailability($this->sourceAvailability);
        $clone->setIsManual(false); // Un clone est toujours considéré comme automatique
        
        return $clone;
    }

    /**
     * Bloque le créneau avec une raison
     */
    public function block(string $reason): self
    {
        $this->isBlocked = true;
        $this->blockReason = $reason;
        return $this;
    }

    /**
     * Débloque le créneau
     */
    public function unblock(): self
    {
        $this->isBlocked = false;
        $this->blockReason = null;
        return $this;
    }

    /**
     * Réserve le créneau (utilisé pour la capacité 1)
     */
    public function book(): self
    {
        if ($this->hasAvailableCapacity()) {
            $this->incrementBookedCount();
        }
        return $this;
    }

    /**
     * Libère le créneau (annulation de réservation)
     */
    public function release(): self
    {
        $this->decrementBookedCount();
        return $this;
    }

    /**
     * Vérifie si le créneau expire bientôt (dans les 24h)
     */
    public function expiresWithin24Hours(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        $now = new \DateTime();
        $diff = $this->expiresAt->getTimestamp() - $now->getTimestamp();
        
        return $diff > 0 && $diff <= 86400; // 24 heures en secondes
    }

    /**
     * Vérifie si le créneau est expiré
     */
    public function isExpired(): bool
    {
        return $this->expiresAt && $this->expiresAt <= new \DateTime();
    }

    /**
     * Retourne une représentation JSON simple du créneau
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date?->format('Y-m-d'),
            'startTime' => $this->startTime?->format('H:i'),
            'endTime' => $this->endTime?->format('H:i'),
            'duration' => $this->duration,
            'isAvailable' => $this->isAvailable(),
            'isBooked' => $this->isBooked,
            'isBlocked' => $this->isBlocked,
            'capacity' => $this->capacity,
            'bookedCount' => $this->bookedCount,
            'remainingCapacity' => $this->getRemainingCapacity(),
            'effectivePrice' => $this->getEffectivePrice(),
            'serviceType' => $this->serviceType,
            'location' => $this->location,
            'isPriority' => $this->isPriority,
        ];
    }
}