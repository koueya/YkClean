<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception pour les erreurs liées aux réservations
 */
class BookingException extends \RuntimeException
{
    private const DEFAULT_MESSAGE = 'Une erreur est survenue lors du traitement de la réservation';
    
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

    public static function invalidDate(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('La date "%s" n\'est pas valide.', $date->format('Y-m-d H:i')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function dateInPast(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('La date "%s" est dans le passé. Veuillez sélectionner une date future.', $date->format('d/m/Y H:i')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function dateTooFarInFuture(\DateTimeInterface $date, int $maxMonths): self
    {
        return new self(
            sprintf('La date "%s" est trop éloignée. Les réservations sont limitées à %d mois à l\'avance.', $date->format('d/m/Y'), $maxMonths),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidDuration(int $duration): self
    {
        return new self(
            sprintf('La durée %d heures n\'est pas valide. Elle doit être positive.', $duration),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function durationTooShort(int $duration, int $minimum): self
    {
        return new self(
            sprintf('La durée %d heures est inférieure au minimum requis de %d heures.', $duration, $minimum),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function durationTooLong(int $duration, int $maximum): self
    {
        return new self(
            sprintf('La durée %d heures dépasse le maximum autorisé de %d heures.', $duration, $maximum),
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

    public static function invalidAmount(float $amount): self
    {
        return new self(
            sprintf('Le montant %.2f € n\'est pas valide.', $amount),
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

    // ============================================
    // Exceptions d'État
    // ============================================

    public static function notFound(int $id): self
    {
        return new self(
            sprintf('La réservation #%d n\'a pas été trouvée.', $id),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function alreadyConfirmed(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est déjà confirmée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyCancelled(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est déjà annulée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyCompleted(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est déjà terminée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyInProgress(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est déjà en cours.', $id),
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

    public static function notScheduled(int $id): self
    {
        return new self(
            sprintf('La réservation #%d n\'est pas encore planifiée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function notInProgress(int $id): self
    {
        return new self(
            sprintf('La réservation #%d n\'est pas en cours. Action impossible.', $id),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Disponibilité
    // ============================================

    public static function prestataireNotAvailable(int $prestataireId, \DateTimeInterface $date): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'est pas disponible le %s.', $prestataireId, $date->format('d/m/Y à H:i')),
            Response::HTTP_CONFLICT
        );
    }

    public static function timeSlotNotAvailable(\DateTimeInterface $startTime, \DateTimeInterface $endTime): self
    {
        return new self(
            sprintf('Le créneau de %s à %s n\'est pas disponible.', $startTime->format('H:i'), $endTime->format('H:i')),
            Response::HTTP_CONFLICT
        );
    }

    public static function conflictWithExistingBooking(int $existingBookingId, \DateTimeInterface $date): self
    {
        return new self(
            sprintf('Une réservation existante (#%d) est déjà prévue le %s. Veuillez choisir un autre créneau.', $existingBookingId, $date->format('d/m/Y à H:i')),
            Response::HTTP_CONFLICT
        );
    }

    public static function prestataireFullyBooked(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('Le prestataire n\'a plus de disponibilités le %s.', $date->format('d/m/Y')),
            Response::HTTP_CONFLICT
        );
    }

    public static function clientAlreadyHasBooking(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('Vous avez déjà une réservation prévue le %s.', $date->format('d/m/Y à H:i')),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions d'Annulation
    // ============================================

    public static function cancellationTooLate(int $id, int $hoursLimit): self
    {
        return new self(
            sprintf('La réservation #%d ne peut plus être annulée. Le délai minimum d\'annulation est de %d heures.', $id, $hoursLimit),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotCancelInProgress(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est en cours et ne peut pas être annulée. Veuillez contacter le support.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotCancelCompleted(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est terminée et ne peut pas être annulée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function cancellationFeeApplies(int $id, float $fee): self
    {
        return new self(
            sprintf('L\'annulation de la réservation #%d entraînera des frais de %.2f €.', $id, $fee),
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    // ============================================
    // Exceptions de Modification
    // ============================================

    public static function modificationTooLate(int $id, int $hoursLimit): self
    {
        return new self(
            sprintf('La réservation #%d ne peut plus être modifiée. Le délai minimum de modification est de %d heures.', $id, $hoursLimit),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotModifyInProgress(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est en cours et ne peut pas être modifiée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotModifyCompleted(int $id): self
    {
        return new self(
            sprintf('La réservation #%d est terminée et ne peut pas être modifiée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function tooManyModifications(int $id, int $maxModifications): self
    {
        return new self(
            sprintf('La réservation #%d a atteint le nombre maximum de modifications autorisées (%d).', $id, $maxModifications),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Récurrence
    // ============================================

    public static function invalidRecurrencePattern(string $pattern): self
    {
        return new self(
            sprintf('Le modèle de récurrence "%s" n\'est pas valide.', $pattern),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function recurrenceNotFound(int $recurrenceId): self
    {
        return new self(
            sprintf('La récurrence #%d n\'a pas été trouvée.', $recurrenceId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function cannotDeleteRecurrenceWithActiveBookings(int $recurrenceId, int $activeCount): self
    {
        return new self(
            sprintf('La récurrence #%d ne peut pas être supprimée car elle contient %d réservation(s) active(s).', $recurrenceId, $activeCount),
            Response::HTTP_CONFLICT
        );
    }

    public static function recurrenceEndDateBeforeStart(\DateTimeInterface $endDate, \DateTimeInterface $startDate): self
    {
        return new self(
            sprintf('La date de fin de récurrence (%s) ne peut pas être antérieure à la date de début (%s).', $endDate->format('d/m/Y'), $startDate->format('d/m/Y')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function tooManyRecurrences(int $count, int $max): self
    {
        return new self(
            sprintf('La récurrence génère %d réservations, ce qui dépasse la limite de %d.', $count, $max),
            Response::HTTP_BAD_REQUEST
        );
    }

    // ============================================
    // Exceptions de Check-in/Check-out
    // ============================================

    public static function checkInTooEarly(int $id, \DateTimeInterface $scheduledTime): self
    {
        return new self(
            sprintf('Il est trop tôt pour commencer la réservation #%d. Heure prévue: %s.', $id, $scheduledTime->format('H:i')),
            Response::HTTP_CONFLICT
        );
    }

    public static function checkInTooLate(int $id, int $maxDelayMinutes): self
    {
        return new self(
            sprintf('Le délai maximum pour commencer la réservation #%d est dépassé (%d minutes).', $id, $maxDelayMinutes),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyCheckedIn(int $id): self
    {
        return new self(
            sprintf('La réservation #%d a déjà été démarrée (check-in effectué).', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function notCheckedIn(int $id): self
    {
        return new self(
            sprintf('La réservation #%d n\'a pas été démarrée. Effectuez le check-in d\'abord.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function checkOutTooEarly(int $id, int $minimumDurationMinutes): self
    {
        return new self(
            sprintf('Il est trop tôt pour terminer la réservation #%d. Durée minimum: %d minutes.', $id, $minimumDurationMinutes),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyCheckedOut(int $id): self
    {
        return new self(
            sprintf('La réservation #%d a déjà été terminée (check-out effectué).', $id),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Remplacement
    // ============================================

    public static function replacementNotAllowed(int $id, string $reason): self
    {
        return new self(
            sprintf('Le remplacement n\'est pas autorisé pour la réservation #%d: %s', $id, $reason),
            Response::HTTP_CONFLICT
        );
    }

    public static function noReplacementFound(int $id): self
    {
        return new self(
            sprintf('Aucun prestataire de remplacement n\'a été trouvé pour la réservation #%d.', $id),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function replacementAlreadyRequested(int $id): self
    {
        return new self(
            sprintf('Une demande de remplacement est déjà en cours pour la réservation #%d.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function replacementTooLate(int $id, int $hoursLimit): self
    {
        return new self(
            sprintf('Il est trop tard pour demander un remplacement pour la réservation #%d. Délai minimum: %d heures.', $id, $hoursLimit),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions d'Autorisation
    // ============================================

    public static function unauthorized(string $action): self
    {
        return new self(
            sprintf('Vous n\'êtes pas autorisé à effectuer l\'action: %s.', $action),
            Response::HTTP_FORBIDDEN
        );
    }

    public static function notClientOwner(int $bookingId, int $userId): self
    {
        return new self(
            sprintf('L\'utilisateur #%d n\'est pas le client de la réservation #%d.', $userId, $bookingId),
            Response::HTTP_FORBIDDEN
        );
    }

    public static function notPrestataireOwner(int $bookingId, int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'est pas assigné à la réservation #%d.', $prestataireId, $bookingId),
            Response::HTTP_FORBIDDEN
        );
    }

    // ============================================
    // Exceptions de Quote
    // ============================================

    public static function quoteNotFound(int $quoteId): self
    {
        return new self(
            sprintf('Le devis #%d n\'a pas été trouvé.', $quoteId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function quoteNotAccepted(int $quoteId): self
    {
        return new self(
            sprintf('Le devis #%d n\'a pas été accepté.', $quoteId),
            Response::HTTP_CONFLICT
        );
    }

    public static function quoteExpired(int $quoteId): self
    {
        return new self(
            sprintf('Le devis #%d a expiré.', $quoteId),
            Response::HTTP_GONE
        );
    }

    public static function quoteAlreadyConverted(int $quoteId, int $existingBookingId): self
    {
        return new self(
            sprintf('Le devis #%d a déjà été converti en réservation (#%d).', $quoteId, $existingBookingId),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Paiement
    // ============================================

    public static function paymentRequired(int $id): self
    {
        return new self(
            sprintf('La réservation #%d nécessite un paiement avant confirmation.', $id),
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    public static function paymentFailed(int $id, string $reason): self
    {
        return new self(
            sprintf('Le paiement de la réservation #%d a échoué: %s', $id, $reason),
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    public static function alreadyPaid(int $id): self
    {
        return new self(
            sprintf('La réservation #%d a déjà été payée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function refundNotAllowed(int $id, string $reason): self
    {
        return new self(
            sprintf('Le remboursement de la réservation #%d n\'est pas autorisé: %s', $id, $reason),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Limitation
    // ============================================

    public static function tooManyActiveBookings(int $current, int $max): self
    {
        return new self(
            sprintf('Vous avez atteint la limite de réservations actives (%d/%d). Veuillez terminer ou annuler certaines réservations.', $current, $max),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    public static function bookingTooSoon(\DateTimeInterface $lastBooking, int $minDelayHours): self
    {
        return new self(
            sprintf('Vous avez créé une réservation il y a moins de %d heures (dernière: %s). Veuillez patienter.', $minDelayHours, $lastBooking->format('d/m/Y à H:i')),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    // ============================================
    // Exceptions de Notes/Évaluation
    // ============================================

    public static function cannotAddNotesBeforeCompletion(int $id): self
    {
        return new self(
            sprintf('Impossible d\'ajouter des notes pour la réservation #%d avant qu\'elle ne soit terminée.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function reviewAlreadySubmitted(int $id): self
    {
        return new self(
            sprintf('Vous avez déjà soumis un avis pour la réservation #%d.', $id),
            Response::HTTP_CONFLICT
        );
    }

    public static function reviewWindowExpired(int $id, int $daysLimit): self
    {
        return new self(
            sprintf('Le délai pour laisser un avis sur la réservation #%d est expiré (%d jours).', $id, $daysLimit),
            Response::HTTP_GONE
        );
    }

    // ============================================
    // Exceptions Métier
    // ============================================

    public static function serviceNotProvidedInArea(string $serviceCategory, string $address): self
    {
        return new self(
            sprintf('Le service "%s" n\'est pas disponible dans la zone: %s', $serviceCategory, $address),
            Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    public static function prestataireNotQualified(int $prestataireId, string $serviceCategory): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'est pas qualifié pour le service "%s".', $prestataireId, $serviceCategory),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function prestataireNotVerified(int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'a pas encore été vérifié par la plateforme.', $prestataireId),
            Response::HTTP_CONFLICT
        );
    }

    public static function prestataireSuspended(int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d est actuellement suspendu.', $prestataireId),
            Response::HTTP_CONFLICT
        );
    }

    public static function clientSuspended(int $clientId): self
    {
        return new self(
            sprintf('Le client #%d est actuellement suspendu.', $clientId),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions Système
    // ============================================

    public static function databaseError(string $operation, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Erreur lors de l\'opération "%s" sur la réservation.', $operation),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    public static function notificationFailed(int $bookingId, string $reason): self
    {
        return new self(
            sprintf('Échec de l\'envoi des notifications pour la réservation #%d: %s', $bookingId, $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    public static function creationFailed(string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Échec de la création de la réservation: %s', $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    public static function unexpectedError(string $message, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Une erreur inattendue est survenue: %s', $message),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
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
            'type' => 'booking_error',
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

    /**
     * Vérifie si l'exception est liée au paiement
     */
    public function isPaymentError(): bool
    {
        return $this->getCode() === Response::HTTP_PAYMENT_REQUIRED;
    }

    /**
     * Vérifie si l'exception est liée à la disponibilité
     */
    public function isAvailabilityError(): bool
    {
        return str_contains($this->getMessage(), 'disponible') || 
               str_contains($this->getMessage(), 'créneau');
    }

    /**
     * Vérifie si l'exception concerne une annulation
     */
    public function isCancellationError(): bool
    {
        return str_contains($this->getMessage(), 'annul');
    }

    /**
     * Vérifie si l'exception est une erreur système
     */
    public function isSystemError(): bool
    {
        return $this->getCode() === Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}