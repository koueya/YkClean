<?php

namespace App\Enum;

/**
 * Enum BookingStatus - Statuts possibles pour une réservation
 * 
 * Flux typique :
 * PENDING → CONFIRMED → IN_PROGRESS → COMPLETED
 * 
 * Flux alternatifs :
 * PENDING → CANCELLED (annulation avant confirmation)
 * CONFIRMED → CANCELLED (annulation après confirmation)
 * * → NO_SHOW (client absent)
 */
enum BookingStatus: string
{
    /**
     * En attente de confirmation
     * État initial après création de la réservation
     */
    case PENDING = 'pending';

    /**
     * Confirmée par le prestataire
     * Le prestataire a accepté la réservation
     */
    case CONFIRMED = 'confirmed';

    /**
     * En cours
     * Le service est en train d'être effectué
     */
    case IN_PROGRESS = 'in_progress';

    /**
     * Terminée
     * Le service a été effectué avec succès
     */
    case COMPLETED = 'completed';

    /**
     * Annulée
     * La réservation a été annulée (par client ou prestataire)
     */
    case CANCELLED = 'cancelled';

    /**
     * Client absent
     * Le prestataire s'est présenté mais le client était absent
     */
    case NO_SHOW = 'no_show';

    /**
     * Remboursée
     * La réservation a été remboursée
     */
    case REFUNDED = 'refunded';

    /**
     * En attente de paiement
     * Réservation créée mais paiement non effectué
     */
    case AWAITING_PAYMENT = 'awaiting_payment';

    /**
     * Obtenir le libellé français du statut
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::NO_SHOW => 'Client absent',
            self::REFUNDED => 'Remboursée',
            self::AWAITING_PAYMENT => 'En attente de paiement',
        };
    }

    /**
     * Obtenir la couleur associée au statut (pour affichage UI)
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'orange',
            self::CONFIRMED => 'blue',
            self::IN_PROGRESS => 'purple',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::NO_SHOW => 'gray',
            self::REFUNDED => 'yellow',
            self::AWAITING_PAYMENT => 'orange',
        };
    }

    /**
     * Obtenir le code couleur hexadécimal
     */
    public function hexColor(): string
    {
        return match($this) {
            self::PENDING => '#FF9800',
            self::CONFIRMED => '#2196F3',
            self::IN_PROGRESS => '#9C27B0',
            self::COMPLETED => '#4CAF50',
            self::CANCELLED => '#F44336',
            self::NO_SHOW => '#9E9E9E',
            self::REFUNDED => '#FFC107',
            self::AWAITING_PAYMENT => '#FF9800',
        };
    }

