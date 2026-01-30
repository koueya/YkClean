<?php

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

class CreateQuoteDTO
{
    #[Assert\NotBlank(message: 'L\'ID de la demande de service est requis')]
    #[Assert\Positive(message: 'L\'ID de la demande de service doit être un nombre positif')]
    private ?int $serviceRequestId = null;

    #[Assert\NotBlank(message: 'Le montant est requis')]
    #[Assert\Positive(message: 'Le montant doit être supérieur à 0')]
    #[Assert\Range(
        min: 10,
        max: 10000,
        notInRangeMessage: 'Le montant doit être entre {{ min }}€ et {{ max }}€'
    )]
    private ?float $amount = null;

    #[Assert\NotBlank(message: 'La date proposée est requise')]
    #[Assert\Date(message: 'La date proposée doit être une date valide au format YYYY-MM-DD')]
    private ?string $proposedDate = null;

    #[Assert\NotBlank(message: 'L\'heure proposée est requise')]
    #[Assert\Regex(
        pattern: '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/',
        message: 'L\'heure proposée doit être au format HH:MM (ex: 14:30)'
    )]
    private ?string $proposedTime = null;

    #[Assert\NotBlank(message: 'La durée estimée est requise')]
    #[Assert\Positive(message: 'La durée doit être supérieure à 0')]
    #[Assert\Range(
        min: 1,
        max: 12,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} heures'
    )]
    private ?float $proposedDuration = null;

    #[Assert\NotBlank(message: 'La description est requise')]
    #[Assert\Length(
        min: 50,
        max: 2000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $description = null;

    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les conditions ne peuvent pas dépasser {{ limit }} caractères'
    )]
    private ?string $conditions = null;

    #[Assert\Positive(message: 'La durée de validité doit être supérieure à 0')]
    #[Assert\Range(
        min: 1,
        max: 30,
        notInRangeMessage: 'La durée de validité doit être entre {{ min }} et {{ max }} jours'
    )]
    private int $validityDays = 7;

    #[Assert\Type(type: 'bool', message: 'includesMaterials doit être un booléen')]
    private bool $includesMaterials = false;

    #[Assert\Length(
        max: 500,
        maxMessage: 'Les détails des fournitures ne peuvent pas dépasser {{ limit }} caractères'
    )]
    private ?string $materialsDetails = null;

    #[Assert\Positive(message: 'Le coût des fournitures doit être supérieur à 0')]
    #[Assert\Range(
        min: 0,
        max: 500,
        notInRangeMessage: 'Le coût des fournitures doit être entre {{ min }}€ et {{ max }}€'
    )]
    private ?float $materialsCost = null;

    #[Assert\Type(type: 'bool', message: 'requiresDeposit doit être un booléen')]
    private bool $requiresDeposit = false;

    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le pourcentage d\'acompte doit être entre {{ min }}% et {{ max }}%'
    )]
    private ?float $depositPercentage = null;

    #[Assert\Length(
        max: 500,
        maxMessage: 'Les notes privées ne peuvent pas dépasser {{ limit }} caractères'
    )]
    private ?string $privateNotes = null;

    // Getters
    public function getServiceRequestId(): ?int
    {
        return $this->serviceRequestId;
    }

    public function getAmount(): ?float
    {
        return $this->amount;
    }

    public function getProposedDate(): ?string
    {
        return $this->proposedDate;
    }

    public function getProposedDateAsDateTime(): ?\DateTimeInterface
    {
        if ($this->proposedDate === null) {
            return null;
        }
        return new \DateTime($this->proposedDate);
    }

    public function getProposedTime(): ?string
    {
        return $this->proposedTime;
    }

    public function getProposedDateTime(): ?\DateTimeInterface
    {
        if ($this->proposedDate === null || $this->proposedTime === null) {
            return null;
        }
        return new \DateTime($this->proposedDate . ' ' . $this->proposedTime);
    }

    public function getProposedDuration(): ?float
    {
        return $this->proposedDuration;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getConditions(): ?string
    {
        return $this->conditions;
    }

    public function getValidityDays(): int
    {
        return $this->validityDays;
    }

    public function getValidUntil(): \DateTimeInterface
    {
        $validUntil = new \DateTime();
        $validUntil->modify('+' . $this->validityDays . ' days');
        return $validUntil;
    }

    public function getIncludesMaterials(): bool
    {
        return $this->includesMaterials;
    }

    public function getMaterialsDetails(): ?string
    {
        return $this->materialsDetails;
    }

    public function getMaterialsCost(): ?float
    {
        return $this->materialsCost;
    }

    public function getRequiresDeposit(): bool
    {
        return $this->requiresDeposit;
    }

    public function getDepositPercentage(): ?float
    {
        return $this->depositPercentage;
    }

    public function getDepositAmount(): ?float
    {
        if ($this->requiresDeposit && $this->depositPercentage !== null && $this->amount !== null) {
            return round(($this->amount * $this->depositPercentage) / 100, 2);
        }
        return null;
    }

    public function getPrivateNotes(): ?string
    {
        return $this->privateNotes;
    }

    public function getServiceCost(): ?float
    {
        if ($this->amount === null) {
            return null;
        }

        $serviceCost = $this->amount;
        
        if ($this->includesMaterials && $this->materialsCost !== null) {
            $serviceCost -= $this->materialsCost;
        }

        return round($serviceCost, 2);
    }

    // Setters
    public function setServiceRequestId(?int $serviceRequestId): self
    {
        $this->serviceRequestId = $serviceRequestId;
        return $this;
    }

    public function setAmount(?float $amount): self
    {
        $this->amount = $amount;
        return $this;
    }

    public function setProposedDate(?string $proposedDate): self
    {
        $this->proposedDate = $proposedDate;
        return $this;
    }

    public function setProposedTime(?string $proposedTime): self
    {
        $this->proposedTime = $proposedTime;
        return $this;
    }

    public function setProposedDuration(?float $proposedDuration): self
    {
        $this->proposedDuration = $proposedDuration;
        return $this;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function setConditions(?string $conditions): self
    {
        $this->conditions = $conditions;
        return $this;
    }

    public function setValidityDays(int $validityDays): self
    {
        $this->validityDays = $validityDays;
        return $this;
    }

    public function setIncludesMaterials(bool $includesMaterials): self
    {
        $this->includesMaterials = $includesMaterials;
        return $this;
    }

    public function setMaterialsDetails(?string $materialsDetails): self
    {
        $this->materialsDetails = $materialsDetails;
        return $this;
    }

    public function setMaterialsCost(?float $materialsCost): self
    {
        $this->materialsCost = $materialsCost;
        return $this;
    }

    public function setRequiresDeposit(bool $requiresDeposit): self
    {
        $this->requiresDeposit = $requiresDeposit;
        return $this;
    }

    public function setDepositPercentage(?float $depositPercentage): self
    {
        $this->depositPercentage = $depositPercentage;
        return $this;
    }

    public function setPrivateNotes(?string $privateNotes): self
    {
        $this->privateNotes = $privateNotes;
        return $this;
    }

    /**
     * Charge les données depuis un tableau
     */
    public static function fromArray(array $data): self
    {
        $dto = new self();

        if (isset($data['serviceRequestId'])) {
            $dto->setServiceRequestId((int) $data['serviceRequestId']);
        }

        if (isset($data['amount'])) {
            $dto->setAmount((float) $data['amount']);
        }

        if (isset($data['proposedDate'])) {
            $dto->setProposedDate($data['proposedDate']);
        }

        if (isset($data['proposedTime'])) {
            $dto->setProposedTime($data['proposedTime']);
        }

        if (isset($data['proposedDuration'])) {
            $dto->setProposedDuration((float) $data['proposedDuration']);
        }

        if (isset($data['description'])) {
            $dto->setDescription($data['description']);
        }

        if (isset($data['conditions'])) {
            $dto->setConditions($data['conditions']);
        }

        if (isset($data['validityDays'])) {
            $dto->setValidityDays((int) $data['validityDays']);
        }

        if (isset($data['includesMaterials'])) {
            $dto->setIncludesMaterials(filter_var($data['includesMaterials'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($data['materialsDetails'])) {
            $dto->setMaterialsDetails($data['materialsDetails']);
        }

        if (isset($data['materialsCost'])) {
            $dto->setMaterialsCost((float) $data['materialsCost']);
        }

        if (isset($data['requiresDeposit'])) {
            $dto->setRequiresDeposit(filter_var($data['requiresDeposit'], FILTER_VALIDATE_BOOLEAN));
        }

        if (isset($data['depositPercentage'])) {
            $dto->setDepositPercentage((float) $data['depositPercentage']);
        }

        if (isset($data['privateNotes'])) {
            $dto->setPrivateNotes($data['privateNotes']);
        }

        return $dto;
    }

    /**
     * Validation personnalisée
     */
    #[Assert\Callback]
    public function validate(\Symfony\Component\Validator\Context\ExecutionContextInterface $context): void
    {
        // Vérifier que la date/heure proposée est dans le futur
        if ($this->proposedDate !== null && $this->proposedTime !== null) {
            $proposedDateTime = $this->getProposedDateTime();
            $now = new \DateTime();

            if ($proposedDateTime <= $now) {
                $context->buildViolation('La date et l\'heure proposées doivent être dans le futur')
                    ->atPath('proposedDate')
                    ->addViolation();
            }

            // Vérifier qu'on ne propose pas un créneau dans plus de 3 mois
            $maxDate = (clone $now)->modify('+3 months');
            if ($proposedDateTime > $maxDate) {
                $context->buildViolation('La date proposée ne peut pas être dans plus de 3 mois')
                    ->atPath('proposedDate')
                    ->addViolation();
            }
        }

        // Si fournitures incluses, vérifier que les détails et le coût sont fournis
        if ($this->includesMaterials) {
            if (empty($this->materialsDetails)) {
                $context->buildViolation('Les détails des fournitures sont requis si elles sont incluses')
                    ->atPath('materialsDetails')
                    ->addViolation();
            }

            if ($this->materialsCost === null || $this->materialsCost <= 0) {
                $context->buildViolation('Le coût des fournitures doit être spécifié et supérieur à 0')
                    ->atPath('materialsCost')
                    ->addViolation();
            }

            // Vérifier que le coût des fournitures n'excède pas 50% du montant total
            if ($this->amount !== null && $this->materialsCost !== null) {
                $maxMaterialsCost = $this->amount * 0.5;
                if ($this->materialsCost > $maxMaterialsCost) {
                    $context->buildViolation('Le coût des fournitures ne peut pas excéder 50% du montant total')
                        ->atPath('materialsCost')
                        ->addViolation();
                }
            }
        }

        // Si acompte requis, vérifier le pourcentage
        if ($this->requiresDeposit) {
            if ($this->depositPercentage === null || $this->depositPercentage <= 0) {
                $context->buildViolation('Le pourcentage d\'acompte doit être spécifié et supérieur à 0')
                    ->atPath('depositPercentage')
                    ->addViolation();
            }
        }

        // Vérifier la cohérence du tarif horaire
        if ($this->amount !== null && $this->proposedDuration !== null) {
            $hourlyRate = $this->amount / $this->proposedDuration;
            
            // Tarif horaire trop bas (< 15€/h)
            if ($hourlyRate < 15) {
                $context->buildViolation('Le tarif horaire ne peut pas être inférieur à 15€/h')
                    ->atPath('amount')
                    ->addViolation();
            }

            // Tarif horaire trop élevé (> 100€/h)
            if ($hourlyRate > 100) {
                $context->buildViolation('Le tarif horaire ne peut pas dépasser 100€/h')
                    ->atPath('amount')
                    ->addViolation();
            }
        }
    }

    /**
     * Retourne un résumé du devis
     */
    public function getSummary(): array
    {
        return [
            'amount' => $this->amount,
            'proposedDateTime' => $this->getProposedDateTime()?->format('d/m/Y à H:i'),
            'duration' => $this->proposedDuration . ' heure(s)',
            'hourlyRate' => $this->amount && $this->proposedDuration 
                ? round($this->amount / $this->proposedDuration, 2) . '€/h'
                : null,
            'includesMaterials' => $this->includesMaterials,
            'materialsCost' => $this->materialsCost,
            'serviceCost' => $this->getServiceCost(),
            'requiresDeposit' => $this->requiresDeposit,
            'depositAmount' => $this->getDepositAmount(),
            'validUntil' => $this->getValidUntil()->format('d/m/Y'),
        ];
    }

    /**
     * Convertit le DTO en tableau
     */
    public function toArray(): array
    {
        return [
            'serviceRequestId' => $this->serviceRequestId,
            'amount' => $this->amount,
            'proposedDate' => $this->proposedDate,
            'proposedTime' => $this->proposedTime,
            'proposedDuration' => $this->proposedDuration,
            'description' => $this->description,
            'conditions' => $this->conditions,
            'validityDays' => $this->validityDays,
            'includesMaterials' => $this->includesMaterials,
            'materialsDetails' => $this->materialsDetails,
            'materialsCost' => $this->materialsCost,
            'requiresDeposit' => $this->requiresDeposit,
            'depositPercentage' => $this->depositPercentage,
            'privateNotes' => $this->privateNotes,
        ];
    }
}