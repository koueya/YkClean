<?php
// src/Entity/Review/Review.php

namespace App\Review\Entity;

use App\Repository\ReviewRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\Booking\Booking;
use App\Entity\User\Client; 
use App\Entity\Planning\Prestataire;
#[ORM\Entity(repositoryClass: ReviewRepository::class)]
#[ORM\Table(name: 'reviews')]
#[ORM\HasLifecycleCallbacks]
class Review
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Client $client = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'reviews')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $rating = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $qualityRating = null; // Note qualité du service

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $punctualityRating = null; // Note ponctualité

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $professionalismRating = null; // Note professionnalisme

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $communicationRating = null; // Note communication

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 2000)]
    private ?string $comment = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $response = null; // Réponse du prestataire

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $respondedAt = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isPublished = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isRecommended = true; // Le client recommande-t-il ?

    #[ORM\Column(type: 'boolean')]
    private bool $isFlagged = false; // Signalé comme inapproprié

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $flagReason = null;

    #[ORM\Column(type: 'integer')]
    private int $helpfulCount = 0; // Nombre de "utile"

    #[ORM\Column(type: 'integer')]
    private int $notHelpfulCount = 0; // Nombre de "pas utile"

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $tags = []; // Tags du service (propre, rapide, soigneux, etc.)

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function preUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // Getters and Setters

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

    public function isRecommended(): bool
    {
        return $this->isRecommended;
    }

    public function setIsRecommended(bool $isRecommended): self
    {
        $this->isRecommended = $isRecommended;
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
        $metadata = $this->getMetadata();
        $metadata[$key] = $value;
        $this->metadata = $metadata;
        return $this;
    }

    public function getMetadataValue(string $key): mixed
    {
        $metadata = $this->getMetadata();
        return $metadata[$key] ?? null;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
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

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Calcule la note moyenne de tous les critères
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
            return (float)$this->rating;
        }

        return round(array_sum($ratings) / count($ratings), 2);
    }

    /**
     * Vérifie si l'avis a une réponse
     */
    public function hasResponse(): bool
    {
        return !empty($this->response);
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
            return 0;
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
        return $this;
    }

    /**
     * Retire le signalement
     */
    public function unflag(): self
    {
        $this->isFlagged = false;
        $this->flagReason = null;
        return $this;
    }

    /**
     * Retourne les étoiles sous forme de texte
     */
    public function getStarsDisplay(): string
    {
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }

    public function __toString(): string
    {
        return sprintf(
            'Avis %d/5 par %s',
            $this->rating,
            $this->client ? $this->client->getFullName() : 'Client'
        );
    }
}