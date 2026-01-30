<?php

declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpFoundation\Response;

/**
 * Exception pour les erreurs liées aux paiements, factures et commissions
 */
class PaymentException extends \RuntimeException
{
    private const DEFAULT_MESSAGE = 'Une erreur est survenue lors du traitement du paiement';
    
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

    public static function invalidAmount(float $amount): self
    {
        return new self(
            sprintf('Le montant %.2f € n\'est pas valide. Il doit être positif.', $amount),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function amountTooLow(float $amount, float $minimum): self
    {
        return new self(
            sprintf('Le montant %.2f € est inférieur au minimum requis de %.2f €.', $amount, $minimum),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function amountTooHigh(float $amount, float $maximum): self
    {
        return new self(
            sprintf('Le montant %.2f € dépasse le maximum autorisé de %.2f €.', $amount, $maximum),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidPaymentMethod(string $method): self
    {
        return new self(
            sprintf('La méthode de paiement "%s" n\'est pas valide ou n\'est pas supportée.', $method),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidCurrency(string $currency): self
    {
        return new self(
            sprintf('La devise "%s" n\'est pas supportée. Seuls les paiements en EUR sont acceptés.', $currency),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function missingPaymentDetails(): self
    {
        return new self(
            'Les informations de paiement sont incomplètes ou manquantes.',
            Response::HTTP_BAD_REQUEST
        );
    }

    // ============================================
    // Exceptions Stripe / Gateway
    // ============================================

    public static function stripeError(string $message, ?string $stripeCode = null, ?\Throwable $previous = null): self
    {
        $fullMessage = sprintf('Erreur Stripe: %s', $message);
        if ($stripeCode) {
            $fullMessage .= sprintf(' (Code: %s)', $stripeCode);
        }
        
        return new self(
            $fullMessage,
            Response::HTTP_PAYMENT_REQUIRED,
            $previous
        );
    }

    public static function cardDeclined(string $reason): self
    {
        return new self(
            sprintf('Votre carte a été refusée: %s. Veuillez utiliser une autre carte.', $reason),
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    public static function insufficientFunds(): self
    {
        return new self(
            'Fonds insuffisants. Veuillez vérifier votre solde ou utiliser une autre carte.',
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    public static function expiredCard(): self
    {
        return new self(
            'Votre carte bancaire a expiré. Veuillez utiliser une carte valide.',
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    public static function invalidCardNumber(): self
    {
        return new self(
            'Le numéro de carte bancaire est invalide.',
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function invalidCvc(): self
    {
        return new self(
            'Le code de sécurité (CVC) est invalide.',
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function paymentGatewayUnavailable(string $gateway = 'Stripe'): self
    {
        return new self(
            sprintf('Le service de paiement %s est temporairement indisponible. Veuillez réessayer dans quelques instants.', $gateway),
            Response::HTTP_SERVICE_UNAVAILABLE
        );
    }

    public static function threeDSecureRequired(): self
    {
        return new self(
            'Une authentification 3D Secure est requise pour ce paiement.',
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    public static function threeDSecureFailed(): self
    {
        return new self(
            'L\'authentification 3D Secure a échoué. Le paiement ne peut pas être traité.',
            Response::HTTP_PAYMENT_REQUIRED
        );
    }

    // ============================================
    // Exceptions d'État
    // ============================================

    public static function notFound(int $paymentId): self
    {
        return new self(
            sprintf('Le paiement #%d n\'a pas été trouvé.', $paymentId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function alreadyProcessed(int $paymentId): self
    {
        return new self(
            sprintf('Le paiement #%d a déjà été traité.', $paymentId),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyRefunded(int $paymentId): self
    {
        return new self(
            sprintf('Le paiement #%d a déjà été remboursé.', $paymentId),
            Response::HTTP_CONFLICT
        );
    }

    public static function alreadyCancelled(int $paymentId): self
    {
        return new self(
            sprintf('Le paiement #%d a déjà été annulé.', $paymentId),
            Response::HTTP_CONFLICT
        );
    }

    public static function cannotRefund(int $paymentId, string $reason): self
    {
        return new self(
            sprintf('Le paiement #%d ne peut pas être remboursé: %s', $paymentId, $reason),
            Response::HTTP_CONFLICT
        );
    }

    public static function paymentPending(int $paymentId): self
    {
        return new self(
            sprintf('Le paiement #%d est en cours de traitement. Veuillez patienter.', $paymentId),
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

    // ============================================
    // Exceptions de Remboursement
    // ============================================

    public static function refundTooLate(int $paymentId, int $daysLimit): self
    {
        return new self(
            sprintf('Le paiement #%d ne peut plus être remboursé. Le délai de %d jours est dépassé.', $paymentId, $daysLimit),
            Response::HTTP_CONFLICT
        );
    }

    public static function partialRefundNotAllowed(int $paymentId): self
    {
        return new self(
            sprintf('Le remboursement partiel n\'est pas autorisé pour le paiement #%d.', $paymentId),
            Response::HTTP_CONFLICT
        );
    }

    public static function refundAmountExceedsOriginal(float $refundAmount, float $originalAmount): self
    {
        return new self(
            sprintf('Le montant du remboursement (%.2f €) dépasse le montant original du paiement (%.2f €).', $refundAmount, $originalAmount),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function refundFailed(int $paymentId, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Le remboursement du paiement #%d a échoué: %s', $paymentId, $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    // ============================================
    // Exceptions de Facture
    // ============================================

    public static function invoiceNotFound(int $invoiceId): self
    {
        return new self(
            sprintf('La facture #%d n\'a pas été trouvée.', $invoiceId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function invoiceAlreadyPaid(int $invoiceId): self
    {
        return new self(
            sprintf('La facture #%d est déjà payée.', $invoiceId),
            Response::HTTP_CONFLICT
        );
    }

    public static function invoiceExpired(int $invoiceId, \DateTimeInterface $expiredAt): self
    {
        return new self(
            sprintf('La facture #%d a expiré le %s.', $invoiceId, $expiredAt->format('d/m/Y')),
            Response::HTTP_GONE
        );
    }

    public static function invoiceCancelled(int $invoiceId): self
    {
        return new self(
            sprintf('La facture #%d a été annulée.', $invoiceId),
            Response::HTTP_CONFLICT
        );
    }

    public static function invoiceGenerationFailed(int $bookingId, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Échec de la génération de la facture pour la réservation #%d: %s', $bookingId, $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    public static function duplicateInvoice(int $bookingId): self
    {
        return new self(
            sprintf('Une facture existe déjà pour la réservation #%d.', $bookingId),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Commission
    // ============================================

    public static function invalidCommissionRate(float $rate): self
    {
        return new self(
            sprintf('Le taux de commission %.2f%% est invalide. Il doit être entre 0 et 100.', $rate),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function commissionAlreadyCalculated(int $paymentId): self
    {
        return new self(
            sprintf('La commission pour le paiement #%d a déjà été calculée.', $paymentId),
            Response::HTTP_CONFLICT
        );
    }

    public static function commissionCalculationFailed(int $paymentId, string $reason): self
    {
        return new self(
            sprintf('Échec du calcul de la commission pour le paiement #%d: %s', $paymentId, $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR
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

    public static function notPaymentOwner(int $paymentId, int $userId): self
    {
        return new self(
            sprintf('L\'utilisateur #%d n\'est pas autorisé à accéder au paiement #%d.', $userId, $paymentId),
            Response::HTTP_FORBIDDEN
        );
    }

    // ============================================
    // Exceptions de Réservation
    // ============================================

    public static function bookingNotFound(int $bookingId): self
    {
        return new self(
            sprintf('La réservation #%d associée au paiement n\'a pas été trouvée.', $bookingId),
            Response::HTTP_NOT_FOUND
        );
    }

    public static function bookingNotCompleted(int $bookingId): self
    {
        return new self(
            sprintf('La réservation #%d n\'est pas terminée. Le paiement ne peut pas être traité.', $bookingId),
            Response::HTTP_CONFLICT
        );
    }

    public static function bookingAlreadyPaid(int $bookingId): self
    {
        return new self(
            sprintf('La réservation #%d a déjà été payée.', $bookingId),
            Response::HTTP_CONFLICT
        );
    }

    public static function paymentMismatch(int $paymentId, int $bookingId): self
    {
        return new self(
            sprintf('Le paiement #%d ne correspond pas à la réservation #%d.', $paymentId, $bookingId),
            Response::HTTP_BAD_REQUEST
        );
    }

    // ============================================
    // Exceptions de Payout (Versement Prestataire)
    // ============================================

    public static function payoutFailed(int $prestataireId, float $amount, string $reason): self
    {
        return new self(
            sprintf('Échec du versement de %.2f € au prestataire #%d: %s', $amount, $prestataireId, $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR
        );
    }

    public static function insufficientBalanceForPayout(int $prestataireId, float $requested, float $available): self
    {
        return new self(
            sprintf('Solde insuffisant pour le prestataire #%d. Demandé: %.2f €, Disponible: %.2f €', $prestataireId, $requested, $available),
            Response::HTTP_CONFLICT
        );
    }

    public static function minimumPayoutNotReached(float $current, float $minimum): self
    {
        return new self(
            sprintf('Le montant minimum pour un versement est de %.2f €. Votre solde actuel est de %.2f €.', $minimum, $current),
            Response::HTTP_CONFLICT
        );
    }

    public static function bankAccountNotConfigured(int $prestataireId): self
    {
        return new self(
            sprintf('Le prestataire #%d n\'a pas configuré ses informations bancaires.', $prestataireId),
            Response::HTTP_BAD_REQUEST
        );
    }

    public static function bankAccountNotVerified(int $prestataireId): self
    {
        return new self(
            sprintf('Le compte bancaire du prestataire #%d n\'a pas été vérifié.', $prestataireId),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions de Sécurité
    // ============================================

    public static function suspiciousActivity(int $userId, string $reason): self
    {
        return new self(
            sprintf('Activité suspecte détectée pour l\'utilisateur #%d: %s. Le paiement a été bloqué.', $userId, $reason),
            Response::HTTP_FORBIDDEN
        );
    }

    public static function fraudDetected(int $paymentId, string $reason): self
    {
        return new self(
            sprintf('Fraude potentielle détectée pour le paiement #%d: %s', $paymentId, $reason),
            Response::HTTP_FORBIDDEN
        );
    }

    public static function tooManyAttempts(int $userId, int $remainingMinutes): self
    {
        return new self(
            sprintf('Trop de tentatives de paiement. Veuillez réessayer dans %d minutes.', $remainingMinutes),
            Response::HTTP_TOO_MANY_REQUESTS
        );
    }

    // ============================================
    // Exceptions de Webhook
    // ============================================

    public static function invalidWebhookSignature(): self
    {
        return new self(
            'La signature du webhook est invalide.',
            Response::HTTP_UNAUTHORIZED
        );
    }

    public static function webhookProcessingFailed(string $eventType, string $reason, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Échec du traitement du webhook "%s": %s', $eventType, $reason),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    public static function duplicateWebhookEvent(string $eventId): self
    {
        return new self(
            sprintf('L\'événement webhook "%s" a déjà été traité.', $eventId),
            Response::HTTP_CONFLICT
        );
    }

    // ============================================
    // Exceptions Système
    // ============================================

    public static function databaseError(string $operation, ?\Throwable $previous = null): self
    {
        return new self(
            sprintf('Erreur lors de l\'opération "%s" sur le paiement.', $operation),
            Response::HTTP_INTERNAL_SERVER_ERROR,
            $previous
        );
    }

    public static function configurationError(string $parameter): self
    {
        return new self(
            sprintf('Erreur de configuration: le paramètre "%s" n\'est pas défini correctement.', $parameter),
            Response::HTTP_INTERNAL_SERVER_ERROR
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
            'type' => 'payment_error',
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
     * Vérifie si l'exception est une erreur de paiement requise (carte refusée, etc.)
     */
    public function isPaymentRequiredError(): bool
    {
        return $this->getCode() === Response::HTTP_PAYMENT_REQUIRED;
    }

    /**
     * Vérifie si l'exception est liée à Stripe
     */
    public function isStripeError(): bool
    {
        return str_contains($this->getMessage(), 'Stripe');
    }

    /**
     * Vérifie si l'exception est une erreur système
     */
    public function isSystemError(): bool
    {
        return $this->getCode() === Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}