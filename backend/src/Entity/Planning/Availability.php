<?php
// src/Entity/Planning/Availability.php

namespace App\Entity\Planning;

use App\Entity\User\Prestataire;
use App\Repository\Planning\AvailabilityRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une disponibilité récurrente ou ponctuelle d'un prestataire
 * Sert de modèle pour générer des créneaux (AvailableSlot)
 */
#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
#[ORM\Table(name: 'availabilities')]
#[ORM\Index(columns: ['prestataire_id', 'day_of_week'], name: 'idx_prestataire_day')]
#[ORM\Index(columns: ['prestataire_id', 'specific_date'], name: 'idx_prestataire_date')]
#[ORM\Index(columns: ['is_recurring', 'is_available'], name: 'idx_recurring_available')]
#[ORM\HasLifecycleCallbacks]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['availability:read', 'availability:list'])]
    private ?int $id = null;

    /**
     * Prestataire concerné par cette disponibilité
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire')]
    #[Groups(['availability:read'])]
    private ?Prestataire $prestataire = null;

    /**
     * Jour de la semaine (0-6) pour disponibilités récurrentes
     * 0 = Dimanche, 1 = Lundi, ..., 6 = Samedi
     * NULL si c'est une date spécifique
     */
    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 0, max: 6, notInRangeMessage: 'Le jour doit être entre 0 (Dimanche) et 6 (Samedi)')]
    #[Groups(['availability:read', 'availability:list'])]
    private ?int $dayOfWeek = null;

    /**
     * Date spécifique pour disponibilités ponctuelles
     * NULL si c'est une disponibilité récurrente
     */
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['availability:read', 'availability:list'])]
    private ?\DateTimeInterface $specificDate = null;

    /**
     * Heure de début de la disponibilité
     */
    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank(message: 'L\'heure de début est obligatoire')]
    #[Groups(['availability:read', 'availability:list'])]
    private ?\DateTimeInterface $startTime = null;

    /**
     * Heure de fin de la disponibilité
     */
    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank(message: 'L\'heure de fin est obligatoire')]
    #[Groups(['availability:read', 'availability:list'])]
    private ?\DateTimeInterface $endTime = null;

    /**
     * Indique si c'est une disponibilité récurrente (chaque semaine)
     * ou ponctuelle (une seule fois)
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups(['availability:read', 'availability:list'])]
    private bool $isRecurring = true;

    /**
     * Indique si le prestataire est disponible ou non
     * Permet de désactiver temporairement sans supprimer
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups(['availability:read', 'availability:list'])]
    private bool $isAvailable = true;

    /**
     * Indique si cette disponibilité est active
     * Permet de désactiver définitivement sans supprimer
     */
    #[ORM\Column(type: 'boolean')]
    #[Groups(['availability:read', 'availability:list'])]
    private bool $isActive = true;

    /**
     * Nombre maximum de réservations possibles en simultané
     * Utile pour les services de groupe
     */
    #[ORM\Column(type: 'integer')]
    #[Assert\Positive(message: 'La capacité maximale doit être positive')]
    #[Groups(['availability:read'])]
    private int $maxBookingsPerSlot = 1;

    /**
     * Notes internes sur cette disponibilité
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['availability:read'])]
    private ?string $notes = null;

    /**
     * Priorité de cette disponibilité (pour optimisation planning)
     * Plus élevé = plus prioritaire
     */
    #[ORM\Column(type: 'integer')]
    #[Assert\Range(min: 0, max: 10)]
    #[Groups(['availability:read'])]
    private int $priority = 5;

    /**
     * Date de création
     */
    #[ORM\Column(type: 'datetime_immutable')]
    #[Groups(['availability:read'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de dernière modification
     */
    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    #[Groups(['availability:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    /**
     * Date jusqu'à laquelle cette disponibilité est valide
     * NULL = indéfiniment
     */
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['availability:read'])]
    private ?\DateTimeInterface $validUntil = null;

    /**
     * Date à partir de laquelle cette disponibilité est valide
     */
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['availability:read'])]
    private ?\DateTimeInterface $validFrom = null;

    // ===== RELATIONS =====

    /**
     * Créneaux générés automatiquement à partir de cette disponibilité
     * @var Collection<int, AvailableSlot>
     */
    #[ORM\OneToMany(
        mappedBy: 'sourceAvailability',
        targetEntity: AvailableSlot::class,
        cascade: ['persist'],
        orphanRemoval: false
    )]
    private Collection $generatedSlots;

    // ===== CONSTRUCTEUR =====

    public function __construct()
    {
        $this->generatedSlots = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
        $this->isRecurring = true;
        $this->isAvailable = true;
        $this->isActive = true;
        $this->maxBookingsPerSlot = 1;
        $this->priority = 5;
    }

    // ===== LIFECYCLE CALLBACKS =====

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    // ===== GETTERS & SETTERS =====

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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): self
    {
        if ($dayOfWeek !== null && ($dayOfWeek < 0 || $dayOfWeek > 6)) {
            throw new \InvalidArgumentException('Le jour de la semaine doit être entre 0 et 6');
        }
        
        $this->dayOfWeek = $dayOfWeek;
        
        // Si on définit un jour de semaine, on supprime la date spécifique
        if ($dayOfWeek !== null) {
            $this->specificDate = null;
            $this->isRecurring = true;
        }
        
        return $this;
    }

    public function getDayOfWeekName(): ?string
    {
        if ($this->dayOfWeek === null) {
            return null;
        }

        $days = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi'
        ];

        return $days[$this->dayOfWeek] ?? null;
    }

    public function getSpecificDate(): ?\DateTimeInterface
    {
        return $this->specificDate;
    }

    public function setSpecificDate(?\DateTimeInterface $specificDate): self
    {
        $this->specificDate = $specificDate;
        
        // Si on définit une date spécifique, on supprime le jour de semaine
        if ($specificDate !== null) {
            $this->dayOfWeek = null;
            $this->isRecurring = false;
        }
        
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

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): self
    {
        $this->isAvailable = $isAvailable;
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

    public function getMaxBookingsPerSlot(): int
    {
        return $this->maxBookingsPerSlot;
    }

    public function setMaxBookingsPerSlot(int $maxBookingsPerSlot): self
    {
        if ($maxBookingsPerSlot < 1) {
            throw new \InvalidArgumentException('La capacité maximale doit être au moins 1');
        }
        
        $this->maxBookingsPerSlot = $maxBookingsPerSlot;
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

    public function getPriority(): int
    {
        return $this->priority;
    }

    public function setPriority(int $priority): self
    {
        if ($priority < 0 || $priority > 10) {
            throw new \InvalidArgumentException('La priorité doit être entre 0 et 10');
        }
        
        $this->priority = $priority;
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

    public function getValidUntil(): ?\DateTimeInterface
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTimeInterface $validUntil): self
    {
        $this->validUntil = $validUntil;
        return $this;
    }

    public function getValidFrom(): ?\DateTimeInterface
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTimeInterface $validFrom): self
    {
        $this->validFrom = $validFrom;
        return $this;
    }

    // ===== RELATION - GENERATED SLOTS =====

    /**
     * @return Collection<int, AvailableSlot>
     */
    public function getGeneratedSlots(): Collection
    {
        return $this->generatedSlots;
    }

    public function addGeneratedSlot(AvailableSlot $slot): self
    {
        if (!$this->generatedSlots->contains($slot)) {
            $this->generatedSlots->add($slot);
            $slot->setSourceAvailability($this);
        }
        return $this;
    }

    public function removeGeneratedSlot(AvailableSlot $slot): self
    {
        if ($this->generatedSlots->removeElement($slot)) {
            if ($slot->getSourceAvailability() === $this) {
                $slot->setSourceAvailability(null);
            }
        }
        return $this;
    }

    /**
     * Retourne le nombre de créneaux générés
     */
    public function getGeneratedSlotsCount(): int
    {
        return $this->generatedSlots->count();
    }

    /**
     * Retourne les créneaux générés pour une date donnée
     */
    public function getGeneratedSlotsForDate(\DateTimeInterface $date): Collection
    {
        return $this->generatedSlots->filter(
            fn(AvailableSlot $slot) => $slot->getDate()->format('Y-m-d') === $date->format('Y-m-d')
        );
    }

    /**
     * Retourne les créneaux générés non réservés
     */
    public function getAvailableGeneratedSlots(): Collection
    {
        return $this->generatedSlots->filter(
            fn(AvailableSlot $slot) => !$slot->isBooked() && !$slot->isBlocked()
        );
    }

    /**
     * Retourne les créneaux générés réservés
     */
    public function getBookedGeneratedSlots(): Collection
    {
        return $this->generatedSlots->filter(
            fn(AvailableSlot $slot) => $slot->isBooked()
        );
    }

    /**
     * Supprime tous les créneaux générés non réservés
     */
    public function clearUnbookedGeneratedSlots(): self
    {
        $toRemove = [];
        
        foreach ($this->generatedSlots as $slot) {
            if (!$slot->isBooked()) {
                $toRemove[] = $slot;
            }
        }
        
        foreach ($toRemove as $slot) {
            $this->removeGeneratedSlot($slot);
        }
        
        return $this;
    }

    // ===== MÉTHODES UTILITAIRES =====

    /**
     * Calcule la durée de la disponibilité en minutes
     */
    public function getDurationInMinutes(): int
    {
        if (!$this->startTime || !$this->endTime) {
            return 0;
        }

        $start = new \DateTime($this->startTime->format('H:i:s'));
        $end = new \DateTime($this->endTime->format('H:i:s'));

        $diff = $start->diff($end);
        return ($diff->h * 60) + $diff->i;
    }

    /**
     * Vérifie si cette disponibilité est valide à une date donnée
     */
    public function isValidOn(\DateTimeInterface $date): bool
    {
        // Vérifier si la disponibilité est active
        if (!$this->isActive || !$this->isAvailable) {
            return false;
        }

        // Vérifier validFrom
        if ($this->validFrom && $date < $this->validFrom) {
            return false;
        }

        // Vérifier validUntil
        if ($this->validUntil && $date > $this->validUntil) {
            return false;
        }

        // Si c'est une date spécifique, vérifier qu'elle correspond
        if ($this->specificDate) {
            return $date->format('Y-m-d') === $this->specificDate->format('Y-m-d');
        }

        // Si c'est récurrent, vérifier le jour de la semaine
        if ($this->isRecurring && $this->dayOfWeek !== null) {
            return (int)$date->format('w') === $this->dayOfWeek;
        }

        return false;
    }

    /**
     * Retourne une description lisible de la disponibilité
     */
    public function getDescription(): string
    {
        $timeRange = sprintf(
            '%s - %s',
            $this->startTime?->format('H:i') ?? 'N/A',
            $this->endTime?->format('H:i') ?? 'N/A'
        );

        if ($this->specificDate) {
            return sprintf(
                '%s le %s',
                $timeRange,
                $this->specificDate->format('d/m/Y')
            );
        }

        if ($this->dayOfWeek !== null) {
            return sprintf(
                '%s chaque %s',
                $timeRange,
                $this->getDayOfWeekName()
            );
        }

        return $timeRange;
    }

    /**
     * Vérifie si cette disponibilité chevauche une autre
     */
    public function overlaps(Availability $other): bool
    {
        // Si les prestataires sont différents, pas de chevauchement
        if ($this->prestataire !== $other->getPrestataire()) {
            return false;
        }

        // Si l'une est récurrente et l'autre ponctuelle
        if ($this->isRecurring !== $other->isRecurring()) {
            // Vérifier si la date spécifique correspond au jour récurrent
            if ($this->isRecurring && $other->getSpecificDate()) {
                $specificDayOfWeek = (int)$other->getSpecificDate()->format('w');
                if ($this->dayOfWeek !== $specificDayOfWeek) {
                    return false;
                }
            } elseif ($other->isRecurring() && $this->specificDate) {
                $specificDayOfWeek = (int)$this->specificDate->format('w');
                if ($other->getDayOfWeek() !== $specificDayOfWeek) {
                    return false;
                }
            }
        }

        // Les deux sont récurrentes
        if ($this->isRecurring && $other->isRecurring()) {
            if ($this->dayOfWeek !== $other->getDayOfWeek()) {
                return false;
            }
        }

        // Les deux sont ponctuelles
        if (!$this->isRecurring && !$other->isRecurring()) {
            if ($this->specificDate && $other->getSpecificDate()) {
                if ($this->specificDate->format('Y-m-d') !== $other->getSpecificDate()->format('Y-m-d')) {
                    return false;
                }
            }
        }

        // Vérifier le chevauchement horaire
        return $this->timeRangeOverlaps(
            $this->startTime,
            $this->endTime,
            $other->getStartTime(),
            $other->getEndTime()
        );
    }

    /**
     * Vérifie si deux plages horaires se chevauchent
     */
    private function timeRangeOverlaps(
        ?\DateTimeInterface $start1,
        ?\DateTimeInterface $end1,
        ?\DateTimeInterface $start2,
        ?\DateTimeInterface $end2
    ): bool {
        if (!$start1 || !$end1 || !$start2 || !$end2) {
            return false;
        }

        $s1 = new \DateTime($start1->format('H:i:s'));
        $e1 = new \DateTime($end1->format('H:i:s'));
        $s2 = new \DateTime($start2->format('H:i:s'));
        $e2 = new \DateTime($end2->format('H:i:s'));

        return ($s1 < $e2) && ($s2 < $e1);
    }

    /**
     * Clone cette disponibilité pour un autre prestataire
     */
    public function cloneForPrestataire(Prestataire $prestataire): self
    {
        $clone = new self();
        $clone->setPrestataire($prestataire);
        $clone->setDayOfWeek($this->dayOfWeek);
        $clone->setSpecificDate($this->specificDate);
        $clone->setStartTime($this->startTime);
        $clone->setEndTime($this->endTime);
        $clone->setIsRecurring($this->isRecurring);
        $clone->setIsAvailable($this->isAvailable);
        $clone->setIsActive($this->isActive);
        $clone->setMaxBookingsPerSlot($this->maxBookingsPerSlot);
        $clone->setPriority($this->priority);
        $clone->setValidFrom($this->validFrom);
        $clone->setValidUntil($this->validUntil);
        $clone->setNotes($this->notes);
        
        return $clone;
    }

    public function __toString(): string
    {
        return $this->getDescription();
    }
}