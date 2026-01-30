<?php

namespace App\Enum;

/**
 * Enum QuoteStatus - Statuts possibles pour un devis
 * 
 * Un devis est une PROPOSITION commerciale envoyée au client
 * AVANT la réalisation du service
 */
enum QuoteStatus: string
{
    /**
     * Brouillon
     * Le prestataire prépare le devis
     */
    case DRAFT = 'draft';

    /**
     * Envoyé au client
     * Le devis a été transmis et attend une réponse
     */
    case SENT = 'sent';

    /**
     * Vu par le client
     * Le client a ouvert/consulté le devis
     */
    case VIEWED = 'viewed';

    /**
     * En négociation
     * Le client demande des modifications
     */
    case NEGOTIATING = 'negotiating';

    /**
     * Accepté
     * Le client a accepté le devis → Devient une réservation
     */
    case ACCEPTED = 'accepted';

    /**
     * Refusé
     * Le client a décliné le devis
     */
    case REJECTED = 'rejected';

    /**
     * Expiré
     * Le délai de validité est dépassé
     */
    case EXPIRED = 'expired';

    /**
     * Annulé
     * Le prestataire a annulé le devis
     */
    case CANCELLED = 'cancelled';

    /**
     * Obtenir le libellé français
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyé',
            self::VIEWED => 'Vu par le client',
            self::NEGOTIATING => 'En négociation',
            self::ACCEPTED => 'Accepté',
            self::REJECTED => 'Refusé',
            self::EXPIRED => 'Expiré',
            self::CANCELLED => 'Annulé',
        };
    }

    /**
     * Obtenir la couleur
     */
    public function color(): string
    {
        return match($this) {
            self::DRAFT => 'gray',
            self::SENT => 'blue',
            self::VIEWED => 'purple',
            self::NEGOTIATING => 'orange',
            self::ACCEPTED => 'green',
            self::REJECTED => 'red',
            self::EXPIRED => 'gray',
            self::CANCELLED => 'red',
        };
    }

    /**
     * Obtenir le code couleur hexadécimal
     */
    public function hexColor(): string
    {
        return match($this) {
            self::DRAFT => '#9E9E9E',
            self::SENT => '#2196F3',
            self::VIEWED => '#9C27B0',
            self::NEGOTIATING => '#FF9800',
            self::ACCEPTED => '#4CAF50',
            self::REJECTED => '#F44336',
            self::EXPIRED => '#757575',
            self::CANCELLED => '#D32F2F',
        };
    }

    /**
     * Obtenir l'icône
     */
    public function icon(): string
    {
        return match($this) {
            self::DRAFT => 'file-alt',
            self::SENT => 'paper-plane',
            self::VIEWED => 'eye',
            self::NEGOTIATING => 'comments',
            self::ACCEPTED => 'check-circle',
            self::REJECTED => 'times-circle',
            self::EXPIRED => 'clock',
            self::CANCELLED => 'ban',
        };
    }

    /**
     * Vérifie si le devis attend une réponse
     */
    public function isPending(): bool
    {
        return in_array($this, [
            self::SENT,
            self::VIEWED,
            self::NEGOTIATING,
        ]);
    }

    /**
     * Vérifie si le devis est final (ne peut plus changer)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::ACCEPTED,
            self::REJECTED,
            self::EXPIRED,
            self::CANCELLED,
        ]);
    }

    /**
     * Vérifie si le devis peut être modifié
     */
    public function canBeModified(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::NEGOTIATING,
        ]);
    }

    /**
     * Vérifie si le devis peut être envoyé
     */
    public function canBeSent(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Vérifie si le devis peut être accepté
     */
    public function canBeAccepted(): bool
    {
        return in_array($this, [
            self::SENT,
            self::VIEWED,
            self::NEGOTIATING,
        ]);
    }

    /**
     * Vérifie si le devis peut être rejeté
     */
    public function canBeRejected(): bool
    {
        return in_array($this, [
            self::SENT,
            self::VIEWED,
            self::NEGOTIATING,
        ]);
    }

    /**
     * Vérifie si le devis peut être annulé
     */
    public function canBeCancelled(): bool
    {
        return !$this->isFinal();
    }

    /**
     * Transitions possibles
     */
    public function possibleTransitions(): array
    {
        return match($this) {
            self::DRAFT => [
                self::SENT,
                self::CANCELLED,
            ],
            self::SENT => [
                self::VIEWED,
                self::NEGOTIATING,
                self::ACCEPTED,
                self::REJECTED,
                self::EXPIRED,
                self::CANCELLED,
            ],
            self::VIEWED => [
                self::NEGOTIATING,
                self::ACCEPTED,
                self::REJECTED,
                self::EXPIRED,
            ],
            self::NEGOTIATING => [
                self::SENT,
                self::ACCEPTED,
                self::REJECTED,
                self::CANCELLED,
            ],
            self::ACCEPTED, self::REJECTED, self::EXPIRED, self::CANCELLED => [],
        };
    }

    /**
     * Vérifie si une transition est possible
     */
    public function canTransitionTo(QuoteStatus $newStatus): bool
    {
        return in_array($newStatus, $this->possibleTransitions());
    }

    /**
     * Obtenir la description
     */
    public function description(): string
    {
        return match($this) {
            self::DRAFT => 'Le devis est en cours de préparation par le prestataire.',
            self::SENT => 'Le devis a été envoyé au client et attend sa réponse.',
            self::VIEWED => 'Le client a consulté le devis.',
            self::NEGOTIATING => 'Le client négocie les conditions du devis.',
            self::ACCEPTED => 'Le client a accepté le devis. Une réservation a été créée.',
            self::REJECTED => 'Le client a refusé le devis.',
            self::EXPIRED => 'Le délai de validité du devis est dépassé.',
            self::CANCELLED => 'Le devis a été annulé par le prestataire.',
        };
    }

    /**
     * Action recommandée pour le client
     */
    public function clientAction(): ?string
    {
        return match($this) {
            self::DRAFT => null,
            self::SENT => 'Consulter le devis',
            self::VIEWED => 'Accepter ou refuser le devis',
            self::NEGOTIATING => 'Continuer la négociation',
            self::ACCEPTED => 'Préparer la réservation',
            self::REJECTED => null,
            self::EXPIRED => 'Demander un nouveau devis',
            self::CANCELLED => null,
        };
    }

    /**
     * Action recommandée pour le prestataire
     */
    public function prestataireAction(): ?string
    {
        return match($this) {
            self::DRAFT => 'Finaliser et envoyer le devis',
            self::SENT => 'Attendre la réponse du client',
            self::VIEWED => 'Attendre la décision du client',
            self::NEGOTIATING => 'Négocier avec le client',
            self::ACCEPTED => 'Créer la réservation',
            self::REJECTED => 'Analyser les raisons du refus',
            self::EXPIRED => 'Proposer un nouveau devis',
            self::CANCELLED => null,
        };
    }

    /**
     * Statuts en attente
     */
    public static function pendingStatuses(): array
    {
        return [
            self::SENT,
            self::VIEWED,
            self::NEGOTIATING,
        ];
    }

    /**
     * Statuts finaux
     */
    public static function finalStatuses(): array
    {
        return [
            self::ACCEPTED,
            self::REJECTED,
            self::EXPIRED,
            self::CANCELLED,
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
}