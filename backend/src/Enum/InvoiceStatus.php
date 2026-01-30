<?php

namespace App\Enum;

/**
 * Enum InvoiceStatus - Statuts possibles pour une facture
 */
enum InvoiceStatus: string
{
    /**
     * Brouillon
     */
    case DRAFT = 'draft';

    /**
     * Envoyée
     */
    case SENT = 'sent';

    /**
     * Payée
     */
    case PAID = 'paid';

    /**
     * Partiellement payée
     */
    case PARTIALLY_PAID = 'partially_paid';

    /**
     * En retard
     */
    case OVERDUE = 'overdue';

    /**
     * Annulée
     */
    case CANCELLED = 'cancelled';

    /**
     * Remboursée
     */
    case REFUNDED = 'refunded';

    /**
     * Obtenir le libellé français
     */
    public function label(): string
    {
        return match($this) {
            self::DRAFT => 'Brouillon',
            self::SENT => 'Envoyée',
            self::PAID => 'Payée',
            self::PARTIALLY_PAID => 'Partiellement payée',
            self::OVERDUE => 'En retard',
            self::CANCELLED => 'Annulée',
            self::REFUNDED => 'Remboursée',
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
            self::PAID => 'green',
            self::PARTIALLY_PAID => 'yellow',
            self::OVERDUE => 'red',
            self::CANCELLED => 'gray',
            self::REFUNDED => 'orange',
        };
    }

    /**
     * Vérifie si la facture est payée
     */
    public function isPaid(): bool
    {
        return $this === self::PAID;
    }

    /**
     * Vérifie si la facture nécessite un paiement
     */
    public function requiresPayment(): bool
    {
        return in_array($this, [
            self::SENT,
            self::PARTIALLY_PAID,
            self::OVERDUE,
        ]);
    }

    /**
     * Vérifie si la facture peut être modifiée
     */
    public function canBeModified(): bool
    {
        return $this === self::DRAFT;
    }
}