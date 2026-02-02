<?php

namespace App\Entity\Booking;

use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceCategory;
use App\Repository\Booking\RecurrenceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une récurrence de réservations (services réguliers)
 * Permet de planifier automatiquement des services hebdomadaires, bi-hebdomadaires ou mensuels
 * Génère automatiquement les réservations selon la fréquence définie
 */
#[ORM\Entity(repositoryClass: RecurrenceRepository::class)]
#[ORM\Table(name: 'recurrences')]
#[ORM\Index(columns: ['client_id', 'is_active'], name: 'idx_client_active')]
#[ORM\Index(columns: ['prestataire_id', 'is_active'], name: 'idx_prestataire_active')]
#[ORM\Index(columns: ['next_occurrence'], name: 'idx_next_occurrence')]
#[ORM\Index(columns: ['frequency'], name: 'idx_frequency')]
#[ORM\Index(columns: ['is_active'], name: 'idx_active')]
#[ORM\HasLifecycleCallbacks]
class Recurrence
{
    // Fréquences disponibles
    public const FREQUENCY_WEEKLY = 'hebdomadaire';           // Chaque semaine
    public const FREQUENCY_BIWEEKLY = 'bihebdomadaire';       // Toutes les 2 semaines
    public const FREQUENCY_MONTHLY = 'mensuel';               // Chaque mois
    public const FREQUENCY_DAILY = 'quotidien';               // Chaque jour (rare)
    public const FREQUENCY_CUSTOM = 'personnalisé';           // Fréquence personnalisée