    /**
     * Obtenir l'icône associée au statut
     */
    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'clock',
            self::CONFIRMED => 'check-circle',
            self::IN_PROGRESS => 'play-circle',
            self::COMPLETED => 'check-double',
            self::CANCELLED => 'times-circle',
            self::NO_SHOW => 'user-times',
            self::REFUNDED => 'undo',
            self::AWAITING_PAYMENT => 'credit-card',
        };
    }

    /**
     * Vérifie si le statut est final (ne peut plus changer)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::NO_SHOW,
            self::REFUNDED,
        ]);
    }

    /**
     * Vérifie si le statut est actif (réservation en cours ou à venir)
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
            self::IN_PROGRESS,
        ]);
    }

    /**
     * Vérifie si la réservation peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
            self::AWAITING_PAYMENT,
        ]);
    }

    /**
     * Vérifie si la réservation peut être modifiée
     */
    public function canBeModified(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
        ]);
    }

    /**
     * Vérifie si un paiement est nécessaire
     */
    public function requiresPayment(): bool
    {
        return in_array($this, [
            self::PENDING,
            self::CONFIRMED,
            self::IN_PROGRESS,
            self::AWAITING_PAYMENT,
        ]);
    }

    /**
     * Vérifie si un remboursement est possible
     */
    public function canBeRefunded(): bool
    {
        return in_array($this, [
            self::CONFIRMED,
            self::COMPLETED,
            self::NO_SHOW,
        ]);
    }

    /**
     * Obtenir les transitions possibles depuis ce statut
     */
    public function possibleTransitions(): array
    {
        return match($this) {
            self::PENDING => [
                self::CONFIRMED,
                self::CANCELLED,
                self::AWAITING_PAYMENT,
            ],
            self::CONFIRMED => [
                self::IN_PROGRESS,
                self::CANCELLED,
                self::NO_SHOW,
            ],
            self::IN_PROGRESS => [
                self::COMPLETED,
                self::CANCELLED,
            ],
            self::COMPLETED => [
                self::REFUNDED,
            ],
            self::AWAITING_PAYMENT => [
                self::PENDING,
                self::CONFIRMED,
                self::CANCELLED,
            ],
            self::CANCELLED, self::NO_SHOW, self::REFUNDED => [],
        };
    }

    /**
     * Vérifie si une transition vers un statut est possible
     */
    public function canTransitionTo(BookingStatus $newStatus): bool
    {
        return in_array($newStatus, $this->possibleTransitions());
    }

    /**
     * Obtenir tous les statuts actifs
     */
    public static function activeStatuses(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
            self::IN_PROGRESS,
        ];
    }

    /**
     * Obtenir tous les statuts finaux
     */
    public static function finalStatuses(): array
    {
        return [
            self::COMPLETED,
            self::CANCELLED,
            self::NO_SHOW,
            self::REFUNDED,
        ];
    }

    /**
     * Obtenir tous les statuts annulables
     */
    public static function cancellableStatuses(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
            self::AWAITING_PAYMENT,
        ];
    }

    /**
     * Créer depuis une chaîne de caractères
     */
    public static function fromString(string $status): ?self
    {
        return self::tryFrom($status);
    }

    /**
     * Obtenir la description détaillée du statut
     */
    public function description(): string
    {
        return match($this) {
            self::PENDING => 'La réservation est en attente de confirmation par le prestataire.',
            self::CONFIRMED => 'La réservation a été confirmée. Le prestataire se présentera à la date et heure prévues.',
            self::IN_PROGRESS => 'Le service est actuellement en cours de réalisation.',
            self::COMPLETED => 'Le service a été effectué avec succès. Un avis peut être laissé.',
            self::CANCELLED => 'La réservation a été annulée.',
            self::NO_SHOW => 'Le prestataire s\'est présenté mais le client était absent.',
            self::REFUNDED => 'La réservation a été remboursée au client.',
            self::AWAITING_PAYMENT => 'La réservation est en attente de paiement.',
        };
    }

    /**
     * Obtenir l'action recommandée pour le client
     */
    public function clientAction(): ?string
    {
        return match($this) {
            self::PENDING => 'Attendez la confirmation du prestataire',
            self::CONFIRMED => 'Préparez-vous pour le rendez-vous',
            self::IN_PROGRESS => 'Le service est en cours',
            self::COMPLETED => 'Laissez un avis sur le prestataire',
            self::CANCELLED => null,
            self::NO_SHOW => 'Contactez le support',
            self::REFUNDED => null,
            self::AWAITING_PAYMENT => 'Effectuez le paiement',
        };
    }

    /**
     * Obtenir l'action recommandée pour le prestataire
     */
    public function prestataireAction(): ?string
    {
        return match($this) {
            self::PENDING => 'Confirmez ou refusez la réservation',
            self::CONFIRMED => 'Préparez-vous pour le rendez-vous',
            self::IN_PROGRESS => 'Effectuez le service',
            self::COMPLETED => 'Réservation terminée',
            self::CANCELLED => null,
            self::NO_SHOW => 'Signalez l\'absence du client',
            self::REFUNDED => null,
            self::AWAITING_PAYMENT => 'En attente du paiement client',
        };
    }

    /**
     * Obtenir le badge HTML (Tailwind CSS)
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
     * Obtenir toutes les valeurs possibles
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Obtenir tous les cas avec leurs labels
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
     * Obtenir un statut aléatoire (pour tests/fixtures)
     */
    public static function random(): self
    {
        $cases = self::cases();
        return $cases[array_rand($cases)];
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
            'isFinal' => $this->isFinal(),
            'isActive' => $this->isActive(),
            'canBeCancelled' => $this->canBeCancelled(),
            'canBeModified' => $this->canBeModified(),
            'description' => $this->description(),
        ];
    }

    /**
     * Conversion en chaîne
     */
    public function __toString(): string
    {
        return $this->value;
    }
}