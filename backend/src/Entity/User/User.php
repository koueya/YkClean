<?php

namespace App\Entity\User;

use App\Enum\UserRole;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;

/**
 * Entité User - Utilisateur de base
 * 
 * Classe parente pour Client, Prestataire et Admin
 */
#[ORM\Entity(repositoryClass: 'App\Repository\User\UserRepository')]
#[ORM\Table(name: 'users')]
#[ORM\InheritanceType('JOINED')]
#[ORM\DiscriminatorColumn(name: 'user_type', type: 'string')]
#[ORM\DiscriminatorMap([
    'user' => User::class,
    'client' => Client::class,
    'prestataire' => Prestataire::class,
    'admin' => Admin::class
])]
#[ORM\Index(columns: ['email'], name: 'idx_email')]
#[ORM\Index(columns: ['is_active'], name: 'idx_active')]
#[ORM\Index(columns: ['is_verified'], name: 'idx_verified')]
#[ORM\Index(columns: ['last_login_at'], name: 'idx_last_login')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà utilisé')]
#[ORM\HasLifecycleCallbacks]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['user:read'])]
    private ?int $id = null;

    /**
     * Email (identifiant unique)
     */
    #[ORM\Column(type: 'string', length: 180, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email {{ value }} n\'est pas valide')]
    #[Groups(['user:read', 'user:write'])]
    private ?string $email = null;

    /**
     * Rôles de l'utilisateur
     */
    #[ORM\Column(type: 'json')]
    #[Groups(['user:read'])]
    private array $roles = [];

    /**
     * Mot de passe hashé
     */
    #[ORM\Column(type: 'string')]
    private ?string $password = null;

    /**
     * Prénom
     */
    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $firstName = null;

    /**
     * Nom de famille
     */
    #[ORM\Column(type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $lastName = null;

    /**
     * Téléphone
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^(?:(?:\+|00)33|0)\s*[1-9](?:[\s.-]*\d{2}){4}$/',
        message: 'Le numéro de téléphone n\'est pas valide'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $phone = null;

    /**
     * Adresse
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $address = null;

    /**
     * Ville
     */
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?string $city = null;

    /**
     * Code postal
     */
    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[0-9]{5}$/',
        message: 'Le code postal doit contenir 5 chiffres'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $postalCode = null;

    /**
     * Photo de profil
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['user:read'])]
    private ?string $avatar = null;

    /**
     * Date de naissance
     */
    #[ORM\Column(type: 'date', nullable: true)]
    #[Groups(['user:read', 'user:write'])]
    private ?\DateTimeInterface $birthDate = null;

    /**
     * Genre
     */
    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    #[Assert\Choice(
        choices: ['male', 'female', 'other', 'prefer_not_to_say'],
        message: 'Le genre doit être male, female, other ou prefer_not_to_say'
    )]
    #[Groups(['user:read', 'user:write'])]
    private ?string $gender = null;

    /**
     * ==========================================
     * PROPRIÉTÉS POUR JWT ET SÉCURITÉ
     * ==========================================
     */

    /**
     * Compte vérifié (email confirmé)
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read'])]
    private bool $isVerified = false;

    /**
     * Compte actif (peut se connecter)
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['user:read'])]
    private bool $isActive = true;

    /**
     * Date de dernière connexion
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $lastLoginAt = null;

    /**
     * IP de dernière connexion
     */
    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    /**
     * Nombre total de connexions
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['user:read'])]
    private int $loginCount = 0;

    /**
     * Token de vérification email
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $emailVerificationToken = null;

    /**
     * Date d'expiration du token de vérification
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $emailVerificationTokenExpiresAt = null;

    /**
     * Token de réinitialisation de mot de passe
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $passwordResetToken = null;

    /**
     * Date d'expiration du token de réinitialisation
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $passwordResetTokenExpiresAt = null;

    /**
     * Date de création du compte
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Date de dernière mise à jour
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['user:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * Langue préférée
     */
    #[ORM\Column(type: 'string', length: 5, options: ['default' => 'fr'])]
    #[Groups(['user:read', 'user:write'])]
    private string $locale = 'fr';

    /**
     * Acceptation des conditions d'utilisation
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $termsAccepted = false;

    /**
     * Date d'acceptation des CGU
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $termsAcceptedAt = null;

    /**
     * Notifications activées
     */
    #[ORM\Column(type: 'boolean', options: ['default' => true])]
    #[Groups(['user:read', 'user:write'])]
    private bool $notificationsEnabled = true;

    /**
     * Emails marketing acceptés
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['user:read', 'user:write'])]
    private bool $marketingEmailsEnabled = false;

    public function __construct()
    {
        $this->roles = ['ROLE_USER'];
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->loginCount = 0;
        $this->isActive = true;
        $this->isVerified = false;
        $this->notificationsEnabled = true;
        $this->marketingEmailsEnabled = false;
        $this->termsAccepted = false;
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    // ==========================================
    // GETTERS ET SETTERS
    // ==========================================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * A visual identifier that represents this user.
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */
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
        if (!in_array($role, $this->roles)) {
            $this->roles[] = $role;
        }
        return $this;
    }

    public function removeRole(string $role): self
    {
        if (($key = array_search($role, $this->roles)) !== false) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }
        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Si vous stockez des données sensibles temporaires, effacez-les ici
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(string $lastName): self
    {
        $this->lastName = $lastName;
        return $this;
    }

    /**
     * Obtenir le nom complet
     */
    #[Groups(['user:read'])]
    public function getFullName(): string
    {
        return trim($this->firstName . ' ' . $this->lastName);
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

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getBirthDate(): ?\DateTimeInterface
    {
        return $this->birthDate;
    }

    public function setBirthDate(?\DateTimeInterface $birthDate): self
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

    // ==========================================
    // JWT ET SÉCURITÉ
    // ==========================================

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
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

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
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

    public function getEmailVerificationToken(): ?string
    {
        return $this->emailVerificationToken;
    }

    public function setEmailVerificationToken(?string $emailVerificationToken): self
    {
        $this->emailVerificationToken = $emailVerificationToken;
        return $this;
    }

    public function getEmailVerificationTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->emailVerificationTokenExpiresAt;
    }

    public function setEmailVerificationTokenExpiresAt(?\DateTimeInterface $emailVerificationTokenExpiresAt): self
    {
        $this->emailVerificationTokenExpiresAt = $emailVerificationTokenExpiresAt;
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

    public function getPasswordResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->passwordResetTokenExpiresAt;
    }

    public function setPasswordResetTokenExpiresAt(?\DateTimeInterface $passwordResetTokenExpiresAt): self
    {
        $this->passwordResetTokenExpiresAt = $passwordResetTokenExpiresAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getLocale(): string
    {
        return $this->locale;
    }

    public function setLocale(string $locale): self
    {
        $this->locale = $locale;
        return $this;
    }

    public function isTermsAccepted(): bool
    {
        return $this->termsAccepted;
    }

    public function setTermsAccepted(bool $termsAccepted): self
    {
        $this->termsAccepted = $termsAccepted;
        
        if ($termsAccepted && !$this->termsAcceptedAt) {
            $this->termsAcceptedAt = new \DateTime();
        }
        
        return $this;
    }

    public function getTermsAcceptedAt(): ?\DateTimeInterface
    {
        return $this->termsAcceptedAt;
    }

    public function setTermsAcceptedAt(?\DateTimeInterface $termsAcceptedAt): self
    {
        $this->termsAcceptedAt = $termsAcceptedAt;
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

    // ==========================================
    // MÉTHODES UTILITAIRES
    // ==========================================

    /**
     * Vérifie si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->getRoles());
    }

    /**
     * Obtenir l'âge de l'utilisateur
     */
    #[Groups(['user:read'])]
    public function getAge(): ?int
    {
        if (!$this->birthDate) {
            return null;
        }

        return $this->birthDate->diff(new \DateTime())->y;
    }

    /**
     * Génère un token de vérification email
     */
    public function generateEmailVerificationToken(): self
    {
        $this->emailVerificationToken = bin2hex(random_bytes(32));
        $this->emailVerificationTokenExpiresAt = new \DateTime('+24 hours');
        return $this;
    }

    /**
     * Vérifie si le token de vérification email est valide
     */
    public function isEmailVerificationTokenValid(string $token): bool
    {
        if (!$this->emailVerificationToken || !$this->emailVerificationTokenExpiresAt) {
            return false;
        }

        if ($this->emailVerificationTokenExpiresAt < new \DateTime()) {
            return false;
        }

        return hash_equals($this->emailVerificationToken, $token);
    }

    /**
     * Génère un token de réinitialisation de mot de passe
     */
    public function generatePasswordResetToken(): self
    {
        $this->passwordResetToken = bin2hex(random_bytes(32));
        $this->passwordResetTokenExpiresAt = new \DateTime('+1 hour');
        return $this;
    }

    /**
     * Vérifie si le token de réinitialisation est valide
     */
    public function isPasswordResetTokenValid(string $token): bool
    {
        if (!$this->passwordResetToken || !$this->passwordResetTokenExpiresAt) {
            return false;
        }

        if ($this->passwordResetTokenExpiresAt < new \DateTime()) {
            return false;
        }

        return hash_equals($this->passwordResetToken, $token);
    }

    /**
     * Efface le token de vérification email
     */
    public function clearEmailVerificationToken(): self
    {
        $this->emailVerificationToken = null;
        $this->emailVerificationTokenExpiresAt = null;
        return $this;
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
     * Obtenir l'adresse complète formatée
     */
    #[Groups(['user:read'])]
    public function getFullAddress(): ?string
    {
        if (!$this->address) {
            return null;
        }

        $parts = array_filter([
            $this->address,
            $this->postalCode . ' ' . $this->city,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Obtenir le nombre de jours depuis l'inscription
     */
    public function getDaysSinceRegistration(): int
    {
        return $this->createdAt->diff(new \DateTime())->days;
    }

    /**
     * Obtenir le nombre de jours depuis la dernière connexion
     */
    public function getDaysSinceLastLogin(): ?int
    {
        if (!$this->lastLoginAt) {
            return null;
        }

        return $this->lastLoginAt->diff(new \DateTime())->days;
    }

    /**
     * Vérifier si l'utilisateur est un nouvel utilisateur
     */
    public function isNewUser(): bool
    {
        return $this->getDaysSinceRegistration() <= 7;
    }

    /**
     * Désactiver le compte
     */
    public function deactivate(): self
    {
        $this->isActive = false;
        return $this;
    }

    /**
     * Activer le compte
     */
    public function activate(): self
    {
        $this->isActive = true;
        return $this;
    }

    /**
     * Marquer l'email comme vérifié
     */
    public function verifyEmail(): self
    {
        $this->isVerified = true;
        $this->clearEmailVerificationToken();
        return $this;
    }
}