<?php

namespace App\Enum;

/**
 * Enum UserRole - Rôles utilisateur dans l'application
 * 
 * Hiérarchie des rôles :
 * SUPER_ADMIN > ADMIN > PRESTATAIRE / CLIENT
 */
enum UserRole: string
{
    /**
     * Super Administrateur
     * Accès total au système, gestion des admins
     */
    case SUPER_ADMIN = 'ROLE_SUPER_ADMIN';

    /**
     * Administrateur
     * Gestion de la plateforme (utilisateurs, catégories, etc.)
     */
    case ADMIN = 'ROLE_ADMIN';

    /**
     * Prestataire
     * Fournisseur de services (auto-entrepreneur)
     */
    case PRESTATAIRE = 'ROLE_PRESTATAIRE';

    /**
     * Client
     * Demandeur de services
     */
    case CLIENT = 'ROLE_CLIENT';

    /**
     * Utilisateur de base
     * Compte créé mais rôle non défini
     */
    case USER = 'ROLE_USER';

    /**
     * Obtenir le libellé français du rôle
     */
    public function label(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'Super Administrateur',
            self::ADMIN => 'Administrateur',
            self::PRESTATAIRE => 'Prestataire',
            self::CLIENT => 'Client',
            self::USER => 'Utilisateur',
        };
    }

    /**
     * Obtenir une description du rôle
     */
    public function description(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'Accès complet au système et gestion des administrateurs',
            self::ADMIN => 'Gestion de la plateforme et modération',
            self::PRESTATAIRE => 'Fournisseur de services professionnels',
            self::CLIENT => 'Utilisateur demandant des services',
            self::USER => 'Utilisateur sans rôle spécifique',
        };
    }

    /**
     * Obtenir la couleur associée au rôle
     */
    public function color(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'red',
            self::ADMIN => 'purple',
            self::PRESTATAIRE => 'blue',
            self::CLIENT => 'green',
            self::USER => 'gray',
        };
    }

    /**
     * Obtenir le code couleur hexadécimal
     */
    public function hexColor(): string
    {
        return match($this) {
            self::SUPER_ADMIN => '#F44336',
            self::ADMIN => '#9C27B0',
            self::PRESTATAIRE => '#2196F3',
            self::CLIENT => '#4CAF50',
            self::USER => '#9E9E9E',
        };
    }

    /**
     * Obtenir l'icône associée au rôle
     */
    public function icon(): string
    {
        return match($this) {
            self::SUPER_ADMIN => 'crown',
            self::ADMIN => 'user-shield',
            self::PRESTATAIRE => 'briefcase',
            self::CLIENT => 'user',
            self::USER => 'user-circle',
        };
    }

    /**
     * Obtenir le niveau hiérarchique du rôle (plus élevé = plus de permissions)
     */
    public function level(): int
    {
        return match($this) {
            self::SUPER_ADMIN => 100,
            self::ADMIN => 80,
            self::PRESTATAIRE => 50,
            self::CLIENT => 50,
            self::USER => 10,
        };
    }

    /**
     * Vérifie si c'est un rôle administratif
     */
    public function isAdmin(): bool
    {
        return in_array($this, [
            self::SUPER_ADMIN,
            self::ADMIN,
        ]);
    }

    /**
     * Vérifie si c'est un super admin
     */
    public function isSuperAdmin(): bool
    {
        return $this === self::SUPER_ADMIN;
    }

    /**
     * Vérifie si c'est un prestataire
     */
    public function isPrestataire(): bool
    {
        return $this === self::PRESTATAIRE;
    }

    /**
     * Vérifie si c'est un client
     */
    public function isClient(): bool
    {
        return $this === self::CLIENT;
    }

    /**
     * Vérifie si le rôle peut gérer les utilisateurs
     */
    public function canManageUsers(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Vérifie si le rôle peut gérer les catégories
     */
    public function canManageCategories(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Vérifie si le rôle peut modérer le contenu
     */
    public function canModerateContent(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Vérifie si le rôle peut approuver les prestataires
     */
    public function canApprovePrestataires(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Vérifie si le rôle peut créer des devis
     */
    public function canCreateQuotes(): bool
    {
        return $this->isPrestataire();
    }

    /**
     * Vérifie si le rôle peut créer des demandes de service
     */
    public function canCreateServiceRequests(): bool
    {
        return $this->isClient();
    }

    /**
     * Vérifie si le rôle peut accéder au dashboard admin
     */
    public function canAccessAdminDashboard(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Vérifie si le rôle peut accéder au dashboard prestataire
     */
    public function canAccessPrestataireDashboard(): bool
    {
        return $this->isPrestataire();
    }

    /**
     * Vérifie si le rôle peut accéder au dashboard client
     */
    public function canAccessClientDashboard(): bool
    {
        return $this->isClient();
    }

    /**
     * Vérifie si ce rôle est supérieur à un autre
     */
    public function isHigherThan(UserRole $other): bool
    {
        return $this->level() > $other->level();
    }

    /**
     * Vérifie si ce rôle est inférieur à un autre
     */
    public function isLowerThan(UserRole $other): bool
    {
        return $this->level() < $other->level();
    }

    /**
     * Vérifie si ce rôle est égal à un autre
     */
    public function isEqualTo(UserRole $other): bool
    {
        return $this->level() === $other->level();
    }

    /**
     * Obtenir les permissions du rôle
     */
    public function permissions(): array
    {
        return match($this) {
            self::SUPER_ADMIN => [
                'user.manage',
                'admin.manage',
                'prestataire.manage',
                'prestataire.approve',
                'client.manage',
                'category.manage',
                'service.manage',
                'booking.manage',
                'payment.manage',
                'invoice.manage',
                'settings.manage',
                'reports.view',
                'logs.view',
            ],
            self::ADMIN => [
                'user.view',
                'prestataire.manage',
                'prestataire.approve',
                'client.view',
                'category.manage',
                'service.manage',
                'booking.view',
                'booking.manage',
                'payment.view',
                'invoice.view',
                'reports.view',
            ],
            self::PRESTATAIRE => [
                'profile.edit',
                'availability.manage',
                'quote.create',
                'quote.manage',
                'booking.view_own',
                'booking.manage_own',
                'payment.view_own',
                'invoice.view_own',
                'review.view_own',
            ],
            self::CLIENT => [
                'profile.edit',
                'service_request.create',
                'service_request.manage_own',
                'quote.view_own',
                'booking.view_own',
                'booking.manage_own',
                'payment.view_own',
                'invoice.view_own',
                'review.create',
            ],
            self::USER => [
                'profile.view',
            ],
        };
    }

    /**
     * Vérifie si le rôle a une permission spécifique
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions());
    }

    /**
     * Obtenir la route du dashboard par défaut
     */
    public function defaultDashboardRoute(): string
    {
        return match($this) {
            self::SUPER_ADMIN => '/admin/dashboard',
            self::ADMIN => '/admin/dashboard',
            self::PRESTATAIRE => '/prestataire/dashboard',
            self::CLIENT => '/client/dashboard',
            self::USER => '/profile',
        };
    }

    /**
     * Obtenir la page d'accueil après connexion
     */
    public function homeRoute(): string
    {
        return $this->defaultDashboardRoute();
    }

    /**
     * Obtenir tous les rôles administratifs
     */
    public static function adminRoles(): array
    {
        return [
            self::SUPER_ADMIN,
            self::ADMIN,
        ];
    }

    /**
     * Obtenir tous les rôles non-administratifs
     */
    public static function userRoles(): array
    {
        return [
            self::PRESTATAIRE,
            self::CLIENT,
            self::USER,
        ];
    }

    /**
     * Obtenir tous les rôles actifs (avec permissions)
     */
    public static function activeRoles(): array
    {
        return [
            self::SUPER_ADMIN,
            self::ADMIN,
            self::PRESTATAIRE,
            self::CLIENT,
        ];
    }

    /**
     * Créer un rôle depuis une chaîne
     */
    public static function fromString(string $role): ?self
    {
        // Enlever le préfixe ROLE_ si présent
        if (!str_starts_with($role, 'ROLE_')) {
            $role = 'ROLE_' . strtoupper($role);
        }
        
        return self::tryFrom($role);
    }

    /**
     * Obtenir toutes les options pour un select
     */
    public static function options(bool $includeUser = false): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            if (!$includeUser && $case === self::USER) {
                continue;
            }
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    /**
     * Obtenir les options pour l'attribution de rôles (selon le rôle actuel)
     */
    public static function assignableRoles(UserRole $currentUserRole): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            // Un admin ne peut pas créer de super admin
            if ($currentUserRole === self::ADMIN && $case === self::SUPER_ADMIN) {
                continue;
            }
            // Ne pas inclure USER
            if ($case === self::USER) {
                continue;
            }
            $options[$case->value] = $case->label();
        }
        return $options;
    }

    /**
     * Obtenir le badge HTML (Tailwind CSS)
     */
    public function badge(): string
    {
        $color = $this->color();
        $label = $this->label();
        
        return sprintf(
            '<span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-%s-100 text-%s-800">%s</span>',
            $color,
            $color,
            $label
        );
    }

    /**
     * Obtenir un rôle aléatoire (pour tests/fixtures)
     */
    public static function random(bool $excludeUser = true): self
    {
        $cases = self::cases();
        
        if ($excludeUser) {
            $cases = array_filter($cases, fn($case) => $case !== self::USER);
        }
        
        return $cases[array_rand($cases)];
    }

    /**
     * Vérifier si peut gérer un autre utilisateur
     */
    public function canManageUser(UserRole $targetUserRole): bool
    {
        // Super admin peut tout gérer
        if ($this->isSuperAdmin()) {
            return true;
        }
        
        // Admin peut gérer prestataires et clients
        if ($this->isAdmin()) {
            return !$targetUserRole->isAdmin();
        }
        
        // Les autres rôles ne peuvent pas gérer d'autres utilisateurs
        return false;
    }

    /**
     * Obtenir les actions disponibles selon le rôle
     */
    public function availableActions(): array
    {
        return match($this) {
            self::SUPER_ADMIN => [
                'Gérer les administrateurs',
                'Gérer tous les utilisateurs',
                'Configurer le système',
                'Voir tous les rapports',
                'Accéder aux logs',
            ],
            self::ADMIN => [
                'Approuver les prestataires',
                'Modérer les contenus',
                'Gérer les catégories',
                'Voir les statistiques',
                'Gérer les litiges',
            ],
            self::PRESTATAIRE => [
                'Créer des devis',
                'Gérer mes disponibilités',
                'Accepter des réservations',
                'Recevoir des paiements',
                'Consulter mes revenus',
            ],
            self::CLIENT => [
                'Créer des demandes',
                'Comparer des devis',
                'Réserver des services',
                'Payer en ligne',
                'Laisser des avis',
            ],
            self::USER => [
                'Voir mon profil',
            ],
        };
    }

    /**
     * Sérialisation JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
            'description' => $this->description(),
            'color' => $this->color(),
            'hexColor' => $this->hexColor(),
            'icon' => $this->icon(),
            'level' => $this->level(),
            'isAdmin' => $this->isAdmin(),
            'permissions' => $this->permissions(),
            'defaultRoute' => $this->defaultDashboardRoute(),
        ];
    }

    /**
     * Conversion en chaîne
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Obtenir le nom court (sans ROLE_)
     */
    public function shortName(): string
    {
        return str_replace('ROLE_', '', $this->value);
    }
}