    // Jours de la semaine
    public const DAY_SUNDAY = 0;
    public const DAY_MONDAY = 1;
    public const DAY_TUESDAY = 2;
    public const DAY_WEDNESDAY = 3;
    public const DAY_THURSDAY = 4;
    public const DAY_FRIDAY = 5;
    public const DAY_SATURDAY = 6;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['recurrence:read', 'recurrence:list', 'booking:read'])]
    private ?int $id = null;

    /**
     * Client bénéficiant du service récurrent
     */
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'recurrences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le client est obligatoire', groups: ['recurrence:create'])]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private ?Client $client = null;

    /**
     * Prestataire effectuant le service récurrent
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'recurrences')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire', groups: ['recurrence:create'])]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private ?Prestataire $prestataire = null;

    /**
     * Catégorie de service
     */
    #[ORM\ManyToOne(targetEntity: ServiceCategory::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La catégorie est obligatoire', groups: ['recurrence:create'])]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private ?ServiceCategory $category = null;

    /**
     * Fréquence de récurrence
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'La fréquence est obligatoire', groups: ['recurrence:create'])]
    #[Assert\Choice(
        choices: [
            self::FREQUENCY_DAILY,
            self::FREQUENCY_WEEKLY,
            self::FREQUENCY_BIWEEKLY,
            self::FREQUENCY_MONTHLY,
            self::FREQUENCY_CUSTOM
        ],
        message: 'Fréquence invalide'
    )]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private string $frequency;

    /**
     * Intervalle personnalisé (nombre de jours entre chaque occurrence)
     * Utilisé uniquement si frequency = 'personnalisé'
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'L\'intervalle doit être positif')]
    #[Groups(['recurrence:read', 'recurrence:detail', 'recurrence:create'])]
    private ?int $intervalValue = null;

    /**
     * Jour de la semaine pour les récurrences hebdomadaires
     * 0 = Dimanche, 1 = Lundi, ..., 6 = Samedi
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 0,
        max: 6,
        notInRangeMessage: 'Le jour de la semaine doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private ?int $dayOfWeek = null;

    /**
     * Jour du mois pour les récurrences mensuelles
     * 1-31
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 31,
        notInRangeMessage: 'Le jour du mois doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private ?int $dayOfMonth = null;

    /**
     * Heure du service
     */
    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotNull(message: 'L\'heure est obligatoire', groups: ['recurrence:create'])]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private ?\DateTimeInterface $time = null;

    /**
     * Durée du service en minutes
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotNull(groups: ['recurrence:create'])]
    #[Assert\Positive(message: 'La durée doit être positive')]
    #[Assert\Range(
        min: 30,
        max: 480,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} minutes'
    )]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private ?int $duration = null;

    /**
     * Adresse du service
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire', groups: ['recurrence:create'])]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private ?string $address = null;

    /**
     * Ville
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private ?string $city = null;

    /**
     * Code postal
     */
    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private ?string $postalCode = null;

    /**
     * Montant du service
     */
    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(groups: ['recurrence:create'])]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private ?string $amount = null;

    /**
     * Date de début de la récurrence
     */
    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotNull(message: 'La date de début est obligatoire', groups: ['recurrence:create'])]
    #[Assert\GreaterThanOrEqual(
        'today',
        message: 'La date de début ne peut pas être dans le passé',
        groups: ['recurrence:create']
    )]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create'])]
    private ?\DateTimeInterface $startDate = null;

    /**
     * Date de fin de la récurrence (optionnelle, si vide = indéfini)
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\GreaterThan(
        propertyPath: 'startDate',
        message: 'La date de fin doit être après la date de début'
    )]
    #[Groups(['recurrence:read', 'recurrence:list', 'recurrence:create', 'recurrence:update'])]
    private ?\DateTimeInterface $endDate = null;

    /**
     * Date de la prochaine occurrence
     * Mise à jour automatiquement après chaque génération de réservation
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private ?\DateTimeInterface $nextOccurrence = null;

    /**
     * Nombre total d'occurrences générées
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['recurrence:read', 'recurrence:detail'])]
    private int $totalOccurrences = 0;

    /**
     * Nombre d'occurrences restantes (si limite définie)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Positive(message: 'Le nombre d\'occurrences doit être positif')]
    #[Groups(['recurrence:read', 'recurrence:detail', 'recurrence:create'])]
    private ?int $maxOccurrences = null;

    /**
     * Indique si la récurrence est active
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private bool $isActive = true;

    /**
     * Indique si la récurrence est suspendue temporairement
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private bool $isSuspended = false;

    /**
     * Date jusqu'à laquelle la récurrence est suspendue
     */
    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    #[Groups(['recurrence:read', 'recurrence:detail'])]
    private ?\DateTimeInterface $suspendedUntil = null;

    /**
     * Raison de la suspension
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['recurrence:read', 'recurrence:detail'])]
    private ?string $suspensionReason = null;

    /**
     * Instructions spécifiques pour ce service récurrent
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les instructions ne peuvent pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['recurrence:read', 'recurrence:detail', 'recurrence:create'])]
    private ?string $instructions = null;

    /**
     * Métadonnées additionnelles (préférences, historique, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['recurrence:admin'])]
    private ?array $metadata = [];

    /**
     * Réservations générées par cette récurrence
     */
    #[ORM\OneToMany(mappedBy: 'recurrence', targetEntity: Booking::class, cascade: ['persist'])]
    #[Groups(['recurrence:detail'])]
    private Collection $bookings;

    /**
     * Date de création
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['recurrence:read', 'recurrence:list'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de dernière mise à jour
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['recurrence:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Date de dernière génération de réservation
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['recurrence:admin'])]
    private ?\DateTimeImmutable $lastGeneratedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->bookings = new ArrayCollection();
        $this->metadata = [];
        $this->totalOccurrences = 0;
        $this->isActive = true;
        $this->isSuspended = false;
    }

    // ==================== LIFECYCLE CALLBACKS ====================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
        
        if ($this->nextOccurrence === null && $this->startDate !== null) {
            $this->nextOccurrence = $this->startDate;
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ==================== GETTERS / SETTERS ====================

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

    public function getIntervalValue(): ?int
    {
        return $this->intervalValue;
    }

    public function setIntervalValue(?int $intervalValue): self
    {
        $this->intervalValue = $intervalValue;
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

    /**
     * Retourne la durée formatée (ex: "2h30")
     */
    public function getFormattedDuration(): string
    {
        if (!$this->duration) {
            return '0h';
        }

        $hours = floor($this->duration / 60);
        $minutes = $this->duration % 60;

        if ($minutes === 0) {
            return "{$hours}h";
        }

        return "{$hours}h{$minutes}";
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

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    /**
     * Retourne l'adresse complète formatée
     */
    public function getFullAddress(): string
    {
        $parts = array_filter([
            $this->address,
            $this->postalCode,
            $this->city
        ]);
        
        return implode(', ', $parts);
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

    /**
     * Retourne le montant formaté (ex: "45,50 €")
     */
    public function getFormattedAmount(): string
    {
        return number_format((float) $this->amount, 2, ',', ' ') . ' €';
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        
        // Initialiser nextOccurrence si non défini
        if ($this->nextOccurrence === null) {
            $this->nextOccurrence = $startDate;
        }
        
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

    public function getTotalOccurrences(): int
    {
        return $this->totalOccurrences;
    }

    public function setTotalOccurrences(int $totalOccurrences): self
    {
        $this->totalOccurrences = $totalOccurrences;
        return $this;
    }

    public function incrementTotalOccurrences(): self
    {
        $this->totalOccurrences++;
        return $this;
    }

    public function getMaxOccurrences(): ?int
    {
        return $this->maxOccurrences;
    }

    public function setMaxOccurrences(?int $maxOccurrences): self
    {
        $this->maxOccurrences = $maxOccurrences;
        return $this;
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

    public function isSuspended(): bool
    {
        return $this->isSuspended;
    }

    public function setIsSuspended(bool $isSuspended): self
    {
        $this->isSuspended = $isSuspended;
        
        // Réinitialiser suspendedUntil si on désactive la suspension
        if (!$isSuspended) {
            $this->suspendedUntil = null;
            $this->suspensionReason = null;
        }
        
        return $this;
    }

    public function getSuspendedUntil(): ?\DateTimeInterface
    {
        return $this->suspendedUntil;
    }

    public function setSuspendedUntil(?\DateTimeInterface $suspendedUntil): self
    {
        $this->suspendedUntil = $suspendedUntil;
        return $this;
    }

    public function getSuspensionReason(): ?string
    {
        return $this->suspensionReason;
    }

    public function setSuspensionReason(?string $suspensionReason): self
    {
        $this->suspensionReason = $suspensionReason;
        return $this;
    }

    public function getInstructions(): ?string
    {
        return $this->instructions;
    }

    public function setInstructions(?string $instructions): self
    {
        $this->instructions = $instructions;
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
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setRecurrence($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getRecurrence() === $this) {
                $booking->setRecurrence(null);
            }
        }

        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
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

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLastGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->lastGeneratedAt;
    }

    public function setLastGeneratedAt(?\DateTimeImmutable $lastGeneratedAt): self
    {
        $this->lastGeneratedAt = $lastGeneratedAt;
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Vérifie si la récurrence peut générer de nouvelles réservations
     */
    public function canGenerateBookings(): bool
    {
        // Vérifier si active
        if (!$this->isActive) {
            return false;
        }

        // Vérifier si suspendue
        if ($this->isSuspended) {
            // Vérifier si la suspension est expirée
            if ($this->suspendedUntil && $this->suspendedUntil < new \DateTime()) {
                $this->isSuspended = false;
                $this->suspendedUntil = null;
                $this->suspensionReason = null;
            } else {
                return false;
            }
        }

        // Vérifier si la date de fin est dépassée
        if ($this->endDate && $this->endDate < new \DateTime()) {
            return false;
        }

        // Vérifier si le nombre max d'occurrences est atteint
        if ($this->maxOccurrences !== null && $this->totalOccurrences >= $this->maxOccurrences) {
            return false;
        }

        return true;
    }

    /**
     * Vérifie si la récurrence est expirée
     */
    public function isExpired(): bool
    {
        if ($this->endDate && $this->endDate < new \DateTime()) {
            return true;
        }

        if ($this->maxOccurrences !== null && $this->totalOccurrences >= $this->maxOccurrences) {
            return true;
        }

        return false;
    }

    /**
     * Calcule le nombre d'occurrences restantes
     */
    public function getRemainingOccurrences(): ?int
    {
        if ($this->maxOccurrences === null) {
            return null; // Illimité
        }

        $remaining = $this->maxOccurrences - $this->totalOccurrences;
        return max(0, $remaining);
    }

    /**
     * Obtient le libellé de la fréquence en français
     */
    public function getFrequencyLabel(): string
    {
        return match ($this->frequency) {
            self::FREQUENCY_DAILY => 'Quotidien',
            self::FREQUENCY_WEEKLY => 'Hebdomadaire',
            self::FREQUENCY_BIWEEKLY => 'Bi-hebdomadaire',
            self::FREQUENCY_MONTHLY => 'Mensuel',
            self::FREQUENCY_CUSTOM => 'Personnalisé',
            default => $this->frequency
        };
    }

    /**
     * Obtient le nom du jour de la semaine
     */
    public function getDayOfWeekName(): ?string
    {
        if ($this->dayOfWeek === null) {
            return null;
        }

        return match ($this->dayOfWeek) {
            self::DAY_SUNDAY => 'Dimanche',
            self::DAY_MONDAY => 'Lundi',
            self::DAY_TUESDAY => 'Mardi',
            self::DAY_WEDNESDAY => 'Mercredi',
            self::DAY_THURSDAY => 'Jeudi',
            self::DAY_FRIDAY => 'Vendredi',
            self::DAY_SATURDAY => 'Samedi',
            default => null
        };
    }

    /**
     * Retourne une description textuelle de la récurrence
     */
    public function getDescription(): string
    {
        $parts = [];
        
        // Fréquence
        $parts[] = $this->getFrequencyLabel();
        
        // Jour de la semaine
        if ($this->dayOfWeek !== null) {
            $parts[] = "le {$this->getDayOfWeekName()}";
        }
        
        // Jour du mois
        if ($this->dayOfMonth !== null) {
            $parts[] = "le {$this->dayOfMonth} du mois";
        }
        
        // Heure
        if ($this->time) {
            $parts[] = "à {$this->time->format('H:i')}";
        }
        
        return implode(' ', $parts);
    }

    /**
     * Compte le nombre de réservations actives
     */
    public function getActiveBookingsCount(): int
    {
        $count = 0;
        foreach ($this->bookings as $booking) {
            if ($booking->isActive()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Compte le nombre de réservations terminées
     */
    public function getCompletedBookingsCount(): int
    {
        $count = 0;
        foreach ($this->bookings as $booking) {
            if ($booking->isCompleted()) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Calcule le montant total généré
     */
    public function getTotalRevenue(): float
    {
        $total = 0.0;
        foreach ($this->bookings as $booking) {
            if ($booking->isCompleted() && $booking->getAmount()) {
                $total += (float) $booking->getAmount();
            }
        }
        return $total;
    }

    /**
     * Obtient les prochaines réservations planifiées
     */
    public function getUpcomingBookings(int $limit = 5): array
    {
        $upcoming = [];
        $now = new \DateTime();
        
        foreach ($this->bookings as $booking) {
            if ($booking->getScheduledDate() >= $now && $booking->isActive()) {
                $upcoming[] = $booking;
            }
        }
        
        // Trier par date
        usort($upcoming, function($a, $b) {
            return $a->getScheduledDate() <=> $b->getScheduledDate();
        });
        
        return array_slice($upcoming, 0, $limit);
    }

    /**
     * Retourne une représentation textuelle
     */
    public function __toString(): string
    {
        $client = $this->client ? $this->client->getFullName() : 'N/A';
        $frequency = $this->getFrequencyLabel();
        
        return "Récurrence #{$this->id} - {$client} - {$frequency}";
    }

    /**
     * Liste de toutes les fréquences disponibles
     */
    public static function getAvailableFrequencies(): array
    {
        return [
            self::FREQUENCY_DAILY,
            self::FREQUENCY_WEEKLY,
            self::FREQUENCY_BIWEEKLY,
            self::FREQUENCY_MONTHLY,
            self::FREQUENCY_CUSTOM
        ];
    }

    /**
     * Liste des fréquences avec libellés
     */
    public static function getFrequencyOptions(): array
    {
        return [
            self::FREQUENCY_DAILY => 'Quotidien',
            self::FREQUENCY_WEEKLY => 'Hebdomadaire',
            self::FREQUENCY_BIWEEKLY => 'Bi-hebdomadaire',
            self::FREQUENCY_MONTHLY => 'Mensuel',
            self::FREQUENCY_CUSTOM => 'Personnalisé'
        ];
    }

    /**
     * Liste des jours de la semaine
     */
    public static function getDaysOfWeek(): array
    {
        return [
            self::DAY_MONDAY => 'Lundi',
            self::DAY_TUESDAY => 'Mardi',
            self::DAY_WEDNESDAY => 'Mercredi',
            self::DAY_THURSDAY => 'Jeudi',
            self::DAY_FRIDAY => 'Vendredi',
            self::DAY_SATURDAY => 'Samedi',
            self::DAY_SUNDAY => 'Dimanche'
        ];
    }
}