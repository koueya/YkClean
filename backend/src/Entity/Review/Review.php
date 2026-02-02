<?php

namespace App\Entity\Review;

use App\Entity\Booking\Booking;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Repository\Review\ReviewRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un avis laissé par un client sur un prestataire
 * Créé après la complétion d'une réservation
 * Permet d'évaluer la qualité du service sur plusieurs critères
 */
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
#[ORM\Index(columns: ['prestataire_id', 'is_published'], name: 'idx_prestataire_published')]
#[ORM\Index(columns: ['client_id'], name: 'idx_client')]
#[ORM\Index(columns: ['booking_id'], name: 'idx_booking')]
#[ORM\Index(columns: ['rating'], name: 'idx_rating')]
#[ORM\Index(columns: ['is_verified'], name: 'idx_verified')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created')]
#[ORM\UniqueConstraint(name: 'unique_booking_review', columns: ['booking_id'])]
#[ORM\HasLifecycleCallbacks]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['review:read', 'review:list', 'prestataire:read', 'booking:read'])]
    private ?int $id = null;

    /**
     * Réservation évaluée (relation 1:1)
     */
    #[ORM\OneToOne(inversedBy: 'review', targetEntity: Booking::class)]
    #[ORM\JoinColumn(nullable: false, unique: true, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'La réservation est obligatoire', groups: ['review:create'])]
    #[Groups(['review:read', 'review:detail'])]
    private ?Booking $booking = null;

    /**
     * Client qui laisse l'avis
     */
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le client est obligatoire', groups: ['review:create'])]
    #[Groups(['review:read', 'review:list'])]
    private ?Client $client = null;

    /**
     * Prestataire évalué
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire', groups: ['review:create'])]
    #[Groups(['review:read', 'review:list'])]
    private ?Prestataire $prestataire = null;

    /**
     * Note globale (1-5 étoiles)
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\NotBlank(message: 'La note est obligatoire', groups: ['review:create'])]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['review:read', 'review:list', 'review:create'])]
    private ?int $rating = null;

    /**
     * Note pour la qualité du service (1-5)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note qualité doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['review:read', 'review:detail', 'review:create'])]
    private ?int $qualityRating = null;

    /**
     * Note pour la ponctualité (1-5)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note ponctualité doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['review:read', 'review:detail', 'review:create'])]
    private ?int $punctualityRating = null;

    /**
     * Note pour le professionnalisme (1-5)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note professionnalisme doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['review:read', 'review:detail', 'review:create'])]
    private ?int $professionalismRating = null;

    /**
     * Note pour la communication (1-5)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 5,
        notInRangeMessage: 'La note communication doit être entre {{ min }} et {{ max }}'
    )]
    #[Groups(['review:read', 'review:detail', 'review:create'])]
    private ?int $communicationRating = null;

    /**
     * Commentaire du client
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        min: 10,
        max: 2000,
        minMessage: 'Le commentaire doit faire au moins {{ limit }} caractères',
        maxMessage: 'Le commentaire ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['review:read', 'review:list', 'review:create', 'review:update'])]
    private ?string $comment = null;

    /**
     * Réponse du prestataire à l'avis
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(
        max: 1000,
        maxMessage: 'La réponse ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['review:read', 'review:detail'])]
    private ?string $response = null;

    /**
     * Date de réponse du prestataire
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['review:read', 'review:detail'])]
    private ?\DateTimeImmutable $respondedAt = null;

    /**
     * Le client recommande-t-il ce prestataire ?
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['review:read', 'review:list', 'review:create'])]
    private bool $wouldRecommend = true;

    /**
     * Photos jointes à l'avis (URLs)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Assert\Count(
        max: 5,
        maxMessage: 'Vous ne pouvez pas joindre plus de {{ limit }} photos'
    )]
    #[Groups(['review:read', 'review:detail', 'review:create'])]
    private ?array $photos = [];

    /**
     * Avis vérifié (service effectivement réalisé)
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['review:read', 'review:list'])]
    private bool $isVerified = false;

    /**
     * Avis publié (visible publiquement)
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['review:read', 'review:list', 'review:update'])]
    private bool $isPublished = true;

    /**
     * Avis signalé comme inapproprié
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['review:admin'])]
    private bool $isFlagged = false;

    /**
     * Raison du signalement
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(
        max: 255,
        maxMessage: 'La raison ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['review:admin'])]
    private ?string $flagReason = null;

    /**
     * Date du signalement
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['review:admin'])]
    private ?\DateTimeImmutable $flaggedAt = null;

    /**
     * Nombre de personnes ayant trouvé cet avis utile
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['review:read', 'review:list'])]
    private int $helpfulCount = 0;

    /**
     * Nombre de personnes n'ayant pas trouvé cet avis utile
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['review:read', 'review:list'])]
    private int $notHelpfulCount = 0;

    /**
     * Tags associés à l'avis (propre, rapide, soigneux, etc.)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['review:read', 'review:detail', 'review:create'])]
    private ?array $tags = [];

    /**
     * Métadonnées additionnelles (context, informations supplémentaires)
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['review:admin'])]
    private ?array $metadata = [];

    /**
     * Date de création de l'avis
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['review:read', 'review:list'])]
    private \DateTimeImmutable $createdAt;

    /**
     * Date de dernière modification
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['review:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->photos = [];
        $this->tags = [];
        $this->metadata = [];
        $this->isVerified = false;
        $this->isPublished = true;
        $this->isFlagged = false;
        $this->wouldRecommend = true;
        $this->helpfulCount = 0;
        $this->notHelpfulCount = 0;
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

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(?Prestataire $prestataire): self
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    public function getQualityRating(): ?int
    {
        return $this->qualityRating;
    }

    public function setQualityRating(?int $qualityRating): self
    {
        $this->qualityRating = $qualityRating;
        return $this;
    }

    public function getPunctualityRating(): ?int
    {
        return $this->punctualityRating;
    }

    public function setPunctualityRating(?int $punctualityRating): self
    {
        $this->punctualityRating = $punctualityRating;
        return $this;
    }

    public function getProfessionalismRating(): ?int
    {
        return $this->professionalismRating;
    }

    public function setProfessionalismRating(?int $professionalismRating): self
    {
        $this->professionalismRating = $professionalismRating;
        return $this;
    }

    public function getCommunicationRating(): ?int
    {
        return $this->communicationRating;
    }

    public function setCommunicationRating(?int $communicationRating): self
    {
        $this->communicationRating = $communicationRating;
        return $this;
    }

    public function getComment(): ?string
    {
        return $this->comment;
    }

    public function setComment(?string $comment): self
    {
        $this->comment = $comment;
        return $this;
    }

    public function getResponse(): ?string
    {
        return $this->response;
    }

    public function setResponse(?string $response): self
    {
        $this->response = $response;
        
        // Mettre à jour automatiquement la date de réponse
        if ($response !== null && $this->respondedAt === null) {
            $this->respondedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getRespondedAt(): ?\DateTimeImmutable
    {
        return $this->respondedAt;
    }

    public function setRespondedAt(?\DateTimeImmutable $respondedAt): self
    {
        $this->respondedAt = $respondedAt;
        return $this;
    }

    public function wouldRecommend(): bool
    {
        return $this->wouldRecommend;
    }

    public function setWouldRecommend(bool $wouldRecommend): self
    {
        $this->wouldRecommend = $wouldRecommend;
        return $this;
    }

    public function getPhotos(): ?array
    {
        return $this->photos ?? [];
    }

    public function setPhotos(?array $photos): self
    {
        $this->photos = $photos;
        return $this;
    }

    public function addPhoto(string $photoUrl): self
    {
        if ($this->photos === null) {
            $this->photos = [];
        }
        
        if (!in_array($photoUrl, $this->photos) && count($this->photos) < 5) {
            $this->photos[] = $photoUrl;
        }
        
        return $this;
    }

    public function removePhoto(string $photoUrl): self
    {
        if ($this->photos === null) {
            return $this;
        }
        
        $key = array_search($photoUrl, $this->photos);
        if ($key !== false) {
            unset($this->photos[$key]);
            $this->photos = array_values($this->photos);
        }
        
        return $this;
    }

    public function hasPhotos(): bool
    {
        return !empty($this->photos);
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        return $this;
    }

    public function isFlagged(): bool
    {
        return $this->isFlagged;
    }

    public function setIsFlagged(bool $isFlagged): self
    {
        $this->isFlagged = $isFlagged;
        return $this;
    }

    public function getFlagReason(): ?string
    {
        return $this->flagReason;
    }

    public function setFlagReason(?string $flagReason): self
    {
        $this->flagReason = $flagReason;
        return $this;
    }

    public function getFlaggedAt(): ?\DateTimeImmutable
    {
        return $this->flaggedAt;
    }

    public function setFlaggedAt(?\DateTimeImmutable $flaggedAt): self
    {
        $this->flaggedAt = $flaggedAt;
        return $this;
    }

    public function getHelpfulCount(): int
    {
        return $this->helpfulCount;
    }

    public function setHelpfulCount(int $helpfulCount): self
    {
        $this->helpfulCount = $helpfulCount;
        return $this;
    }

    public function incrementHelpfulCount(): self
    {
        $this->helpfulCount++;
        return $this;
    }

    public function getNotHelpfulCount(): int
    {
        return $this->notHelpfulCount;
    }

    public function setNotHelpfulCount(int $notHelpfulCount): self
    {
        $this->notHelpfulCount = $notHelpfulCount;
        return $this;
    }

    public function incrementNotHelpfulCount(): self
    {
        $this->notHelpfulCount++;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags ?? [];
    }

    public function setTags(?array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function addTag(string $tag): self
    {
        $tags = $this->getTags();
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $this->tags = $tags;
        }
        return $this;
    }

    public function removeTag(string $tag): self
    {
        $tags = $this->getTags();
        $key = array_search($tag, $tags);
        if ($key !== false) {
            unset($tags[$key]);
            $this->tags = array_values($tags);
        }
        return $this;
    }

    public function hasTag(string $tag): bool
    {
        return in_array($tag, $this->getTags());
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
     * Calcule la note moyenne de tous les critères détaillés
     */
    public function getAverageDetailedRating(): float
    {
        $ratings = array_filter([
            $this->qualityRating,
            $this->punctualityRating,
            $this->professionalismRating,
            $this->communicationRating
        ]);

        if (empty($ratings)) {
            return (float) $this->rating;
        }

        return round(array_sum($ratings) / count($ratings), 2);
    }

    /**
     * Vérifie si l'avis a des notes détaillées
     */
    public function hasDetailedRatings(): bool
    {
        return $this->qualityRating !== null
            || $this->punctualityRating !== null
            || $this->professionalismRating !== null
            || $this->communicationRating !== null;
    }

    /**
     * Vérifie si l'avis a une réponse du prestataire
     */
    public function hasResponse(): bool
    {
        return $this->response !== null && !empty($this->response);
    }

    /**
     * Retourne le nombre total de votes (utile + pas utile)
     */
    public function getTotalVotes(): int
    {
        return $this->helpfulCount + $this->notHelpfulCount;
    }

    /**
     * Calcule le pourcentage d'utilité
     */
    public function getHelpfulPercentage(): float
    {
        $total = $this->getTotalVotes();
        if ($total === 0) {
            return 0.0;
        }

        return round(($this->helpfulCount / $total) * 100, 2);
    }

    /**
     * Vérifie si c'est un avis positif (>= 4 étoiles)
     */
    public function isPositive(): bool
    {
        return $this->rating >= 4;
    }

    /**
     * Vérifie si c'est un avis négatif (<= 2 étoiles)
     */
    public function isNegative(): bool
    {
        return $this->rating <= 2;
    }

    /**
     * Vérifie si c'est un avis neutre (3 étoiles)
     */
    public function isNeutral(): bool
    {
        return $this->rating === 3;
    }

    /**
     * Marque l'avis comme vérifié
     */
    public function verify(): self
    {
        $this->isVerified = true;
        return $this;
    }

    /**
     * Publie l'avis
     */
    public function publish(): self
    {
        $this->isPublished = true;
        return $this;
    }

    /**
     * Dépublie l'avis
     */
    public function unpublish(): self
    {
        $this->isPublished = false;
        return $this;
    }

    /**
     * Ajoute une réponse du prestataire
     */
    public function respond(string $response): self
    {
        $this->response = $response;
        $this->respondedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Signale l'avis comme inapproprié
     */
    public function flag(string $reason): self
    {
        $this->isFlagged = true;
        $this->flagReason = $reason;
        $this->flaggedAt = new \DateTimeImmutable();
        return $this;
    }

    /**
     * Retire le signalement
     */
    public function unflag(): self
    {
        $this->isFlagged = false;
        $this->flagReason = null;
        $this->flaggedAt = null;
        return $this;
    }

    /**
     * Retourne les étoiles sous forme de texte
     */
    public function getStarsDisplay(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    /**
     * Retourne les étoiles sous forme d'emoji
     */
    public function getStarsEmoji(): string
    {
        return str_repeat('⭐', $this->rating);
    }

    /**
     * Obtient la classe CSS pour la note
     */
    public function getRatingClass(): string
    {
        return match (true) {
            $this->rating >= 4 => 'rating-excellent',
            $this->rating === 3 => 'rating-good',
            $this->rating === 2 => 'rating-fair',
            default => 'rating-poor'
        };
    }

    /**
     * Obtient le label de la note
     */
    public function getRatingLabel(): string
    {
        return match ($this->rating) {
            5 => 'Excellent',
            4 => 'Très bien',
            3 => 'Bien',
            2 => 'Moyen',
            1 => 'Insuffisant',
            default => 'Non noté'
        };
    }

    /**
     * Vérifie si l'avis peut être modifié
     * Un avis peut être modifié dans les 7 jours suivant sa création
     */
    public function canBeEdited(): bool
    {
        if ($this->isFlagged) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $editDeadline = $this->createdAt->modify('+7 days');
        
        return $now <= $editDeadline;
    }

    /**
     * Vérifie si le prestataire peut répondre
     */
    public function canBeResponded(): bool
    {
        return $this->isPublished && !$this->hasResponse();
    }

    /**
     * Calcule l'âge de l'avis
     */
    public function getAge(): \DateInterval
    {
        $now = new \DateTimeImmutable();
        return $this->createdAt->diff($now);
    }

    /**
     * Retourne l'âge de l'avis formaté (ex: "il y a 2 jours")
     */
    public function getAgeFormatted(): string
    {
        $age = $this->getAge();
        
        if ($age->y > 0) {
            return $age->y === 1 ? 'il y a 1 an' : "il y a {$age->y} ans";
        }
        
        if ($age->m > 0) {
            return $age->m === 1 ? 'il y a 1 mois' : "il y a {$age->m} mois";
        }
        
        if ($age->d > 0) {
            return $age->d === 1 ? 'il y a 1 jour' : "il y a {$age->d} jours";
        }
        
        if ($age->h > 0) {
            return $age->h === 1 ? 'il y a 1 heure' : "il y a {$age->h} heures";
        }
        
        return 'à l\'instant';
    }

    /**
     * Retourne une représentation textuelle
     */
    public function __toString(): string
    {
        $clientName = $this->client ? $this->client->getFullName() : 'Client';
        return "Avis {$this->rating}/5 par {$clientName}";
    }

    /**
     * Retourne les critères disponibles pour l'évaluation
     */
    public static function getAvailableCriteria(): array
    {
        return [
            'quality' => 'Qualité du service',
            'punctuality' => 'Ponctualité',
            'professionalism' => 'Professionnalisme',
            'communication' => 'Communication'
        ];
    }

    /**
     * Retourne les tags suggérés
     */
    public static function getSuggestedTags(): array
    {
        return [
            'Propre',
            'Rapide',
            'Soigneux',
            'Ponctuel',
            'Professionnel',
            'À l\'écoute',
            'Efficace',
            'Sympathique',
            'Minutieux',
            'Fiable'
        ];
    }
}