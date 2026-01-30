<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception pour les erreurs liées aux demandes de service
 */
class ServiceRequestException extends \RuntimeException
{
    private const DEFAULT_MESSAGE = 'Une erreur est survenue lors du traitement de la demande de service';
    
    /**
     * Constructeur
     */
    public function __construct(
        string $message = self::DEFAULT_MESSAGE,
        int $code = Response::HTTP_BAD_REQUEST,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    // ============================================
    // Exceptions de Validation
    // ============================================

    public static function invalidCategory(string $category): self
    {
        return new self(
            sprintf('La catégorie de service "%s" n\'est pas valide.', $category),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidFrequency(string $frequency): self
    {
        return new self(
            sprintf('La fréquence "%s" n\'est pas valide.', $frequency),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidDuration(?int $duration): self
    {
        return new self(
            sprintf('La durée "%s" n\'est pas valide. Elle doit être positive.', $duration ?? 'null'),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidBudget(?float $budget): self
    {
        return new self(
            sprintf('Le budget "%s" n\'est pas valide. Il doit être positif.', $budget ?? 'null'),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function missingRequiredField(string $field): self
    {
        return new self(
            sprintf('Le champ requis "%s" est manquant.', $field),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidAddress(): self
    {
        return new self(
            'L\'adresse fournie n\'est pas valide ou est incomplète.',
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidDate(string $date): self
    {
        return new self(
            sprintf('La date "%s" n\'est pas valide.', $date),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function dateInPast(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('La date "%s" est dans le passé. Veuillez sélectionner une date future.', $date->format('Y-m-d H:i')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function tooManyAlternativeDates(int $count, int $max): self
    {
        return new self(
            sprintf('Vous avez fourni %d dates alternatives. Le maximum autorisé est de %d.', $count, $max),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function emptyDescription(): self
    {
        return new self(
            'La description de la demande ne peut pas être vide.',
            Response::HTTP_BAD_REQUEST
        );
    }

    // ============================================
    // Exceptions d'État
    // ============================================

    public static function notFound(int $id): self
    {
        return new self(
            sprintf('La demande de service #%d n\'a pas été trouvée.', $id),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function alreadyClosed(int $id): self
    {
        return new self(
            sprintf('La demande de service #%d est déjà clôturée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyCancelled(int $id): self
    {
        return new self(
            sprintf('La demande de service #%d est déjà annulée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyAccepted(int $id): self
    {
        return new self(
            sprintf('La demande de service #%d a déjà été acceptée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function hasActiveQuotes(int $id, int $quoteCount): self
    {
        return new self(
            sprintf('La demande de service #%d a %d devis actif(s). Vous devez d\'abord les traiter avant de pouvoir la modifier.', $id, $quoteCount),
            Response::HTTP_CONFLICT
        );
    }

    public static function invalidStatus(string $currentStatus, string $newStatus): self
    {
        return new self(
            sprintf('Impossible de passer du statut "%s" au statut "%s".', $currentStatus, $newStatus),
            Response::HTTP_CONFLICT
        );
    }

    public static function expired(int $id): self
    {
        return new self(
            sprintf('La demande de service #%d a expiré.', $id),
            Response::HTTP_GONE
        );
    }

    // ============================================
    // Exceptions d'Autorisation
    // ============================================

    public static function notOwner(int $requestId, int $userId): self
    {
        return new self(
            sprintf('L\'utilisateur #%d n\'est pas autorisé à modifier la demande #%d.', $userId, $requestId),
            Response::HTTP_FORBIDDEN
        );
    }

    public static function unauthorized(string $action): self
    {
        return new self(
            sprintf('Vous n\'êtes pas autorisé à effectuer l\'action: %s.', $action),
            Response::HTTP_FORBIDDEN
        );
    }

    // ============================================
    // Exceptions de Limitation
    // ============================================

    public static function tooManyActiveRequests(int $current, int $max): self
    {
        return new self(
            sprintf('Vous avez atteint la limite de demandes actives (%d/%d). Veuillez clôturer ou annuler certaines demandes avant d\'en créer de nouvelles.', $current, $max),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    public static function requestTooSoon(\DateTimeInterface $lastRequest, int $minDelayMinutes): self
    {
        return new self(
            sprintf('Vous avez créé une demande il y a moins de %d minutes (dernière demande: %s). Veuillez patienter.', $minDelayMinutes, $lastRequest->format('H:i')),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    // ============================================
    // Exceptions de Disponibilité
    // ============================================

    public static function noAvailableProviders(string $category, string $address): self
    {
        return new self(
            sprintf('Aucun prestataire n\'est disponible pour la catégorie "%s" dans votre zone (%s).', $category, $address),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function serviceNotAvailableInArea(string $category, string $city): self
    {
        return new self(
            sprintf('Le service "%s" n\'est pas encore disponible dans la ville de %s.', $category, $city),
            Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    // ============================================
    // Exceptions de Modification
    // ============================================

    public static function cannotModifyAfterQuotes(int $id): self
    {
        return new self(
            sprintf('La demande #%d ne peut plus être modifiée car des devis ont déjà été reçus.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotCancelWithAcceptedQuote(int $id): self
    {
        return new self(
            sprintf('La demande #%d ne peut pas être annulée car un devis a déjà été accepté. Veuillez annuler la réservation à la place.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function modificationWindowExpired(int $id, int $hoursLimit): self
    {
        return new self(
            sprintf('La demande #%d ne peut plus être modifiée. Le délai de modification (%d heures) est dépassé.', $id, $hoursLimit),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions Métier
    // ============================================

    public static function budgetTooLow(float $budget, float $minimumBudget): self
    {
        return new self(
            sprintf('Le budget proposé (%.2f €) est inférieur au minimum requis (%.2f €).', $budget, $minimumBudget),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function durationTooShort(int $duration, int $minimumDuration): self
    {
        return new self(
            sprintf('La durée demandée (%d heures) est inférieure à la durée minimum (%d heures).', $duration, $minimumDuration),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidRecurrenceConfiguration(string $reason): self
    {
        return new self(
            sprintf('Configuration de récurrence invalide: %s', $reason),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function conflictWithExistingRequest(int $existingRequestId, \DateTimeInterface $date): self
    {
        return new self(
            sprintf('Vous avez déjà une demande active (#%d) pour la date du %s. Veuillez choisir une autre date ou modifier votre demande existante.', $existingRequestId, $date->format('d/m/Y')),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions Système
    // ============================================

    public static function databaseError(string $operation, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Erreur lors de l\'opération "%s" sur la demande de service.', $operation),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    public static function notificationFailed(int $requestId, string $reason): self
    {
        return new self(
            sprintf('Échec de l\'envoi des notifications pour la demande #%d: %s', $requestId, $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    public static function matchingServiceUnavailable(): self
    {
        return new self(
            'Le service de mise en relation est temporairement indisponible. Veuillez réessayer ultérieurement.',
            Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    // ============================================
    // Helpers
    // ============================================

    /**
     * Retourne un tableau formaté pour les réponses API
     */
    public function toArray(): array
    {
        return [
            'error' => true,
            'message' => $this->getMessage(),
            'code' => $this->getCode(),
            'type' => 'service_request_error',
        ];
    }

    /**
     * Vérifie si l'exception est une erreur de validation
     */
    public function isValidationError(): bool
    {
        return $this->getCode() === Response::HTTP_BAD_REQUEST;
    }

    /**
     * Vérifie si l'exception est une erreur d'autorisation
     */
    public function isAuthorizationError(): bool
    {
        return $this->getCode() === Response::HTTP_FORBIDDEN;
    }

    /**
     * Vérifie si l'exception est une erreur de ressource non trouvée
     */
    public function isNotFoundError(): bool
    {
        return $this->getCode() === Response::HTTP_NOT_FOUND;
    }

    /**
     * Vérifie si l'exception est une erreur de conflit
     */
    public function isConflictError(): bool
    {
        return $this->getCode() === Response::HTTP_CONFLICT;
    }
}