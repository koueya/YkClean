<?php

namespace App\Enum;

/**
 * Enum ServiceRequestStatus - Statuts possibles pour une demande de service
 * 
 * Flux typique :
 * OPEN → QUOTES_RECEIVED → IN_PROGRESS → COMPLETED
 * 
 * Une ServiceRequest est la demande initiale du client
 * Elle génère des devis (Quotes) qui deviennent des réservations (Bookings)
 */
enum ServiceRequestStatus: string
{
    /**
     * Ouverte
     * La demande vient d'être créée et attend des devis
     */
    case OPEN = 'open';

    /**
     * Devis reçus
     * Un ou plusieurs prestataires ont envoyé des devis
     */
    case QUOTES_RECEIVED = 'quotes_received';

    /**
     * En cours
     * Un devis a été accepté et une réservation est en cours
     */
    case IN_PROGRESS = 'in_progress';

    /**
     * Terminée
     * Le service a été effectué avec succès
     */
    case COMPLETED = 'completed';

    /**
     * Annulée
     * La demande a été annulée par le client
     */
    case CANCELLED = 'cancelled';

    /**
     * Expirée
     * Aucun devis reçu dans le délai imparti
     */
    case EXPIRED = 'expired';

    /**
     * En attente de réponse
     * Client doit choisir un devis
     */
    case AWAITING_RESPONSE = 'awaiting_response';

    /**
     * Brouillon
     * Demande non encore publiée
     */
    case DRAFT = 'draft';

    /**
     * Obtenir le libellé français du statut
     */
    public function label(): string
    {
        return match($this) {
            self::OPEN => 'Ouverte',
            self::QUOTES_RECEIVED => 'Devis reçus',
            self::IN_PROGRESS => 'En cours',
            self::COMPLETED => 'Terminée',
            self::CANCELLED => 'Annulée',
            self::EXPIRED => 'Expirée',
            self::AWAITING_RESPONSE => 'En attente de réponse',
            self::DRAFT => 'Brouillon',
        };
    }

    /**
     * Obtenir la couleur associée au statut
     */
    public function color(): string
    {
        return match($this) {
            self::OPEN => 'blue',
            self::QUOTES_RECEIVED => 'purple',
            self::IN_PROGRESS => 'orange',
            self::COMPLETED => 'green',
            self::CANCELLED => 'red',
            self::EXPIRED => 'gray',
            self::AWAITING_RESPONSE => 'yellow',
            self::DRAFT => 'gray',
        };
    }

    /**
     * Obtenir le code couleur hexadécimal
     */
    public function hexColor(): string
    {
        return match($this) {
            self::OPEN => '#2196F3',
            self::QUOTES_RECEIVED => '#9C27B0',
            self::IN_PROGRESS => '#FF9800',
            self::COMPLETED => '#4CAF50',
            self::CANCELLED => '#F44336',
            self::EXPIRED => '#9E9E9E',
            self::AWAITING_RESPONSE => '#FFC107',
            self::DRAFT => '#757575',
        };
    }

    /**
     * Obtenir l'icône associée au statut
     */
    public function icon(): string
    {
        return match($this) {
            self::OPEN => 'folder-open',
            self::QUOTES_RECEIVED => 'file-invoice',
            self::IN_PROGRESS => 'spinner',
            self::COMPLETED => 'check-circle',
            self::CANCELLED => 'times-circle',
            self::EXPIRED => 'clock',
            self::AWAITING_RESPONSE => 'hourglass-half',
            self::DRAFT => 'file-alt',
        };
    }

    /**
     * Vérifie si le statut est actif (demande en cours)
     */
    public function isActive(): bool
    {
        return in_array($this, [
            self::OPEN,
            self::QUOTES_RECEIVED,
            self::IN_PROGRESS,
            self::AWAITING_RESPONSE,
        ]);
    }

    /**
     * Vérifie si le statut est final (ne peut plus changer)
     */
    public function isFinal(): bool
    {
        return in_array($this, [
            self::COMPLETED,
            self::CANCELLED,
            self::EXPIRED,
        ]);
    }

    /**
     * Vérifie si la demande peut recevoir des devis
     */
    public function canReceiveQuotes(): bool
    {
        return in_array($this, [
            self::OPEN,
            self::QUOTES_RECEIVED,
            self::AWAITING_RESPONSE,
        ]);
    }

