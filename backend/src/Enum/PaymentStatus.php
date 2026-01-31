<?php

namespace App\Enum;

/**
 * Enum PaymentStatus - Statuts possibles pour un paiement
 * 
 * Flux typique Stripe :
 * PENDING → AUTHORIZED → CAPTURED → PAID
 * 
 * Flux remboursement :
 * PAID → REFUND_PENDING → REFUNDED
 */
enum PaymentStatus: string
{
    /**
     * En attente
     * Le paiement est en cours de traitement
     */
    case PENDING = 'pending';

    /**
     * Autorisé
     * Le paiement est autorisé mais pas encore capturé (Stripe pre-auth)
     */
    case AUTHORIZED = 'authorized';

    /**
     * Capturé
     * Le paiement a été capturé (fonds réservés)
     */
    case CAPTURED = 'captured';

    /**
     * Payé
     * Le paiement a été effectué avec succès
     */
    case PAID = 'paid';

    /**
     * Échoué
     * Le paiement a échoué (carte refusée, fonds insuffisants, etc.)
     */
    case FAILED = 'failed';

    /**
     * Annulé
     * Le paiement a été annulé avant traitement
     */
    case CANCELLED = 'cancelled';

    /**
     * Remboursé
     * Le paiement a été entièrement remboursé
     */
    case REFUNDED = 'refunded';

    /**
     * Remboursement partiel
     * Une partie du paiement a été remboursée
     */
    case PARTIALLY_REFUNDED = 'partially_refunded';

    /**
     * En attente de remboursement
     * Une demande de remboursement est en cours
     */
    case REFUND_PENDING = 'refund_pending';

    /**
     * Expiré
     * La session de paiement a expiré
     */
    case EXPIRED = 'expired';

    /**
     * En litige
     * Le client a contesté le paiement (chargeback)
     */
    case DISPUTED = 'disputed';

    /**
     * Litige gagné
     * Le litige a été résolu en faveur du marchand
     */
    case DISPUTE_WON = 'dispute_won';

    /**
     * Litige perdu
     * Le litige a été résolu en faveur du client
     */
    case DISPUTE_LOST = 'dispute_lost';

    /**
     * En attente de validation 3D Secure
     */
    case AWAITING_3DS = 'awaiting_3ds';

    /**
     * Obtenir le libellé français
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::AUTHORIZED => 'Autorisé',
            self::CAPTURED => 'Capturé',
            self::PAID => 'Payé',
            self::FAILED => 'Échoué',
            self::CANCELLED => 'Annulé',
            self::REFUNDED => 'Remboursé',
            self::PARTIALLY_REFUNDED => 'Partiellement remboursé',
            self::REFUND_PENDING => 'Remboursement en attente',
            self::EXPIRED => 'Expiré',
            self::DISPUTED => 'En litige',
            self::DISPUTE_WON => 'Litige gagné',
            self::DISPUTE_LOST => 'Litige perdu',
            self::AWAITING_3DS => 'Validation 3D Secure',
        };
    }

    /**
     * Obtenir la couleur
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'orange',
            self::AUTHORIZED => 'blue',
            self::CAPTURED => 'blue',
            self::PAID => 'green',
            self::FAILED => 'red',
            self::CANCELLED => 'gray',
            self::REFUNDED => 'yellow',
            self::PARTIALLY_REFUNDED => 'yellow',
            self::REFUND_PENDING => 'orange',
            self::EXPIRED => 'gray',
            self::DISPUTED => 'red',
            self::DISPUTE_WON => 'green',
            self::DISPUTE_LOST => 'red',
            self::AWAITING_3DS => 'purple',
        };
    }

    /**
     * Obtenir le code couleur hexadécimal
     */
    public function hexColor(): string
    {
        return match($this) {
            self::PENDING => '#FF9800',
            self::AUTHORIZED => '#2196F3',
            self::CAPTURED => '#1976D2',
            self::PAID => '#4CAF50',
            self::FAILED => '#F44336',
            self::CANCELLED => '#9E9E9E',
            self::REFUNDED => '#FFC107',
            self::PARTIALLY_REFUNDED => '#FFB300',
            self::REFUND_PENDING => '#FF9800',
            self::EXPIRED => '#757575',
            self::DISPUTED => '#E91E63',
            self::DISPUTE_WON => '#4CAF50',
            self::DISPUTE_LOST => '#D32F2F',
            self::AWAITING_3DS => '#9C27B0',
        };
    }

