<?php
// src/Entity/Review/Rating.php

namespace App\Review\Entity;

use App\Repository\RatingRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\User\User;
#[ORM\Entity(repositoryClass: RatingRepository::class)]
#[ORM\Table(name: 'ratings')]
#[ORM\HasLifecycleCallbacks]
#[ORM\Index(name: 'idx_entity_type', columns: ['entity_type', 'entity_id'])]
class Rating
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private string $entityType; // prestataire, service, product, etc.

    #[ORM\Column(type: 'integer')]
    private int $entityId;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $ratedBy = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank]
    #[Assert\Range(min: 1, max: 5)]
    private ?int $score = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $category = null; // quality, price, support, etc.

    #[ORM\Column(type: 'text', nullable: true)]
    #[Assert\Length(max: 1000)]
    private ?string $comment = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $context = null; // booking, quote, general, etc.

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $contextId = null; // ID de la réservation, devis, etc.

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadata = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isPublic = true;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

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

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getEntityId(): int
    {
        return $this->entityId;
    }

    public function setEntityId(int $entityId): self
    {
        $this->entityId = $entityId;
        return $this;
    }

    public function getRatedBy(): ?User
    {
        return $this->ratedBy;
    }

    public function setRatedBy(?User $ratedBy): self
    {
        $this->ratedBy = $ratedBy;
        return $this;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): self
    {
        $this->score = $score;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(?string $category): self
    {
        $this->category = $category;
        return $this;
    }

    public function getCategoryLabel(): string
    {
        return match($this->category) {
            'quality' => 'Qualité',
            'price' => 'Prix',
            'support' => 'Support',
            'speed' => 'Rapidité',
            'reliability' => 'Fiabilité',
            'communication' => 'Communication',
            'professionalism' => 'Professionnalisme',
            default => $this->category ?? 'Général',
        };
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

    public function getContext(): ?string
    {
        return $this->context;
    }

    public function setContext(?string $context): self
    {
        $this->context = $context;
        return $this;
    }

    public function getContextId(): ?int
    {
        return $this->contextId;
    }

    public function setContextId(?int $contextId): self
    {
        $this->contextId = $contextId;
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

    public function isPublic(): bool
    {
        return $this->isPublic;
    }

    public function setIsPublic(bool $isPublic): self
    {
        $this->isPublic = $isPublic;
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
     * Vérifie si c'est une note positive (>= 4)
     */
    public function isPositive(): bool
    {
        return $this->score >= 4;
    }

    /**
     * Vérifie si c'est une note négative (<= 2)
     */
    public function isNegative(): bool
    {
        return $this->score <= 2;
    }

    /**
     * Vérifie si c'est une note neutre (3)
     */
    public function isNeutral(): bool
    {
        return $this->score === 3;
    }

    /**
     * Retourne les étoiles sous forme de texte
     */
    public function getStarsDisplay(): string
    {
        return str_repeat('★', $this->score) . str_repeat('☆', 5 - $this->score);
    }

    /**
     * Marque comme vérifié
     */
    public function verify(): self
    {
        $this->isVerified = true;
        return $this;
    }

    /**
     * Retourne un résumé de la note
     */
    public function getSummary(): string
    {
        return sprintf(
            '%s - %d/5%s',
            $this->getCategoryLabel(),
            $this->score,
            $this->comment ? ' - ' . substr($this->comment, 0, 50) : ''
        );
    }

    public function __toString(): string
    {
        return sprintf(
            'Note %d/5 pour %s #%d',
            $this->score,
            $this->entityType,
            $this->entityId
        );
    }
}