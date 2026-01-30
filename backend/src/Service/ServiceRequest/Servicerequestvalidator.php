<?php

namespace App\Service\ServiceRequest;

use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceRequest;
use App\Repository\Service\ServiceRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class ServiceRequestValidator
{
    // Constantes de validation
    private const MIN_DESCRIPTION_LENGTH = 20;
    private const MAX_DESCRIPTION_LENGTH = 2000;
    private const MIN_BUDGET = 10;
    private const MAX_BUDGET = 10000;
    private const MIN_DURATION = 30; // minutes
    private const MAX_DURATION = 480; // 8 heures
    private const MAX_ACTIVE_REQUESTS_PER_CLIENT = 5;
    private const MIN_ADVANCE_BOOKING_HOURS = 24;
    private const MAX_ADVANCE_BOOKING_DAYS = 90;
    private const MAX_ALTERNATIVE_DATES = 3;

    private const VALID_CATEGORIES = [
        'nettoyage' => [
            'name' => 'Nettoyage',
            'minDuration' => 60,
            'maxDuration' => 480,
            'minBudget' => 15,
        ],
        'repassage' => [
            'name' => 'Repassage',
            'minDuration' => 60,
            'maxDuration' => 240,
            'minBudget' => 12,
        ],
        'menage_complet' => [
            'name' => 'Ménage complet',
            'minDuration' => 120,
            'maxDuration' => 480,
            'minBudget' => 25,
        ],
        'vitres' => [
            'name' => 'Nettoyage de vitres',
            'minDuration' => 60,
            'maxDuration' => 240,
            'minBudget' => 15,
        ],
        'jardinage' => [
            'name' => 'Jardinage',
            'minDuration' => 120,
            'maxDuration' => 480,
            'minBudget' => 20,
        ],
        'bricolage' => [
            'name' => 'Bricolage',
            'minDuration' => 60,
            'maxDuration' => 480,
            'minBudget' => 25,
        ],
        'garde_enfants' => [
            'name' => 'Garde d\'enfants',
            'minDuration' => 120,
            'maxDuration' => 600,
            'minBudget' => 15,
        ],
        'aide_personne_agee' => [
            'name' => 'Aide aux personnes âgées',
            'minDuration' => 60,
            'maxDuration' => 480,
            'minBudget' => 18,
        ],
        'cours_particuliers' => [
            'name' => 'Cours particuliers',
            'minDuration' => 60,
            'maxDuration' => 240,
            'minBudget' => 20,
        ],
        'autre' => [
            'name' => 'Autre',
            'minDuration' => 30,
            'maxDuration' => 480,
            'minBudget' => 10,
        ],
    ];

    private const VALID_FREQUENCIES = [
        'ponctuel' => 'Ponctuel',
        'hebdomadaire' => 'Hebdomadaire',
        'bi_hebdomadaire' => 'Bi-hebdomadaire',
        'mensuel' => 'Mensuel',
    ];

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRequestRepository $serviceRequestRepository,
        private LoggerInterface $logger
    ) {}

    /**
     * Valide complètement les données d'une demande de service
     */
    public function validateServiceRequestData(array $data, Client $client = null): array
    {
        $errors = [];

        // Validation de la catégorie
        $categoryErrors = $this->validateCategory($data);
        if (!empty($categoryErrors)) {
            $errors = array_merge($errors, $categoryErrors);
        }

        // Validation de la description
        $descriptionErrors = $this->validateDescription($data);
        if (!empty($descriptionErrors)) {
            $errors = array_merge($errors, $descriptionErrors);
        }

        // Validation de l'adresse
        $addressErrors = $this->validateAddress($data);
        if (!empty($addressErrors)) {
            $errors = array_merge($errors, $addressErrors);
        }

        // Validation des dates
        $dateErrors = $this->validateDates($data);
        if (!empty($dateErrors)) {
            $errors = array_merge($errors, $dateErrors);
        }

        // Validation de la durée
        if (isset($data['duration'])) {
            $durationErrors = $this->validateDuration($data['duration'], $data['category'] ?? null);
            if (!empty($durationErrors)) {
                $errors = array_merge($errors, $durationErrors);
            }
        }

        // Validation du budget
        if (isset($data['budget'])) {
            $budgetErrors = $this->validateBudget($data['budget'], $data['category'] ?? null);
            if (!empty($budgetErrors)) {
                $errors = array_merge($errors, $budgetErrors);
            }
        }

        // Validation de la fréquence
        $frequencyErrors = $this->validateFrequency($data);
        if (!empty($frequencyErrors)) {
            $errors = array_merge($errors, $frequencyErrors);
        }

        // Validation du client si fourni
        if ($client) {
            $clientErrors = $this->validateClientEligibility($client);
            if (!empty($clientErrors)) {
                $errors = array_merge($errors, $clientErrors);
            }
        }

        return $errors;
    }

    /**
     * Valide la catégorie de service
     */
    public function validateCategory(array $data): array
    {
        $errors = [];

        if (!isset($data['category']) || empty($data['category'])) {
            $errors['category'] = 'La catégorie de service est requise.';
            return $errors;
        }

        if (!array_key_exists($data['category'], self::VALID_CATEGORIES)) {
            $errors['category'] = 'La catégorie de service est invalide.';
        }

        return $errors;
    }

    /**
     * Valide la description
     */
    public function validateDescription(array $data): array
    {
        $errors = [];

        if (!isset($data['description']) || empty($data['description'])) {
            $errors['description'] = 'La description est requise.';
            return $errors;
        }

        $description = trim($data['description']);
        $length = mb_strlen($description);

        if ($length < self::MIN_DESCRIPTION_LENGTH) {
            $errors['description'] = sprintf(
                'La description doit contenir au moins %d caractères.',
                self::MIN_DESCRIPTION_LENGTH
            );
        }

        if ($length > self::MAX_DESCRIPTION_LENGTH) {
            $errors['description'] = sprintf(
                'La description ne peut pas dépasser %d caractères.',
                self::MAX_DESCRIPTION_LENGTH
            );
        }

        // Vérifier qu'il n'y a pas que des caractères spéciaux
        if (preg_match('/^[^a-zA-Z0-9]+$/', $description)) {
            $errors['description'] = 'La description doit contenir du texte significatif.';
        }

        return $errors;
    }

    /**
     * Valide l'adresse
     */
    public function validateAddress(array $data): array
    {
        $errors = [];

        if (!isset($data['address']) || empty($data['address'])) {
            $errors['address'] = 'L\'adresse est requise.';
            return $errors;
        }

        $address = $data['address'];

        // Si l'adresse est un tableau (structure complète)
        if (is_array($address)) {
            $requiredFields = ['street', 'city', 'postalCode'];
            
            foreach ($requiredFields as $field) {
                if (!isset($address[$field]) || empty($address[$field])) {
                    $errors["address.{$field}"] = sprintf('Le champ "%s" est requis dans l\'adresse.', $field);
                }
            }

            // Validation du code postal français
            if (isset($address['postalCode']) && !empty($address['postalCode'])) {
                if (!preg_match('/^[0-9]{5}$/', $address['postalCode'])) {
                    $errors['address.postalCode'] = 'Le code postal doit contenir 5 chiffres.';
                }
            }

            // Validation coordonnées GPS si fournies
            if (isset($address['latitude']) || isset($address['longitude'])) {
                if (!isset($address['latitude']) || !isset($address['longitude'])) {
                    $errors['address.coordinates'] = 'Les coordonnées GPS doivent être complètes (latitude et longitude).';
                } else {
                    if (!is_numeric($address['latitude']) || $address['latitude'] < -90 || $address['latitude'] > 90) {
                        $errors['address.latitude'] = 'La latitude doit être entre -90 et 90.';
                    }
                    if (!is_numeric($address['longitude']) || $address['longitude'] < -180 || $address['longitude'] > 180) {
                        $errors['address.longitude'] = 'La longitude doit être entre -180 et 180.';
                    }
                }
            }
        } elseif (is_string($address)) {
            // Si l'adresse est une chaîne simple
            if (strlen(trim($address)) < 10) {
                $errors['address'] = 'L\'adresse doit être plus détaillée (minimum 10 caractères).';
            }
        } else {
            $errors['address'] = 'Le format de l\'adresse est invalide.';
        }

        return $errors;
    }

    /**
     * Valide les dates (préférée et alternatives)
     */
    public function validateDates(array $data): array
    {
        $errors = [];
        $now = new \DateTime();

        // Date préférée requise
        if (!isset($data['preferredDate']) || empty($data['preferredDate'])) {
            $errors['preferredDate'] = 'La date préférée est requise.';
            return $errors;
        }

        try {
            $preferredDate = new \DateTime($data['preferredDate']);

            // Vérifier que la date est dans le futur avec délai minimum
            $minDate = clone $now;
            $minDate->modify('+' . self::MIN_ADVANCE_BOOKING_HOURS . ' hours');

            if ($preferredDate < $minDate) {
                $errors['preferredDate'] = sprintf(
                    'La date préférée doit être au moins %d heures dans le futur.',
                    self::MIN_ADVANCE_BOOKING_HOURS
                );
            }

            // Vérifier que la date n'est pas trop loin dans le futur
            $maxDate = clone $now;
            $maxDate->modify('+' . self::MAX_ADVANCE_BOOKING_DAYS . ' days');

            if ($preferredDate > $maxDate) {
                $errors['preferredDate'] = sprintf(
                    'La date préférée ne peut pas être à plus de %d jours dans le futur.',
                    self::MAX_ADVANCE_BOOKING_DAYS
                );
            }

        } catch (\Exception $e) {
            $errors['preferredDate'] = 'Le format de la date préférée est invalide.';
        }

        // Validation des dates alternatives
        if (isset($data['alternativeDates']) && !empty($data['alternativeDates'])) {
            if (!is_array($data['alternativeDates'])) {
                $errors['alternativeDates'] = 'Les dates alternatives doivent être un tableau.';
            } else {
                if (count($data['alternativeDates']) > self::MAX_ALTERNATIVE_DATES) {
                    $errors['alternativeDates'] = sprintf(
                        'Vous ne pouvez pas fournir plus de %d dates alternatives.',
                        self::MAX_ALTERNATIVE_DATES
                    );
                }

                foreach ($data['alternativeDates'] as $index => $altDate) {
                    try {
                        $alternativeDate = new \DateTime($altDate);
                        
                        $minDate = clone $now;
                        $minDate->modify('+' . self::MIN_ADVANCE_BOOKING_HOURS . ' hours');

                        if ($alternativeDate < $minDate) {
                            $errors["alternativeDates.{$index}"] = 'Chaque date alternative doit être dans le futur.';
                        }

                        $maxDate = clone $now;
                        $maxDate->modify('+' . self::MAX_ADVANCE_BOOKING_DAYS . ' days');

                        if ($alternativeDate > $maxDate) {
                            $errors["alternativeDates.{$index}"] = 'Les dates alternatives ne peuvent pas être trop éloignées.';
                        }

                    } catch (\Exception $e) {
                        $errors["alternativeDates.{$index}"] = 'Format de date invalide.';
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * Valide la durée estimée
     */
    public function validateDuration(?int $duration, ?string $category = null): array
    {
        $errors = [];

        if ($duration === null) {
            return $errors; // La durée est optionnelle
        }

        if (!is_numeric($duration) || $duration < self::MIN_DURATION) {
            $errors['duration'] = sprintf(
                'La durée doit être d\'au moins %d minutes.',
                self::MIN_DURATION
            );
        }

        if ($duration > self::MAX_DURATION) {
            $errors['duration'] = sprintf(
                'La durée ne peut pas dépasser %d minutes (8 heures).',
                self::MAX_DURATION
            );
        }

        // Validation spécifique à la catégorie
        if ($category && isset(self::VALID_CATEGORIES[$category])) {
            $categoryConfig = self::VALID_CATEGORIES[$category];

            if ($duration < $categoryConfig['minDuration']) {
                $errors['duration'] = sprintf(
                    'Pour la catégorie "%s", la durée minimum est de %d minutes.',
                    $categoryConfig['name'],
                    $categoryConfig['minDuration']
                );
            }

            if ($duration > $categoryConfig['maxDuration']) {
                $errors['duration'] = sprintf(
                    'Pour la catégorie "%s", la durée maximum est de %d minutes.',
                    $categoryConfig['name'],
                    $categoryConfig['maxDuration']
                );
            }
        }

        return $errors;
    }

    /**
     * Valide le budget
     */
    public function validateBudget(?float $budget, ?string $category = null): array
    {
        $errors = [];

        if ($budget === null) {
            return $errors; // Le budget est optionnel
        }

        if (!is_numeric($budget) || $budget < self::MIN_BUDGET) {
            $errors['budget'] = sprintf(
                'Le budget minimum est de %d€.',
                self::MIN_BUDGET
            );
        }

        if ($budget > self::MAX_BUDGET) {
            $errors['budget'] = sprintf(
                'Le budget ne peut pas dépasser %d€.',
                self::MAX_BUDGET
            );
        }

        // Validation spécifique à la catégorie
        if ($category && isset(self::VALID_CATEGORIES[$category])) {
            $categoryConfig = self::VALID_CATEGORIES[$category];

            if ($budget < $categoryConfig['minBudget']) {
                $errors['budget'] = sprintf(
                    'Pour la catégorie "%s", le budget minimum est de %d€.',
                    $categoryConfig['name'],
                    $categoryConfig['minBudget']
                );
            }
        }

        return $errors;
    }

    /**
     * Valide la fréquence
     */
    public function validateFrequency(array $data): array
    {
        $errors = [];

        // Fréquence par défaut: ponctuel
        $frequency = $data['frequency'] ?? 'ponctuel';

        if (!array_key_exists($frequency, self::VALID_FREQUENCIES)) {
            $errors['frequency'] = 'La fréquence sélectionnée est invalide.';
        }

        return $errors;
    }

    /**
     * Valide l'éligibilité d'un client à créer une demande
     */
    public function validateClientEligibility(Client $client): array
    {
        $errors = [];

        // Vérifier que le compte est actif
        if (!$client->getIsActive()) {
            $errors['client'] = 'Votre compte est désactivé. Contactez le support.';
            return $errors;
        }

        // Vérifier que l'email est vérifié
        if (!$client->getIsVerified()) {
            $errors['client'] = 'Vous devez vérifier votre email avant de créer une demande.';
        }

        // Vérifier le nombre de demandes actives
        $activeRequestsCount = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.client = :client')
            ->andWhere('sr.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['open', 'quoted'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeRequestsCount >= self::MAX_ACTIVE_REQUESTS_PER_CLIENT) {
            $errors['client'] = sprintf(
                'Vous avez atteint le maximum de %d demandes actives. Veuillez en annuler ou en finaliser avant d\'en créer une nouvelle.',
                self::MAX_ACTIVE_REQUESTS_PER_CLIENT
            );
        }

        return $errors;
    }

    /**
     * Valide qu'une demande peut être mise à jour
     */
    public function canUpdateRequest(ServiceRequest $serviceRequest, Client $client): array
    {
        $errors = [];

        // Vérifier que c'est bien le propriétaire
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            $errors['authorization'] = 'Vous n\'êtes pas autorisé à modifier cette demande.';
            return $errors;
        }

        // Vérifier le statut
        $validStatuses = ['open', 'quoted'];
        if (!in_array($serviceRequest->getStatus(), $validStatuses, true)) {
            $errors['status'] = 'Cette demande ne peut plus être modifiée car son statut est : ' . $serviceRequest->getStatus();
        }

        // Vérifier que la demande n'est pas expirée
        if ($serviceRequest->getExpiresAt() < new \DateTimeImmutable()) {
            $errors['expired'] = 'Cette demande a expiré et ne peut plus être modifiée.';
        }

        return $errors;
    }

    /**
     * Valide qu'une demande peut être annulée
     */
    public function canCancelRequest(ServiceRequest $serviceRequest, Client $client): array
    {
        $errors = [];

        // Vérifier que c'est bien le propriétaire
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            $errors['authorization'] = 'Vous n\'êtes pas autorisé à annuler cette demande.';
            return $errors;
        }

        // Vérifier le statut
        $invalidStatuses = ['cancelled', 'completed', 'expired'];
        if (in_array($serviceRequest->getStatus(), $invalidStatuses, true)) {
            $errors['status'] = 'Cette demande ne peut pas être annulée car son statut est : ' . $serviceRequest->getStatus();
        }

        return $errors;
    }

    /**
     * Valide qu'une demande peut être supprimée
     */
    public function canDeleteRequest(ServiceRequest $serviceRequest, Client $client): array
    {
        $errors = [];

        // Vérifier que c'est bien le propriétaire
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            $errors['authorization'] = 'Vous n\'êtes pas autorisé à supprimer cette demande.';
            return $errors;
        }

        // Vérifier qu'il n'y a pas de devis acceptés
        $qb = $this->entityManager->createQueryBuilder();
        $acceptedQuotesCount = $qb->select('COUNT(q.id)')
            ->from('App\Entity\Quote\Quote', 'q')
            ->where('q.serviceRequest = :request')
            ->andWhere('q.status = :status')
            ->setParameter('request', $serviceRequest)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();

        if ($acceptedQuotesCount > 0) {
            $errors['quotes'] = 'Cette demande ne peut pas être supprimée car un devis a été accepté.';
        }

        return $errors;
    }

    /**
     * Valide qu'un prestataire peut voir une demande
     */
    public function canPrestataireViewRequest(ServiceRequest $serviceRequest, Prestataire $prestataire): array
    {
        $errors = [];

        // Vérifier que le prestataire est approuvé
        if (!$prestataire->getIsApproved()) {
            $errors['approval'] = 'Votre compte doit être approuvé pour voir les demandes.';
            return $errors;
        }

        // Vérifier que le compte est actif
        if (!$prestataire->getIsActive()) {
            $errors['active'] = 'Votre compte est désactivé.';
            return $errors;
        }

        // Vérifier que la demande est ouverte
        if ($serviceRequest->getStatus() !== 'open') {
            $errors['status'] = 'Cette demande n\'est plus disponible.';
        }

        // Vérifier que la catégorie correspond
        $prestataireCategories = $prestataire->getServiceCategories();
        if (!in_array($serviceRequest->getCategory(), $prestataireCategories, true)) {
            $errors['category'] = 'Cette demande ne correspond pas à vos catégories de service.';
        }

        return $errors;
    }

    /**
     * Obtient les catégories valides
     */
    public function getValidCategories(): array
    {
        return self::VALID_CATEGORIES;
    }

    /**
     * Obtient les fréquences valides
     */
    public function getValidFrequencies(): array
    {
        return self::VALID_FREQUENCIES;
    }

    /**
     * Obtient les limites de durée pour une catégorie
     */
    public function getDurationLimitsForCategory(string $category): ?array
    {
        if (!isset(self::VALID_CATEGORIES[$category])) {
            return null;
        }

        return [
            'min' => self::VALID_CATEGORIES[$category]['minDuration'],
            'max' => self::VALID_CATEGORIES[$category]['maxDuration'],
        ];
    }

    /**
     * Obtient les limites de budget pour une catégorie
     */
    public function getBudgetLimitsForCategory(string $category): ?array
    {
        if (!isset(self::VALID_CATEGORIES[$category])) {
            return null;
        }

        return [
            'min' => self::VALID_CATEGORIES[$category]['minBudget'],
            'max' => self::MAX_BUDGET,
        ];
    }

    /**
     * Valide toutes les règles métier pour une création de demande
     */
    public function validateBusinessRules(array $data, Client $client): array
    {
        $errors = [];

        // Validation complète des données
        $dataErrors = $this->validateServiceRequestData($data, $client);
        if (!empty($dataErrors)) {
            return $dataErrors;
        }

        // Vérifications métier supplémentaires
        
        // Si service récurrent, vérifier que la date préférée est un jour de semaine approprié
        if (isset($data['frequency']) && $data['frequency'] !== 'ponctuel') {
            try {
                $preferredDate = new \DateTime($data['preferredDate']);
                $dayOfWeek = (int) $preferredDate->format('N'); // 1 (lundi) à 7 (dimanche)
                
                // Avertir si c'est un dimanche (7)
                if ($dayOfWeek === 7) {
                    $errors['preferredDate'] = 'Attention : peu de prestataires sont disponibles le dimanche pour les services récurrents.';
                }
            } catch (\Exception $e) {
                // Erreur déjà capturée dans validateDates
            }
        }

        // Cohérence durée/budget
        if (isset($data['duration']) && isset($data['budget'])) {
            $hourlyRate = ($data['budget'] / $data['duration']) * 60;
            
            if ($hourlyRate < 10) {
                $errors['budget'] = 'Le budget semble trop faible par rapport à la durée estimée (minimum ~10€/h).';
            }
            
            if ($hourlyRate > 50) {
                $errors['budget'] = 'Le budget semble très élevé par rapport à la durée. Vérifiez vos informations.';
            }
        }

        return $errors;
    }

    /**
     * Log les erreurs de validation
     */
    private function logValidationErrors(array $errors, string $context): void
    {
        if (!empty($errors)) {
            $this->logger->warning('Service request validation errors', [
                'context' => $context,
                'errors' => $errors
            ]);
        }
    }
}