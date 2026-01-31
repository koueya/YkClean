<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class UpdateAbsenceDTO
{
    #[Assert\Date(message: 'Format de date invalide')]
    private ?string $startDate = null;

    #[Assert\Date(message: 'Format de date invalide')]
    private ?string $endDate = null;

    #[Assert\Choice(
        choices: ['vacation', 'sick_leave', 'personal', 'training', 'other'],
        message: 'Raison invalide'
    )]
    private ?string $reason = null;

    #[Assert\Length(max: 500, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    private ?string $description = null;

    public static function fromArray(array $data): self
    {
        $dto = new self();
        $dto->startDate = $data['start_date'] ?? null;
        $dto->endDate = $data['end_date'] ?? null;
        $dto->reason = $data['reason'] ?? null;
        $dto->description = $data['description'] ?? null;

        return $dto;
    }

    public function getStartDate(): ?string
    {
        return $this->startDate;
    }

    public function setStartDate(?string $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?string
    {
        return $this->endDate;
    }

    public function setEndDate(?string $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): self
    {
        $this->reason = $reason;
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
}