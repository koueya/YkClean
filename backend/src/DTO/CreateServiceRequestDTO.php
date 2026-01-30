<?php

declare(strict_types=1);

namespace App\DTO;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * DTO pour la création d'une demande de service
 */
class CreateServiceRequestDTO
{
    /**
     * Catégorie de service demandée
     */
    #[Assert\NotBlank(message: 'La catégorie de service est obligatoire.')]
    #[Assert\Choice(
        choices: ['nettoyage', 'repassage', 'nettoyage_repassage', 'menage_complet', 'vitres'],
        message: 'La catégorie "{{ value }}" n\'est pas valide.'
    )]
    public string $category;

    /**
     * Description détaillée de la demande
     */
    #[Assert\NotBlank(message: 'La description est obligatoire.')]
    #[Assert\Length(
        min: 20,
        max: 1000,
        minMessage: 'La description doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères.'
    )]
    public string $description;

    /**
     * Adresse complète du service
     */
    #[Assert\NotBlank(message: 'L\'adresse est obligatoire.')]
    public string $address;

    /**
     * Complément d\'adresse (appartement, étage, etc.)
     */
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le complément d\'adresse ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $addressComplement = null;

    /**
     * Code postal
     */
    #[Assert\NotBlank(message: 'Le code postal est obligatoire.')]
    #[Assert\Regex(
        pattern: '/^[0-9]{5}$/',
        message: 'Le code postal doit contenir exactement 5 chiffres.'
    )]
    public string $postalCode;

    /**
     * Ville
     */
    #[Assert\NotBlank(message: 'La ville est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom de la ville doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom de la ville ne peut pas dépasser {{ limit }} caractères.'
    )]
    public string $city;

    /**
     * Date préférée pour le service (format ISO 8601)
     */
    #[Assert\NotBlank(message: 'La date préférée est obligatoire.')]
    #[Assert\DateTime(
        format: \DateTimeInterface::ATOM,
        message: 'Le format de la date préférée n\'est pas valide. Utilisez le format ISO 8601.'
    )]
    public string $preferredDate;

    /**
     * Dates alternatives (max 3)
     */
    #[Assert\Count(
        max: 3,
        maxMessage: 'Vous ne pouvez pas fournir plus de {{ limit }} dates alternatives.'
    )]
    #[Assert\All([
        new Assert\DateTime(
            format: \DateTimeInterface::ATOM,
            message: 'Le format d\'une date alternative n\'est pas valide.'
        )
    ])]
    public array $alternativeDates = [];

    /**
     * Durée estimée en heures
     */
    #[Assert\NotBlank(message: 'La durée estimée est obligatoire.')]
    #[Assert\Type(type: 'integer', message: 'La durée doit être un nombre entier.')]
    #[Assert\Positive(message: 'La durée doit être positive.')]
    #[Assert\Range(
        min: 1,
        max: 8,
        notInRangeMessage: 'La durée doit être entre {{ min }} et {{ max }} heures.'
    )]
    public int $duration;

    /**
     * Fréquence du service
     */
    #[Assert\NotBlank(message: 'La fréquence est obligatoire.')]
    #[Assert\Choice(
        choices: ['ponctuel', 'hebdomadaire', 'bihebdomadaire', 'mensuel'],
        message: 'La fréquence "{{ value }}" n\'est pas valide.'
    )]
    public string $frequency;

    /**
     * Budget proposé en euros
     */
    #[Assert\Type(type: 'float', message: 'Le budget doit être un nombre.')]
    #[Assert\Positive(message: 'Le budget doit être positif.')]
    #[Assert\Range(
        min: 10,
        max: 500,
        notInRangeMessage: 'Le budget doit être entre {{ min }}€ et {{ max }}€.'
    )]
    public ?float $budget = null;

    /**
     * Préférences horaires (matin, après-midi, soir)
     */
    #[Assert\Choice(
        choices: ['matin', 'apres_midi', 'soir', 'flexible'],
        message: 'La préférence horaire "{{ value }}" n\'est pas valide.'
    )]
    public ?string $timePreference = 'flexible';

    /**
     * Nombre de pièces (pour catégories concernées)
     */
    #[Assert\Type(type: 'integer', message: 'Le nombre de pièces doit être un nombre entier.')]
    #[Assert\Positive(message: 'Le nombre de pièces doit être positif.')]
    #[Assert\Range(
        min: 1,
        max: 20,
        notInRangeMessage: 'Le nombre de pièces doit être entre {{ min }} et {{ max }}.'
    )]
    public ?int $numberOfRooms = null;

    /**
     * Surface en m² (pour catégories concernées)
     */
    #[Assert\Type(type: 'integer', message: 'La surface doit être un nombre entier.')]
    #[Assert\Positive(message: 'La surface doit être positive.')]
    #[Assert\Range(
        min: 10,
        max: 500,
        notInRangeMessage: 'La surface doit être entre {{ min }}m² et {{ max }}m².'
    )]
    public ?int $surface = null;

    /**
     * Présence d\'animaux de compagnie
     */
    #[Assert\Type(type: 'bool', message: 'La présence d\'animaux doit être un booléen.')]
    public bool $hasPets = false;

    /**
     * Type d\'animaux (si présents)
     */
    #[Assert\Length(
        max: 255,
        maxMessage: 'La description des animaux ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $petDetails = null;

    /**
     * Présence d\'allergies ou restrictions
     */
    public bool $hasAllergies = false;

    /**
     * Détails des allergies
     */
    #[Assert\Length(
        max: 500,
        maxMessage: 'Les détails des allergies ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    public ?string $allergyDetails = null;

    /**
     * Matériel fourni par le client
     */
    public bool $equipmentProvided = false;

    /**
     * Liste du matériel fourni
     */
    #[Assert\Length(
        max: 500,
        maxMessage: 'La liste du matériel ne peut pas dépasser {{ limit }} caractères.'
    )]
    public ?string $equipmentList = null;

    /**
     * Accès au logement (code, clé, présence)
     */
    #[Assert\NotBlank(message: 'Le type d\'accès est obligatoire.')]
    #[Assert\Choice(
        choices: ['present', 'cle', 'code', 'gardien', 'autre'],
        message: 'Le type d\'accès "{{ value }}" n\'est pas valide.'
    )]
    public string $accessType = 'present';

    /**
     * Détails de l\'accès (code, instructions, etc.)
     */
    #[Assert\Length(
        max: 500,
        maxMessage: 'Les détails d\'accès ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    public ?string $accessDetails = null;

    /**
     * Instructions spéciales
     */
    #[Assert\Length(
        max: 1000,
        maxMessage: 'Les instructions spéciales ne peuvent pas dépasser {{ limit }} caractères.'
    )]
    public ?string $specialInstructions = null;

    /**
     * Urgence de la demande
     */
    public bool $isUrgent = false;

    /**
     * Acceptation des conditions générales
     */
    #[Assert\IsTrue(message: 'Vous devez accepter les conditions générales.')]
    public bool $acceptTerms = false;

    /**
     * Constructeur
     */
    public function __construct(array $data = [])
    {
        foreach ($data as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /**
     * Convertit le DTO en tableau
     */
    public function toArray(): array
    {
        return [
            'category' => $this->category,
            'description' => $this->description,
            'address' => $this->address,
            'addressComplement' => $this->addressComplement,
            'postalCode' => $this->postalCode,
            'city' => $this->city,
            'preferredDate' => $this->preferredDate,
            'alternativeDates' => $this->alternativeDates,
            'duration' => $this->duration,
            'frequency' => $this->frequency,
            'budget' => $this->budget,
            'timePreference' => $this->timePreference,
            'numberOfRooms' => $this->numberOfRooms,
            'surface' => $this->surface,
            'hasPets' => $this->hasPets,
            'petDetails' => $this->petDetails,
            'hasAllergies' => $this->hasAllergies,
            'allergyDetails' => $this->allergyDetails,
            'equipmentProvided' => $this->equipmentProvided,
            'equipmentList' => $this->equipmentList,
            'accessType' => $this->accessType,
            'accessDetails' => $this->accessDetails,
            'specialInstructions' => $this->specialInstructions,
            'isUrgent' => $this->isUrgent,
            'acceptTerms' => $this->acceptTerms,
        ];
    }

    /**
     * Crée un DTO depuis un tableau de données
     */
    public static function fromArray(array $data): self
    {
        return new self($data);
    }

    /**
     * Retourne l'adresse complète formatée
     */
    public function getFullAddress(): string
    {
        $parts = [$this->address];
        
        if ($this->addressComplement) {
            $parts[] = $this->addressComplement;
        }
        
        $parts[] = $this->postalCode . ' ' . $this->city;
        
        return implode(', ', $parts);
    }

    /**
     * Vérifie si la demande nécessite des informations sur le nombre de pièces
     */
    public function requiresRoomCount(): bool
    {
        return in_array($this->category, ['nettoyage', 'menage_complet']);
    }

    /**
     * Vérifie si la demande nécessite des informations sur la surface
     */
    public function requiresSurface(): bool
    {
        return in_array($this->category, ['nettoyage', 'menage_complet', 'vitres']);
    }

    /**
     * Vérifie si la demande est récurrente
     */
    public function isRecurring(): bool
    {
        return $this->frequency !== 'ponctuel';
    }

    /**
     * Retourne le nombre de dates proposées (préférée + alternatives)
     */
    public function getTotalDatesProposed(): int
    {
        return 1 + count($this->alternativeDates);
    }

    /**
     * Retourne toutes les dates proposées (préférée en premier)
     */
    public function getAllProposedDates(): array
    {
        return array_merge([$this->preferredDate], $this->alternativeDates);
    }

    /**
     * Vérifie si un budget a été spécifié
     */
    public function hasBudget(): bool
    {
        return $this->budget !== null && $this->budget > 0;
    }

    /**
     * Vérifie si des instructions spéciales ont été fournies
     */
    public function hasSpecialInstructions(): bool
    {
        return !empty($this->specialInstructions);
    }

    /**
     * Retourne un score de complétude du formulaire (0-100)
     */
    public function getCompletenessScore(): int
    {
        $score = 0;
        $optionalFields = [
            'budget' => 10,
            'numberOfRooms' => 10,
            'surface' => 10,
            'petDetails' => 5,
            'allergyDetails' => 5,
            'equipmentList' => 5,
            'accessDetails' => 10,
            'specialInstructions' => 10,
            'alternativeDates' => 10,
        ];

        // Champs obligatoires valent 25 points de base
        $score = 25;

        foreach ($optionalFields as $field => $points) {
            if ($field === 'alternativeDates') {
                if (!empty($this->alternativeDates)) {
                    $score += $points;
                }
            } elseif (isset($this->$field) && !empty($this->$field)) {
                $score += $points;
            }
        }

        return min(100, $score);
    }

    /**
     * Valide la cohérence des données du DTO
     */
    public function validateConsistency(): array
    {
        $errors = [];

        // Vérification des animaux
        if ($this->hasPets && empty($this->petDetails)) {
            $errors[] = 'Veuillez préciser le type d\'animaux présents.';
        }

        // Vérification des allergies
        if ($this->hasAllergies && empty($this->allergyDetails)) {
            $errors[] = 'Veuillez préciser les allergies ou restrictions.';
        }

        // Vérification du matériel
        if ($this->equipmentProvided && empty($this->equipmentList)) {
            $errors[] = 'Veuillez lister le matériel fourni.';
        }

        // Vérification de l'accès
        if (in_array($this->accessType, ['code', 'autre']) && empty($this->accessDetails)) {
            $errors[] = 'Veuillez fournir les détails d\'accès au logement.';
        }

        // Vérification du nombre de pièces si requis
        if ($this->requiresRoomCount() && empty($this->numberOfRooms)) {
            $errors[] = 'Le nombre de pièces est requis pour ce type de service.';
        }

        // Vérification de la surface si requise
        if ($this->requiresSurface() && empty($this->surface)) {
            $errors[] = 'La surface est requise pour ce type de service.';
        }

        return $errors;
    }

    /**
     * Retourne un résumé lisible de la demande
     */
    public function getSummary(): string
    {
        $categoryLabels = [
            'nettoyage' => 'Nettoyage',
            'repassage' => 'Repassage',
            'nettoyage_repassage' => 'Nettoyage + Repassage',
            'menage_complet' => 'Ménage complet',
            'vitres' => 'Nettoyage de vitres',
        ];

        $frequencyLabels = [
            'ponctuel' => 'ponctuel',
            'hebdomadaire' => 'chaque semaine',
            'bihebdomadaire' => 'toutes les 2 semaines',
            'mensuel' => 'chaque mois',
        ];

        $summary = sprintf(
            '%s (%s) - %dh - %s',
            $categoryLabels[$this->category] ?? $this->category,
            $frequencyLabels[$this->frequency] ?? $this->frequency,
            $this->duration,
            $this->city
        );

        if ($this->hasBudget()) {
            $summary .= sprintf(' - Budget: %.2f€', $this->budget);
        }

        return $summary;
    }

    /**
     * Retourne les métadonnées de la demande
     */
    public function getMetadata(): array
    {
        return [
            'is_recurring' => $this->isRecurring(),
            'has_budget' => $this->hasBudget(),
            'has_pets' => $this->hasPets,
            'has_allergies' => $this->hasAllergies,
            'equipment_provided' => $this->equipmentProvided,
            'is_urgent' => $this->isUrgent,
            'completeness_score' => $this->getCompletenessScore(),
            'total_dates_proposed' => $this->getTotalDatesProposed(),
        ];
    }
}