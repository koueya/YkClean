<?php
// src/Entity/User/User.php

namespace App\Entity\User;

use App\Repository\User\UserRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité User - Classe de base pour tous les utilisateurs
 * 
 * Utilise l'héritage JOINED pour Client, Prestataire et Admin
 */
#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: 'users')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'user_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'user' => User::class,
    'client' => Client::class,
    'prestataire' => Prestataire::class,
    'admin' => Admin::class
])]
#[ORM\Index(columns: ['email'], name: 'idx_user_email')]
#[ORM\Index(columns: ['is_active'], name: 'idx_user_active')]
#[ORM\Index(columns: ['is_verified'], name: 'idx_user_verified')]
#[ORM\Index(columns: ['last_login_at'], name: 'idx_user_last_login')]
#[ORM\Index(columns: ['created_at'], name: 'idx_user_created')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    #[Groups(['user:read'])]
    private ?int $id = null;

    // ============================================
    // AUTHENTIFICATION
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]
    #[Assert\Length(max: 180)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $email = null;

    #[ORM\Column(type: Types::JSON)]
    #[Groups(['user:read'])]
    private array $roles = [];

    #[ORM\Column(type: Types::STRING)]
    private ?string $password = null;

    // ============================================
    // INFORMATIONS PERSONNELLES
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
        message: 'Le numéro de téléphone français n\'est pas valide'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $phone = null;

    // ============================================
    // ADRESSE
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $address = null;

    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $city = null;

    #[ORM\Column(type: Types::STRING, length: 10, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[0-9]{5}$/',
        message: 'Le code postal doit contenir 5 chiffres'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $postalCode = null;

    #[ORM\Column(type: Types::STRING, length: 100)]
    #[Groups(['user:read', 'user:write'])]
    private string $country = 'France';

    // ============================================
    // COORDONNÉES GPS (optionnel)
    // ============================================

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 7, nullable: true)]
    private ?string $longitude = null;

    // ============================================
    // PROFIL
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?\DateTimeImmutable $birthDate = null;

    #[ORM\Column(type: Types::STRING, length: 20, nullable: true)]
    #[Assert\Choice(
        choices: ['male', 'female', 'other', 'prefer_not_to_say'],
        message: 'Le genre doit être male, female, other ou prefer_not_to_say'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $gender = null;

    // ============================================
    // STATUT DU COMPTE
    // ============================================

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['user:read'])]
    private bool $isVerified = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['user:read'])]
    private bool $isActive = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $emailVerifiedAt = null;

    // ============================================
    // SÉCURITÉ ET CONNEXION
    // ============================================

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $lastLoginAt = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    #[ORM\Column(type: Types::INTEGER)]
    private int $loginCount = 0;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $passwordResetTokenExpiresAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $passwordChangedAt = null;

    // ============================================
    // PRÉFÉRENCES UTILISATEUR
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 5)]
    #[Groups(['user:read', 'user:write'])]
    private string $locale = 'fr';

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $timezone = 'Europe/Paris';

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['user:read', 'user:write'])]
    private bool $notificationsEnabled = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    #[Groups(['user:read', 'user:write'])]
    private bool $marketingEmailsEnabled = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $termsAccepted = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $termsAcceptedAt = null;

    // ============================================
    // DATES ET TRAÇABILITÉ
    // ============================================

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['user:read'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $deletedAt = null;

    // ============================================
    // CONSTRUCTEUR
    // ============================================

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->createdAt = new \DateTimeImmutable();
        $this->isActive = true;
        $this->isVerified = false;
        $this->loginCount = 0;
        $this->notificationsEnabled = true;
        $this->marketingEmailsEnabled = false;
        $this->termsAccepted = false;
        $this->locale = 'fr';
        $this->timezone = 'Europe/Paris';
        $this->country = 'France';
    }

    // ============================================
    // USERINTERFACE IMPLEMENTATION
    // ============================================

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = $this->roles;
        // Garantir que chaque utilisateur a au moins ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roles = $roles;
        return $this;
    }

    public function addRole(string $role): self
    {
        if (!in_array($role, $this->roles, true)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    public function removeRole(string $role): self
    {
        $key = array_search($role, $this->roles, true);
        if ($key !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }
        return $this;
    }

    public function eraseCredentials(): void
    {
        // Si vous stockez des données sensibles temporaires, effacez-les ici
    }

    // ============================================
    // PASSWORDAUTHENTICATEDUSERINTERFACE
    // ============================================

    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        $this->passwordChangedAt = new \DateTimeImmutable();
        return $this;
    }

    // ============================================
    // GETTERS & SETTERS
    // ============================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    #[Groups(['user:read'])]
    public function getFullName(): string
    {
        return trim(sprintf('%s %s', $this->firstName, $this->lastName));
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): self
    {
        $this->address = $address;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function getPostalCode(): ?string
    {
        return $this->postalCode;
    }

    public function setPostalCode(?string $postalCode): self
    {
        $this->postalCode = $postalCode;
        return $this;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): self
    {
        $this->country = $country;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getBirthDate(): ?\DateTimeImmutable
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeImmutable $birthDate): self
    {
        $this->birthDate = $birthDate;
        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): self
    {
        $this->gender = $gender;
        return $this;
    }

    // ============================================
    // STATUT DU COMPTE
    // ============================================

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        
        if ($isVerified && $this->emailVerifiedAt === null) {
            $this->emailVerifiedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?\DateTimeImmutable $emailVerifiedAt): self
    {
        $this->emailVerifiedAt = $emailVerifiedAt;
        return $this;
    }

    // ============================================
    // SÉCURITÉ ET CONNEXION
    // ============================================

    public function getLastLoginAt(): ?\DateTimeImmutable
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeImmutable $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(?string $lastLoginIp): self
    {
        $this->lastLoginIp = $lastLoginIp;
        return $this;
    }

    public function getLoginCount(): int
    {
        return $this->loginCount;
    }

    public function setLoginCount(int $loginCount): self
    {
        $this->loginCount = $loginCount;
        return $this;
    }

    public function incrementLoginCount(): self
    {
        $this->loginCount++;
        return $this;
    }

    public function getPasswordResetToken(): ?string
    {
        return $this->passwordResetToken;
    }

    public function setPasswordResetToken(?string $passwordResetToken): self
    {
        $this->passwordResetToken = $passwordResetToken;
        return $this;
    }

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeImmutable
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeImmutable $passwordResetTokenExpiresAt): self
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;
        return $this;
    }

    public function getPasswordChangedAt(): ?\DateTimeImmutable
    {
        return $this->passwordChangedAt;
    }

    public function setPasswordChangedAt(?\DateTimeImmutable $passwordChangedAt): self
    {
        $this->passwordChangedAt = $passwordChangedAt;
        return $this;
    }

    // ============================================
    // PRÉFÉRENCES
    // ============================================

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function getTimezone(): ?string
    {
        return $this->timezone;
    }

    public function setTimezone(?string $timezone): self
    {
        $this->timezone = $timezone;
        return $this;
    }

    public function isNotificationsEnabled(): bool
    {
        return $this->notificationsEnabled;
    }

    public function setNotificationsEnabled(bool $notificationsEnabled): self
    {
        $this->notificationsEnabled = $notificationsEnabled;
        return $this;
    }

    public function isMarketingEmailsEnabled(): bool
    {
        return $this->marketingEmailsEnabled;
    }

    public function setMarketingEmailsEnabled(bool $marketingEmailsEnabled): self
    {
        $this->marketingEmailsEnabled = $marketingEmailsEnabled;
        return $this;
    }

    public function isTermsAccepted(): bool
    {
        return $this->termsAccepted;
    }

    public function setTermsAccepted(bool $termsAccepted): self
    {
        $this->termsAccepted = $termsAccepted;
        
        if ($termsAccepted && $this->termsAcceptedAt === null) {
            $this->termsAcceptedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getTermsAcceptedAt(): ?\DateTimeImmutable
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?\DateTimeImmutable $termsAcceptedAt): self
    {
        $this->termsAcceptedAt = $termsAcceptedAt;
        return $this;
    }

    // ============================================
    // DATES
    // ============================================

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

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;
        return $this;
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Obtient l'adresse complète formatée
     */
    public function getFullAddress(): ?string
    {
        if (!$this->address) {
            return null;
        }

        $parts = [$this->address];
        
        if ($this->postalCode && $this->city) {
            $parts[] = sprintf('%s %s', $this->postalCode, $this->city);
        }
        
        if ($this->country && $this->country !== 'France') {
            $parts[] = $this->country;
        }

        return implode(', ', $parts);
    }

    /**
     * Vérifie si l'utilisateur a des coordonnées GPS
     */
    public function hasCoordinates(): bool
    {
        return $this->latitude !== null && $this->longitude !== null;
    }

    /**
     * Vérifie si le token de réinitialisation est valide
     */
    public function isPasswordResetTokenValid(): bool
    {
        if (!$this->passwordResetToken || !$this->passwordResetTokenExpiresAt) {
            return false;
        }

        return new \DateTimeImmutable() <= $this->passwordResetTokenExpiresAt;
    }

    /**
     * Génère un token de réinitialisation de mot de passe
     */
    public function generatePasswordResetToken(int $validityHours = 24): string
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetTokenExpiresAt = (new \DateTimeImmutable())
            ->modify("+{$validityHours} hours");
        
        return $this->passwordResetToken;
    }

    /**
     * Efface le token de réinitialisation
     */
    public function clearPasswordResetToken(): self
    {
        $this->passwordResetToken = null;
        $this->passwordResetTokenExpiresAt = null;
        return $this;
    }

    /**
     * Enregistre une connexion
     */
    public function recordLogin(?string $ipAddress = null): self
    {
        $this->lastLoginAt = new \DateTimeImmutable();
        $this->lastLoginIp = $ipAddress;
        $this->loginCount++;
        return $this;
    }

    /**
     * Vérifie si le compte est supprimé (soft delete)
     */
    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    /**
     * Soft delete du compte
     */
    public function softDelete(): self
    {
        $this->deletedAt = new \DateTimeImmutable();
        $this->isActive = false;
        return $this;
    }

    /**
     * Restaure un compte supprimé
     */
    public function restore(): self
    {
        $this->deletedAt = null;
        $this->isActive = true;
        return $this;
    }

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles(), true);
    }

    /**
     * Calcule l'âge de l'utilisateur
     */
    public function getAge(): ?int
    {
        if (!$this->birthDate) {
            return null;
        }

        return $this->birthDate->diff(new \DateTimeImmutable())->y;
    }

    // ============================================
    // LIFECYCLE CALLBACKS
    // ============================================

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    // ============================================
    // MÉTHODES SPÉCIALES
    // ============================================

    public function __toString(): string
    {
        return $this->getFullName();
    }

    /**
     * Retourne une représentation JSON-friendly de l'utilisateur
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
            'firstName' => $this->firstName,
            'lastName' => $this->lastName,
            'fullName' => $this->getFullName(),
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'postalCode' => $this->postalCode,
            'country' => $this->country,
            'avatar' => $this->avatar,
            'roles' => $this->getRoles(),
            'isVerified' => $this->isVerified,
            'isActive' => $this->isActive,
            'locale' => $this->locale,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}