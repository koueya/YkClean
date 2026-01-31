<?php
// src/Repository/AdminRepository.php

namespace App\Repository\User;

use App\Entity\User\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Admin>
 */
class AdminRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Admin::class);
    }

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
    public function findOneByEmail(string $email): ?Admin
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les super admins
     */
    public function findSuperAdmins(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.roles LIKE :role')
            ->andWhere('a.isActive = :active')
            ->setParameter('role', '%"ROLE_SUPER_ADMIN"%')
            ->setParameter('active', true)
            ->orderBy('a.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les admins par rôle
     */
    public function countByRole(string $role): int
    {
        return $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.roles LIKE :role')
            ->andWhere('a.isActive = :active')
            ->setParameter('role', '%"' . $role . '"%')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Statistiques des admins
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('a');

        return [
            'total' => (clone $qb)->select('COUNT(a.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'active' => (clone $qb)->select('COUNT(a.id)')
                ->andWhere('a.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'super_admins' => $this->countByRole('ROLE_SUPER_ADMIN'),
            
            'moderators' => $this->countByRole('ROLE_MODERATOR'),
        ];
    }

    /**
     * Admins connectés récemment
     */
    public function findRecentlyActive(int $days = 7): array
    {
        $date = new \DateTimeImmutable('-' . $days . ' days');

        return $this->createQueryBuilder('a')
            ->andWhere('a.lastLoginAt >= :date')
            ->andWhere('a.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('a.lastLoginAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Admins inactifs depuis X jours
     */
    public function findInactiveSince(int $days): array
    {
        $date = new \DateTimeImmutable('-' . $days . ' days');
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
 * Derniers admins créés
 */
public function findLatest(int $limit = 10): array
{
    return $this->createQueryBuilder('a')
        ->orderBy('a.createdAt', 'DESC')
        ->setMaxResults($limit)
        ->getQuery()
        ->getResult();
}

/**
 * Recherche d'admins
 */
public function search(string $searchTerm): array
{
    return $this->createQueryBuilder('a')
        ->andWhere(
            'a.firstName LIKE :term OR ' .
            'a.lastName LIKE :term OR ' .
            'a.email LIKE :term OR ' .
            'a.phone LIKE :term'
        )
        ->setParameter('term', '%' . $searchTerm . '%')
        ->orderBy('a.lastName', 'ASC')
        ->getQuery()
        ->getResult();
}

/**
 * Admins par département (si applicable)
 */
public function findByDepartment(?string $department = null): array
{
    $qb = $this->createQueryBuilder('a')
        ->andWhere('a.isActive = :active')
        ->setParameter('active', true);

    if ($department) {
        $qb->andWhere('a.department = :department')
           ->setParameter('department', $department);
    }

    return $qb->orderBy('a.lastName', 'ASC')
        ->getQuery()
        ->getResult();
}

/**
 * Exporte les admins en CSV
 */
public function exportToCsv(array $admins): string
{
    $handle = fopen('php://temp', 'r+');
    
    // En-têtes
    fputcsv($handle, [
        'ID',
        'Email',
        'Prénom',
        'Nom',
        'Téléphone',
        'Rôles',
        'Département',
        'Actif',
        'Créé le',
        'Dernière connexion'
    ]);

    // Données
    foreach ($admins as $admin) {
        fputcsv($handle, [
            $admin->getId(),
            $admin->getEmail(),
            $admin->getFirstName(),
            $admin->getLastName(),
            $admin->getPhone(),
            implode(', ', $admin->getRoles()),
            $admin->getDepartment() ?? 'Non défini',
            $admin->isActive() ? 'Oui' : 'Non',
            $admin->getCreatedAt()->format('d/m/Y H:i'),
            $admin->getLastLoginAt() ? $admin->getLastLoginAt()->format('d/m/Y H:i') : 'Jamais'
        ]);
    }

    rewind($handle);
    $csv = stream_get_contents($handle);
    fclose($handle);

    return $csv;
}

/**
 * Taux d'activité des admins
 */
public function getActivityRate(int $days = 30): float
{
    $total = $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->andWhere('a.isActive = :active')
        ->setParameter('active', true)
        ->getQuery()
        ->getSingleScalarResult();

    if ($total === 0) {
        return 0;
    }

    $date = new \DateTimeImmutable('-' . $days . ' days');

    $active = $this->createQueryBuilder('a')
        ->select('COUNT(a.id)')
        ->andWhere('a.isActive = :active')
        ->andWhere('a.lastLoginAt >= :date')
        ->setParameter('active', true)
        ->setParameter('date', $date)
        ->getQuery()
        ->getSingleScalarResult();

    return round(($active / $total) * 100, 2);
}

/**
 * Répartition par rôle
 */
public function getRoleDistribution(): array
{
    $roles = ['ROLE_ADMIN', 'ROLE_SUPER_ADMIN', 'ROLE_MODERATOR'];
    $distribution = [];

    foreach ($roles as $role) {
        $distribution[$role] = $this->countByRole($role);
    }

    return $distribution;
}
}