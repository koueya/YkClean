<?php
// src/Entity/Document/Document.php

namespace App\Entity\Document;

use App\Entity\User\Admin;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\User\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use App\Repository\Document\DocumentRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Représente un document téléchargé par un utilisateur (Client ou Prestataire)
 * 
 * PRESTATAIRE :
 * - KBIS (obligatoire)
 * - Assurance professionnelle (obligatoire)
 * - Pièce d'identité (obligatoire)
 * - Diplômes, certificats (optionnel)
 * - Casier judiciaire (optionnel)
 * - Attestation fiscale (optionnel)
 * 
 * CLIENT :
 * - RIB pour prélèvement automatique
 * - Pièce d'identité (pour certains services)
 * - Justificatif de domicile (pour certains services)
 * - Autres documents demandés
 * 
 * Gère le cycle de validation : pending → verified/rejected
 */
#[ORM\Entity(repositoryClass: DocumentRepository::class)]
#[ORM\Table(name: 'documents')]
#[ORM\Index(columns: ['user_id', 'type'], name: 'idx_user_type')]
#[ORM\Index(columns: ['user_type'], name: 'idx_user_type_discriminator')]
#[ORM\Index(columns: ['status'], name: 'idx_status')]
#[ORM\Index(columns: ['type'], name: 'idx_type')]
#[ORM\Index(columns: ['expires_at'], name: 'idx_expires')]
#[ORM\Index(columns: ['uploaded_at'], name: 'idx_uploaded')]
#[ORM\Index(columns: ['requires_verification'], name: 'idx_requires_verification')]
#[ORM\HasLifecycleCallbacks]
class Document
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['document:read', 'document:list', 'user:read'])]
    private ?int $id = null;

    // ============================================
    // RELATIONS - PROPRIÉTAIRE POLYMORPHE
    // ============================================

    /**
     * Utilisateur propriétaire du document (Client ou Prestataire)
     * Relation polymorphe via la table users
     */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le propriétaire du document est obligatoire')]
    #[Groups(['document:read', 'document:detail'])]
    private ?User $user = null;

    /**
     * Type d'utilisateur (pour faciliter les requêtes)
     * Stocke 'client' ou 'prestataire'
     */
    #[ORM\Column(type: Types::STRING, length: 20)]
    #[Groups(['document:read', 'document:list'])]
    private string $userType;

    /**
     * Administrateur ayant vérifié le document
     */
    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['document:read', 'document:detail'])]
    private ?Admin $verifiedBy = null;

    // ============================================
    // INFORMATIONS DU DOCUMENT
    // ============================================

    /**
     * Type de document
     * @see DocumentType enum
     */
    #[ORM\Column(type: Types::STRING, length: 50, enumType: DocumentType::class)]
    #[Assert\NotBlank(message: 'Le type de document est obligatoire')]
    #[Groups(['document:read', 'document:list', 'document:create'])]
    private ?DocumentType $type = null;

    /**
     * Nom original du fichier
     */
    #[ORM\Column(type: Types::STRING, length: 255)]
    #[Assert\NotBlank(message: 'Le nom du fichier est obligatoire')]
    #[Groups(['document:read', 'document:detail'])]
    private ?string $fileName = null;

    /**
     * Chemin de stockage du fichier
     */
    #[ORM\Column(type: Types::STRING, length: 500)]
    #[Assert\NotBlank(message: 'Le chemin du fichier est obligatoire')]
    private ?string $filePath = null;

    /**
     * Type MIME du fichier
     */
    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['document:read', 'document:detail'])]
    private ?string $mimeType = null;

    /**
     * Taille du fichier en octets
     */
    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\Positive(message: 'La taille du fichier doit être positive')]
    #[Groups(['document:read', 'document:detail'])]
    private ?int $fileSize = null;

    /**
     * Description ou notes sur le document
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'La description ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['document:read', 'document:detail', 'document:create', 'document:update'])]
    private ?string $description = null;

    // ============================================
    // STATUT ET VALIDATION
    // ============================================

    /**
     * Statut du document
     * @see DocumentStatus enum
     */
    #[ORM\Column(type: Types::STRING, length: 20, enumType: DocumentStatus::class)]
    #[Groups(['document:read', 'document:list'])]
    private DocumentStatus $status = DocumentStatus::PENDING;

    /**
     * Indique si le document nécessite une vérification manuelle
     * false pour RIB client (vérification automatique Stripe)
     * true pour documents prestataire (vérification admin)
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['document:read'])]
    private bool $requiresVerification = true;

    /**
     * Raison du rejet (si status = rejected)
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500, maxMessage: 'La raison du rejet ne peut pas dépasser {{ limit }} caractères')]
    #[Groups(['document:read', 'document:detail'])]
    private ?string $rejectionReason = null;

    /**
     * Notes de vérification par l'admin
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 1000, maxMessage: 'Les notes ne peuvent pas dépasser {{ limit }} caractères')]
    #[Groups(['document:read', 'document:detail'])]
    private ?string $verificationNotes = null;

    // ============================================
    // DATES
    // ============================================

    /**
     * Date de téléchargement du document
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['document:read', 'document:list'])]
    private \DateTimeImmutable $uploadedAt;

    /**
     * Date de vérification du document
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['document:read', 'document:detail'])]
    private ?\DateTimeImmutable $verifiedAt = null;

    /**
     * Date d'expiration du document (pour assurance, KBIS, RIB, etc.)
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['document:read', 'document:list', 'document:create', 'document:update'])]
    private ?\DateTimeImmutable $expiresAt = null;

    /**
     * Date de dernière mise à jour
     */
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['document:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    // ============================================
    // MÉTADONNÉES ADDITIONNELLES
    // ============================================

    /**
     * Numéro de document
     * - SIRET pour KBIS
     * - Numéro de police pour assurance
     * - IBAN pour RIB
     * - Numéro de pièce d'identité
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Groups(['document:read', 'document:detail', 'document:create', 'document:update'])]
    private ?string $documentNumber = null;

    /**
     * Organisme émetteur (pour assurance, diplômes, etc.)
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['document:read', 'document:detail', 'document:create', 'document:update'])]
    private ?string $issuingAuthority = null;

    /**
     * Date d'émission du document
     */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['document:read', 'document:detail', 'document:create', 'document:update'])]
    private ?\DateTimeImmutable $issuedAt = null;

    /**
     * Indique si le document a été scanné et vérifié automatiquement
     * Ex: RIB vérifié par Stripe, identité vérifiée par service OCR
     */
    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['document:read', 'document:detail'])]
    private bool $isAutoVerified = false;

    /**
     * Score de confiance de la vérification automatique (0-100)
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 0, max: 100)]
    #[Groups(['document:read', 'document:detail'])]
    private ?int $autoVerificationScore = null;

    /**
     * ID externe (ex: Stripe bank account ID pour RIB)
     */
    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['document:read', 'document:detail'])]
    private ?string $externalId = null;

    /**
     * Hash du fichier pour détecter les doublons
     */
    #[ORM\Column(type: Types::STRING, length: 64, nullable: true)]
    private ?string $fileHash = null;

    /**
     * Métadonnées extraites du document (JSON)
     * Pour RIB : {bank_name, bic, holder_name, account_status}
     * Pour identité : {birth_date, nationality, address}
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['document:read', 'document:detail'])]
    private ?array $metadata = null;

    // ============================================
    // CONSTRUCTEUR
    // ============================================

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
        $this->status = DocumentStatus::PENDING;
        $this->isAutoVerified = false;
        $this->requiresVerification = true;
        $this->metadata = [];
    }

    // ============================================
    // LIFECYCLE CALLBACKS
    // ============================================

    #[ORM\PrePersist]
    public function setUserTypeValue(): void
    {
        if ($this->user) {
            if ($this->user instanceof Client) {
                $this->userType = 'client';
            } elseif ($this->user instanceof Prestataire) {
                $this->userType = 'prestataire';
            } else {
                $this->userType = 'user';
            }
        }
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Marque le document comme vérifié
     */
    public function verify(?Admin $admin = null, ?string $notes = null): self
    {
        $this->status = DocumentStatus::APPROVED;
        $this->verifiedBy = $admin;
        $this->verifiedAt = new \DateTimeImmutable();
        $this->verificationNotes = $notes;
        $this->rejectionReason = null;

        return $this;
    }

    /**
     * Rejette le document
     */
    public function reject(?Admin $admin, string $reason, ?string $notes = null): self
    {
        $this->status = DocumentStatus::REJECTED;
        $this->verifiedBy = $admin;
        $this->verifiedAt = new \DateTimeImmutable();
        $this->rejectionReason = $reason;
        $this->verificationNotes = $notes;

        return $this;
    }

    /**
     * Marque le document comme vérifié automatiquement
     */
    public function autoVerify(int $score, ?string $externalId = null): self
    {
        $this->isAutoVerified = true;
        $this->autoVerificationScore = $score;
        $this->externalId = $externalId;
        
        // Si le score est suffisant, approuver automatiquement
        if ($score >= 90) {
            $this->status = DocumentStatus::APPROVED;
            $this->verifiedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    /**
     * Vérifie si le document est expiré
     */
    public function isExpired(): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        $now = new \DateTimeImmutable();
        return $this->expiresAt < $now;
    }

    /**
     * Vérifie si le document expire bientôt (dans les X jours)
     */
    public function isExpiringSoon(int $days = 30): bool
    {
        if (!$this->expiresAt) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $threshold = $now->modify("+{$days} days");

        return $this->expiresAt <= $threshold && $this->expiresAt >= $now;
    }

    /**
     * Obtient le nombre de jours avant expiration
     */
    public function getDaysUntilExpiration(): ?int
    {
        if (!$this->expiresAt) {
            return null;
        }

        $now = new \DateTimeImmutable();
        $diff = $now->diff($this->expiresAt);

        return $diff->invert ? -$diff->days : $diff->days;
    }

    /**
     * Vérifie si le document est en attente de vérification
     */
    public function isPending(): bool
    {
        return $this->status === DocumentStatus::PENDING;
    }

    /**
     * Vérifie si le document est approuvé
     */
    public function isApproved(): bool
    {
        return $this->status === DocumentStatus::APPROVED;
    }

    /**
     * Vérifie si le document est rejeté
     */
    public function isRejected(): bool
    {
        return $this->status === DocumentStatus::REJECTED;
    }

    /**
     * Vérifie si le propriétaire est un client
     */
    public function isClientDocument(): bool
    {
        return $this->userType === 'client' || $this->user instanceof Client;
    }

    /**
     * Vérifie si le propriétaire est un prestataire
     */
    public function isPrestataireDocument(): bool
    {
        return $this->userType === 'prestataire' || $this->user instanceof Prestataire;
    }

    /**
     * Obtient la taille du fichier en format lisible
     */
    public function getReadableFileSize(): string
    {
        if (!$this->fileSize) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB'];
        $size = $this->fileSize;
        $unitIndex = 0;

        while ($size >= 1024 && $unitIndex < count($units) - 1) {
            $size /= 1024;
            $unitIndex++;
        }

        return round($size, 2) . ' ' . $units[$unitIndex];
    }

    /**
     * Obtient l'URL de téléchargement du document
     */
    public function getDownloadUrl(): string
    {
        return '/api/documents/' . $this->id . '/download';
    }

    /**
     * Vérifie si le document est une image
     */
    public function isImage(): bool
    {
        return $this->mimeType && str_starts_with($this->mimeType, 'image/');
    }

    /**
     * Vérifie si le document est un PDF
     */
    public function isPdf(): bool
    {
        return $this->mimeType === 'application/pdf';
    }

    /**
     * Calcule et définit le hash du fichier
     */
    public function calculateFileHash(string $fileContent): self
    {
        $this->fileHash = hash('sha256', $fileContent);
        return $this;
    }

    /**
     * Obtient le libellé du type de document
     */
    public function getTypeLabel(): string
    {
        return $this->type?->label() ?? 'Inconnu';
    }

    /**
     * Obtient le libellé du statut
     */
    public function getStatusLabel(): string
    {
        return $this->status->label();
    }

    /**
     * Obtient la couleur associée au statut
     */
    public function getStatusColor(): string
    {
        return $this->status->color();
    }

    /**
     * Vérifie si c'est un document obligatoire pour un prestataire
     */
    public function isRequiredForPrestataire(): bool
    {
        return $this->isPrestataireDocument() && in_array($this->type, [
            DocumentType::IDENTITY_CARD,
            DocumentType::KBIS,
            DocumentType::INSURANCE,
        ]);
    }

    /**
     * Obtient le client propriétaire (si applicable)
     */
    public function getClient(): ?Client
    {
        return $this->user instanceof Client ? $this->user : null;
    }

    /**
     * Obtient le prestataire propriétaire (si applicable)
     */
    public function getPrestataire(): ?Prestataire
    {
        return $this->user instanceof Prestataire ? $this->user : null;
    }

    // ============================================
    // GETTERS / SETTERS
    // ============================================

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
        
        // Mise à jour automatique du userType
        if ($user instanceof Client) {
            $this->userType = 'client';
        } elseif ($user instanceof Prestataire) {
            $this->userType = 'prestataire';
        }

        return $this;
    }

    public function getUserType(): string
    {
        return $this->userType;
    }

    public function setUserType(string $userType): self
    {
        $this->userType = $userType;
        return $this;
    }

    public function getVerifiedBy(): ?Admin
    {
        return $this->verifiedBy;
    }

    public function setVerifiedBy(?Admin $verifiedBy): self
    {
        $this->verifiedBy = $verifiedBy;
        return $this;
    }

    public function getType(): ?DocumentType
    {
        return $this->type;
    }

    public function setType(DocumentType $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getFileName(): ?string
    {
        return $this->fileName;
    }

    public function setFileName(string $fileName): self
    {
        $this->fileName = $fileName;
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): self
    {
        $this->mimeType = $mimeType;
        return $this;
    }

    public function getFileSize(): ?int
    {
        return $this->fileSize;
    }

    public function setFileSize(int $fileSize): self
    {
        $this->fileSize = $fileSize;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getStatus(): DocumentStatus
    {
        return $this->status;
    }

    public function setStatus(DocumentStatus $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function requiresVerification(): bool
    {
        return $this->requiresVerification;
    }

    public function setRequiresVerification(bool $requiresVerification): self
    {
        $this->requiresVerification = $requiresVerification;
        return $this;
    }

    public function getRejectionReason(): ?string
    {
        return $this->rejectionReason;
    }

    public function setRejectionReason(?string $rejectionReason): self
    {
        $this->rejectionReason = $rejectionReason;
        return $this;
    }

    public function getVerificationNotes(): ?string
    {
        return $this->verificationNotes;
    }

    public function setVerificationNotes(?string $verificationNotes): self
    {
        $this->verificationNotes = $verificationNotes;
        return $this;
    }

    public function getUploadedAt(): \DateTimeImmutable
    {
        return $this->uploadedAt;
    }

    public function setUploadedAt(\DateTimeImmutable $uploadedAt): self
    {
        $this->uploadedAt = $uploadedAt;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getDocumentNumber(): ?string
    {
        return $this->documentNumber;
    }

    public function setDocumentNumber(?string $documentNumber): self
    {
        $this->documentNumber = $documentNumber;
        return $this;
    }

    public function getIssuingAuthority(): ?string
    {
        return $this->issuingAuthority;
    }

    public function setIssuingAuthority(?string $issuingAuthority): self
    {
        $this->issuingAuthority = $issuingAuthority;
        return $this;
    }

    public function getIssuedAt(): ?\DateTimeImmutable
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeImmutable $issuedAt): self
    {
        $this->issuedAt = $issuedAt;
        return $this;
    }

    public function isAutoVerified(): bool
    {
        return $this->isAutoVerified;
    }

    public function setIsAutoVerified(bool $isAutoVerified): self
    {
        $this->isAutoVerified = $isAutoVerified;
        return $this;
    }

    public function getAutoVerificationScore(): ?int
    {
        return $this->autoVerificationScore;
    }

    public function setAutoVerificationScore(?int $autoVerificationScore): self
    {
        $this->autoVerificationScore = $autoVerificationScore;
        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;
        return $this;
    }

    public function getFileHash(): ?string
    {
        return $this->fileHash;
    }

    public function setFileHash(?string $fileHash): self
    {
        $this->fileHash = $fileHash;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;
        return $this;
    }

    /**
     * Ajoute une métadonnée
     */
    public function addMetadata(string $key, mixed $value): self
    {
        if ($this->metadata === null) {
            $this->metadata = [];
        }

        $this->metadata[$key] = $value;
        return $this;
    }

    /**
     * Obtient une métadonnée spécifique
     */
    public function getMetadataValue(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }
}