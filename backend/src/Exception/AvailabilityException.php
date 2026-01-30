<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception pour les erreurs liées aux disponibilités des prestataires
 */
class AvailabilityException extends \RuntimeException
{
    private const DEFAULT_MESSAGE = 'Une erreur est survenue lors du traitement des disponibilités';
    
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

    public static function invalidDayOfWeek(int $day): self
    {
        return new self(
            sprintf('Le jour de la semaine %d n\'est pas valide. Utilisez 0 (dimanche) à 6 (samedi).', $day),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidTimeFormat(string $time): self
    {
        return new self(
            sprintf('Le format de l\'heure "%s" n\'est pas valide. Utilisez le format HH:MM.', $time),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function startTimeAfterEndTime(string $startTime, string $endTime): self
    {
        return new self(
            sprintf('L\'heure de début (%s) ne peut pas être postérieure à l\'heure de fin (%s).', $startTime, $endTime),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidTimeSlotDuration(int $minutes, int $minimum): self
    {
        return new self(
            sprintf('La durée du créneau (%d minutes) est inférieure au minimum requis (%d minutes).', $minutes, $minimum),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidDate(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('La date "%s" n\'est pas valide.', $date->format('Y-m-d')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function dateInPast(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('La date "%s" est dans le passé. Les disponibilités ne peuvent être définies que pour le futur.', $date->format('d/m/Y')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function dateTooFarInFuture(\DateTimeInterface $date, int $maxMonths): self
    {
        return new self(
            sprintf('La date "%s" est trop éloignée. Les disponibilités sont limitées à %d mois à l\'avance.', $date->format('d/m/Y'), $maxMonths),
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

    public static function invalidRecurrenceType(string $type): self
    {
        return new self(
            sprintf('Le type de récurrence "%s" n\'est pas valide. Types autorisés: weekly, biweekly, monthly.', $type),
            Response::HTTP_BAD_REQUEST
        );
    }

    // ============================================
    // Exceptions d'État
    // ============================================

    public static function notFound(int $id): self
    {
        return new self(
            sprintf('La disponibilité #%d n\'a pas été trouvée.', $id),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function prestataireNotFound(int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'a pas été trouvé.', $prestataireId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function noAvailabilitiesDefined(int $prestataireId): self
    {
        return new self(
            sprintf('Aucune disponibilité n\'a été définie pour le prestataire #%d.', $prestataireId),
            Response::HTTP_NOT_FOUND
        );
    }

    // ============================================
    // Exceptions de Conflit
    // ============================================

    public static function overlapWithExistingAvailability(int $existingId, string $period): self
    {
        return new self(
            sprintf('Cette disponibilité chevauche une disponibilité existante (#%d) pour la période: %s', $existingId, $period),
            Response::HTTP_CONFLICT
        );
    }

    public static function conflictWithBooking(int $bookingId, \DateTimeInterface $bookingDate): self
    {
        return new self(
            sprintf('Impossible de supprimer cette disponibilité car une réservation (#%d) est prévue le %s.', $bookingId, $bookingDate->format('d/m/Y à H:i')),
            Response::HTTP_CONFLICT
        );
    }

    public static function hasActiveBookings(int $availabilityId, int $bookingCount): self
    {
        return new self(
            sprintf('Impossible de modifier la disponibilité #%d car elle contient %d réservation(s) active(s).', $availabilityId, $bookingCount),
            Response::HTTP_CONFLICT
        );
    }

    public static function timeSlotAlreadyBooked(\DateTimeInterface $startTime, \DateTimeInterface $endTime): self
    {
        return new self(
            sprintf('Le créneau de %s à %s est déjà réservé.', $startTime->format('H:i'), $endTime->format('H:i')),
            Response::HTTP_CONFLICT
        );
    }

    public static function duplicateAvailability(int $dayOfWeek, string $startTime, string $endTime): self
    {
        $days = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
        return new self(
            sprintf('Une disponibilité existe déjà pour %s de %s à %s.', $days[$dayOfWeek], $startTime, $endTime),
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
            Response::HTTP_NOT_FOUND
        );
    }

    public static function noAvailableSlots(int $prestataireId, \DateTimeInterface $date): self
    {
        return new self(
            sprintf('Aucun créneau disponible pour le prestataire #%d le %s.', $prestataireId, $date->format('d/m/Y')),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function notEnoughConsecutiveTime(int $required, int $available): self
    {
        return new self(
            sprintf('Temps consécutif insuffisant. Requis: %d minutes, Disponible: %d minutes.', $required, $available),
            Response::HTTP_CONFLICT
        );
    }

    public static function slotTooShort(int $slotDuration, int $requiredDuration): self
    {
        return new self(
            sprintf('Le créneau disponible (%d minutes) est trop court pour la prestation demandée (%d minutes).', $slotDuration, $requiredDuration),
            Response::HTTP_CONFLICT
        );
    }

    public static function prestataireFullyBooked(\DateTimeInterface $date): self
    {
        return new self(
            sprintf('Le prestataire est entièrement réservé le %s.', $date->format('d/m/Y')),
            Response::HTTP_CONFLICT
        );
    }

    public static function prestataireFullyBookedForWeek(\DateTimeInterface $weekStart): self
    {
        return new self(
            sprintf('Le prestataire est entièrement réservé pour la semaine du %s.', $weekStart->format('d/m/Y')),
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

    public static function notOwner(int $availabilityId, int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'est pas propriétaire de la disponibilité #%d.', $prestataireId, $availabilityId),
            Response::HTTP_FORBIDDEN
        );
    }

    public static function cannotModifyOtherPrestataireAvailability(int $requestingId, int $ownerId): self
    {
        return new self(
            sprintf('Le prestataire #%d ne peut pas modifier les disponibilités du prestataire #%d.', $requestingId, $ownerId),
            Response::HTTP_FORBIDDEN
        );
    }

    // ============================================
    // Exceptions de Modification
    // ============================================

    public static function cannotModifyPastAvailability(int $id, \DateTimeInterface $date): self
    {
        return new self(
            sprintf('Impossible de modifier la disponibilité #%d car elle est dans le passé (%s).', $id, $date->format('d/m/Y')),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotDeleteWithFutureBookings(int $id, int $futureBookingCount): self
    {
        return new self(
            sprintf('Impossible de supprimer la disponibilité #%d car elle contient %d réservation(s) future(s).', $id, $futureBookingCount),
            Response::HTTP_CONFLICT
        );
    }

    public static function modificationTooLate(int $id, int $hoursLimit): self
    {
        return new self(
            sprintf('Trop tard pour modifier la disponibilité #%d. Le délai de %d heures est dépassé.', $id, $hoursLimit),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotReduceWithBookings(int $id): self
    {
        return new self(
            sprintf('Impossible de réduire la disponibilité #%d car des réservations existent déjà sur cette plage.', $id),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions d'Absence / Indisponibilité
    // ============================================

    public static function absenceNotFound(int $absenceId): self
    {
        return new self(
            sprintf('L\'absence #%d n\'a pas été trouvée.', $absenceId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function absenceOverlapWithBooking(int $bookingId, \DateTimeInterface $bookingDate): self
    {
        return new self(
            sprintf('L\'absence chevauche une réservation existante (#%d) prévue le %s.', $bookingId, $bookingDate->format('d/m/Y à H:i')),
            Response::HTTP_CONFLICT
        );
    }

    public static function absenceTooShort(\DateTimeInterface $start, \DateTimeInterface $end): self
    {
        return new self(
            sprintf('La période d\'absence du %s au %s est trop courte.', $start->format('d/m/Y'), $end->format('d/m/Y')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function absenceAlreadyDeclared(\DateTimeInterface $start, \DateTimeInterface $end): self
    {
        return new self(
            sprintf('Une absence est déjà déclarée pour la période du %s au %s.', $start->format('d/m/Y'), $end->format('d/m/Y')),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotCancelAbsenceWithReplacements(int $absenceId, int $replacementCount): self
    {
        return new self(
            sprintf('Impossible d\'annuler l\'absence #%d car %d remplacement(s) sont déjà en place.', $absenceId, $replacementCount),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Plage Horaire
    // ============================================

    public static function outsideBusinessHours(string $time): self
    {
        return new self(
            sprintf('L\'heure %s est en dehors des heures d\'ouverture (08:00 - 20:00).', $time),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function breakTooShort(int $minutes, int $minimum): self
    {
        return new self(
            sprintf('La pause de %d minutes est trop courte. Minimum requis: %d minutes.', $minutes, $minimum),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function workingHoursTooLong(int $hours, int $maximum): self
    {
        return new self(
            sprintf('Les heures de travail (%d heures) dépassent le maximum autorisé (%d heures par jour).', $hours, $maximum),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function insufficientRestTime(int $hours, int $required): self
    {
        return new self(
            sprintf('Temps de repos insuffisant. %d heures entre deux prestations, %d heures requises.', $hours, $required),
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

    public static function recurrenceEndBeforeStart(\DateTimeInterface $end, \DateTimeInterface $start): self
    {
        return new self(
            sprintf('La date de fin de récurrence (%s) ne peut pas être antérieure à la date de début (%s).', $end->format('d/m/Y'), $start->format('d/m/Y')),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function tooManyRecurrences(int $count, int $max): self
    {
        return new self(
            sprintf('La récurrence génère %d occurrences, ce qui dépasse la limite de %d.', $count, $max),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function cannotDeleteRecurringAvailability(int $id): self
    {
        return new self(
            sprintf('Impossible de supprimer la disponibilité récurrente #%d. Veuillez la désactiver à la place.', $id),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Limitation
    // ============================================

    public static function tooManyAvailabilities(int $current, int $max): self
    {
        return new self(
            sprintf('Vous avez atteint la limite de disponibilités (%d/%d). Veuillez supprimer ou fusionner certaines plages.', $current, $max),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    public static function tooManyChangesInPeriod(int $changes, int $max, string $period): self
    {
        return new self(
            sprintf('Trop de modifications de disponibilités (%d/%d) pour la période: %s', $changes, $max, $period),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    // ============================================
    // Exceptions Métier
    // ============================================

    public static function prestataireNotVerified(int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'a pas encore été vérifié. Les disponibilités ne peuvent pas être définies.', $prestataireId),
            Response::HTTP_CONFLICT
        );
    }

    public static function prestataireSuspended(int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d est suspendu. Les disponibilités ne peuvent pas être modifiées.', $prestataireId),
            Response::HTTP_CONFLICT
        );
    }

    public static function prestataireInactive(int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d est inactif.', $prestataireId),
            Response::HTTP_CONFLICT
        );
    }

    public static function noAvailabilityForServiceCategory(int $prestataireId, string $category): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'a pas de disponibilités pour la catégorie "%s".', $prestataireId, $category),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function minimumAdvanceNoticeRequired(int $hours): self
    {
        return new self(
            sprintf('Un délai de prévenance de %d heures est requis pour toute réservation.', $hours),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Zone Géographique
    // ============================================

    public static function outsideServiceArea(string $address): self
    {
        return new self(
            sprintf('L\'adresse "%s" est en dehors de la zone de service du prestataire.', $address),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function tooFarFromLocation(float $distance, float $maxDistance): self
    {
        return new self(
            sprintf('La distance (%.1f km) dépasse le rayon d\'intervention maximum (%.1f km).', $distance, $maxDistance),
            Response::HTTP_BAD_REQUEST
        );
    }

    // ============================================
    // Exceptions de Template
    // ============================================

    public static function templateNotFound(int $templateId): self
    {
        return new self(
            sprintf('Le modèle de disponibilité #%d n\'a pas été trouvé.', $templateId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function cannotApplyTemplate(int $templateId, string $reason): self
    {
        return new self(
            sprintf('Impossible d\'appliquer le modèle #%d: %s', $templateId, $reason),
            Response::HTTP_CONFLICT
        );
    }

    public static function templateAlreadyApplied(int $templateId, \DateTimeInterface $date): self
    {
        return new self(
            sprintf('Le modèle #%d a déjà été appliqué pour la date du %s.', $templateId, $date->format('d/m/Y')),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions Système
    // ============================================

    public static function databaseError(string $operation, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Erreur lors de l\'opération "%s" sur les disponibilités.', $operation),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    public static function calculationError(string $operation, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Erreur lors du calcul: %s', $operation),
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
            'type' => 'availability_error',
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
     * Vérifie si l'exception est liée à un chevauchement de créneaux
     */
    public function isOverlapError(): bool
    {
        return str_contains($this->getMessage(), 'chevauche') || 
               str_contains($this->getMessage(), 'overlap');
    }

    /**
     * Vérifie si l'exception est liée à une réservation
     */
    public function isBookingRelated(): bool
    {
        return str_contains($this->getMessage(), 'réservation') || 
               str_contains($this->getMessage(), 'booking');
    }

    /**
     * Vérifie si l'exception est liée à une récurrence
     */
    public function isRecurrenceError(): bool
    {
        return str_contains($this->getMessage(), 'récurrence') || 
               str_contains($this->getMessage(), 'recurrence');
    }

    /**
     * Vérifie si l'exception est une erreur système
     */
    public function isSystemError(): bool
    {
        return $this->getCode() === Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}