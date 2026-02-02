<?php
// src/Repository/User/ClientRepository.php

namespace App\Repository\User;

use App\Entity\User\Client;
use App\Entity\Service\ServiceCategory;
use App\Entity\Service\ServiceSubcategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Client
 * Gère les requêtes spécifiques aux clients
 * 
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    // ============================================
    // RECHERCHE DE BASE
    // ============================================

    /**
     * Trouve tous les clients actifs
     */
    public function findAllActive(bool $verifiedOnly = false): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'DESC');

        if ($verifiedOnly) {
            $qb->andWhere('c.isVerified = :verified')
               ->setParameter('verified', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve un client par email
     */
    public function findByEmail(string $email): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve un client par téléphone
     */
    public function findByPhone(string $phone): ?Client
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.phone = :phone')
            ->setParameter('phone', $phone)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // RECHERCHE PAR ACTIVITÉ
    // ============================================

    /**
     * Trouve les clients avec des demandes actives
     */
    public function findWithActiveRequests(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.serviceRequests', 'sr')
            ->andWhere('c.isActive = :active')
            ->andWhere('sr.status IN (:statuses)')
            ->setParameter('active', true)
            ->setParameter('statuses', ['open', 'quoted', 'in_progress'])
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients avec des réservations actives
     */
    public function findWithActiveBookings(): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('active', true)
            ->setParameter('statuses', ['scheduled', 'confirmed', 'in_progress'])
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients inactifs depuis X jours
     */
    public function findInactiveSince(int $days = 90): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('c')
            ->leftJoin('c.serviceRequests', 'sr')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.lastLoginAt < :date OR c.lastLoginAt IS NULL')
            ->andWhere('sr.createdAt < :date OR sr.id IS NULL')
            ->andWhere('b.createdAt < :date OR b.id IS NULL')
            ->setParameter('active', true)
            ->setParameter('date', $date)
            ->groupBy('c.id')
            ->orderBy('c.lastLoginAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR LOCALISATION
    // ============================================

    /**
     * Trouve les clients par ville
     */
    public function findByCity(string $city, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.city = :city')
            ->setParameter('city', $city)
            ->orderBy('c.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les clients par code postal
     */
    public function findByPostalCode(string $postalCode, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.postalCode = :postalCode')
            ->setParameter('postalCode', $postalCode)
            ->orderBy('c.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les clients dans un rayon donné
     */
    public function findNearLocation(
        float $latitude,
        float $longitude,
        int $radiusKm = 50,
        bool $activeOnly = true
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.latitude IS NOT NULL')
            ->andWhere('c.longitude IS NOT NULL');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        // Formule de Haversine pour calculer la distance
        $qb->andWhere(
            '(6371 * acos(cos(radians(:latitude)) * cos(radians(c.latitude)) * ' .
            'cos(radians(c.longitude) - radians(:longitude)) + ' .
            'sin(radians(:latitude)) * sin(radians(c.latitude)))) <= :radius'
        )
        ->setParameter('latitude', $latitude)
        ->setParameter('longitude', $longitude)
        ->setParameter('radius', $radiusKm)
        ->orderBy('c.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE PAR HISTORIQUE D'UTILISATION
    // ============================================

    /**
     * Trouve les clients par nombre de demandes
     */
    public function findByServiceRequestCount(
        int $minCount = null,
        int $maxCount = null,
        bool $activeOnly = true
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->select('c, COUNT(DISTINCT sr.id) as requestCount')
            ->leftJoin('c.serviceRequests', 'sr')
            ->groupBy('c.id');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        if ($minCount !== null) {
            $qb->having('COUNT(sr.id) >= :minCount')
               ->setParameter('minCount', $minCount);
        }

        if ($maxCount !== null) {
            $qb->having('COUNT(sr.id) <= :maxCount')
               ->setParameter('maxCount', $maxCount);
        }

        $qb->orderBy('requestCount', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les clients par nombre de réservations
     */
    public function findByBookingCount(
        int $minCount = null,
        int $maxCount = null,
        bool $activeOnly = true
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->select('c, COUNT(DISTINCT b.id) as bookingCount')
            ->leftJoin('c.bookings', 'b')
            ->groupBy('c.id');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        if ($minCount !== null) {
            $qb->having('COUNT(b.id) >= :minCount')
               ->setParameter('minCount', $minCount);
        }

        if ($maxCount !== null) {
            $qb->having('COUNT(b.id) <= :maxCount')
               ->setParameter('maxCount', $maxCount);
        }

        $qb->orderBy('bookingCount', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les clients les plus actifs
     */
    public function findMostActive(int $limit = 20, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c, COUNT(DISTINCT b.id) as bookingCount')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('bookingCount', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('b.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les nouveaux clients
     */
    public function findNew(int $days = 30, int $limit = 50): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('c')
            ->andWhere('c.createdAt >= :date')
            ->andWhere('c.isActive = :active')
            ->setParameter('date', $date)
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients qui n'ont jamais fait de réservation
     */
    public function findWithoutBookings(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('b.id IS NULL');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients qui n'ont fait qu'une seule réservation
     */
    public function findOneTimeClients(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->having('COUNT(b.id) = 1')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR CATÉGORIE DE SERVICE
    // ============================================

    /**
     * Trouve les clients par catégorie de service utilisée
     */
    public function findByServiceCategory(ServiceCategory $category, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.serviceRequests', 'sr')
            ->andWhere('sr.category = :category')
            ->setParameter('category', $category)
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les clients par sous-catégorie de service
     */
    public function findByServiceSubcategory(ServiceSubcategory $subcategory, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.serviceRequests', 'sr')
            ->andWhere('sr.subcategory = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE PAR DÉPENSES
    // ============================================

    /**
     * Trouve les clients par total dépensé
     */
    public function findByTotalSpent(
        ?float $minAmount = null,
        ?float $maxAmount = null,
        bool $activeOnly = true
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->select('c, SUM(p.amount) as totalSpent')
            ->leftJoin('c.payments', 'p')
            ->andWhere('p.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('c.id');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        if ($minAmount !== null) {
            $qb->having('SUM(p.amount) >= :minAmount')
               ->setParameter('minAmount', $minAmount);
        }

        if ($maxAmount !== null) {
            $qb->having('SUM(p.amount) <= :maxAmount')
               ->setParameter('maxAmount', $maxAmount);
        }

        $qb->orderBy('totalSpent', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les meilleurs clients (par montant dépensé)
     */
    public function findTopSpenders(int $limit = 20, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c, SUM(p.amount) as totalSpent')
            ->leftJoin('c.payments', 'p')
            ->andWhere('c.isActive = :active')
            ->andWhere('p.status = :status')
            ->setParameter('active', true)
            ->setParameter('status', 'completed')
            ->groupBy('c.id')
            ->orderBy('totalSpent', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('p.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE PAR AVIS
    // ============================================

    /**
     * Trouve les clients qui ont laissé des avis
     */
    public function findWithReviews(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.reviews', 'r')
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les clients qui n'ont jamais laissé d'avis
     */
    public function findWithoutReviews(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.reviews', 'r')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('r.id IS NULL')
            ->andWhere('b.status = :status')
            ->setParameter('status', 'completed')
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche de clients par terme
     */
    public function search(string $term, int $limit = 20): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.email LIKE :term OR c.firstName LIKE :term OR c.lastName LIKE :term OR c.phone LIKE :term')
            ->andWhere('c.isActive = :active')
            ->setParameter('term', '%' . $term . '%')
            ->setParameter('active', true)
            ->orderBy('c.lastName', 'ASC')
            ->addOrderBy('c.firstName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche avec critères multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC');

        if (isset($criteria['active'])) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', $criteria['active']);
        } else {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        if (isset($criteria['verified'])) {
            $qb->andWhere('c.isVerified = :verified')
               ->setParameter('verified', $criteria['verified']);
        }

        if (isset($criteria['email'])) {
            $qb->andWhere('c.email LIKE :email')
               ->setParameter('email', '%' . $criteria['email'] . '%');
        }

        if (isset($criteria['firstName'])) {
            $qb->andWhere('c.firstName LIKE :firstName')
               ->setParameter('firstName', '%' . $criteria['firstName'] . '%');
        }

        if (isset($criteria['lastName'])) {
            $qb->andWhere('c.lastName LIKE :lastName')
               ->setParameter('lastName', '%' . $criteria['lastName'] . '%');
        }

        if (isset($criteria['phone'])) {
            $qb->andWhere('c.phone LIKE :phone')
               ->setParameter('phone', '%' . $criteria['phone'] . '%');
        }

        if (isset($criteria['city'])) {
            $qb->andWhere('c.city = :city')
               ->setParameter('city', $criteria['city']);
        }

        if (isset($criteria['postal_code'])) {
            $qb->andWhere('c.postalCode = :postalCode')
               ->setParameter('postalCode', $criteria['postal_code']);
        }

        if (isset($criteria['has_bookings'])) {
            if ($criteria['has_bookings']) {
                $qb->join('c.bookings', 'b');
            } else {
                $qb->leftJoin('c.bookings', 'b')
                   ->andWhere('b.id IS NULL');
            }
        }

        if (isset($criteria['has_reviews'])) {
            if ($criteria['has_reviews']) {
                $qb->join('c.reviews', 'r');
            } else {
                $qb->leftJoin('c.reviews', 'r')
                   ->andWhere('r.id IS NULL');
            }
        }

        if (isset($criteria['created_after'])) {
            $qb->andWhere('c.createdAt >= :createdAfter')
               ->setParameter('createdAfter', $criteria['created_after']);
        }

        if (isset($criteria['created_before'])) {
            $qb->andWhere('c.createdAt <= :createdBefore')
               ->setParameter('createdBefore', $criteria['created_before']);
        }

        if (isset($criteria['last_login_after'])) {
            $qb->andWhere('c.lastLoginAt >= :lastLoginAfter')
               ->setParameter('lastLoginAfter', $criteria['last_login_after']);
        }

        if (!empty($criteria['search'])) {
            $qb->andWhere('c.email LIKE :search OR c.firstName LIKE :search OR c.lastName LIKE :search OR c.phone LIKE :search')
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
    public function emailExists(string $email, ?int $excludeClientId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.email = :email')
            ->setParameter('email', $email);

        if ($excludeClientId !== null) {
            $qb->andWhere('c.id != :clientId')
               ->setParameter('clientId', $excludeClientId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Vérifie si un téléphone existe déjà
     */
    public function phoneExists(string $phone, ?int $excludeClientId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.phone = :phone')
            ->setParameter('phone', $phone);

        if ($excludeClientId !== null) {
            $qb->andWhere('c.id != :clientId')
               ->setParameter('clientId', $excludeClientId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Compte le nombre total de clients
     */
    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les clients actifs
     */
    public function countActive(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les clients vérifiés
     */
    public function countVerified(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.isVerified = :verified')
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les clients par ville
     */
    public function countByCity(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.city, COUNT(c.id) as count')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.city IS NOT NULL')
            ->setParameter('active', true)
            ->groupBy('c.city')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des nouvelles inscriptions
     */
    public function getRegistrationStats(int $days = 30): array
    {
        $startDate = new \DateTimeImmutable("-{$days} days");

        $total = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.createdAt >= :startDate')
            ->setParameter('startDate', $startDate)
            ->getQuery()
            ->getSingleScalarResult();

        $verified = (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.createdAt >= :startDate')
            ->andWhere('c.isVerified = :verified')
            ->setParameter('startDate', $startDate)
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'period_days' => $days,
            'total' => $total,
            'verified' => $verified,
            'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
            'average_per_day' => round($total / $days, 2),
        ];
    }

    /**
     * Statistiques globales des clients
     */
    public function getGlobalStats(): array
    {
        $total = $this->countAll();
        $active = $this->countActive();
        $verified = $this->countVerified();

        $withBookings = (int) $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->join('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $withReviews = (int) $this->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.id)')
            ->join('c.reviews', 'r')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $avgBookingsPerClient = $this->createQueryBuilder('c')
            ->select('AVG(bookingCount)')
            ->join('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->having('COUNT(b.id) > 0')
            ->getQuery()
            ->getSingleScalarResult();

        $recentRegistrations = $this->getRegistrationStats(30);

        return [
            'total_clients' => $total,
            'active_clients' => $active,
            'verified_clients' => $verified,
            'clients_with_bookings' => $withBookings,
            'clients_with_reviews' => $withReviews,
            'average_bookings_per_client' => $avgBookingsPerClient ? round((float) $avgBookingsPerClient, 2) : 0,
            'conversion_rate' => $total > 0 ? round(($withBookings / $total) * 100, 2) : 0,
            'new_clients_last_30_days' => $recentRegistrations['total'],
        ];
    }

    // ============================================
    // OPÉRATIONS DE MASSE
    // ============================================

    /**
     * Active ou désactive plusieurs clients
     */
    public function toggleActiveStatus(array $clientIds, bool $isActive): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isActive', ':active')
            ->set('c.updatedAt', ':now')
            ->where('c.id IN (:ids)')
            ->setParameter('active', $isActive)
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('ids', $clientIds)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // PERSISTENCE
    // ============================================

    public function save(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Client $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}