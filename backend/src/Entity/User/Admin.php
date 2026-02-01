<?php
// src/Entity/User/Admin.php

namespace App\Entity\User;

use App\Repository\AdminRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdminRepository::class)]
#[ORM\Table(name: 'admins')]
#[ORM\HasLifecycleCallbacks]
class Admin extends User
{
    // ===== INFORMATIONS ADMINISTRATIVES =====
    
    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $department = null; // Service/département (ex: "Support", "Modération", "Finance")

    #[ORM\Column(type: 'string', length: 150, nullable: true)]
    #[Assert\Length(max: 150)]
    private ?string $jobTitle = null; // Poste/titre (ex: "Gestionnaire de plateforme", "Modérateur senior")

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null; // Biographie/description

    // ===== PERMISSIONS ET RÔLES =====
    
    /**
     * Permissions granulaires de l'admin
     * Exemples: "manage_users", "approve_prestataires", "view_analytics", "manage_payments"
     */
    #[ORM\Column(type: 'json')]
    private array $permissions = [];

    #[ORM\Column(type: 'boolean')]
    private bool $isSuperAdmin = false;

    #[ORM\Column(type: 'boolean')]
    private bool $canApprovePrestataires = false;

    #[ORM\Column(type: 'boolean')]
    private bool $canManagePayments = false;

    #[ORM\Column(type: 'boolean')]
    private bool $canManageUsers = false;

    #[ORM\Column(type: 'boolean')]
    private bool $canViewAnalytics = false;

    #[ORM\Column(type: 'boolean')]
    private bool $canManageContent = false;

    #[ORM\Column(type: 'boolean')]
    private bool $canHandleDisputes = false;

    // ===== ACTIVITÉ ET LOGS =====
    
    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    #[ORM\Column(type: 'integer')]
    private int $loginCount = 0;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastActivityAt = null;

    // ===== STATISTIQUES D'ACTIVITÉ =====
    
    #[ORM\Column(type: 'integer')]
    private int $prestataireApprovals = 0; // Nombre de prestataires approuvés

    #[ORM\Column(type: 'integer')]
    private int $disputesResolved = 0; // Nombre de litiges résolus

    #[ORM\Column(type: 'integer')]
    private int $actionsPerformed = 0; // Nombre total d'actions effectuées

    // ===== PARAMÈTRES DE NOTIFICATION =====
    
    #[ORM\Column(type: 'json')]
    private array $notificationPreferences = [
        'email' => [
            'newPrestataire' => true,
            'newDispute' => true,
            'criticalIssues' => true,
            'weeklyReport' => true,
        ],
        'push' => [
            'urgentAlerts' => true,
            'newReports' => false,
        ]
    ];

    // ===== SÉCURITÉ =====
    
    #[ORM\Column(type: 'boolean')]
    private bool $requiresTwoFactor = false;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $twoFactorSecret = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $backupCodes = null;

    #[ORM\Column(type: 'json')]
    private array $allowedIpAddresses = []; // Liste d'IPs autorisées (si restriction activée)

    #[ORM\Column(type: 'boolean')]
    private bool $ipRestrictionEnabled = false;

    // ===== NOTES ET COMMENTAIRES =====
    
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null; // Notes internes sur cet admin

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $hiredAt = null; // Date d'embauche/activation

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $terminatedAt = null; // Date de fin d'activité

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $terminationReason = null;

    // ===== RELATIONS =====
    
    /**
     * Prestataires approuvés par cet admin
     * @var Collection<int, Prestataire>
     */
    #[ORM\OneToMany(mappedBy: 'approvedBy', targetEntity: Prestataire::class)]
    private Collection $approvedPrestataires;

    // ===== CONSTRUCTEUR =====
    
    public function __construct()
    {
        parent::__construct();
        
        $this->approvedPrestataires = new ArrayCollection();
        
        $this->setRoles(['ROLE_ADMIN']);
        $this->permissions = [];
        $this->isSuperAdmin = false;
        $this->canApprovePrestataires = false;
        $this->canManagePayments = false;
        $this->canManageUsers = false;
        $this->canViewAnalytics = false;
        $this->canManageContent = false;
        $this->canHandleDisputes = false;
        $this->loginCount = 0;
        $this->prestataireApprovals = 0;
        $this->disputesResolved = 0;
        $this->actionsPerformed = 0;
        $this->requiresTwoFactor = false;
        $this->ipRestrictionEnabled = false;
        $this->allowedIpAddresses = [];
        $this->notificationPreferences = [
            'email' => [
                'newPrestataire' => true,
                'newDispute' => true,
                'criticalIssues' => true,
                'weeklyReport' => true,
            ],
            'push' => [
                'urgentAlerts' => true,
                'newReports' => false,
            ]
        ];
    }

