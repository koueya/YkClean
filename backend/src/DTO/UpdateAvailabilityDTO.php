<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAvailabilityDTO
{
    #[Assert\NotBlank(message: 'L\'ID de la disponibilité est requis')]
    #[Assert\Positive(message: 'L\'ID doit être un nombre positif')]
    private ?int $id = null;

    #[Assert\Range(
        min: 0,
        max: 6,
        notInRangeMessage: 'Le jour de la semaine doit être entre 0 (dimanche) et 6 (samedi)'
    )]
    private ?int $dayOfWeek = null;

    #[Assert\Regex(
        pattern: '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
        message: 'L\'heure de début doit être au format HH:MM (ex: 09:00)'
    )]
    private ?string $startTime = null;

    #[Assert\Regex(
        pattern: '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
        message: 'L\'heure de fin doit être au format HH:MM (ex: 17:00)'
    )]
    private ?string $endTime = null;

    #[Assert\Type(type: 'bool', message: 'isRecurring doit être un booléen')]
    private ?bool $isRecurring = null;

    #[Assert\Date(message: 'La date spécifique doit être une date valide au format YYYY-MM-DD')]
    private ?string $specificDate = null;

    #[Assert\Type(type: 'bool', message: 'isActive doit être un booléen')]
    private ?bool $isActive = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères'
    )]
    private ?string $notes = null;

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function getStartTime(): ?string
    {
        return $this->startTime;
    }

    public function getStartTimeAsDateTime(): ?\DateTimeInterface
    {
        if ($this->startTime === null) {
            return null;
        }
        return \DateTime::createFromFormat('H:i', $this->startTime);
    }

    public function getEndTime(): ?string
    {
        return $this->endTime;
    }

    public function getEndTimeAsDateTime(): ?\DateTimeInterface
    {
        if ($this->endTime === null) {
            return null;
        }
        return \DateTime::createFromFormat('H:i', $this->endTime);
    }

    public function getIsRecurring(): ?bool
    {
        return $this->isRecurring;
    }

    public function getSpecificDate(): ?string
    {
        return $this->specificDate;
    }

    public function getSpecificDateAsDateTime(): ?\DateTimeInterface
    {
        if ($this->specificDate === null) {
            return null;
        }
        return new \DateTime($this->specificDate);
    }

    public function getIsActive(): ?bool
    {
        return $this->isActive;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    // Setters
    public function setId(?int $id): self
    {
        $this->id = $id;
        return $this;
    }

    public function setDayOfWeek(?int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function setStartTime(?string $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function setEndTime(?string $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function setIsRecurring(?bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function setSpecificDate(?string $specificDate): self
    {
        $this->specificDate = $specificDate;
        return $this;
    }

    public function setIsActive(?bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    /**
     * Charge les données depuis un tableau
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        if (isset($data['id'])) {
            $dto->setId((int) $data['id']);
        }

        if (isset($data['dayOfWeek'])) {
            $dto->setDayOfWeek((int) $data['dayOfWeek']);
        }

        if (isset($data['startTime'])) {
            $dto->setStartTime($data['startTime']);
        }

        if (isset($data['endTime'])) {
            $dto->setEndTime($data['endTime']);
        }

        if (isset($data['isRecurring'])) {
            $dto->setIsRecurring(filter_var($data['isRecurring'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($data['specificDate'])) {
            $dto->setSpecificDate($data['specificDate']);
        }

        if (isset($data['isActive'])) {
            $dto->setIsActive(filter_var($data['isActive'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($data['notes'])) {
            $dto->setNotes($data['notes']);
        }

        return $dto;
    }

    /**
     * Vérifie si des données ont été modifiées
     */
    public function hasUpdates(): bool
    {
        return $this->dayOfWeek !== null
            || $this->startTime !== null
            || $this->endTime !== null
            || $this->isRecurring !== null
            || $this->specificDate !== null
            || $this->isActive !== null
            || $this->notes !== null;
    }

    /**
     * Retourne les champs modifiés sous forme de tableau
     */
    public function getUpdatedFields(): array
    {
        $fields = [];

        if ($this->dayOfWeek !== null) {
            $fields['dayOfWeek'] = $this->dayOfWeek;
        }

        if ($this->startTime !== null) {
            $fields['startTime'] = $this->startTime;
        }

        if ($this->endTime !== null) {
            $fields['endTime'] = $this->endTime;
        }

        if ($this->isRecurring !== null) {
            $fields['isRecurring'] = $this->isRecurring;
        }

        if ($this->specificDate !== null) {
            $fields['specificDate'] = $this->specificDate;
        }

        if ($this->isActive !== null) {
            $fields['isActive'] = $this->isActive;
        }

        if ($this->notes !== null) {
            $fields['notes'] = $this->notes;
        }

        return $fields;
    }

    /**
     * Valide la cohérence des horaires
     */
    #[Assert\Callback]
    public function validate(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        // Vérifier que l'heure de fin est après l'heure de début
        if ($this->startTime !== null && $this->endTime !== null) {
            $start = \DateTime::createFromFormat('H:i', $this->startTime);
            $end = \DateTime::createFromFormat('H:i', $this->endTime);

            if ($start && $end && $end <= $start) {
                $context->buildViolation('L\'heure de fin doit être après l\'heure de début')
                    ->atPath('endTime')
                    ->addViolation();
            }

            // Vérifier la durée minimale (au moins 1 heure)
            if ($start && $end) {
                $diff = $start->diff($end);
                $hours = $diff->h + ($diff->days * 24);
                
                if ($hours < 1) {
                    $context->buildViolation('La plage horaire doit être d\'au moins 1 heure')
                        ->atPath('endTime')
                        ->addViolation();
                }
            }
        }

        // Vérifier la cohérence entre récurrent et date spécifique
        if ($this->isRecurring === true && $this->specificDate !== null) {
            $context->buildViolation('Une disponibilité récurrente ne peut pas avoir de date spécifique')
                ->atPath('specificDate')
                ->addViolation();
        }

        if ($this->isRecurring === false && $this->dayOfWeek !== null && $this->specificDate === null) {
            $context->buildViolation('Une disponibilité ponctuelle doit avoir une date spécifique')
                ->atPath('specificDate')
                ->addViolation();
        }

        // Vérifier que la date spécifique est dans le futur
        if ($this->specificDate !== null) {
            $date = new \DateTime($this->specificDate);
            $now = new \DateTime('today');

            if ($date < $now) {
                $context->buildViolation('La date spécifique doit être dans le futur')
                    ->atPath('specificDate')
                    ->addViolation();
            }
        }
    }

    /**
     * Retourne une représentation lisible de la disponibilité
     */
    public function getReadableSchedule(): string
    {
        if ($this->specificDate !== null) {
            $date = new \DateTime($this->specificDate);
            return sprintf(
                'Le %s de %s à %s',
                $date->format('d/m/Y'),
                $this->startTime ?? '??:??',
                $this->endTime ?? '??:??'
            );
        }

        if ($this->dayOfWeek !== null) {
            $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
            return sprintf(
                '%s de %s à %s%s',
                $days[$this->dayOfWeek],
                $this->startTime ?? '??:??',
                $this->endTime ?? '??:??',
                $this->isRecurring ? ' (chaque semaine)' : ''
            );
        }

        return 'Disponibilité non définie';
    }
}