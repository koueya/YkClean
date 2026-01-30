<?php

namespace App\Message;

/**
 * Message pour mettre à jour les statistiques de la plateforme de manière asynchrone
 */
class UpdateStatisticsMessage
{
    private string $statisticType;
    private ?int $entityId;
    private array $metadata;
    private \DateTimeInterface $calculatedAt;

    public const TYPE_PLATFORM_GLOBAL = 'platform_global';
    public const TYPE_PRESTATAIRE_STATS = 'prestataire_stats';
    public const TYPE_CLIENT_STATS = 'client_stats';
    public const TYPE_CATEGORY_STATS = 'category_stats';
    public const TYPE_REVENUE_STATS = 'revenue_stats';
    public const TYPE_BOOKING_STATS = 'booking_stats';
    public const TYPE_RATING_STATS = 'rating_stats';
    public const TYPE_RESPONSE_RATE = 'response_rate';

    // ... constructeur et getters ...

    /**
     * Créer un message pour les statistiques globales de la plateforme
     */
    public static function forPlatform(array $metadata = []): self
    {
        return new self(self::TYPE_PLATFORM_GLOBAL, null, $metadata);
    }

    /**
     * Créer un message pour les statistiques d'un prestataire
     */
    public static function forPrestataire(int $prestataireId, array $metadata = []): self
    {
        return new self(self::TYPE_PRESTATAIRE_STATS, $prestataireId, $metadata);
    }

    /**
     * Créer un message pour les statistiques d'un client
     */
    public static function forClient(int $clientId, array $metadata = []): self
    {
        return new self(self::TYPE_CLIENT_STATS, $clientId, $metadata);
    }

    // ... autres méthodes factory ...
}