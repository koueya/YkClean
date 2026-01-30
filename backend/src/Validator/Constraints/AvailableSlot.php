<?php

namespace App\Entity\Planning;

use App\Entity\User\Prestataire;
use Doctrine\ORM\Mapping as ORM;
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
#[ORM\HasLifecycleCallbacks]
class AvailableSlot
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    /**
     * Prestataire concerné par ce créneau
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'availableSlots')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire')]
    private ?Prestataire $prestataire = null;

    /**
     * Date du créneau
     */
    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'La date est obligatoire')]
    #[Assert\GreaterThanOrEqual(
        'today',
        message: 'La date ne peut pas être dans le passé'
    )]
    private ?\DateTimeInterface $date = null;

    /**
     * Heure de début du créneau
     */
    #[ORM\Column(type: 'time')]
    #[Assert\NotNull(message: 'L\'heure de début est obligatoire')]
    private ?\DateTimeInterface $startTime = null;

    /**
     * Heure de fin du créneau
     */
    #[ORM\Column(type: 'time')]
    #[Assert\NotNull(message: 'L\'heure de fin est obligatoire')]
    private ?\DateTimeInterface $endTime = null;

    /**
     * Durée du créneau en minutes
     */
    #[ORM\Column(type: 'integer')]
    #[Assert\NotNull]
    #[Assert\Range(
        min: 30,
        max: 480,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
    )]
    private ?int $duration = null;

    /**
     * Indique si le créneau est déjà réservé
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isBooked = false;

    /**
     * Référence à la réservation si le créneau est réservé
     */
    #[ORM\OneToOne(targetEntity: 'App\Entity\Booking\Booking', mappedBy: 'availableSlot')]
    private mixed $booking = null;

    /**
     * Indique si le créneau est bloqué (indisponible pour réservation)
     * Par exemple : pause déjeuner, rendez-vous personnel, etc.
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isBlocked = false;

    /**
     * Raison du blocage si applicable
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $blockReason = null;

    /**
     * Référence à la disponibilité source si généré automatiquement
     */
    #[ORM\ManyToOne(targetEntity: Availability::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Availability $sourceAvailability = null;

    /**
     * Indique si le créneau a été créé manuellement (true) ou généré automatiquement (false)
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isManual = false;

    /**
     * Prix spécifique pour ce créneau (si différent du tarif horaire standard)
     * Utile pour tarifs variables selon l'heure ou le jour
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $customPrice = null;

    /**
     * Type de service proposé pour ce créneau
     * Par exemple : nettoyage, repassage, jardinage, etc.
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $serviceType = null;

    /**
     * Capacité maximale de réservations pour ce créneau
     * Utile si un prestataire peut accepter plusieurs clients simultanément
     */
    #[ORM\Column(type: 'integer', options: ['default' => 1])]
    #[Assert\Range(
        min: 1,
        max: 10,
        notInRangeMessage: 'La capacité doit être entre {{ min }} et {{ max }}'
    )]
    private int $capacity = 1;

    /**
     * Nombre de réservations actuelles pour ce créneau
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private int $bookedCount = 0;

    /**
     * Notes internes pour le prestataire
     */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    /**
     * Adresse ou zone géographique spécifique pour ce créneau
     * Si le prestataire se déplace, indique où il sera disponible
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $location = null;

    /**
     * Latitude de la localisation
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    /**
     * Longitude de la localisation
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    /**
     * Indique si ce créneau est prioritaire (affiché en premier)
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isPriority = false;

    /**
     * Date de création du créneau
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Date de dernière modification
     */
    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Date d'expiration du créneau (pour les créneaux temporaires)
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
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
            && (!$this->expiresAt || $this->expiresAt > new \DateTime());
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
     * Obtient le prix effectif du créneau (custom ou tarif horaire du prestataire)
     */
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
     * Ex: "Lundi 15 janvier 2024, 14:00 - 16:00 (2h)"
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

        return "{$dateStr}, {$timeStr} ({$durationStr})";
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
        
        return $clone;
    }
}