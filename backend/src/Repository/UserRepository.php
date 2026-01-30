<?php
// src/Repository/UserRepository.php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

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
     * Trouve tous les utilisateurs actifs
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'DESC')
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
    public function findUnverified(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', false)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

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
     * Compte les utilisateurs par rôle
     */
    public function countByRole(string $role): int
    {
        return $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('role', '%"' . $role . '"%')
            ->getQuery()
            ->getSingleScalarResult();
    }

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
     * Trouve les utilisateurs inactifs depuis X jours
     */
    public function findInactiveSince(int $days): array
    {
        $date = new \DateTimeImmutable('-' . $days . ' days');
        
        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt < :date OR u.lastLoginAt IS NULL')
            ->andWhere('u.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('u.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs par ville
     */
    public function findByCity(string $city): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.city LIKE :city')
            ->setParameter('city', '%' . $city . '%')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les utilisateurs par code postal
     */
    public function findByPostalCode(string $postalCode): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.postalCode = :postalCode')
            ->setParameter('postalCode', $postalCode)
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche d'utilisateurs
     */
    public function search(string $searchTerm): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere(
                'u.firstName LIKE :term OR ' .
                'u.lastName LIKE :term OR ' .
                'u.email LIKE :term OR ' .
                'u.phone LIKE :term'
            )
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('u.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des utilisateurs
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('u');

        return [
            'total' => $qb->select('COUNT(u.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'active' => $qb->select('COUNT(u.id)')
                ->andWhere('u.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'inactive' => $qb->select('COUNT(u.id)')
                ->andWhere('u.isActive = :active')
                ->setParameter('active', false)
                ->getQuery()
                ->getSingleScalarResult(),

            'verified' => $qb->select('COUNT(u.id)')
                ->andWhere('u.isVerified = :verified')
                ->setParameter('verified', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'unverified' => $qb->select('COUNT(u.id)')
                ->andWhere('u.isVerified = :verified')
                ->setParameter('verified', false)
                ->getQuery()
                ->getSingleScalarResult(),

            'clients' => $this->countByRole('ROLE_CLIENT'),
            'prestataires' => $this->countByRole('ROLE_PRESTATAIRE'),
            'admins' => $this->countByRole('ROLE_ADMIN'),
        ];
    }

    /**
     * Inscriptions par mois
     */
    public function getMonthlyRegistrations(int $year): array
    {
        return $this->createQueryBuilder('u')
            ->select('MONTH(u.createdAt) as month, COUNT(u.id) as count')
            ->andWhere('YEAR(u.createdAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Derniers utilisateurs inscrits
     */
    public function findLatestRegistrations(int $limit = 10): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs connectés récemment
     */
    public function findRecentlyActive(int $days = 7, int $limit = 20): array
    {
        $date = new \DateTimeImmutable('-' . $days . ' days');

        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('u.lastLoginAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition par ville
     */
    public function getCityDistribution(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.city, COUNT(u.id) as count')
            ->andWhere('u.city IS NOT NULL')
            ->groupBy('u.city')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition par code postal
     */
    public function getPostalCodeDistribution(): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.postalCode, u.city, COUNT(u.id) as count')
            ->andWhere('u.postalCode IS NOT NULL')
            ->groupBy('u.postalCode', 'u.city')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs sans connexion depuis leur inscription
     */
    public function findNeverLoggedIn(): array
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.lastLoginAt IS NULL')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('u.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Utilisateurs inactifs (à supprimer potentiellement)
     */
    public function findInactiveForDeletion(int $inactiveDays = 365): array
    {
        $date = new \DateTimeImmutable('-' . $inactiveDays . ' days');

        return $this->createQueryBuilder('u')
            ->andWhere(
                '(u.lastLoginAt IS NULL AND u.createdAt < :date) OR ' .
                '(u.lastLoginAt IS NOT NULL AND u.lastLoginAt < :date)'
            )
            ->andWhere('u.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', false)
            ->orderBy('u.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Taux de vérification
     */
    public function getVerificationRate(): float
    {
        $total = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $verified = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($verified / $total) * 100, 2);
    }

    /**
     * Taux d'activité
     */
    public function getActivityRate(): float
    {
        $total = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $active = $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->andWhere('u.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($active / $total) * 100, 2);
    }

    /**
     * Exporte les utilisateurs en CSV
     */
    public function exportToCsv(array $users): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Email',
            'Prénom',
            'Nom',
            'Téléphone',
            'Adresse',
            'Ville',
            'Code Postal',
            'Vérifié',
            'Actif',
            'Rôles',
            'Inscrit le',
            'Dernière connexion'
        ]);

        // Données
        foreach ($users as $user) {
            fputcsv($handle, [
                $user->getId(),
                $user->getEmail(),
                $user->getFirstName(),
                $user->getLastName(),
                $user->getPhone(),
                $user->getAddress(),
                $user->getCity(),
                $user->getPostalCode(),
                $user->isVerified() ? 'Oui' : 'Non',
                $user->isActive() ? 'Oui' : 'Non',
                implode(', ', $user->getRoles()),
                $user->getCreatedAt()->format('d/m/Y H:i'),
                $user->getLastLoginAt() ? $user->getLastLoginAt()->format('d/m/Y H:i') : 'Jamais'
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Trouve les doublons potentiels (même email ou téléphone)
     */
    public function findPotentialDuplicates(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        // Doublons par email
        $emailDuplicates = $conn->executeQuery('
            SELECT email, COUNT(*) as count
            FROM users
            GROUP BY email
            HAVING count > 1
        ')->fetchAllAssociative();

        // Doublons par téléphone
        $phoneDuplicates = $conn->executeQuery('
            SELECT phone, COUNT(*) as count
            FROM users
            GROUP BY phone
            HAVING count > 1
        ')->fetchAllAssociative();

        return [
            'email_duplicates' => $emailDuplicates,
            'phone_duplicates' => $phoneDuplicates
        ];
    }

    /**
     * Moyenne d'âge des comptes (en jours)
     */
    public function getAverageAccountAge(): float
    {
        $result = $this->createQueryBuilder('u')
            ->select('AVG(TIMESTAMPDIFF(DAY, u.createdAt, CURRENT_TIMESTAMP()))')
            ->getQuery()
            ->getSingleScalarResult();

        return (float)($result ?? 0);
    }
}