<?php

namespace App\Entity\Notification;

use App\Entity\User\User;
use App\Repository\Notification\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente une notification envoyée à un utilisateur
 * Supporte plusieurs canaux : email, SMS, push, in-app
 * Permet le suivi de lecture et de statut d'envoi
 */
#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\Index(columns: ['user_id', 'is_read'], name: 'idx_user_read')]
#[ORM\Index(columns: ['user_id', 'created_at'], name: 'idx_user_created')]
#[ORM\Index(columns: ['type'], name: 'idx_type')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['priority'], name: 'idx_priority')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    // Types de notifications
    public const TYPE_BOOKING_CONFIRMED = 'booking_confirmed';
    public const TYPE_BOOKING_CANCELLED = 'booking_cancelled';
    public const TYPE_BOOKING_REMINDER = 'booking_reminder';
    public const TYPE_BOOKING_COMPLETED = 'booking_completed';
    public const TYPE_BOOKING_STARTED = 'booking_started';
    public const TYPE_QUOTE_RECEIVED = 'quote_received';
    public const TYPE_QUOTE_ACCEPTED = 'quote_accepted';
    public const TYPE_QUOTE_REJECTED = 'quote_rejected';
    public const TYPE_SERVICE_REQUEST_NEW = 'service_request_new';
    public const TYPE_REPLACEMENT_NEEDED = 'replacement_needed';
    public const TYPE_REPLACEMENT_FOUND = 'replacement_found';
    public const TYPE_PAYMENT_RECEIVED = 'payment_received';
    public const TYPE_PAYMENT_FAILED = 'payment_failed';
    public const TYPE_REVIEW_REQUEST = 'review_request';
    public const TYPE_REVIEW_RECEIVED = 'review_received';
    public const TYPE_DOCUMENT_EXPIRING = 'document_expiring';
    public const TYPE_AVAILABILITY_CONFLICT = 'availability_conflict';
    public const TYPE_PRESTATAIRE_APPROVED = 'prestataire_approved';
    public const TYPE_PRESTATAIRE_REJECTED = 'prestataire_rejected';
    public const TYPE_NEW_MESSAGE = 'new_message';
    public const TYPE_SYSTEM_ANNOUNCEMENT = 'system_announcement';

    // Canaux de notification
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_IN_APP = 'in_app';

    // Priorités
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_NORMAL = 'normal';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    // Statuts d'envoi
    public const STATUS_PENDING = 'pending';     // En attente d'envoi
    public const STATUS_SENT = 'sent';           // Envoyée
    public const STATUS_FAILED = 'failed';       // Échec d'envoi
    public const STATUS_DELIVERED = 'delivered'; // Livrée
    public const STATUS_READ = 'read';           // Lue

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?int $id = null;

    /**
     * Utilisateur destinataire
     */
    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'notifications')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'L\'utilisateur est obligatoire', groups: ['notification:create'])]
    #[Groups(['notification:admin'])]
    private ?User $user = null;

    /**
     * Type de notification
     */
    #[ORM\Column(type: Types::STRING, length: 50)]
    #[Assert\NotBlank(message: 'Le type est obligatoire', groups: ['notification:create'])]
    #[Assert\Choice(
        choices: [
            self::TYPE_BOOKING_CONFIRMED,
            self::TYPE_BOOKING_CANCELLED,
            self::TYPE_BOOKING_REMINDER,
            self::TYPE_BOOKING_COMPLETED,
            self::TYPE_BOOKING_STARTED,
            self::TYPE_QUOTE_RECEIVED,
            self::TYPE_QUOTE_ACCEPTED,
            self::TYPE_QUOTE_REJECTED,
            self::TYPE_SERVICE_REQUEST_NEW,
            self::TYPE_REPLACEMENT_NEEDED,
            self::TYPE_REPLACEMENT_FOUND,
            self::TYPE_PAYMENT_RECEIVED,
            self::TYPE_PAYMENT_FAILED,
            self::TYPE_REVIEW_REQUEST,
            self::TYPE_REVIEW_RECEIVED,
            self::TYPE_DOCUMENT_EXPIRING,
            self::TYPE_AVAILABILITY_CONFLICT,
            self::TYPE_PRESTATAIRE_APPROVED,
            self::TYPE_PRESTATAIRE_REJECTED,
            self::TYPE_NEW_MESSAGE,
            self::TYPE_SYSTEM_ANNOUNCEMENT
        ],
        message: 'Type de notification invalide'
    )]
    #[Groups(['notification:read', 'notification:list', 'notification:create'])]
    private string $type;

    /**
     * Canaux utilisés pour envoyer la notification
     */
    #[ORM\Column(type: Types::JSON)]
    #[Assert\NotBlank(groups: ['notification:create'])]
    #[Groups(['notification:read', 'notification:detail', 'notification:create'])]
    private array $channels = [];

    /**
     * Titre de la notification
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire', groups: ['notification:create'])]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le titre ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['notification:read', 'notification:list', 'notification:create'])]
    private string $title;

    /**
     * Message/corps de la notification
     */
    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Le message est obligatoire', groups: ['notification:create'])]
    #[Assert\Length(
        max: 2000,
        maxMessage: 'Le message ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['notification:read', 'notification:list', 'notification:create'])]
    private string $message;

    /**
     * Sujet (utilisé pour les emails)
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Le sujet ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?string $subject = null;

    /**
     * Données additionnelles (IDs, URLs, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?array $data = [];

    /**
     * URL d'action (pour les notifications avec CTA)
     */
    #[ORM\Column(type: Types::STRING, length: 500, nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: 'L\'URL ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?string $actionUrl = null;

    /**
     * Libellé du bouton d'action
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(
        max: 100,
        maxMessage: 'Le libellé ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?string $actionLabel = null;

    /**
     * Priorité de la notification
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(
        choices: [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT
        ],
        message: 'Priorité invalide'
    )]
    #[Groups(['notification:read', 'notification:list', 'notification:create'])]
    private string $priority = self::PRIORITY_NORMAL;

    /**
     * Statut d'envoi
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Assert\Choice(
        choices: [
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_DELIVERED,
            self::STATUS_READ
        ],
        message: 'Statut invalide'
    )]
    #[Groups(['notification:read', 'notification:list'])]
    private string $status = self::STATUS_PENDING;

    /**
     * La notification a-t-elle été lue ?
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['notification:read', 'notification:list'])]
    private bool $isRead = false;

    /**
     * Date de lecture
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?\DateTimeImmutable $readAt = null;

    /**
     * Date d'envoi
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?\DateTimeImmutable $sentAt = null;

    /**
     * Date de livraison (pour push/SMS)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?\DateTimeImmutable $deliveredAt = null;

    /**
     * Date d'expiration (après laquelle la notification n'est plus pertinente)
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read', 'notification:detail'])]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * Raison de l'échec (si status = failed)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['notification:admin'])]
    private ?string $failureReason = null;

    /**
     * Nombre de tentatives d'envoi
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['notification:admin'])]
    private int $sendAttempts = 0;

    /**
     * Date de dernière tentative
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:admin'])]
    private ?\DateTimeImmutable $lastAttemptAt = null;

    /**
     * Icône à afficher (pour in-app)
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?string $icon = null;

    /**
     * Couleur/badge (pour in-app)
     */
    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Groups(['notification:read', 'notification:list'])]
    private ?string $badge = null;

    /**
     * Template utilisé pour l'email
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['notification:admin'])]
    private ?string $emailTemplate = null;

    /**
     * Métadonnées additionnelles
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['notification:admin'])]
    private ?array $metadata = [];

    /**
     * Date de création
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['notification:read', 'notification:list'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de mise à jour
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['notification:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->channels = [self::CHANNEL_IN_APP];
        $this->data = [];
        $this->metadata = [];
        $this->isRead = false;
        $this->sendAttempts = 0;
        $this->status = self::STATUS_PENDING;
        $this->priority = self::PRIORITY_NORMAL;
    }

    // ==================== LIFECYCLE CALLBACKS ====================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ==================== GETTERS / SETTERS ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getChannels(): array
    {
        return $this->channels ?? [];
    }

    public function setChannels(array $channels): self
    {
        $this->channels = $channels;
        return $this;
    }

    public function addChannel(string $channel): self
    {
        if (!in_array($channel, $this->channels)) {
            $this->channels[] = $channel;
        }
        return $this;
    }

    public function hasChannel(string $channel): bool
    {
        return in_array($channel, $this->channels);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;
        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;
        return $this;
    }

    public function getSubject(): ?string
    {
        return $this->subject;
    }

    public function setSubject(?string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function getData(): ?array
    {
        return $this->data ?? [];
    }

    public function setData(?array $data): self
    {
        $this->data = $data;
        return $this;
    }

    public function addData(string $key, mixed $value): self
    {
        if ($this->data === null) {
            $this->data = [];
        }
        $this->data[$key] = $value;
        return $this;
    }

    public function getDataValue(string $key): mixed
    {
        return $this->data[$key] ?? null;
    }

    public function getActionUrl(): ?string
    {
        return $this->actionUrl;
    }

    public function setActionUrl(?string $actionUrl): self
    {
        $this->actionUrl = $actionUrl;
        return $this;
    }

    public function getActionLabel(): ?string
    {
        return $this->actionLabel;
    }

    public function setActionLabel(?string $actionLabel): self
    {
        $this->actionLabel = $actionLabel;
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

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $oldStatus = $this->status;
        $this->status = $status;

        // Mise à jour automatique des dates
        $now = new \DateTimeImmutable();

        if ($status === self::STATUS_SENT && $this->sentAt === null) {
            $this->sentAt = $now;
        } elseif ($status === self::STATUS_DELIVERED && $this->deliveredAt === null) {
            $this->deliveredAt = $now;
        } elseif ($status === self::STATUS_READ && $this->readAt === null) {
            $this->readAt = $now;
            $this->isRead = true;
        }

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): self
    {
        $this->isRead = $isRead;

        if ($isRead && $this->readAt === null) {
            $this->readAt = new \DateTimeImmutable();
            if ($this->status !== self::STATUS_READ) {
                $this->status = self::STATUS_READ;
            }
        }

        return $this;
    }

    public function getReadAt(): ?\DateTimeImmutable
    {
        return $this->readAt;
    }

    public function setReadAt(?\DateTimeImmutable $readAt): self
    {
        $this->readAt = $readAt;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getDeliveredAt(): ?\DateTimeImmutable
    {
        return $this->deliveredAt;
    }

    public function setDeliveredAt(?\DateTimeImmutable $deliveredAt): self
    {
        $this->deliveredAt = $deliveredAt;
        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;
        return $this;
    }

    public function getFailureReason(): ?string
    {
        return $this->failureReason;
    }

    public function setFailureReason(?string $failureReason): self
    {
        $this->failureReason = $failureReason;
        return $this;
    }

    public function getSendAttempts(): int
    {
        return $this->sendAttempts;
    }

    public function setSendAttempts(int $sendAttempts): self
    {
        $this->sendAttempts = $sendAttempts;
        return $this;
    }

    public function incrementSendAttempts(): self
    {
        $this->sendAttempts++;
        $this->lastAttemptAt = new \DateTimeImmutable();
        return $this;
    }

    public function getLastAttemptAt(): ?\DateTimeImmutable
    {
        return $this->lastAttemptAt;
    }

    public function setLastAttemptAt(?\DateTimeImmutable $lastAttemptAt): self
    {
        $this->lastAttemptAt = $lastAttemptAt;
        return $this;
    }

    public function getIcon(): ?string
    {
        return $this->icon;
    }

    public function setIcon(?string $icon): self
    {
        $this->icon = $icon;
        return $this;
    }

    public function getBadge(): ?string
    {
        return $this->badge;
    }

    public function setBadge(?string $badge): self
    {
        $this->badge = $badge;
        return $this;
    }

    public function getEmailTemplate(): ?string
    {
        return $this->emailTemplate;
    }

    public function setEmailTemplate(?string $emailTemplate): self
    {
        $this->emailTemplate = $emailTemplate;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata ?? [];
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function addMetadata(string $key, mixed $value): self
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }
        $this->metadata[$key] = $value;
        return $this;
    }

    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // ==================== MÉTHODES UTILITAIRES ====================

    /**
     * Marque la notification comme lue
     */
    public function markAsRead(): self
    {
        $this->isRead = true;
        $this->readAt = new \DateTimeImmutable();
        $this->status = self::STATUS_READ;
        return $this;
    }

    /**
     * Marque la notification comme envoyée
     */
    public function markAsSent(): self
    {
        $this->status = self::STATUS_SENT;
        $this->sentAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Marque la notification comme échouée
     */
    public function markAsFailed(string $reason): self
    {
        $this->status = self::STATUS_FAILED;
        $this->failureReason = $reason;
        return $this;
    }

    /**
     * Vérifie si la notification a expiré
     */
    public function isExpired(): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        return $this->expiresAt < new \DateTimeImmutable();
    }

    /**
     * Vérifie si la notification peut être renvoyée
     */
    public function canBeRetried(): bool
    {
        return $this->status === self::STATUS_FAILED 
            && $this->sendAttempts < 3 
            && !$this->isExpired();
    }

    /**
     * Vérifie si c'est une notification urgente
     */
    public function isUrgent(): bool
    {
        return $this->priority === self::PRIORITY_URGENT;
    }

    /**
     * Vérifie si c'est une notification haute priorité
     */
    public function isHighPriority(): bool
    {
        return in_array($this->priority, [self::PRIORITY_HIGH, self::PRIORITY_URGENT]);
    }

    /**
     * Vérifie si la notification a une action
     */
    public function hasAction(): bool
    {
        return $this->actionUrl !== null && !empty($this->actionUrl);
    }

    /**
     * Calcule l'âge de la notification
     */
    public function getAge(): \DateInterval
    {
        $now = new \DateTimeImmutable();
        return $this->createdAt->diff($now);
    }

    /**
     * Retourne l'âge formaté
     */
    public function getAgeFormatted(): string
    {
        $age = $this->getAge();

        if ($age->d > 0) {
            return $age->d === 1 ? 'il y a 1 jour' : "il y a {$age->d} jours";
        }

        if ($age->h > 0) {
            return $age->h === 1 ? 'il y a 1 heure' : "il y a {$age->h} heures";
        }

        if ($age->i > 0) {
            return $age->i === 1 ? 'il y a 1 minute' : "il y a {$age->i} minutes";
        }

        return 'à l\'instant';
    }

    /**
     * Obtient le libellé du type
     */
    public function getTypeLabel(): string
    {
        return match ($this->type) {
            self::TYPE_BOOKING_CONFIRMED => 'Réservation confirmée',
            self::TYPE_BOOKING_CANCELLED => 'Réservation annulée',
            self::TYPE_BOOKING_REMINDER => 'Rappel de réservation',
            self::TYPE_BOOKING_COMPLETED => 'Service terminé',
            self::TYPE_BOOKING_STARTED => 'Service démarré',
            self::TYPE_QUOTE_RECEIVED => 'Nouveau devis',
            self::TYPE_QUOTE_ACCEPTED => 'Devis accepté',
            self::TYPE_QUOTE_REJECTED => 'Devis refusé',
            self::TYPE_SERVICE_REQUEST_NEW => 'Nouvelle demande',
            self::TYPE_REPLACEMENT_NEEDED => 'Remplacement nécessaire',
            self::TYPE_REPLACEMENT_FOUND => 'Remplaçant trouvé',
            self::TYPE_PAYMENT_RECEIVED => 'Paiement reçu',
            self::TYPE_PAYMENT_FAILED => 'Échec de paiement',
            self::TYPE_REVIEW_REQUEST => 'Demande d\'avis',
            self::TYPE_REVIEW_RECEIVED => 'Nouvel avis',
            self::TYPE_DOCUMENT_EXPIRING => 'Document expirant',
            self::TYPE_AVAILABILITY_CONFLICT => 'Conflit de disponibilité',
            self::TYPE_PRESTATAIRE_APPROVED => 'Compte approuvé',
            self::TYPE_PRESTATAIRE_REJECTED => 'Compte refusé',
            self::TYPE_NEW_MESSAGE => 'Nouveau message',
            self::TYPE_SYSTEM_ANNOUNCEMENT => 'Annonce système',
            default => 'Notification'
        };
    }

    /**
     * Obtient la classe CSS pour la priorité
     */
    public function getPriorityClass(): string
    {
        return match ($this->priority) {
            self::PRIORITY_LOW => 'priority-low',
            self::PRIORITY_NORMAL => 'priority-normal',
            self::PRIORITY_HIGH => 'priority-high',
            self::PRIORITY_URGENT => 'priority-urgent',
            default => 'priority-normal'
        };
    }

    /**
     * Retourne une représentation textuelle
     */
    public function __toString(): string
    {
        return "{$this->getTypeLabel()} - {$this->title}";
    }

    /**
     * Liste de tous les types disponibles
     */
    public static function getAvailableTypes(): array
    {
        return [
            self::TYPE_BOOKING_CONFIRMED,
            self::TYPE_BOOKING_CANCELLED,
            self::TYPE_BOOKING_REMINDER,
            self::TYPE_BOOKING_COMPLETED,
            self::TYPE_BOOKING_STARTED,
            self::TYPE_QUOTE_RECEIVED,
            self::TYPE_QUOTE_ACCEPTED,
            self::TYPE_QUOTE_REJECTED,
            self::TYPE_SERVICE_REQUEST_NEW,
            self::TYPE_REPLACEMENT_NEEDED,
            self::TYPE_REPLACEMENT_FOUND,
            self::TYPE_PAYMENT_RECEIVED,
            self::TYPE_PAYMENT_FAILED,
            self::TYPE_REVIEW_REQUEST,
            self::TYPE_REVIEW_RECEIVED,
            self::TYPE_DOCUMENT_EXPIRING,
            self::TYPE_AVAILABILITY_CONFLICT,
            self::TYPE_PRESTATAIRE_APPROVED,
            self::TYPE_PRESTATAIRE_REJECTED,
            self::TYPE_NEW_MESSAGE,
            self::TYPE_SYSTEM_ANNOUNCEMENT
        ];
    }

    /**
     * Liste de tous les canaux disponibles
     */
    public static function getAvailableChannels(): array
    {
        return [
            self::CHANNEL_EMAIL,
            self::CHANNEL_SMS,
            self::CHANNEL_PUSH,
            self::CHANNEL_IN_APP
        ];
    }

    /**
     * Liste de toutes les priorités disponibles
     */
    public static function getAvailablePriorities(): array
    {
        return [
            self::PRIORITY_LOW,
            self::PRIORITY_NORMAL,
            self::PRIORITY_HIGH,
            self::PRIORITY_URGENT
        ];
    }

    /**
     * Liste de tous les statuts disponibles
     */
    public static function getAvailableStatuses(): array
    {
        return [
            self::STATUS_PENDING,
            self::STATUS_SENT,
            self::STATUS_FAILED,
            self::STATUS_DELIVERED,
            self::STATUS_READ
        ];
    }
}