    // ===== LIFECYCLE CALLBACKS =====
    
    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        if ($this->hiredAt === null) {
            $this->hiredAt = new \DateTime();
        }
    }

    // ===== GETTERS & SETTERS - INFORMATIONS ADMINISTRATIVES =====

    public function getDepartment(): ?string
    {
        return $this->department;
    }

    public function setDepartment(?string $department): self
    {
        $this->department = $department;
        return $this;
    }

    public function getJobTitle(): ?string
    {
        return $this->jobTitle;
    }

    public function setJobTitle(?string $jobTitle): self
    {
        $this->jobTitle = $jobTitle;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): self
    {
        $this->bio = $bio;
        return $this;
    }

    // ===== PERMISSIONS =====

    public function getPermissions(): array
    {
        return $this->permissions;
    }

    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;
        return $this;
    }

    public function addPermission(string $permission): self
    {
        if (!in_array($permission, $this->permissions, true)) {
            $this->permissions[] = $permission;
        }
        return $this;
    }

    public function removePermission(string $permission): self
    {
        $key = array_search($permission, $this->permissions, true);
        if ($key !== false) {
            unset($this->permissions[$key]);
            $this->permissions = array_values($this->permissions);
        }
        return $this;
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->isSuperAdmin) {
            return true; // Super admin a toutes les permissions
        }
        return in_array($permission, $this->permissions, true);
    }

    public function hasAnyPermission(array $permissions): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }
        
        foreach ($permissions as $permission) {
            if ($this->hasPermission($permission)) {
                return true;
            }
        }
        return false;
    }

    public function hasAllPermissions(array $permissions): bool
    {
        if ($this->isSuperAdmin) {
            return true;
        }
        
        foreach ($permissions as $permission) {
            if (!$this->hasPermission($permission)) {
                return false;
            }
        }
        return true;
    }

    // ===== SUPER ADMIN =====

    public function isSuperAdmin(): bool
    {
        return $this->isSuperAdmin;
    }

    public function setIsSuperAdmin(bool $isSuperAdmin): self
    {
        $this->isSuperAdmin = $isSuperAdmin;
        
        if ($isSuperAdmin) {
            $this->addRole('ROLE_SUPER_ADMIN');
            // Super admin a automatiquement toutes les capacités
            $this->canApprovePrestataires = true;
            $this->canManagePayments = true;
            $this->canManageUsers = true;
            $this->canViewAnalytics = true;
            $this->canManageContent = true;
            $this->canHandleDisputes = true;
        }
        
        return $this;
    }

    // ===== CAPACITÉS SPÉCIFIQUES =====

    public function canApprovePrestataires(): bool
    {
        return $this->canApprovePrestataires || $this->isSuperAdmin;
    }

    public function setCanApprovePrestataires(bool $canApprovePrestataires): self
    {
        $this->canApprovePrestataires = $canApprovePrestataires;
        return $this;
    }

    public function canManagePayments(): bool
    {
        return $this->canManagePayments || $this->isSuperAdmin;
    }

    public function setCanManagePayments(bool $canManagePayments): self
    {
        $this->canManagePayments = $canManagePayments;
        return $this;
    }

    public function canManageUsers(): bool
    {
        return $this->canManageUsers || $this->isSuperAdmin;
    }

    public function setCanManageUsers(bool $canManageUsers): self
    {
        $this->canManageUsers = $canManageUsers;
        return $this;
    }

    public function canViewAnalytics(): bool
    {
        return $this->canViewAnalytics || $this->isSuperAdmin;
    }

    public function setCanViewAnalytics(bool $canViewAnalytics): self
    {
        $this->canViewAnalytics = $canViewAnalytics;
        return $this;
    }

    public function canManageContent(): bool
    {
        return $this->canManageContent || $this->isSuperAdmin;
    }

    public function setCanManageContent(bool $canManageContent): self
    {
        $this->canManageContent = $canManageContent;
        return $this;
    }

    public function canHandleDisputes(): bool
    {
        return $this->canHandleDisputes || $this->isSuperAdmin;
    }

    public function setCanHandleDisputes(bool $canHandleDisputes): self
    {
        $this->canHandleDisputes = $canHandleDisputes;
        return $this;
    }

    // ===== ACTIVITÉ ET LOGS =====

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    public function recordLogin(?string $ipAddress = null): self
    {
        $this->lastLoginAt = new \DateTime();
        $this->lastLoginIp = $ipAddress;
        $this->loginCount++;
        $this->updateLastActivity();
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

    public function getLastActivityAt(): ?\DateTimeInterface
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeInterface $lastActivityAt): self
    {
        $this->lastActivityAt = $lastActivityAt;
        return $this;
    }

    public function updateLastActivity(): self
    {
        $this->lastActivityAt = new \DateTime();
        return $this;
    }

    public function isActiveRecently(int $minutes = 15): bool
    {
        if (!$this->lastActivityAt) {
            return false;
        }
        
        $threshold = new \DateTime("-{$minutes} minutes");
        return $this->lastActivityAt > $threshold;
    }

    // ===== STATISTIQUES =====

    public function getPrestataireApprovals(): int
    {
        return $this->prestataireApprovals;
    }

    public function setPrestataireApprovals(int $prestataireApprovals): self
    {
        $this->prestataireApprovals = $prestataireApprovals;
        return $this;
    }

    public function incrementPrestataireApprovals(): self
    {
        $this->prestataireApprovals++;
        $this->incrementActionsPerformed();
        return $this;
    }

    public function getDisputesResolved(): int
    {
        return $this->disputesResolved;
    }

    public function setDisputesResolved(int $disputesResolved): self
    {
        $this->disputesResolved = $disputesResolved;
        return $this;
    }

    public function incrementDisputesResolved(): self
    {
        $this->disputesResolved++;
        $this->incrementActionsPerformed();
        return $this;
    }

    public function getActionsPerformed(): int
    {
        return $this->actionsPerformed;
    }

    public function setActionsPerformed(int $actionsPerformed): self
    {
        $this->actionsPerformed = $actionsPerformed;
        return $this;
    }

    public function incrementActionsPerformed(): self
    {
        $this->actionsPerformed++;
        $this->updateLastActivity();
        return $this;
    }

    // ===== NOTIFICATIONS =====

    public function getNotificationPreferences(): array
    {
        return $this->notificationPreferences;
    }

    public function setNotificationPreferences(array $notificationPreferences): self
    {
        $this->notificationPreferences = $notificationPreferences;
        return $this;
    }

    public function updateNotificationPreference(string $channel, string $type, bool $enabled): self
    {
        if (!isset($this->notificationPreferences[$channel])) {
            $this->notificationPreferences[$channel] = [];
        }
        
        $this->notificationPreferences[$channel][$type] = $enabled;
        return $this;
    }

    public function isNotificationEnabled(string $channel, string $type): bool
    {
        return $this->notificationPreferences[$channel][$type] ?? false;
    }

    // ===== SÉCURITÉ - TWO FACTOR =====

    public function requiresTwoFactor(): bool
    {
        return $this->requiresTwoFactor;
    }

    public function setRequiresTwoFactor(bool $requiresTwoFactor): self
    {
        $this->requiresTwoFactor = $requiresTwoFactor;
        return $this;
    }

    public function getTwoFactorSecret(): ?string
    {
        return $this->twoFactorSecret;
    }

    public function setTwoFactorSecret(?string $twoFactorSecret): self
    {
        $this->twoFactorSecret = $twoFactorSecret;
        return $this;
    }

    public function getBackupCodes(): ?array
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?array $backupCodes): self
    {
        $this->backupCodes = $backupCodes;
        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->requiresTwoFactor && $this->twoFactorSecret !== null;
    }

    // ===== SÉCURITÉ - IP RESTRICTION =====

    public function getAllowedIpAddresses(): array
    {
        return $this->allowedIpAddresses;
    }

    public function setAllowedIpAddresses(array $allowedIpAddresses): self
    {
        $this->allowedIpAddresses = $allowedIpAddresses;
        return $this;
    }

    public function addAllowedIpAddress(string $ipAddress): self
    {
        if (!in_array($ipAddress, $this->allowedIpAddresses, true)) {
            $this->allowedIpAddresses[] = $ipAddress;
        }
        return $this;
    }

    public function removeAllowedIpAddress(string $ipAddress): self
    {
        $key = array_search($ipAddress, $this->allowedIpAddresses, true);
        if ($key !== false) {
            unset($this->allowedIpAddresses[$key]);
            $this->allowedIpAddresses = array_values($this->allowedIpAddresses);
        }
        return $this;
    }

    public function isIpRestrictionEnabled(): bool
    {
        return $this->ipRestrictionEnabled;
    }

    public function setIpRestrictionEnabled(bool $ipRestrictionEnabled): self
    {
        $this->ipRestrictionEnabled = $ipRestrictionEnabled;
        return $this;
    }

    public function isIpAllowed(string $ipAddress): bool
    {
        if (!$this->ipRestrictionEnabled) {
            return true; // Pas de restriction
        }
        
        if (empty($this->allowedIpAddresses)) {
            return false; // Restriction activée mais aucune IP autorisée
        }
        
        return in_array($ipAddress, $this->allowedIpAddresses, true);
    }

    // ===== NOTES ET COMMENTAIRES =====

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getHiredAt(): ?\DateTimeInterface
    {
        return $this->hiredAt;
    }

    public function setHiredAt(?\DateTimeInterface $hiredAt): self
    {
        $this->hiredAt = $hiredAt;
        return $this;
    }

    public function getTerminatedAt(): ?\DateTimeInterface
    {
        return $this->terminatedAt;
    }

    public function setTerminatedAt(?\DateTimeInterface $terminatedAt): self
    {
        $this->terminatedAt = $terminatedAt;
        
        // Désactiver l'admin lors de la terminaison
        if ($terminatedAt !== null) {
            $this->setIsActive(false);
        }
        
        return $this;
    }

    public function getTerminationReason(): ?string
    {
        return $this->terminationReason;
    }

    public function setTerminationReason(?string $terminationReason): self
    {
        $this->terminationReason = $terminationReason;
        return $this;
    }

    public function isTerminated(): bool
    {
        return $this->terminatedAt !== null;
    }

    public function terminate(?string $reason = null): self
    {
        $this->terminatedAt = new \DateTime();
        $this->terminationReason = $reason;
        $this->setIsActive(false);
        return $this;
    }

    // ===== RELATIONS =====

    /**
     * @return Collection<int, Prestataire>
     */
    public function getApprovedPrestataires(): Collection
    {
        return $this->approvedPrestataires;
    }

    public function addApprovedPrestataire(Prestataire $prestataire): self
    {
        if (!$this->approvedPrestataires->contains($prestataire)) {
            $this->approvedPrestataires->add($prestataire);
            $prestataire->setApprovedBy($this);
        }
        return $this;
    }

    public function removeApprovedPrestataire(Prestataire $prestataire): self
    {
        if ($this->approvedPrestataires->removeElement($prestataire)) {
            if ($prestataire->getApprovedBy() === $this) {
                $prestataire->setApprovedBy(null);
            }
        }
        return $this;
    }

    // ===== MÉTHODES UTILITAIRES =====

    public function getDisplayName(): string
    {
        $name = $this->getFullName();
        if ($this->jobTitle) {
            return "{$name} ({$this->jobTitle})";
        }
        return $name;
    }

    public function getTenure(): ?\DateInterval
    {
        if (!$this->hiredAt) {
            return null;
        }
        
        $endDate = $this->terminatedAt ?? new \DateTime();
        return $this->hiredAt->diff($endDate);
    }

    public function getPermissionsSummary(): string
    {
        if ($this->isSuperAdmin) {
            return 'Super Administrateur (Accès complet)';
        }
        
        $capabilities = [];
        if ($this->canApprovePrestataires) $capabilities[] = 'Approbation prestataires';
        if ($this->canManagePayments) $capabilities[] = 'Gestion paiements';
        if ($this->canManageUsers) $capabilities[] = 'Gestion utilisateurs';
        if ($this->canViewAnalytics) $capabilities[] = 'Analytiques';
        if ($this->canManageContent) $capabilities[] = 'Gestion contenu';
        if ($this->canHandleDisputes) $capabilities[] = 'Gestion litiges';
        
        if (empty($capabilities)) {
            return 'Aucune permission spéciale';
        }
        
        return implode(', ', $capabilities);
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}