    /**
     * Obtenir l'icône
     */
    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'clock',
            self::AUTHORIZED => 'shield-alt',
            self::CAPTURED => 'lock',
            self::PAID => 'check-circle',
            self::FAILED => 'times-circle',
            self::CANCELLED => 'ban',
            self::REFUNDED => 'undo',
            self::PARTIALLY_REFUNDED => 'undo-alt',
            self::REFUND_PENDING => 'hourglass-half',
            self::EXPIRED => 'clock',
            self::DISPUTED => 'exclamation-triangle',
            self::DISPUTE_WON => 'trophy',
            self::DISPUTE_LOST => 'times',
            self::AWAITING_3DS => 'shield-check',
        };
    }

    /**
     * Vérifie si le paiement est réussi
     */
    public function isSuccessful(): bool
    {
        return in_array($this, [
            self::AUTHORIZED,
            self::CAPTURED,
            self::PAID,
        ]);
    }

    /**
     * Vérifie si le paiement est en cours
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::AUTHORIZED,
            self::CAPTURED,
            self::AWAITING_3DS,
        ]);
    }

    /**
     * Vérifie si le paiement a échoué
     */
    public function isFailed(): bool
    {
        return in_array($this, [
            self::FAILED,
            self::CANCELLED,
            self::EXPIRED,
        ]);
    }

    /**
     * Vérifie si le paiement est final (complété)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::PAID,
            self::FAILED,
            self::CANCELLED,
            self::REFUNDED,
            self::EXPIRED,
            self::DISPUTE_WON,
            self::DISPUTE_LOST,
        ]);
    }

    /**
     * Vérifie si un remboursement est possible
     */
    public function canBeRefunded(): bool
    {
        return in_array($this, [
            self::PAID,
            self::PARTIALLY_REFUNDED,
        ]);
    }

    /**
     * Vérifie si le paiement peut être annulé
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::AUTHORIZED,
            self::AWAITING_3DS,
        ]);
    }

    /**
     * Vérifie si le paiement peut être capturé
     */
    public function canBeCaptured(): bool
    {
        return $this === self::AUTHORIZED;
    }

    /**
     * Vérifie si c'est un statut de remboursement
     */
    public function isRefundStatus(): bool
    {
        return in_array($this, [
            self::REFUNDED,
            self::PARTIALLY_REFUNDED,
            self::REFUND_PENDING,
        ]);
    }

    /**
     * Vérifie si c'est un statut de litige
     */
    public function isDisputeStatus(): bool
    {
        return in_array($this, [
            self::DISPUTED,
            self::DISPUTE_WON,
            self::DISPUTE_LOST,
        ]);
    }

    /**
     * Vérifie si le paiement nécessite une action du client
     */
    public function requiresClientAction(): bool
    {
        return in_array($this, [
            self::AWAITING_3DS,
            self::FAILED,
        ]);
    }

    /**
     * Vérifie si les fonds sont disponibles pour le prestataire
     */
    public function fundsAvailable(): bool
    {
        return in_array($this, [
            self::CAPTURED,
            self::PAID,
        ]);
    }

    /**
     * Obtenir les transitions possibles
     */
    public function possibleTransitions(): array
    {
        return match($this) {
            self::PENDING => [
                self::AUTHORIZED,
                self::CAPTURED,
                self::PAID,
                self::FAILED,
                self::CANCELLED,
                self::AWAITING_3DS,
            ],
            self::AUTHORIZED => [
                self::CAPTURED,
                self::PAID,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::CAPTURED => [
                self::PAID,
                self::REFUND_PENDING,
            ],
            self::PAID => [
                self::REFUND_PENDING,
                self::PARTIALLY_REFUNDED,
                self::REFUNDED,
                self::DISPUTED,
            ],
            self::AWAITING_3DS => [
                self::AUTHORIZED,
                self::PAID,
                self::FAILED,
                self::EXPIRED,
            ],
            self::REFUND_PENDING => [
                self::REFUNDED,
                self::PARTIALLY_REFUNDED,
                self::FAILED,
            ],
            self::PARTIALLY_REFUNDED => [
                self::REFUNDED,
            ],
            self::DISPUTED => [
                self::DISPUTE_WON,
                self::DISPUTE_LOST,
            ],
            self::FAILED, self::CANCELLED, self::REFUNDED, 
            self::EXPIRED, self::DISPUTE_WON, self::DISPUTE_LOST => [],
        };
    }

    /**
     * Vérifie si une transition est possible
     */
    public function canTransitionTo(PaymentStatus $newStatus): bool
    {
        return in_array($newStatus, $this->possibleTransitions());
    }

    /**
     * Obtenir la description
     */
    public function description(): string
    {
        return match($this) {
            self::PENDING => 'Le paiement est en cours de traitement.',
            self::AUTHORIZED => 'Le paiement est autorisé mais les fonds ne sont pas encore capturés.',
            self::CAPTURED => 'Les fonds ont été capturés et sont en attente de transfert.',
            self::PAID => 'Le paiement a été effectué avec succès.',
            self::FAILED => 'Le paiement a échoué. Veuillez vérifier vos informations bancaires.',
            self::CANCELLED => 'Le paiement a été annulé.',
            self::REFUNDED => 'Le paiement a été entièrement remboursé.',
            self::PARTIALLY_REFUNDED => 'Une partie du paiement a été remboursée.',
            self::REFUND_PENDING => 'Votre demande de remboursement est en cours de traitement.',
            self::EXPIRED => 'La session de paiement a expiré. Veuillez recommencer.',
            self::DISPUTED => 'Le paiement fait l\'objet d\'un litige.',
            self::DISPUTE_WON => 'Le litige a été résolu en votre faveur.',
            self::DISPUTE_LOST => 'Le litige a été résolu en faveur du client.',
            self::AWAITING_3DS => 'Validation 3D Secure requise pour finaliser le paiement.',
        };
    }

    /**
     * Action recommandée pour le client
     */
    public function clientAction(): ?string
    {
        return match($this) {
            self::PENDING => 'Veuillez patienter',
            self::AUTHORIZED => 'Paiement en cours de validation',
            self::CAPTURED => 'Paiement en cours de traitement',
            self::PAID => 'Paiement effectué',
            self::FAILED => 'Réessayer le paiement',
            self::CANCELLED => null,
            self::REFUNDED => 'Remboursement reçu',
            self::PARTIALLY_REFUNDED => 'Remboursement partiel reçu',
            self::REFUND_PENDING => 'Remboursement en cours',
            self::EXPIRED => 'Recommencer le paiement',
            self::DISPUTED => 'Contacter le support',
            self::DISPUTE_WON => null,
            self::DISPUTE_LOST => null,
            self::AWAITING_3DS => 'Valider via 3D Secure',
        };
    }

    /**
     * Action recommandée pour le prestataire
     */
    public function prestataireAction(): ?string
    {
        return match($this) {
            self::PENDING => 'En attente de confirmation',
            self::AUTHORIZED => 'Paiement autorisé',
            self::CAPTURED => 'Fonds capturés',
            self::PAID => 'Fonds disponibles',
            self::FAILED => 'Paiement échoué',
            self::CANCELLED => null,
            self::REFUNDED => 'Montant remboursé',
            self::PARTIALLY_REFUNDED => 'Remboursement partiel effectué',
            self::REFUND_PENDING => 'Remboursement en cours',
            self::EXPIRED => null,
            self::DISPUTED => 'Litige en cours - fournir preuves',
            self::DISPUTE_WON => 'Litige gagné',
            self::DISPUTE_LOST => 'Litige perdu - montant débité',
            self::AWAITING_3DS => 'En attente validation client',
        };
    }

    /**
     * Obtenir le délai typique de traitement (en heures)
     */
    public function typicalProcessingTime(): ?int
    {
        return match($this) {
            self::PENDING => 1,
            self::AUTHORIZED => 24,
            self::CAPTURED => 48,
            self::AWAITING_3DS => 24,
            self::REFUND_PENDING => 72,
            default => null,
        };
    }

    /**
     * Statuts réussis
     */
    public static function successStatuses(): array
    {
        return [
            self::AUTHORIZED,
            self::CAPTURED,
            self::PAID,
        ];
    }

    /**
     * Statuts échoués
     */
    public static function failedStatuses(): array
    {
        return [
            self::FAILED,
            self::CANCELLED,
            self::EXPIRED,
        ];
    }

    /**
     * Statuts de remboursement
     */
    public static function refundStatuses(): array
    {
        return [
            self::REFUND_PENDING,
            self::PARTIALLY_REFUNDED,
            self::REFUNDED,
        ];
    }

    /**
     * Statuts de litige
     */
    public static function disputeStatuses(): array
    {
        return [
            self::DISPUTED,
            self::DISPUTE_WON,
            self::DISPUTE_LOST,
        ];
    }

    /**
     * Obtenir toutes les options pour select
     */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    /**
     * Créer depuis une chaîne
     */
    public static function fromString(string $status): ?self
    {
        return self::tryFrom($status);
    }

    /**
     * Mapper depuis Stripe status
     */
    public static function fromStripeStatus(string $stripeStatus): self
    {
        return match($stripeStatus) {
            'requires_payment_method', 'requires_confirmation' => self::PENDING,
            'requires_action' => self::AWAITING_3DS,
            'processing' => self::PENDING,
            'requires_capture' => self::AUTHORIZED,
            'succeeded' => self::PAID,
            'canceled' => self::CANCELLED,
            'failed' => self::FAILED,
            default => self::PENDING,
        };
    }

    /**
     * Badge HTML
     */
    public function badge(): string
    {
        $color = $this->color();
        $label = $this->label();
        
        return sprintf(
            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-%s-100 text-%s-800">%s</span>',
            $color,
            $color,
            $label
        );
    }

    /**
     * Sérialisation JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'color' => $this->color(),
            'hexColor' => $this->hexColor(),
            'icon' => $this->icon(),
            'isSuccessful' => $this->isSuccessful(),
            'isPending' => $this->isPending(),
            'isFailed' => $this->isFailed(),
            'canBeRefunded' => $this->canBeRefunded(),
            'fundsAvailable' => $this->fundsAvailable(),
            'description' => $this->description(),
        ];
    }

    /**
     * Conversion en chaîne
     
    public function __toString(): string
    {
        return $this->value;
    } 
    */
}