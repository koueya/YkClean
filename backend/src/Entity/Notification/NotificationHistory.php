<?php

namespace App\Entity\Notification;

use App\Entity\Service\ServiceRequest;
use App\Entity\User\Prestataire;
use App\Repository\Notification\NotificationHistoryRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Historique des notifications envoyées aux prestataires
 * Permet de tracker les notifications et analyser les performances
 */
#[ORM\Entity(repositoryClass: NotificationHistoryRepository::class)]
#[ORM\Table(name: 'notification_history')]
#[ORM\Index(name: 'idx_service_request', columns: ['service_request_id'])]
#[ORM\Index(name: 'idx_prestataire', columns: ['prestataire_id'])]
#[ORM\Index(name: 'idx_notified_at', columns: ['notified_at'])]
class NotificationHistory
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: ServiceRequest::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ServiceRequest $serviceRequest;

    #[ORM\ManyToOne(targetEntity: Prestataire::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Prestataire $prestataire;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private float $matchingScore;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $scoreDetails = [];

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2, nullable: true)]
    private ?float $distance = null;

    #[ORM\Column(type: 'json')]
    private array $channels = [];

    #[ORM\Column(type: 'string', length: 20)]
    private string $priority = 'medium';

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $notifiedAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $viewedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $quotedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $wasViewed = false;

    #[ORM\Column(type: 'boolean')]
    private bool $hasQuoted = false;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $responseStatus = null; // viewed, quoted, ignored, expired

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $responseTimeMinutes = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $notificationResults = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isRenotification = false;

    #[ORM\Column(type: 'integer')]
    private int $notificationAttempt = 1;

    public function __construct()
    {
        $this->notifiedAt = new \DateTimeImmutable();
    }

    // ============ Getters and Setters ============

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getServiceRequest(): ServiceRequest
    {
        return $this->serviceRequest;
    }

    public function setServiceRequest(ServiceRequest $serviceRequest): self
    {
        $this->serviceRequest = $serviceRequest;
        return $this;
    }

    public function getPrestataire(): Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(Prestataire $prestataire): self
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getMatchingScore(): float
    {
        return $this->matchingScore;
    }

    public function setMatchingScore(float $matchingScore): self
    {
        $this->matchingScore = $matchingScore;
        return $this;
    }

    public function getScoreDetails(): ?array
    {
        return $this->scoreDetails;
    }

    public function setScoreDetails(?array $scoreDetails): self
    {
        $this->scoreDetails = $scoreDetails;
        return $this;
    }

    public function getDistance(): ?float
    {
        return $this->distance;
    }

    public function setDistance(?float $distance): self
    {
        $this->distance = $distance;
        return $this;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function getNotifiedAt(): \DateTimeImmutable
    {
        return $this->notifiedAt;
    }

    public function setNotifiedAt(\DateTimeImmutable $notifiedAt): self
    {
        $this->notifiedAt = $notifiedAt;
        return $this;
    }

    public function getViewedAt(): ?\DateTimeImmutable
    {
        return $this->viewedAt;
    }

    public function setViewedAt(?\DateTimeImmutable $viewedAt): self
    {
        $this->viewedAt = $viewedAt;
        $this->wasViewed = true;
        $this->calculateResponseTime();
        
        if (!$this->responseStatus) {
            $this->responseStatus = 'viewed';
        }
        
        return $this;
    }

    public function getQuotedAt(): ?\DateTimeImmutable
    {
        return $this->quotedAt;
    }

    public function setQuotedAt(?\DateTimeImmutable $quotedAt): self
    {
        $this->quotedAt = $quotedAt;
        $this->hasQuoted = true;
        $this->responseStatus = 'quoted';
        $this->calculateResponseTime();
        return $this;
    }

    public function wasViewed(): bool
    {
        return $this->wasViewed;
    }

    public function setWasViewed(bool $wasViewed): self
    {
        $this->wasViewed = $wasViewed;
        return $this;
    }

    public function hasQuoted(): bool
    {
        return $this->hasQuoted;
    }

    public function setHasQuoted(bool $hasQuoted): self
    {
        $this->hasQuoted = $hasQuoted;
        return $this;
    }

    public function getResponseStatus(): ?string
    {
        return $this->responseStatus;
    }

    public function setResponseStatus(?string $responseStatus): self
    {
        $this->responseStatus = $responseStatus;
        return $this;
    }

    public function getResponseTimeMinutes(): ?int
    {
        return $this->responseTimeMinutes;
    }

    public function setResponseTimeMinutes(?int $responseTimeMinutes): self
    {
        $this->responseTimeMinutes = $responseTimeMinutes;
        return $this;
    }

    public function getNotificationResults(): ?array
    {
        return $this->notificationResults;
    }

    public function setNotificationResults(?array $notificationResults): self
    {
        $this->notificationResults = $notificationResults;
        return $this;
    }

    public function isRenotification(): bool
    {
        return $this->isRenotification;
    }

    public function setIsRenotification(bool $isRenotification): self
    {
        $this->isRenotification = $isRenotification;
        return $this;
    }

    public function getNotificationAttempt(): int
    {
        return $this->notificationAttempt;
    }

    public function setNotificationAttempt(int $notificationAttempt): self
    {
        $this->notificationAttempt = $notificationAttempt;
        return $this;
    }

    // ============ Helper Methods ============

    /**
     * Calcule le temps de réponse en minutes
     */
    private function calculateResponseTime(): void
    {
        $responseDate = $this->quotedAt ?? $this->viewedAt;
        
        if ($responseDate) {
            $interval = $this->notifiedAt->diff($responseDate);
            $this->responseTimeMinutes = ($interval->days * 24 * 60) + 
                                        ($interval->h * 60) + 
                                        $interval->i;
        }
    }

    /**
     * Marque comme vue
     */
    public function markAsViewed(): self
    {
        if (!$this->wasViewed) {
            $this->setViewedAt(new \DateTimeImmutable());
        }
        return $this;
    }

    /**
     * Marque comme ayant reçu un devis
     */
    public function markAsQuoted(): self
    {
        if (!$this->hasQuoted) {
            $this->setQuotedAt(new \DateTimeImmutable());
        }
        return $this;
    }

    /**
     * Marque comme ignorée (après expiration)
     */
    public function markAsIgnored(): self
    {
        if (!$this->wasViewed && !$this->hasQuoted) {
            $this->responseStatus = 'ignored';
        }
        return $this;
    }

    /**
     * Marque comme expirée
     */
    public function markAsExpired(): self
    {
        if (!$this->hasQuoted) {
            $this->responseStatus = 'expired';
        }
        return $this;
    }

    /**
     * Vérifie si le prestataire a répondu dans les X minutes
     */
    public function hasRespondedWithin(int $minutes): bool
    {
        return $this->responseTimeMinutes !== null && 
               $this->responseTimeMinutes <= $minutes;
    }

    /**
     * Obtient le taux de réponse pour ce prestataire sur cette notification
     */
    public function getResponseRate(): float
    {
        if ($this->hasQuoted) {
            return 100.0;
        } elseif ($this->wasViewed) {
            return 50.0;
        } else {
            return 0.0;
        }
    }

    /**
     * Vérifie si c'est une réponse rapide (< 1h)
     */
    public function isFastResponse(): bool
    {
        return $this->hasRespondedWithin(60);
    }

    /**
     * Obtient une description lisible du statut
     */
    public function getStatusDescription(): string
    {
        return match($this->responseStatus) {
            'quoted' => 'Devis soumis',
            'viewed' => 'Demande consultée',
            'ignored' => 'Ignorée',
            'expired' => 'Expirée',
            default => 'En attente',
        };
    }
}