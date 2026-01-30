<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class BookingFilterDTO
{
    #[Assert\Choice(
        choices: ['scheduled', 'confirmed', 'in_progress', 'completed', 'cancelled'],
        message: 'Le statut doit être l\'un des suivants: scheduled, confirmed, in_progress, completed, cancelled'
    )]
    private ?string $status = null;

    #[Assert\Date(message: 'La date de début doit être une date valide')]
    private ?string $startDate = null;

    #[Assert\Date(message: 'La date de fin doit être une date valide')]
    private ?string $endDate = null;

    #[Assert\Positive(message: 'L\'ID du client doit être un nombre positif')]
    private ?int $clientId = null;

    #[Assert\Positive(message: 'L\'ID du prestataire doit être un nombre positif')]
    private ?int $prestataireId = null;

    #[Assert\Choice(
        choices: ['nettoyage', 'repassage', 'combine'],
        message: 'La catégorie doit être l\'une des suivantes: nettoyage, repassage, combine'
    )]
    private ?string $category = null;

    #[Assert\Type(type: 'bool', message: 'La valeur récurrent doit être un booléen')]
    private ?bool $isRecurring = null;

    #[Assert\Choice(
        choices: ['date_asc', 'date_desc', 'amount_asc', 'amount_desc', 'status'],
        message: 'Le tri doit être l\'un des suivants: date_asc, date_desc, amount_asc, amount_desc, status'
    )]
    private string $sortBy = 'date_desc';

    #[Assert\Positive(message: 'La page doit être un nombre positif')]
    #[Assert\LessThanOrEqual(value: 1000, message: 'La page ne peut pas dépasser 1000')]
    private int $page = 1;

    #[Assert\Positive(message: 'La limite doit être un nombre positif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'La limite ne peut pas dépasser 100')]
    private int $limit = 20;

    // Getters
    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate ? new \DateTime($this->startDate) : null;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate ? new \DateTime($this->endDate) : null;
    }

    public function getClientId(): ?int
    {
        return $this->clientId;
    }

    public function getPrestataireId(): ?int
    {
        return $this->prestataireId;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function getIsRecurring(): ?bool
    {
        return $this->isRecurring;
    }

    public function getSortBy(): string
    {
        return $this->sortBy;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->limit;
    }

    // Setters
    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function setStartDate(?string $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function setEndDate(?string $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function setClientId(?int $clientId): self
    {
        $this->clientId = $clientId;
        return $this;
    }

    public function setPrestataireId(?int $prestataireId): self
    {
        $this->prestataireId = $prestataireId;
        return $this;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function setIsRecurring(?bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function setSortBy(string $sortBy): self
    {
        $this->sortBy = $sortBy;
        return $this;
    }

    public function setPage(int $page): self
    {
        $this->page = max(1, $page);
        return $this;
    }

    public function setLimit(int $limit): self
    {
        $this->limit = min(100, max(1, $limit));
        return $this;
    }

    /**
     * Charge les filtres depuis un tableau (ex: query parameters)
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        if (isset($data['status'])) {
            $dto->setStatus($data['status']);
        }

        if (isset($data['startDate'])) {
            $dto->setStartDate($data['startDate']);
        }

        if (isset($data['endDate'])) {
            $dto->setEndDate($data['endDate']);
        }

        if (isset($data['clientId'])) {
            $dto->setClientId((int) $data['clientId']);
        }

        if (isset($data['prestataireId'])) {
            $dto->setPrestataireId((int) $data['prestataireId']);
        }

        if (isset($data['category'])) {
            $dto->setCategory($data['category']);
        }

        if (isset($data['isRecurring'])) {
            $dto->setIsRecurring(filter_var($data['isRecurring'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($data['sortBy'])) {
            $dto->setSortBy($data['sortBy']);
        }

        if (isset($data['page'])) {
            $dto->setPage((int) $data['page']);
        }

        if (isset($data['limit'])) {
            $dto->setLimit((int) $data['limit']);
        }

        return $dto;
    }

    /**
     * Vérifie si des filtres sont actifs
     */
    public function hasFilters(): bool
    {
        return $this->status !== null
            || $this->startDate !== null
            || $this->endDate !== null
            || $this->clientId !== null
            || $this->prestataireId !== null
            || $this->category !== null
            || $this->isRecurring !== null;
    }

    /**
     * Retourne les filtres actifs sous forme de tableau
     */
    public function toArray(): array
    {
        $filters = [];

        if ($this->status !== null) {
            $filters['status'] = $this->status;
        }

        if ($this->startDate !== null) {
            $filters['startDate'] = $this->startDate;
        }

        if ($this->endDate !== null) {
            $filters['endDate'] = $this->endDate;
        }

        if ($this->clientId !== null) {
            $filters['clientId'] = $this->clientId;
        }

        if ($this->prestataireId !== null) {
            $filters['prestataireId'] = $this->prestataireId;
        }

        if ($this->category !== null) {
            $filters['category'] = $this->category;
        }

        if ($this->isRecurring !== null) {
            $filters['isRecurring'] = $this->isRecurring;
        }

        $filters['sortBy'] = $this->sortBy;
        $filters['page'] = $this->page;
        $filters['limit'] = $this->limit;

        return $filters;
    }
}