    /**
     * Vérifie si la demande peut être modifiée
     */
    public function canBeModified(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::OPEN,
        ]);
    }

    /**
     * Vérifie si la demande peut être annulée
     */
    public function canBeCancelled(): bool
    {
        return in_array($this, [
            self::DRAFT,
            self::OPEN,
            self::QUOTES_RECEIVED,
            self::AWAITING_RESPONSE,
        ]);
    }

    /**
     * Vérifie si la demande peut être publiée
     */
    public function canBePublished(): bool
    {
        return $this === self::DRAFT;
    }

    /**
     * Vérifie si le client doit choisir un devis
     */
    public function requiresClientAction(): bool
    {
        return in_array($this, [
            self::QUOTES_RECEIVED,
            self::AWAITING_RESPONSE,
        ]);
    }

    /**
     * Vérifie si les prestataires peuvent voir cette demande
     */
    public function isVisibleToPrestataires(): bool
    {
        return in_array($this, [
            self::OPEN,
            self::QUOTES_RECEIVED,
            self::AWAITING_RESPONSE,
        ]);
    }

    /**
     * Obtenir les transitions possibles depuis ce statut
     */
    public function possibleTransitions(): array
    {
        return match($this) {
            self::DRAFT => [
                self::OPEN,
                self::CANCELLED,
            ],
            self::OPEN => [
                self::QUOTES_RECEIVED,
                self::AWAITING_RESPONSE,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::QUOTES_RECEIVED => [
                self::AWAITING_RESPONSE,
                self::IN_PROGRESS,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::AWAITING_RESPONSE => [
                self::IN_PROGRESS,
                self::CANCELLED,
                self::EXPIRED,
            ],
            self::IN_PROGRESS => [
                self::COMPLETED,
                self::CANCELLED,
            ],
            self::COMPLETED, self::CANCELLED, self::EXPIRED => [],
        };
    }

    /**
     * Vérifie si une transition vers un statut est possible
     */
    public function canTransitionTo(ServiceRequestStatus $newStatus): bool
    {
        return in_array($newStatus, $this->possibleTransitions());
    }

    /**
     * Obtenir la description détaillée du statut
     */
    public function description(): string
    {
        return match($this) {
            self::DRAFT => 'La demande est en cours de rédaction et n\'est pas encore visible par les prestataires.',
            self::OPEN => 'La demande est ouverte et visible par les prestataires qui peuvent envoyer des devis.',
            self::QUOTES_RECEIVED => 'Un ou plusieurs prestataires ont envoyé des devis pour cette demande.',
            self::IN_PROGRESS => 'Un devis a été accepté et le service est en cours de réalisation.',
            self::COMPLETED => 'Le service a été effectué avec succès.',
            self::CANCELLED => 'La demande a été annulée.',
            self::EXPIRED => 'La demande a expiré sans recevoir de devis ou sans qu\'un devis soit accepté.',
            self::AWAITING_RESPONSE => 'Le client doit choisir un devis parmi ceux reçus.',
        };
    }

    /**
     * Obtenir l'action recommandée pour le client
     */
    public function clientAction(): ?string
    {
        return match($this) {
            self::DRAFT => 'Finalisez et publiez votre demande',
            self::OPEN => 'Attendez de recevoir des devis',
            self::QUOTES_RECEIVED => 'Consultez et comparez les devis reçus',
            self::AWAITING_RESPONSE => 'Choisissez un devis pour continuer',
            self::IN_PROGRESS => 'Le service est en cours',
            self::COMPLETED => 'Laissez un avis sur le prestataire',
            self::CANCELLED => null,
            self::EXPIRED => 'Créez une nouvelle demande',
        };
    }

    /**
     * Obtenir l'action recommandée pour le prestataire
     */
    public function prestataireAction(): ?string
    {
        return match($this) {
            self::DRAFT => null,
            self::OPEN => 'Envoyez un devis pour cette demande',
            self::QUOTES_RECEIVED => 'Attendez la réponse du client',
            self::AWAITING_RESPONSE => 'Le client étudie les devis',
            self::IN_PROGRESS => 'Effectuez le service',
            self::COMPLETED => 'Service terminé',
            self::CANCELLED => null,
            self::EXPIRED => null,
        };
    }

    /**
     * Obtenir le délai typique en jours
     */
    public function typicalDuration(): ?int
    {
        return match($this) {
            self::DRAFT => 1,
            self::OPEN => 3,
            self::QUOTES_RECEIVED => 2,
            self::AWAITING_RESPONSE => 2,
            self::IN_PROGRESS => 7,
            self::COMPLETED, self::CANCELLED, self::EXPIRED => null,
        };
    }

    /**
     * Obtenir tous les statuts actifs
     */
    public static function activeStatuses(): array
    {
        return [
            self::OPEN,
            self::QUOTES_RECEIVED,
            self::IN_PROGRESS,
            self::AWAITING_RESPONSE,
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
            self::EXPIRED,
        ];
    }

    /**
     * Obtenir tous les statuts où des devis peuvent être envoyés
     */
    public static function quotableStatuses(): array
    {
        return [
            self::OPEN,
            self::QUOTES_RECEIVED,
            self::AWAITING_RESPONSE,
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
     * Obtenir un statut aléatoire (pour tests/fixtures)
     */
    public static function random(): self
    {
        $cases = self::cases();
        return $cases[array_rand($cases)];
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
            'isActive' => $this->isActive(),
            'isFinal' => $this->isFinal(),
            'canReceiveQuotes' => $this->canReceiveQuotes(),
            'canBeModified' => $this->canBeModified(),
            'canBeCancelled' => $this->canBeCancelled(),
            'description' => $this->description(),
            'typicalDuration' => $this->typicalDuration(),
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