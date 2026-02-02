<?php
// src/Repository/User/AdminRepository.php

namespace App\Repository\User;

use App\Entity\User\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Admin
 * Gère les requêtes spécifiques aux administrateurs
 * 
 * @extends ServiceEntityRepository<Admin>
 */
class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

    // ============================================
    // RECHERCHE DE BASE
    // ============================================

    /**
     * Trouve tous les admins actifs
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve un admin par email
     */
    public function findByEmail(string $email): ?Admin
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // RECHERCHE PAR RÔLE ET PERMISSIONS
    // ============================================

    /**
     * Trouve tous les super admins
     */
    public function findSuperAdmins(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isSuperAdmin = :superAdmin')
            ->setParameter('superAdmin', true)
            ->orderBy('a.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les admins par permission
     */
    public function findByPermission(string $permission, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.permissions LIKE :permission')
            ->setParameter('permission', '%"' . $permission . '"%')
            ->orderBy('a.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les admins ayant une capacité spécifique
     */
    public function findByCapability(string $capability, bool $activeOnly = true): array
    {
        $validCapabilities = [
            'canApprovePrestataires',
            'canManagePayments',
            'canManageUsers',
            'canViewAnalytics',
            'canManageContent',
            'canHandleDisputes'
        ];

        if (!in_array($capability, $validCapabilities)) {
            return [];
        }

        $qb = $this->createQueryBuilder('a')
            ->andWhere("a.{$capability} = :value")
            ->setParameter('value', true)
            ->orderBy('a.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les admins pouvant approuver les prestataires
     */
    public function findCanApprovePrestataires(bool $activeOnly = true): array
    {
        return $this->findByCapability('canApprovePrestataires', $activeOnly);
    }

    /**
     * Trouve les admins pouvant gérer les paiements
     */
    public function findCanManagePayments(bool $activeOnly = true): array
    {
        return $this->findByCapability('canManagePayments', $activeOnly);
    }

    /**
     * Trouve les admins pouvant gérer les utilisateurs
     */
    public function findCanManageUsers(bool $activeOnly = true): array
    {
        return $this->findByCapability('canManageUsers', $activeOnly);
    }

    /**
     * Trouve les admins pouvant gérer les litiges
     */
    public function findCanHandleDisputes(bool $activeOnly = true): array
    {
        return $this->findByCapability('canHandleDisputes', $activeOnly);
    }

    // ============================================
    // RECHERCHE PAR DÉPARTEMENT
    // ============================================

    /**
     * Trouve les admins par département
     */
    public function findByDepartment(string $department, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.department = :department')
            ->setParameter('department', $department)
            ->orderBy('a.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Liste tous les départements
     */
    public function findAllDepartments(): array
    {
        $results = $this->createQueryBuilder('a')
            ->select('DISTINCT a.department')
            ->andWhere('a.department IS NOT NULL')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.department', 'ASC')
            ->getQuery()
            ->getResult();

        return array_column($results, 'department');
    }

    // ============================================
    // RECHERCHE PAR ACTIVITÉ
    // ============================================

    /**
     * Trouve les admins connectés récemment
     */
    public function findRecentlyActive(int $days = 7, int $limit = 20): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('a')
            ->andWhere('a.lastLoginAt >= :date')
            ->andWhere('a.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('a.lastLoginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les admins inactifs depuis longtemps
     */
    public function findInactiveSince(int $days = 90): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('a')
            ->andWhere('a.lastLoginAt < :date OR a.lastLoginAt IS NULL')
            ->andWhere('a.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('a.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les admins par période d'embauche
     */
    public function findHiredBetween(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.hiredAt >= :startDate')
            ->andWhere('a.hiredAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.hiredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR PERFORMANCE
    // ============================================

    /**
     * Trouve les admins les plus actifs (par actions)
     */
    public function findMostActive(int $limit = 10, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('a.actionsPerformed', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('a.lastActivityAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les admins ayant approuvé le plus de prestataires
     */
    public function findTopApprovers(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.prestataireApprovals > 0')
            ->setParameter('active', true)
            ->orderBy('a.prestataireApprovals', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les admins ayant résolu le plus de litiges
     */
    public function findTopDisputeResolvers(int $limit = 10): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.disputesResolved > 0')
            ->setParameter('active', true)
            ->orderBy('a.disputesResolved', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR SÉCURITÉ
    // ============================================

    /**
     * Trouve les admins avec authentification à deux facteurs
     */
    public function findWithTwoFactor(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.requiresTwoFactor = :twoFactor')
            ->setParameter('twoFactor', true)
            ->orderBy('a.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les admins sans authentification à deux facteurs
     */
    public function findWithoutTwoFactor(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.requiresTwoFactor = :twoFactor')
            ->setParameter('twoFactor', false)
            ->orderBy('a.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les admins avec restriction IP
     */
    public function findWithIpRestriction(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('a')
            ->andWhere('a.ipRestrictionEnabled = :enabled')
            ->setParameter('enabled', true)
            ->orderBy('a.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche d'admins par terme
     */
    public function search(string $term, int $limit = 20): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.email LIKE :term OR a.firstName LIKE :term OR a.lastName LIKE :term OR a.department LIKE :term OR a.jobTitle LIKE :term')
            ->andWhere('a.isActive = :active')
            ->setParameter('term', '%' . $term . '%')
            ->setParameter('active', true)
            ->orderBy('a.lastName', 'ASC')
            ->addOrderBy('a.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avec critères multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC');

        if (isset($criteria['active'])) {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', $criteria['active']);
        } else {
            $qb->andWhere('a.isActive = :active')
               ->setParameter('active', true);
        }

        if (isset($criteria['super_admin'])) {
            $qb->andWhere('a.isSuperAdmin = :superAdmin')
               ->setParameter('superAdmin', $criteria['super_admin']);
        }

        if (isset($criteria['department'])) {
            $qb->andWhere('a.department = :department')
               ->setParameter('department', $criteria['department']);
        }

        if (isset($criteria['permission'])) {
            $qb->andWhere('a.permissions LIKE :permission')
               ->setParameter('permission', '%"' . $criteria['permission'] . '"%');
        }

        if (isset($criteria['can_approve_prestataires'])) {
            $qb->andWhere('a.canApprovePrestataires = :canApprove')
               ->setParameter('canApprove', $criteria['can_approve_prestataires']);
        }

        if (isset($criteria['can_manage_payments'])) {
            $qb->andWhere('a.canManagePayments = :canManage')
               ->setParameter('canManage', $criteria['can_manage_payments']);
        }

        if (isset($criteria['can_manage_users'])) {
            $qb->andWhere('a.canManageUsers = :canManage')
               ->setParameter('canManage', $criteria['can_manage_users']);
        }

        if (isset($criteria['requires_two_factor'])) {
            $qb->andWhere('a.requiresTwoFactor = :twoFactor')
               ->setParameter('twoFactor', $criteria['requires_two_factor']);
        }

        if (isset($criteria['hired_after'])) {
            $qb->andWhere('a.hiredAt >= :hiredAfter')
               ->setParameter('hiredAfter', $criteria['hired_after']);
        }

        if (isset($criteria['last_login_after'])) {
            $qb->andWhere('a.lastLoginAt >= :lastLoginAfter')
               ->setParameter('lastLoginAfter', $criteria['last_login_after']);
        }

        if (isset($criteria['min_actions'])) {
            $qb->andWhere('a.actionsPerformed >= :minActions')
               ->setParameter('minActions', $criteria['min_actions']);
        }

        if (!empty($criteria['search'])) {
            $qb->andWhere('a.email LIKE :search OR a.firstName LIKE :search OR a.lastName LIKE :search OR a.department LIKE :search')
               ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (isset($criteria['limit'])) {
            $qb->setMaxResults($criteria['limit']);
        }

        if (isset($criteria['offset'])) {
            $qb->setFirstResult($criteria['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // VÉRIFICATIONS
    // ============================================

    /**
     * Vérifie si un email existe déjà
     */
    public function emailExists(string $email, ?int $excludeAdminId = null): bool
    {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.email = :email')
            ->setParameter('email', $email);

        if ($excludeAdminId !== null) {
            $qb->andWhere('a.id != :adminId')
               ->setParameter('adminId', $excludeAdminId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Vérifie si un admin a une permission
     */
    public function hasPermission(Admin $admin, string $permission): bool
    {
        if ($admin->isSuperAdmin()) {
            return true;
        }

        return in_array($permission, $admin->getPermissions(), true);
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Compte le nombre total d'admins
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les admins actifs
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les super admins
     */
    public function countSuperAdmins(): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isSuperAdmin = :superAdmin')
            ->andWhere('a.isActive = :active')
            ->setParameter('superAdmin', true)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les admins par département
     */
    public function countByDepartment(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.department, COUNT(a.id) as count')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.department IS NOT NULL')
            ->setParameter('active', true)
            ->groupBy('a.department')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les admins par permission
     */
    public function countByPermission(string $permission): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.permissions LIKE :permission')
            ->andWhere('a.isActive = :active')
            ->setParameter('permission', '%"' . $permission . '"%')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les admins par rôle
     */
    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.roles LIKE :role')
            ->andWhere('a.isActive = :active')
            ->setParameter('role', '%"' . $role . '"%')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Statistiques d'activité des admins
     */
    public function getActivityStats(int $days = 30): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        $total = $this->countActive();

        $activeInPeriod = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.lastLoginAt >= :date')
            ->setParameter('active', true)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        $totalLogins = (int) $this->createQueryBuilder('a')
            ->select('SUM(a.loginCount)')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.lastLoginAt >= :date')
            ->setParameter('active', true)
            ->setParameter('date', $date)
            ->getQuery()
            ->getSingleScalarResult();

        $totalActions = (int) $this->createQueryBuilder('a')
            ->select('SUM(a.actionsPerformed)')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'period_days' => $days,
            'total_admins' => $total,
            'active_in_period' => $activeInPeriod,
            'activity_rate' => $total > 0 ? round(($activeInPeriod / $total) * 100, 2) : 0,
            'total_logins' => $totalLogins,
            'total_actions' => $totalActions,
            'average_logins_per_admin' => $activeInPeriod > 0 ? round($totalLogins / $activeInPeriod, 2) : 0,
        ];
    }

    /**
     * Statistiques globales des admins
     */
    public function getGlobalStats(): array
    {
        $total = $this->countAll();
        $active = $this->countActive();
        $superAdmins = $this->countSuperAdmins();

        $totalApprovals = (int) $this->createQueryBuilder('a')
            ->select('SUM(a.prestataireApprovals)')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $totalDisputes = (int) $this->createQueryBuilder('a')
            ->select('SUM(a.disputesResolved)')
            ->andWhere('a.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $withTwoFactor = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.isActive = :active')
            ->andWhere('a.requiresTwoFactor = :twoFactor')
            ->setParameter('active', true)
            ->setParameter('twoFactor', true)
            ->getQuery()
            ->getSingleScalarResult();

        $activityStats = $this->getActivityStats(30);

        return [
            'total_admins' => $total,
            'active_admins' => $active,
            'super_admins' => $superAdmins,
            'total_prestataire_approvals' => $totalApprovals,
            'total_disputes_resolved' => $totalDisputes,
            'two_factor_enabled' => $withTwoFactor,
            'two_factor_rate' => $active > 0 ? round(($withTwoFactor / $active) * 100, 2) : 0,
            'activity_30_days' => $activityStats,
        ];
    }

    /**
     * Répartition des permissions
     */
    public function getPermissionDistribution(): array
    {
        $commonPermissions = [
            'user.manage',
            'prestataire.approve',
            'payment.manage',
            'reports.view',
            'settings.manage'
        ];

        $distribution = [];
        foreach ($commonPermissions as $permission) {
            $distribution[$permission] = $this->countByPermission($permission);
        }

        return $distribution;
    }

    // ============================================
    // OPÉRATIONS DE MASSE
    // ============================================

    /**
     * Active ou désactive plusieurs admins
     */
    public function toggleActiveStatus(array $adminIds, bool $isActive): int
    {
        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.isActive', ':active')
            ->set('a.updatedAt', ':now')
            ->where('a.id IN (:ids)')
            ->setParameter('active', $isActive)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ids', $adminIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Ajoute une permission à plusieurs admins
     */
    public function addPermissionBatch(array $adminIds, string $permission): void
    {
        $admins = $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $adminIds)
            ->getQuery()
            ->getResult();

        foreach ($admins as $admin) {
            if (!$admin->hasPermission($permission)) {
                $admin->addPermission($permission);
            }
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Retire une permission à plusieurs admins
     */
    public function removePermissionBatch(array $adminIds, string $permission): void
    {
        $admins = $this->createQueryBuilder('a')
            ->andWhere('a.id IN (:ids)')
            ->setParameter('ids', $adminIds)
            ->getQuery()
            ->getResult();

        foreach ($admins as $admin) {
            $admin->removePermission($permission);
        }

        $this->getEntityManager()->flush();
    }

    /**
     * Force l'authentification à deux facteurs pour plusieurs admins
     */
    public function enableTwoFactorBatch(array $adminIds): int
    {
        return $this->createQueryBuilder('a')
            ->update()
            ->set('a.requiresTwoFactor', ':twoFactor')
            ->where('a.id IN (:ids)')
            ->setParameter('twoFactor', true)
            ->setParameter('ids', $adminIds)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // PERSISTENCE
    // ============================================

    public function save(Admin $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Admin $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}