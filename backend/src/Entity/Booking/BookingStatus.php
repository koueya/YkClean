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
 * COMPLETED → REFUNDED (remboursement)
 * 
 * @method string value() Retourne la valeur de l'enum
 * @method string label() Retourne le libellé français
 * @method string color() Retourne la couleur Bootstrap
 * @method string hexColor() Retourne le code couleur hexadécimal
 * @method string icon() Retourne l'icône Font Awesome
 * @method bool isFinal() Vérifie si c'est un statut final
 * @method bool isActive() Vérifie si c'est un statut actif
 * @method bool canBeCancelled() Vérifie si peut être annulé
 * @method bool canBeModified() Vérifie si peut être modifié
 * @method bool requiresPayment() Vérifie si nécessite un paiement
 * @method bool canBeRefunded() Vérifie si peut être remboursé
 * @method array possibleTransitions() Retourne les transitions possibles
 * @method bool canTransitionTo(BookingStatus $newStatus) Vérifie si transition possible
 * @method string description() Retourne la description détaillée
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
     * En litige
     * Un désaccord existe entre client et prestataire
     */
    case DISPUTED = 'disputed';

    /**
     * Planifiée (ancien statut pour compatibilité)
     * Alias de CONFIRMED
     */
    case SCHEDULED = 'scheduled';

    // ==================== MÉTHODES DE LABELLISATION ====================

    /**
     * Obtenir le libellé français du statut
     */
    public function label(): string
    {
        return match($this) {
            self::PENDING => 'En attente',
            self::CONFIRMED => 'Confirmée',
            self::SCHEDULED => 'Planifiée',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::NO_SHOW => 'Client absent',
            self::REFUNDED => 'Remboursée',
            self::AWAITING_PAYMENT => 'En attente de paiement',
            self::DISPUTED => 'En litige',
        };
    }

    /**
     * Obtenir la couleur Bootstrap du statut
     */
    public function color(): string
    {
        return match($this) {
            self::PENDING => 'warning',
            self::AWAITING_PAYMENT => 'info',
            self::CONFIRMED, self::SCHEDULED => 'primary',
            self::IN_PROGRESS => 'info',
            self::COMPLETED => 'success',
            self::CANCELLED => 'secondary',
            self::NO_SHOW => 'danger',
            self::REFUNDED => 'dark',
            self::DISPUTED => 'danger',
        };
    }

    /**
     * Obtenir le code couleur hexadécimal
     */
    public function hexColor(): string
    {
        return match($this) {
            self::PENDING => '#ffc107',           // Jaune/Orange
            self::AWAITING_PAYMENT => '#17a2b8',  // Cyan
            self::CONFIRMED, self::SCHEDULED => '#007bff', // Bleu
            self::IN_PROGRESS => '#17a2b8',       // Cyan
            self::COMPLETED => '#28a745',         // Vert
            self::CANCELLED => '#6c757d',         // Gris
            self::NO_SHOW => '#dc3545',           // Rouge
            self::REFUNDED => '#343a40',          // Noir/Gris foncé
            self::DISPUTED => '#dc3545',          // Rouge
        };
    }

    /**
     * Obtenir l'icône Font Awesome associée
     */
    public function icon(): string
    {
        return match($this) {
            self::PENDING => 'fa-clock',
            self::AWAITING_PAYMENT => 'fa-credit-card',
            self::CONFIRMED, self::SCHEDULED => 'fa-check-circle',
            self::IN_PROGRESS => 'fa-spinner',
            self::COMPLETED => 'fa-check-double',
            self::CANCELLED => 'fa-times-circle',
            self::NO_SHOW => 'fa-user-slash',
            self::REFUNDED => 'fa-undo',
            self::DISPUTED => 'fa-exclamation-triangle',
        };
    }

    /**
     * Obtenir l'emoji associé
     */
    public function emoji(): string
    {
        return match($this) {
            self::PENDING => '⏳',
            self::AWAITING_PAYMENT => '💳',
            self::CONFIRMED, self::SCHEDULED => '✅',
            self::IN_PROGRESS => '🔄',
            self::COMPLETED => '✅',
            self::CANCELLED => '❌',
            self::NO_SHOW => '👻',
            self::REFUNDED => '↩️',
            self::DISPUTED => '⚠️',
        };
    }

    // ==================== MÉTHODES DE VÉRIFICATION ====================

    /**
     * Vérifie si le statut est final (pas de retour en arrière possible)
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
            self::SCHEDULED,
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
            self::SCHEDULED,
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
            self::SCHEDULED,
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
            self::SCHEDULED,
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
            self::SCHEDULED,
            self::COMPLETED,
            self::NO_SHOW,
        ]);
    }

    /**
     * Vérifie si un avis peut être laissé
     */
    public function canBeReviewed(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Vérifie si le prestataire peut démarrer le service
     */
    public function canBeStarted(): bool
    {
        return in_array($this, [
            self::CONFIRMED,
            self::SCHEDULED,
        ]);
    }

    /**
     * Vérifie si le prestataire peut terminer le service
     */
    public function canBeCompleted(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    // ==================== TRANSITIONS D'ÉTAT ====================

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
            self::SCHEDULED => [
                self::CONFIRMED,
                self::IN_PROGRESS,
                self::CANCELLED,
                self::NO_SHOW,
            ],
            self::CONFIRMED => [
                self::IN_PROGRESS,
                self::CANCELLED,
                self::NO_SHOW,
            ],
            self::IN_PROGRESS => [
                self::COMPLETED,
                self::CANCELLED,
                self::DISPUTED,
            ],
            self::COMPLETED => [
                self::REFUNDED,
                self::DISPUTED,
            ],
            self::AWAITING_PAYMENT => [
                self::PENDING,
                self::CONFIRMED,
                self::CANCELLED,
            ],
            self::DISPUTED => [
                self::COMPLETED,
                self::REFUNDED,
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
     * Valide une transition et retourne une erreur si invalide
     */
    public function validateTransition(BookingStatus $newStatus): ?string
    {
        if ($this === $newStatus) {
            return "Le statut est déjà '{$this->label()}'";
        }

        if (!$this->canTransitionTo($newStatus)) {
            return "Impossible de passer de '{$this->label()}' à '{$newStatus->label()}'";
        }

        return null;
    }

    // ==================== MÉTHODES STATIQUES ====================

    /**
     * Obtenir tous les statuts actifs
     */
    public static function activeStatuses(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
            self::SCHEDULED,
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
            self::SCHEDULED,
            self::AWAITING_PAYMENT,
        ];
    }

    /**
     * Obtenir tous les statuts nécessitant un paiement
     */
    public static function paymentRequiredStatuses(): array
    {
        return [
            self::PENDING,
            self::CONFIRMED,
            self::SCHEDULED,
            self::IN_PROGRESS,
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
     * Créer depuis une chaîne avec valeur par défaut
     */
    public static function fromStringOrDefault(string $status, self $default = self::PENDING): self
    {
        return self::tryFrom($status) ?? $default;
    }

    // ==================== DESCRIPTIONS ====================

    /**
     * Obtenir la description détaillée du statut
     */
    public function description(): string
    {
        return match($this) {
            self::PENDING => 'La réservation est en attente de confirmation par le prestataire.',
            self::CONFIRMED, self::SCHEDULED => 'La réservation a été confirmée. Le prestataire se présentera à la date et heure prévues.',
            self::IN_PROGRESS => 'Le service est actuellement en cours de réalisation.',
            self::COMPLETED => 'Le service a été effectué avec succès. Un avis peut être laissé.',
            self::CANCELLED => 'La réservation a été annulée.',
            self::NO_SHOW => 'Le prestataire s\'est présenté mais le client était absent.',
            self::REFUNDED => 'La réservation a été remboursée au client.',
            self::AWAITING_PAYMENT => 'La réservation est en attente de paiement.',
            self::DISPUTED => 'Un litige est en cours concernant cette réservation.',
        };
    }

    /**
     * Obtenir la description pour le client
     */
    public function descriptionForClient(): string
    {
        return match($this) {
            self::PENDING => 'Nous attendons la confirmation du prestataire.',
            self::CONFIRMED, self::SCHEDULED => 'Votre réservation est confirmée ! Le prestataire viendra comme prévu.',
            self::IN_PROGRESS => 'Le prestataire est actuellement chez vous.',
            self::COMPLETED => 'Le service est terminé. N\'oubliez pas de laisser un avis !',
            self::CANCELLED => 'Cette réservation a été annulée.',
            self::NO_SHOW => 'Le prestataire s\'est présenté mais vous étiez absent.',
            self::REFUNDED => 'Cette réservation a été remboursée.',
            self::AWAITING_PAYMENT => 'Veuillez effectuer le paiement pour confirmer votre réservation.',
            self::DISPUTED => 'Un problème a été signalé. Notre équipe examine la situation.',
        };
    }

    /**
     * Obtenir la description pour le prestataire
     */
    public function descriptionForPrestataire(): string
    {
        return match($this) {
            self::PENDING => 'Vous devez confirmer cette réservation.',
            self::CONFIRMED, self::SCHEDULED => 'Réservation confirmée. Présentez-vous à l\'heure prévue.',
            self::IN_PROGRESS => 'Service en cours. N\'oubliez pas de valider la fin.',
            self::COMPLETED => 'Service terminé avec succès.',
            self::CANCELLED => 'Cette réservation a été annulée.',
            self::NO_SHOW => 'Vous avez signalé l\'absence du client.',
            self::REFUNDED => 'Cette réservation a été remboursée au client.',
            self::AWAITING_PAYMENT => 'En attente du paiement du client.',
            self::DISPUTED => 'Un litige a été ouvert. Consultez les détails.',
        };
    }

    // ==================== ACTIONS RECOMMANDÉES ====================

    /**
     * Obtenir les actions recommandées pour le client
     */
    public function clientActions(): array
    {
        return match($this) {
            self::PENDING => [
                'wait' => 'Attendre la confirmation',
                'cancel' => 'Annuler la réservation',
            ],
            self::CONFIRMED, self::SCHEDULED => [
                'view' => 'Voir les détails',
                'cancel' => 'Annuler la réservation',
                'modify' => 'Modifier la réservation',
            ],
            self::IN_PROGRESS => [
                'view' => 'Suivre l\'avancement',
            ],
            self::COMPLETED => [
                'review' => 'Laisser un avis',
                'rebook' => 'Réserver à nouveau',
            ],
            self::AWAITING_PAYMENT => [
                'pay' => 'Payer maintenant',
                'cancel' => 'Annuler',
            ],
            default => [],
        };
    }

    /**
     * Obtenir les actions recommandées pour le prestataire
     */
    public function prestataireActions(): array
    {
        return match($this) {
            self::PENDING => [
                'confirm' => 'Confirmer la réservation',
                'decline' => 'Refuser la réservation',
            ],
            self::CONFIRMED, self::SCHEDULED => [
                'start' => 'Démarrer le service',
                'cancel' => 'Annuler',
                'report_no_show' => 'Signaler l\'absence du client',
            ],
            self::IN_PROGRESS => [
                'complete' => 'Terminer le service',
            ],
            self::COMPLETED => [
                'view_invoice' => 'Voir la facture',
            ],
            default => [],
        };
    }

    // ==================== UTILITAIRES ====================

    /**
     * Obtenir tous les statuts sous forme de tableau [value => label]
     */
    public static function toArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[$case->value] = $case->label();
        }
        return $array;
    }

    /**
     * Obtenir tous les statuts avec leurs détails
     */
    public static function toDetailedArray(): array
    {
        $array = [];
        foreach (self::cases() as $case) {
            $array[] = [
                'value' => $case->value,
                'label' => $case->label(),
                'color' => $case->color(),
                'hexColor' => $case->hexColor(),
                'icon' => $case->icon(),
                'emoji' => $case->emoji(),
                'isFinal' => $case->isFinal(),
                'isActive' => $case->isActive(),
                'description' => $case->description(),
            ];
        }
        return $array;
    }

    /**
     * Obtenir les options pour un formulaire select
     */
    public static function getSelectOptions(): array
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
     * Obtenir un statut aléatoire actif
     */
    public static function randomActive(): self
    {
        $active = self::activeStatuses();
        return $active[array_rand($active)];
    }

    /**
     * Obtenir un statut aléatoire final
     */
    public static function randomFinal(): self
    {
        $final = self::finalStatuses();
        return $final[array_rand($final)];
    }

    // ==================== SÉRIALISATION ====================

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
            'emoji' => $this->emoji(),
            'isFinal' => $this->isFinal(),
            'isActive' => $this->isActive(),
            'canBeCancelled' => $this->canBeCancelled(),
            'canBeModified' => $this->canBeModified(),
            'canBeReviewed' => $this->canBeReviewed(),
            'requiresPayment' => $this->requiresPayment(),
            'description' => $this->description(),
            'possibleTransitions' => array_map(
                fn($status) => $status->value,
                $this->possibleTransitions()
            ),
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