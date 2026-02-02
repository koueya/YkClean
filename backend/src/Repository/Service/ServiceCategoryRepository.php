<?php
// src/Repository/Service/ServiceCategoryRepository.php

namespace App\Repository\Service;

use App\Entity\Service\ServiceCategory;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité ServiceCategory
 * Gère les requêtes liées aux catégories avec support hiérarchique
 * 
 * @extends ServiceEntityRepository<ServiceCategory>
 */
class ServiceCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceCategory::class);
    }

    // ============================================
    // RECHERCHE DE BASE
    // ============================================

    /**
     * Trouve toutes les catégories actives
     */
    public function findAllActive(bool $orderByPosition = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true);

        if ($orderByPosition) {
            $qb->orderBy('c.position', 'ASC')
               ->addOrderBy('c.name', 'ASC');
        } else {
            $qb->orderBy('c.name', 'ASC');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve une catégorie par son slug
     */
    public function findBySlug(string $slug): ?ServiceCategory
    {
        return $this->createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->andWhere('c.isActive = :active')
            ->setParameter('slug', $slug)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les catégories racines (niveau 0, sans parent)
     */
    public function findRootCategories(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les catégories racines avec leurs enfants
     */
    public function findRootCategoriesWithChildren(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->where('c.parent IS NULL')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('children.position', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->andWhere('children.isActive = :active OR children.id IS NULL')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE PAR HIÉRARCHIE
    // ============================================

    /**
     * Trouve les enfants directs d'une catégorie
     */
    public function findChildrenByParent(ServiceCategory $parent, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->setParameter('parent', $parent)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve toutes les catégories descendantes (enfants, petits-enfants, etc.)
     */
    public function findAllDescendants(ServiceCategory $category, bool $activeOnly = true): array
    {
        $descendants = [];
        $children = $this->findChildrenByParent($category, $activeOnly);

        foreach ($children as $child) {
            $descendants[] = $child;
            $descendants = array_merge(
                $descendants, 
                $this->findAllDescendants($child, $activeOnly)
            );
        }

        return $descendants;
    }

    /**
     * Trouve le chemin complet d'une catégorie (breadcrumb)
     */
    public function getPath(ServiceCategory $category): array
    {
        $path = [$category];
        $current = $category;

        while ($current->getParent()) {
            $parent = $current->getParent();
            array_unshift($path, $parent);
            $current = $parent;
        }

        return $path;
    }

    /**
     * Trouve toutes les catégories par niveau
     */
    public function findByLevel(int $level, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.level = :level')
            ->setParameter('level', $level)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // ARBORESCENCE
    // ============================================

    /**
     * Construit l'arborescence complète des catégories
     */
    public function findCategoryTree(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->addOrderBy('children.position', 'ASC');

        if ($activeOnly) {
            $qb->where('c.isActive = :active')
               ->andWhere('children.isActive = :active OR children.id IS NULL')
               ->setParameter('active', true);
        }

        $categories = $qb->getQuery()->getResult();

        return $this->buildTree($categories);
    }

    /**
     * Construit une structure d'arbre à partir d'une liste plate
     */
    private function buildTree(array $categories, ?ServiceCategory $parent = null): array
    {
        $branch = [];

        foreach ($categories as $category) {
            if ($category->getParent() === $parent) {
                $children = $this->buildTree($categories, $category);
                
                $node = [
                    'category' => $category,
                    'children' => $children,
                ];
                
                $branch[] = $node;
            }
        }

        return $branch;
    }

    /**
     * Trouve la profondeur maximale de l'arborescence
     */
    public function getMaxDepth(): int
    {
        $result = $this->createQueryBuilder('c')
            ->select('MAX(c.level)')
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result : 0;
    }

    /**
     * Calcule la profondeur d'une catégorie dans l'arborescence
     */
    public function calculateDepth(ServiceCategory $category, int $currentDepth = 0): int
    {
        $children = $this->findChildrenByParent($category);

        if (empty($children)) {
            return $currentDepth;
        }

        $maxChildDepth = $currentDepth;

        foreach ($children as $child) {
            $childDepth = $this->calculateDepth($child, $currentDepth + 1);
            if ($childDepth > $maxChildDepth) {
                $maxChildDepth = $childDepth;
            }
        }

        return $maxChildDepth;
    }

    // ============================================
    // RECHERCHE AVEC SOUS-CATÉGORIES
    // ============================================

    /**
     * Trouve une catégorie avec ses sous-catégories
     */
    public function findWithSubcategories(int $id): ?ServiceCategory
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.subcategories', 'sc')
            ->addSelect('sc')
            ->where('c.id = :id')
            ->andWhere('c.isActive = :active')
            ->setParameter('id', $id)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les catégories avec leurs sous-catégories
     */
    public function findAllWithSubcategories(bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.subcategories', 'sc')
            ->addSelect('sc')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('sc.displayOrder', 'ASC');

        if ($activeOnly) {
            $qb->where('c.isActive = :active')
               ->andWhere('sc.isActive = :active OR sc.id IS NULL')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE PAR PRESTATAIRE
    // ============================================

    /**
     * Trouve les catégories d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.prestataires', 'p')
            ->where('p = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('c.position', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les catégories disponibles pour un prestataire dans sa zone
     */
    public function findAvailableForPrestataire(Prestataire $prestataire): array
    {
        // Récupère les catégories déjà associées au prestataire
        $existingCategories = $this->findByPrestataire($prestataire, false);
        $existingIds = array_map(fn($cat) => $cat->getId(), $existingCategories);

        $qb = $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC');

        if (!empty($existingIds)) {
            $qb->andWhere('c.id NOT IN (:existingIds)')
               ->setParameter('existingIds', $existingIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte le nombre de prestataires par catégorie
     */
    public function countPrestatairesByCategory(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.id, c.name, COUNT(DISTINCT p.id) as prestataireCount')
            ->leftJoin('c.prestataires', 'p')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('prestataireCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE AVEC STATISTIQUES
    // ============================================

    /**
     * Trouve les catégories avec le nombre de demandes de service
     */
    public function findWithServiceRequestCount(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c, COUNT(DISTINCT sr.id) as requestCount')
            ->leftJoin('c.serviceRequests', 'sr')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('requestCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories les plus populaires
     */
    public function findMostPopular(int $limit = 5, ?\DateTimeInterface $since = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->select('c, COUNT(DISTINCT sr.id) as requestCount')
            ->leftJoin('c.serviceRequests', 'sr')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('requestCount', 'DESC')
            ->setMaxResults($limit);

        if ($since) {
            $qb->andWhere('sr.createdAt >= :since')
               ->setParameter('since', $since);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // VALIDATION ET VÉRIFICATIONS
    // ============================================

    /**
     * Vérifie si une catégorie a des enfants
     */
    public function hasChildren(ServiceCategory $category): bool
    {
        $count = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.parent = :parent')
            ->setParameter('parent', $category)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Vérifie si une catégorie a des sous-catégories
     */
    public function hasSubcategories(ServiceCategory $category): bool
    {
        return $category->getSubcategories()->count() > 0;
    }

    /**
     * Vérifie si un slug existe déjà
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId !== null) {
            $qb->andWhere('c.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        $count = $qb->getQuery()->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Vérifie si une catégorie peut être supprimée
     */
    public function canBeDeleted(ServiceCategory $category): bool
    {
        // Ne peut pas être supprimée si elle a des enfants
        if ($this->hasChildren($category)) {
            return false;
        }

        // Ne peut pas être supprimée si elle a des sous-catégories
        if ($this->hasSubcategories($category)) {
            return false;
        }

        // Ne peut pas être supprimée si elle a des demandes de service associées
        if ($category->getServiceRequests()->count() > 0) {
            return false;
        }

        return true;
    }

    // ============================================
    // OPÉRATIONS DE MASSE
    // ============================================

    /**
     * Réorganise l'ordre d'affichage des catégories
     */
    public function reorderCategories(?ServiceCategory $parent, array $categoryIdsOrdered): void
    {
        $position = 1;
        foreach ($categoryIdsOrdered as $categoryId) {
            $qb = $this->createQueryBuilder('c')
                ->update()
                ->set('c.position', ':position')
                ->where('c.id = :id')
                ->setParameter('position', $position)
                ->setParameter('id', $categoryId);

            if ($parent !== null) {
                $qb->andWhere('c.parent = :parent')
                   ->setParameter('parent', $parent);
            } else {
                $qb->andWhere('c.parent IS NULL');
            }

            $qb->getQuery()->execute();
            
            $position++;
        }
    }

    /**
     * Active ou désactive des catégories en masse
     */
    public function toggleActiveStatus(array $categoryIds, bool $isActive): int
    {
        return $this->createQueryBuilder('c')
            ->update()
            ->set('c.isActive', ':active')
            ->where('c.id IN (:ids)')
            ->setParameter('active', $isActive)
            ->setParameter('ids', $categoryIds)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche de catégories par terme
     */
    public function search(string $term, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('c')
            ->where('c.name LIKE :term')
            ->orWhere('c.description LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('c.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les catégories par critères multiples
     */
    public function findByCriteria(array $criteria): array
    {
        $qb = $this->createQueryBuilder('c')
            ->orderBy('c.position', 'ASC');

        if (isset($criteria['active'])) {
            $qb->andWhere('c.isActive = :active')
               ->setParameter('active', $criteria['active']);
        }

        if (isset($criteria['parent'])) {
            if ($criteria['parent'] === null) {
                $qb->andWhere('c.parent IS NULL');
            } else {
                $qb->andWhere('c.parent = :parent')
                   ->setParameter('parent', $criteria['parent']);
            }
        }

        if (isset($criteria['level'])) {
            $qb->andWhere('c.level = :level')
               ->setParameter('level', $criteria['level']);
        }

        if (isset($criteria['has_icon'])) {
            if ($criteria['has_icon']) {
                $qb->andWhere('c.icon IS NOT NULL');
            } else {
                $qb->andWhere('c.icon IS NULL');
            }
        }

        if (!empty($criteria['search'])) {
            $qb->andWhere('c.name LIKE :search OR c.description LIKE :search')
               ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // PERSISTENCE
    // ============================================

    public function save(ServiceCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ServiceCategory $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}