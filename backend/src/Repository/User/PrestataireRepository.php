<?php
// src/Repository/User/UserRepository.php

namespace App\Repository\User;

use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\User\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * Repository pour l'entité User (classe de base)
 * Gère les requêtes communes à tous les types d'utilisateurs
 * 
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    // ============================================
    // PASSWORD UPGRADER INTERFACE
    // ============================================

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    // ============================================
    // RECHERCHE PAR IDENTIFIANT
    // ============================================

    /**
     * Trouve un utilisateur par email
     */
    public function findOneByEmail(string $email): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un utilisateur par téléphone
     */
    public function findOneByPhone(string $phone): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.phone = :phone')
            ->setParameter('phone', $phone)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un utilisateur par token de vérification
     */
    public function findOneByVerificationToken(string $token): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.verificationToken = :token')
            ->setParameter('token', $token)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un utilisateur par token de réinitialisation de mot de passe
     */
    public function findOneByPasswordResetToken(string $token): ?User
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.passwordResetToken = :token')
            ->andWhere('u.passwordResetExpiresAt > :now')
            ->setParameter('token', $token)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // RECHERCHE PAR STATUT
    // ============================================

    /**
     * Trouve tous les utilisateurs actifs
     */
    public function findAllActive(bool $verifiedOnly = false): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'DESC');

        if ($verifiedOnly) {
            $qb->andWhere('u.isVerified = :verified')
               ->setParameter('verified', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les utilisateurs inactifs
     */
    public function findInactive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', false)
            ->orderBy('u.updatedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les utilisateurs vérifiés
     */
    public function findAllVerified(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->orderBy('u.verifiedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs non vérifiés
     */
    public function findUnverified(int $daysOld = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', false)
            ->orderBy('u.createdAt', 'DESC');

        if ($daysOld !== null) {
            $date = new \DateTimeImmutable("-{$daysOld} days");
            $qb->andWhere('u.createdAt <= :date')
               ->setParameter('date', $date);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les utilisateurs en attente de vérification
     */
    public function findPendingVerification(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isVerified = :verified')
            ->andWhere('u.verificationToken IS NOT NULL')
            ->setParameter('verified', false)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR TYPE D'UTILISATEUR
    // ============================================

    /**
     * Trouve tous les clients
     */
    public function findAllClients(bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u INSTANCE OF :clientType')
            ->setParameter('clientType', Client::class)
            ->orderBy('u.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('u.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les prestataires
     */
    public function findAllPrestataires(bool $activeOnly = false, bool $approvedOnly = false): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u INSTANCE OF :prestataireType')
            ->setParameter('prestataireType', Prestataire::class)
            ->orderBy('u.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('u.isActive = :active')
               ->setParameter('active', true);
        }

        if ($approvedOnly) {
            $qb->andWhere('u.isApproved = :approved')
               ->setParameter('approved', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les admins
     */
    public function findAllAdmins(bool $activeOnly = false): array
    {
        $qb = $this->createQueryBuilder('u')
            ->andWhere('u INSTANCE OF :adminType')
            ->setParameter('adminType', Admin::class)
            ->orderBy('u.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('u.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE PAR RÔLE
    // ============================================

    /**
     * Trouve les utilisateurs par rôle
     */
    public function findByRole(string $role): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les utilisateurs par rôle
     */
    public function countByRole(string $role): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ============================================
    // RECHERCHE PAR DATE
    // ============================================

    /**
     * Trouve les utilisateurs créés après une date
     */
    public function findCreatedAfter(\DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs créés entre deux dates
     */
    public function findCreatedBetween(\DateTimeInterface $startDate, \DateTimeInterface $endDate): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :startDate')
            ->andWhere('u.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs récemment inscrits
     */
    public function findRecentlyRegistered(int $days = 7, int $limit = 20): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs connectés récemment
     */
    public function findRecentlyActive(int $days = 30, int $limit = 50): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs inactifs depuis longtemps
     */
    public function findInactiveSince(int $days = 90): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt < :date OR u.lastLoginAt IS NULL')
            ->andWhere('u.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('u.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche d'utilisateurs par terme
     */
    public function search(string $term, int $limit = 20): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email LIKE :term OR u.firstName LIKE :term OR u.lastName LIKE :term OR u.phone LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('u.lastName', 'ASC')
            ->addOrderBy('u.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avec critères multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC');

        if (isset($criteria['email'])) {
            $qb->andWhere('u.email LIKE :email')
               ->setParameter('email', '%' . $criteria['email'] . '%');
        }

        if (isset($criteria['firstName'])) {
            $qb->andWhere('u.firstName LIKE :firstName')
               ->setParameter('firstName', '%' . $criteria['firstName'] . '%');
        }

        if (isset($criteria['lastName'])) {
            $qb->andWhere('u.lastName LIKE :lastName')
               ->setParameter('lastName', '%' . $criteria['lastName'] . '%');
        }

        if (isset($criteria['phone'])) {
            $qb->andWhere('u.phone LIKE :phone')
               ->setParameter('phone', '%' . $criteria['phone'] . '%');
        }

        if (isset($criteria['isActive'])) {
            $qb->andWhere('u.isActive = :active')
               ->setParameter('active', $criteria['isActive']);
        }

        if (isset($criteria['isVerified'])) {
            $qb->andWhere('u.isVerified = :verified')
               ->setParameter('verified', $criteria['isVerified']);
        }

        if (isset($criteria['role'])) {
            $qb->andWhere('u.roles LIKE :role')
               ->setParameter('role', '%"' . $criteria['role'] . '"%');
        }

        if (isset($criteria['userType'])) {
            switch ($criteria['userType']) {
                case 'client':
                    $qb->andWhere('u INSTANCE OF :type')
                       ->setParameter('type', Client::class);
                    break;
                case 'prestataire':
                    $qb->andWhere('u INSTANCE OF :type')
                       ->setParameter('type', Prestataire::class);
                    break;
                case 'admin':
                    $qb->andWhere('u INSTANCE OF :type')
                       ->setParameter('type', Admin::class);
                    break;
            }
        }

        if (isset($criteria['createdAfter'])) {
            $qb->andWhere('u.createdAt >= :createdAfter')
               ->setParameter('createdAfter', $criteria['createdAfter']);
        }

        if (isset($criteria['createdBefore'])) {
            $qb->andWhere('u.createdAt <= :createdBefore')
               ->setParameter('createdBefore', $criteria['createdBefore']);
        }

        if (isset($criteria['lastLoginAfter'])) {
            $qb->andWhere('u.lastLoginAt >= :lastLoginAfter')
               ->setParameter('lastLoginAfter', $criteria['lastLoginAfter']);
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
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email);

        if ($excludeUserId !== null) {
            $qb->andWhere('u.id != :userId')
               ->setParameter('userId', $excludeUserId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Vérifie si un téléphone existe déjà
     */
    public function phoneExists(string $phone, ?int $excludeUserId = null): bool
    {
        $qb = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.phone = :phone')
            ->setParameter('phone', $phone);

        if ($excludeUserId !== null) {
            $qb->andWhere('u.id != :userId')
               ->setParameter('userId', $excludeUserId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Compte le nombre total d'utilisateurs
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les utilisateurs actifs
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les utilisateurs vérifiés
     */
    public function countVerified(): int
    {
        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les utilisateurs par type
     */
    public function countByType(): array
    {
        $total = $this->countAll();
        
        $clients = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u INSTANCE OF :type')
            ->setParameter('type', Client::class)
            ->getQuery()
            ->getSingleScalarResult();

        $prestataires = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u INSTANCE OF :type')
            ->setParameter('type', Prestataire::class)
            ->getQuery()
            ->getSingleScalarResult();

        $admins = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u INSTANCE OF :type')
            ->setParameter('type', Admin::class)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'clients' => $clients,
            'prestataires' => $prestataires,
            'admins' => $admins,
        ];
    }

    /**
     * Statistiques des nouvelles inscriptions
     */
    public function getRegistrationStats(int $days = 30): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");

        $total = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult();

        $clients = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u INSTANCE OF :type')
            ->andWhere('u.createdAt >= :startDate')
            ->setParameter('type', Client::class)
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult();

        $prestataires = (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u INSTANCE OF :type')
            ->andWhere('u.createdAt >= :startDate')
            ->setParameter('type', Prestataire::class)
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'period_days' => $days,
            'total' => $total,
            'clients' => $clients,
            'prestataires' => $prestataires,
            'average_per_day' => round($total / $days, 2),
        ];
    }

    /**
     * Statistiques globales
     */
    public function getGlobalStats(): array
    {
        $typeCounts = $this->countByType();
        $activeCount = $this->countActive();
        $verifiedCount = $this->countVerified();

        $lastLogin = $this->createQueryBuilder('u')
            ->select('u.lastLoginAt')
            ->andWhere('u.lastLoginAt IS NOT NULL')
            ->orderBy('u.lastLoginAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        $recentRegistrations = $this->getRegistrationStats(30);

        return [
            'total_users' => $typeCounts['total'],
            'active_users' => $activeCount,
            'verified_users' => $verifiedCount,
            'clients' => $typeCounts['clients'],
            'prestataires' => $typeCounts['prestataires'],
            'admins' => $typeCounts['admins'],
            'last_login_at' => $lastLogin ? $lastLogin['lastLoginAt'] : null,
            'new_users_last_30_days' => $recentRegistrations['total'],
        ];
    }

    // ============================================
    // OPÉRATIONS DE MASSE
    // ============================================

    /**
     * Active ou désactive plusieurs utilisateurs
     */
    public function toggleActiveStatus(array $userIds, bool $isActive): int
    {
        return $this->createQueryBuilder('u')
            ->update()
            ->set('u.isActive', ':active')
            ->set('u.updatedAt', ':now')
            ->where('u.id IN (:ids)')
            ->setParameter('active', $isActive)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ids', $userIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les tokens de vérification expirés
     */
    public function clearExpiredVerificationTokens(int $daysOld = 7): int
    {
        $date = new \DateTimeImmutable("-{$daysOld} days");

        return $this->createQueryBuilder('u')
            ->update()
            ->set('u.verificationToken', 'NULL')
            ->where('u.isVerified = :verified')
            ->andWhere('u.createdAt < :date')
            ->andWhere('u.verificationToken IS NOT NULL')
            ->setParameter('verified', false)
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les tokens de réinitialisation de mot de passe expirés
     */
    public function clearExpiredPasswordResetTokens(): int
    {
        return $this->createQueryBuilder('u')
            ->update()
            ->set('u.passwordResetToken', 'NULL')
            ->set('u.passwordResetExpiresAt', 'NULL')
            ->where('u.passwordResetExpiresAt < :now')
            ->andWhere('u.passwordResetToken IS NOT NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    // ============================================
    // PERSISTENCE
    // ============================================

    public function save(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(User $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}