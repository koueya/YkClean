<?php
// src/Repository/Service/ServiceSubcategoryRepository.php

namespace App\Repository\Service;

use App\Entity\Service\ServiceSubcategory;
use App\Entity\Service\ServiceCategory;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité ServiceSubcategory
 * Gère les requêtes liées aux sous-catégories de services
 * 
 * @extends ServiceEntityRepository<ServiceSubcategory>
 */
class ServiceSubcategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceSubcategory::class);
    }

    // ============================================
    // RECHERCHE DE BASE
    // ============================================

    /**
     * Trouve toutes les sous-catégories actives
     */
    public function findAllActive(bool $orderByPosition = true): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->where('sc.isActive = :active')
            ->setParameter('active', true);

        if ($orderByPosition) {
            $qb->orderBy('sc.displayOrder', 'ASC')
               ->addOrderBy('sc.name', 'ASC');
        } else {
            $qb->orderBy('sc.name', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve une sous-catégorie par son slug
     */
    public function findBySlug(string $slug): ?ServiceSubcategory
    {
        return $this->createQueryBuilder('sc')
            ->where('sc.slug = :slug')
            ->andWhere('sc.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve une sous-catégorie avec sa catégorie parente
     */
    public function findWithCategory(int $id): ?ServiceSubcategory
    {
        return $this->createQueryBuilder('sc')
            ->leftJoin('sc.category', 'c')
            ->addSelect('c')
            ->where('sc.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // RECHERCHE PAR CATÉGORIE
    // ============================================

    /**
     * Trouve les sous-catégories d'une catégorie
     */
    public function findByCategory(ServiceCategory $category, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->where('sc.category = :category')
            ->setParameter('category', $category)
            ->orderBy('sc.displayOrder', 'ASC')
            ->addOrderBy('sc.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les sous-catégories par ID de catégorie
     */
    public function findByCategoryId(int $categoryId, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->where('sc.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('sc.displayOrder', 'ASC')
            ->addOrderBy('sc.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les sous-catégories de plusieurs catégories
     */
    public function findByCategories(array $categories, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->where('sc.category IN (:categories)')
            ->setParameter('categories', $categories)
            ->orderBy('sc.displayOrder', 'ASC')
            ->addOrderBy('sc.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de sous-catégories par catégorie
     */
    public function countByCategory(ServiceCategory $category, bool $activeOnly = true): int
    {
        $qb = $this->createQueryBuilder('sc')
            ->select('COUNT(sc.id)')
            ->where('sc.category = :category')
            ->setParameter('category', $category);

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    // ============================================
    // RECHERCHE PAR PRESTATAIRE
    // ============================================

    /**
     * Trouve les sous-catégories d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->join('sc.prestataires', 'p')
            ->where('p = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('sc.displayOrder', 'ASC')
            ->addOrderBy('sc.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les sous-catégories disponibles pour un prestataire
     */
    public function findAvailableForPrestataire(Prestataire $prestataire): array
    {
        // Récupère les sous-catégories déjà associées au prestataire
        $existingSubcategories = $this->findByPrestataire($prestataire, false);
        $existingIds = array_map(fn($subcat) => $subcat->getId(), $existingSubcategories);

        $qb = $this->createQueryBuilder('sc')
            ->where('sc.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('sc.displayOrder', 'ASC')
            ->addOrderBy('sc.name', 'ASC');

        if (!empty($existingIds)) {
            $qb->andWhere('sc.id NOT IN (:existingIds)')
               ->setParameter('existingIds', $existingIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de prestataires par sous-catégorie
     */
    public function countPrestatairesBySubcategory(): array
    {
        return $this->createQueryBuilder('sc')
            ->select('sc.id, sc.name, COUNT(DISTINCT p.id) as prestataireCount')
            ->leftJoin('sc.prestataires', 'p')
            ->where('sc.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('sc.id')
            ->orderBy('prestataireCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un prestataire propose une sous-catégorie
     */
    public function prestataireHasSubcategory(Prestataire $prestataire, ServiceSubcategory $subcategory): bool
    {
        $count = $this->createQueryBuilder('sc')
            ->select('COUNT(sc.id)')
            ->join('sc.prestataires', 'p')
            ->where('sc = :subcategory')
            ->andWhere('p = :prestataire')
            ->setParameter('subcategory', $subcategory)
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    // ============================================
    // RECHERCHE PAR TARIF
    // ============================================

    /**
     * Trouve les sous-catégories par plage de prix
     */
    public function findByPriceRange(
        ?float $minPrice = null,
        ?float $maxPrice = null,
        bool $activeOnly = true
    ): array {
        $qb = $this->createQueryBuilder('sc')
            ->where('sc.basePrice IS NOT NULL')
            ->orderBy('sc.basePrice', 'ASC');

        if ($minPrice !== null) {
            $qb->andWhere('sc.basePrice >= :minPrice')
               ->setParameter('minPrice', $minPrice);
        }

        if ($maxPrice !== null) {
            $qb->andWhere('sc.basePrice <= :maxPrice')
               ->setParameter('maxPrice', $maxPrice);
        }

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les sous-catégories les moins chères
     */
    public function findCheapest(int $limit = 10): array
    {
        return $this->createQueryBuilder('sc')
            ->where('sc.isActive = :active')
            ->andWhere('sc.basePrice IS NOT NULL')
            ->setParameter('active', true)
            ->orderBy('sc.basePrice', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le prix moyen par catégorie
     */
    public function getAveragePriceByCategory(): array
    {
        return $this->createQueryBuilder('sc')
            ->select('c.id as categoryId, c.name as categoryName, AVG(sc.basePrice) as averagePrice')
            ->join('sc.category', 'c')
            ->where('sc.isActive = :active')
            ->andWhere('sc.basePrice IS NOT NULL')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('averagePrice', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR DURÉE
    // ============================================

    /**
     * Trouve les sous-catégories par durée estimée
     */
    public function findByDurationRange(
        ?int $minDuration = null,
        ?int $maxDuration = null,
        bool $activeOnly = true
    ): array {
        $qb = $this->createQueryBuilder('sc')
            ->where('sc.estimatedDuration IS NOT NULL')
            ->orderBy('sc.estimatedDuration', 'ASC');

        if ($minDuration !== null) {
            $qb->andWhere('sc.estimatedDuration >= :minDuration')
               ->setParameter('minDuration', $minDuration);
        }

        if ($maxDuration !== null) {
            $qb->andWhere('sc.estimatedDuration <= :maxDuration')
               ->setParameter('maxDuration', $maxDuration);
        }

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les sous-catégories rapides (moins d'une heure)
     */
    public function findQuickServices(): array
    {
        return $this->createQueryBuilder('sc')
            ->where('sc.isActive = :active')
            ->andWhere('sc.estimatedDuration < :duration')
            ->setParameter('active', true)
            ->setParameter('duration', 60) // 60 minutes
            ->orderBy('sc.estimatedDuration', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche de sous-catégories par terme
     */
    public function search(string $term, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->where('sc.name LIKE :term')
            ->orWhere('sc.description LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('sc.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche avec filtres multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->orderBy('sc.displayOrder', 'ASC');

        if (isset($criteria['active'])) {
            $qb->andWhere('sc.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        if (isset($criteria['category'])) {
            $qb->andWhere('sc.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (isset($criteria['min_price'])) {
            $qb->andWhere('sc.basePrice >= :minPrice')
               ->setParameter('minPrice', $criteria['min_price']);
        }

        if (isset($criteria['max_price'])) {
            $qb->andWhere('sc.basePrice <= :maxPrice')
               ->setParameter('maxPrice', $criteria['max_price']);
        }

        if (isset($criteria['min_duration'])) {
            $qb->andWhere('sc.estimatedDuration >= :minDuration')
               ->setParameter('minDuration', $criteria['min_duration']);
        }

        if (isset($criteria['max_duration'])) {
            $qb->andWhere('sc.estimatedDuration <= :maxDuration')
               ->setParameter('maxDuration', $criteria['max_duration']);
        }

        if (isset($criteria['requires_equipment'])) {
            $qb->andWhere('sc.requiresEquipment = :requires')
               ->setParameter('requires', $criteria['requires_equipment']);
        }

        if (isset($criteria['has_icon'])) {
            if ($criteria['has_icon']) {
                $qb->andWhere('sc.icon IS NOT NULL');
            } else {
                $qb->andWhere('sc.icon IS NULL');
            }
        }

        if (!empty($criteria['search'])) {
            $qb->andWhere('sc.name LIKE :search OR sc.description LIKE :search')
               ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Trouve les sous-catégories les plus populaires
     */
    public function findMostPopular(int $limit = 10, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->select('sc, COUNT(DISTINCT sr.id) as requestCount')
            ->leftJoin('sc.serviceRequests', 'sr')
            ->where('sc.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('sc.id')
            ->orderBy('requestCount', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('sr.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les statistiques d'une sous-catégorie
     */
    public function getSubcategoryStats(ServiceSubcategory $subcategory): array
    {
        // Nombre de prestataires
        $prestataireCount = $this->createQueryBuilder('sc')
            ->select('COUNT(DISTINCT p.id)')
            ->leftJoin('sc.prestataires', 'p')
            ->where('sc = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->getQuery()
            ->getSingleScalarResult();

        // Nombre de demandes de service
        $requestCount = $this->createQueryBuilder('sc')
            ->select('COUNT(DISTINCT sr.id)')
            ->leftJoin('sc.serviceRequests', 'sr')
            ->where('sc = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->getQuery()
            ->getSingleScalarResult();

        // Nombre de réservations
        $bookingCount = $this->createQueryBuilder('sc')
            ->select('COUNT(DISTINCT b.id)')
            ->leftJoin('sc.bookings', 'b')
            ->where('sc = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'prestataire_count' => (int) $prestataireCount,
            'request_count' => (int) $requestCount,
            'booking_count' => (int) $bookingCount,
        ];
    }

    /**
     * Obtient les sous-catégories avec le nombre de demandes
     */
    public function findWithRequestCount(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('sc')
            ->select('sc, COUNT(DISTINCT sr.id) as requestCount')
            ->leftJoin('sc.serviceRequests', 'sr')
            ->groupBy('sc.id')
            ->orderBy('requestCount', 'DESC');

        if ($activeOnly) {
            $qb->where('sc.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // VALIDATION ET VÉRIFICATIONS
    // ============================================

    /**
     * Vérifie si un slug existe déjà
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('sc')
            ->select('COUNT(sc.id)')
            ->where('sc.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('sc.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Vérifie si une sous-catégorie a des demandes de service
     */
    public function hasServiceRequests(ServiceSubcategory $subcategory): bool
    {
        return $subcategory->getServiceRequests()->count() > 0;
    }

    /**
     * Vérifie si une sous-catégorie a des réservations
     */
    public function hasBookings(ServiceSubcategory $subcategory): bool
    {
        return $subcategory->getBookings()->count() > 0;
    }

    /**
     * Vérifie si une sous-catégorie peut être supprimée
     */
    public function canBeDeleted(ServiceSubcategory $subcategory): bool
    {
        // Ne peut pas être supprimée si elle a des demandes de service
        if ($this->hasServiceRequests($subcategory)) {
            return false;
        }

        // Ne peut pas être supprimée si elle a des réservations
        if ($this->hasBookings($subcategory)) {
            return false;
        }

        return true;
    }

    // ============================================
    // OPÉRATIONS DE MASSE
    // ============================================

    /**
     * Réorganise l'ordre d'affichage des sous-catégories
     */
    public function reorderSubcategories(ServiceCategory $category, array $subcategoryIdsOrdered): void
    {
        $position = 1;
        foreach ($subcategoryIdsOrdered as $subcategoryId) {
            $this->createQueryBuilder('sc')
                ->update()
                ->set('sc.displayOrder', ':position')
                ->where('sc.id = :id')
                ->andWhere('sc.category = :category')
                ->setParameter('position', $position)
                ->setParameter('id', $subcategoryId)
                ->setParameter('category', $category)
                ->getQuery()
                ->execute();
            
            $position++;
        }
    }

    /**
     * Active ou désactive des sous-catégories en masse
     */
    public function toggleActiveStatus(array $subcategoryIds, bool $isActive): int
    {
        return $this->createQueryBuilder('sc')
            ->update()
            ->set('sc.isActive', ':active')
            ->where('sc.id IN (:ids)')
            ->setParameter('active', $isActive)
            ->setParameter('ids', $subcategoryIds)
            ->getQuery()
            ->execute();
    }

    /**
     * Met à jour les prix en masse pour une catégorie
     */
    public function updateBasePriceByCategory(ServiceCategory $category, float $percentage): int
    {
        return $this->createQueryBuilder('sc')
            ->update()
            ->set('sc.basePrice', 'sc.basePrice * :multiplier')
            ->where('sc.category = :category')
            ->andWhere('sc.basePrice IS NOT NULL')
            ->setParameter('multiplier', 1 + ($percentage / 100))
            ->setParameter('category', $category)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // COMPARAISONS
    // ============================================

    /**
     * Compare les sous-catégories d'une catégorie
     */
    public function compareByCategory(ServiceCategory $category): array
    {
        return $this->createQueryBuilder('sc')
            ->select(
                'sc.id',
                'sc.name',
                'sc.basePrice',
                'sc.estimatedDuration',
                'COUNT(DISTINCT p.id) as prestataireCount',
                'COUNT(DISTINCT sr.id) as requestCount'
            )
            ->leftJoin('sc.prestataires', 'p')
            ->leftJoin('sc.serviceRequests', 'sr')
            ->where('sc.category = :category')
            ->andWhere('sc.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->groupBy('sc.id')
            ->orderBy('sc.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // PERSISTENCE
    // ============================================

    public function save(ServiceSubcategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ServiceSubcategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}