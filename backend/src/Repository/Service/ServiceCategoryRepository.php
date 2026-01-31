<?php

namespace App\Repository\Service;

use App\Entity\Service\ServiceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité ServiceCategory
 * Gère les requêtes liées aux catégories et sous-catégories
 * 
 * @extends ServiceEntityRepository<ServiceCategory>
 */
class ServiceCategoryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceCategory::class);
    }

    // ========================================
    // RECHERCHE DE BASE
    // ========================================

    /**
     * Trouve toutes les catégories actives
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les catégories racines (niveau 0, sans parent)
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les catégories racines avec leurs enfants
     */
    public function findRootCategoriesWithChildren(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les enfants directs d'une catégorie
     */
    public function findChildrenByParent(ServiceCategory $parent): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.parent = :parent')
            ->andWhere('c.isActive = true')
            ->setParameter('parent', $parent)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les catégories d'un certain parent (avec slug)
     */
    public function findChildrenByParentSlug(string $parentSlug): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.parent', 'p')
            ->where('p.slug = :parentSlug')
            ->andWhere('c.isActive = true')
            ->setParameter('parentSlug', $parentSlug)
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ========================================
    // RECHERCHE PAR SLUG
    // ========================================

    /**
     * Trouve une catégorie par slug
     */
    public function findOneBySlug(string $slug): ?ServiceCategory
    {
        return $this->createQueryBuilder('c')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve une catégorie par son slug avec ses enfants
     */
    public function findOneBySlugWithChildren(string $slug): ?ServiceCategory
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve une catégorie par son slug avec parent et enfants
     */
    public function findOneBySlugWithRelations(string $slug): ?ServiceCategory
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.parent', 'parent')
            ->leftJoin('c.children', 'children')
            ->addSelect('parent', 'children')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ========================================
    // RECHERCHE ET FILTRES
    // ========================================

    /**
     * Recherche des catégories par nom, description ou slug
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.name LIKE :query OR c.description LIKE :query OR c.slug LIKE :query')
            ->andWhere('c.isActive = true')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('c.requestCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche générale de catégories
     */
    public function search(string $searchTerm): array
    {
        return $this->searchByName($searchTerm, 50);
    }

    /**
     * Trouve toutes les catégories par niveau
     */
    public function findByLevel(int $level): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.level = :level')
            ->andWhere('c.isActive = true')
            ->setParameter('level', $level)
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les catégories feuilles (sans enfants)
     */
    public function findLeafCategories(): array
    {
        $qb = $this->createQueryBuilder('c');
        
        return $qb
            ->leftJoin('c.children', 'children')
            ->where('c.isActive = true')
            ->andWhere($qb->expr()->isNull('children.id'))
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories avec tarifs dans une fourchette
     */
    public function findByPriceRange(float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.minHourlyRate IS NOT NULL')
            ->andWhere('c.maxHourlyRate IS NOT NULL')
            ->andWhere('c.minHourlyRate >= :minPrice')
            ->andWhere('c.maxHourlyRate <= :maxPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->orderBy('c.minHourlyRate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories par durée
     */
    public function findByDuration(int $minDuration, int $maxDuration): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.defaultDuration IS NOT NULL')
            ->andWhere('c.defaultDuration BETWEEN :minDuration AND :maxDuration')
            ->setParameter('minDuration', $minDuration)
            ->setParameter('maxDuration', $maxDuration)
            ->orderBy('c.defaultDuration', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ========================================
    // CATÉGORIES POPULAIRES ET TENDANCES
    // ========================================

    /**
     * Trouve toutes les catégories populaires
     */
    public function findPopularCategories(int $limit = 6): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isPopular = true')
            ->andWhere('c.isActive = true')
            ->orderBy('c.requestCount', 'DESC')
            ->addOrderBy('c.position', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories les plus demandées
     */
    public function findMostRequested(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.isActive = true')
            ->andWhere('c.requestCount > 0')
            ->orderBy('c.requestCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories les plus récemment créées
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories les plus récemment modifiées
     */
    public function findRecentlyUpdated(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->orderBy('c.updatedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories les plus populaires (avec le plus de prestataires)
     */
    public function findMostPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(DISTINCT sr.id) as HIDDEN request_count')
            ->leftJoin('c.serviceRequests', 'sr')
            ->where('c.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('request_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ========================================
    // MENU ET NAVIGATION
    // ========================================

    /**
     * Trouve les catégories visibles dans le menu
     */
    public function findMenuCategories(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = true')
            ->andWhere('c.isVisibleInMenu = true')
            ->orderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve toutes les catégories avec leurs enfants (arborescence)
     */
    public function findAllWithChildren(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = true')
            ->orderBy('c.position', 'ASC')
            ->addOrderBy('children.position', 'ASC')
            ->getQuery()
            ->getResult();
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

    // ========================================
    // ARBORESCENCE
    // ========================================

    /**
     * Construit l'arborescence complète des catégories
     */
    public function findCategoryTree(): array
    {
        $categories = $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->addSelect('children')
            ->where('c.isActive = true')
            ->orderBy('c.level', 'ASC')
            ->addOrderBy('c.position', 'ASC')
            ->getQuery()
            ->getResult();

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
     * Trouve toutes les catégories descendantes (enfants, petits-enfants, etc.)
     */
    public function findAllDescendants(ServiceCategory $category): array
    {
        $descendants = [];
        $children = $this->findChildrenByParent($category);

        foreach ($children as $child) {
            $descendants[] = $child;
            $descendants = array_merge(
                $descendants, 
                $this->findAllDescendants($child)
            );
        }

        return $descendants;
    }

    /**
     * Trouve la profondeur maximale de l'arborescence
     */
    public function getMaxDepth(): int
    {
        $mainCategories = $this->findRootCategories();
        $maxDepth = 0;

        foreach ($mainCategories as $category) {
            $depth = $this->calculateDepth($category);
            if ($depth > $maxDepth) {
                $maxDepth = $depth;
            }
        }

        return $maxDepth;
    }

    /**
     * Calcule la profondeur d'une catégorie dans l'arborescence
     */
    private function calculateDepth(ServiceCategory $category, int $currentDepth = 0): int
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

    // ========================================
    // CATÉGORIES SIMILAIRES
    // ========================================

    /**
     * Trouve les catégories similaires (même parent)
     */
    public function findSimilarCategories(ServiceCategory $category, int $limit = 4): array
    {
        $qb = $this->createQueryBuilder('c');
        
        if ($category->getParent()) {
            $qb->where('c.parent = :parent')
                ->setParameter('parent', $category->getParent());
        } else {
            $qb->where('c.parent IS NULL');
        }
        
        return $qb
            ->andWhere('c.id != :id')
            ->andWhere('c.isActive = true')
            ->setParameter('id', $category->getId())
            ->orderBy('c.requestCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories similaires (par nom)
     */
    public function findSimilarByName(ServiceCategory $category, int $limit = 5): array
    {
        $words = explode(' ', $category->getName());
        
        if (empty($words)) {
            return [];
        }

        $qb = $this->createQueryBuilder('c')
            ->where('c.id != :id')
            ->andWhere('c.isActive = :active')
            ->setParameter('id', $category->getId())
            ->setParameter('active', true);

        $orX = $qb->expr()->orX();
        foreach ($words as $index => $word) {
            if (strlen($word) > 3) {
                $orX->add($qb->expr()->like('c.name', ':word' . $index));
                $qb->setParameter('word' . $index, '%' . $word . '%');
            }
        }

        if ($orX->count() === 0) {
            return [];
        }

        $qb->andWhere($orX);

        return $qb->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ========================================
    // COMPTEURS ET STATISTIQUES
    // ========================================

    /**
     * Compte le nombre total de catégories actives
     */
    public function countActiveCategories(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de catégories racines
     */
    public function countRootCategories(): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.parent IS NULL')
            ->andWhere('c.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les catégories avec le plus d'enfants
     */
    public function findCategoriesWithMostChildren(int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->select('c', 'COUNT(children.id) as HIDDEN childrenCount')
            ->leftJoin('c.children', 'children')
            ->where('c.isActive = true')
            ->groupBy('c.id')
            ->orderBy('childrenCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Met à jour le compteur de demandes pour une catégorie
     */
    public function incrementRequestCount(ServiceCategory $category): void
    {
        $this->createQueryBuilder('c')
            ->update()
            ->set('c.requestCount', 'c.requestCount + 1')
            ->where('c.id = :id')
            ->setParameter('id', $category->getId())
            ->getQuery()
            ->execute();
    }

    /**
     * Compte le nombre de demandes de service par catégorie
     */
    public function countServiceRequests(ServiceCategory $category): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(sr.id)')
            ->leftJoin('c.serviceRequests', 'sr')
            ->where('c.id = :categoryId')
            ->setParameter('categoryId', $category->getId())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte le nombre de réservations par catégorie
     */
    public function countBookings(ServiceCategory $category): int
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT COUNT(DISTINCT b.id)
            FROM bookings b
            INNER JOIN service_requests sr ON b.service_request_id = sr.id
            WHERE sr.category_id = :categoryId
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['categoryId' => $category->getId()]);

        return (int) $result->fetchOne();
    }

    /**
     * Obtient les statistiques des catégories
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('c');
        
        $result = $qb
            ->select(
                'COUNT(c.id) as totalCategories',
                'COUNT(CASE WHEN c.parent IS NULL THEN 1 END) as rootCategories',
                'COUNT(CASE WHEN c.isActive = true THEN 1 END) as activeCategories',
                'COUNT(CASE WHEN c.isPopular = true THEN 1 END) as popularCategories',
                'SUM(c.requestCount) as totalRequests',
                'AVG(c.requestCount) as avgRequestsPerCategory',
                'COUNT(CASE WHEN c.icon IS NOT NULL THEN 1 END) as withIcon',
                'COUNT(CASE WHEN c.image IS NOT NULL THEN 1 END) as withImage'
            )
            ->getQuery()
            ->getSingleResult();

        return [
            'totalCategories' => (int) $result['totalCategories'],
            'rootCategories' => (int) $result['rootCategories'],
            'activeCategories' => (int) $result['activeCategories'],
            'popularCategories' => (int) $result['popularCategories'],
            'subCategories' => (int) $result['totalCategories'] - (int) $result['rootCategories'],
            'totalRequests' => (int) $result['totalRequests'],
            'avgRequestsPerCategory' => round((float) $result['avgRequestsPerCategory'], 2),
            'withIcon' => (int) $result['withIcon'],
            'withImage' => (int) $result['withImage'],
        ];
    }

    /**
     * Trouve les catégories avec le plus de réservations
     */
    public function findMostBooked(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                sc.id,
                sc.name,
                COUNT(DISTINCT b.id) as booking_count
            FROM service_categories sc
            LEFT JOIN service_requests sr ON sr.category_id = sc.id
            LEFT JOIN bookings b ON b.service_request_id = sr.id
            WHERE sc.is_active = 1
            GROUP BY sc.id
            ORDER BY booking_count DESC
            LIMIT :limit
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['limit' => $limit]);

        $categoryIds = array_column($result->fetchAllAssociative(), 'id');

        if (empty($categoryIds)) {
            return [];
        }

        return $this->createQueryBuilder('c')
            ->where('c.id IN (:ids)')
            ->setParameter('ids', $categoryIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition des réservations par catégorie
     */
    public function getBookingDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                sc.id,
                sc.name,
                COUNT(DISTINCT b.id) as booking_count,
                COUNT(DISTINCT CASE WHEN b.status = "completed" THEN b.id END) as completed_count,
                SUM(CASE WHEN b.status = "completed" THEN b.amount ELSE 0 END) as total_revenue
            FROM service_categories sc
            LEFT JOIN service_requests sr ON sr.category_id = sc.id
            LEFT JOIN bookings b ON b.service_request_id = sr.id
            WHERE sc.is_active = 1
            GROUP BY sc.id
            ORDER BY booking_count DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Catégories avec le meilleur taux de conversion (demandes -> réservations)
     */
    public function findBestConversionRate(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                sc.id,
                sc.name,
                COUNT(DISTINCT sr.id) as request_count,
                COUNT(DISTINCT b.id) as booking_count,
                CASE 
                    WHEN COUNT(DISTINCT sr.id) > 0 
                    THEN (COUNT(DISTINCT b.id) * 100.0 / COUNT(DISTINCT sr.id))
                    ELSE 0 
                END as conversion_rate
            FROM service_categories sc
            LEFT JOIN service_requests sr ON sr.category_id = sc.id
            LEFT JOIN bookings b ON b.service_request_id = sr.id
            WHERE sc.is_active = 1
            GROUP BY sc.id
            HAVING request_count > 10
            ORDER BY conversion_rate DESC
            LIMIT :limit
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['limit' => $limit]);

        return $result->fetchAllAssociative();
    }

    /**
     * Revenu moyen par catégorie
     */
    public function getAverageRevenue(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                sc.id,
                sc.name,
                AVG(b.amount) as average_revenue,
                COUNT(b.id) as booking_count
            FROM service_categories sc
            INNER JOIN service_requests sr ON sr.category_id = sc.id
            INNER JOIN bookings b ON b.service_request_id = sr.id
            WHERE sc.is_active = 1 AND b.status = "completed"
            GROUP BY sc.id
            ORDER BY average_revenue DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Catégories par saison / mois (tendances saisonnières)
     */
    public function getSeasonalTrends(int $year): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                sc.id,
                sc.name,
                MONTH(b.scheduled_date) as month,
                COUNT(b.id) as booking_count
            FROM service_categories sc
            INNER JOIN service_requests sr ON sr.category_id = sc.id
            INNER JOIN bookings b ON b.service_request_id = sr.id
            WHERE YEAR(b.scheduled_date) = :year
            GROUP BY sc.id, month
            ORDER BY sc.name, month
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['year' => $year]);

        return $result->fetchAllAssociative();
    }

    // ========================================
    // GESTION DES SLUGS
    // ========================================

    /**
     * Vérifie si un slug existe déjà
     */
    public function slugExists(string $slug, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.slug = :slug')
            ->setParameter('slug', $slug);

        if ($excludeId) {
            $qb->andWhere('c.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Génère un slug unique
     */
    public function generateUniqueSlug(string $name, ?int $excludeId = null): string
    {
        $slug = $this->slugify($name);
        $originalSlug = $slug;
        $counter = 1;

        while ($this->slugExists($slug, $excludeId)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    /**
     * Convertit une chaîne en slug
     */
    private function slugify(string $text): string
    {
        $text = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        $text = strtolower($text);
        $text = preg_replace('/[^a-z0-9]+/', '-', $text);
        $text = trim($text, '-');
        
        return $text;
    }

    // ========================================
    // CATÉGORIES MANQUANTES OU INCOMPLÈTES
    // ========================================

    /**
     * Trouve les catégories sans enfants
     */
    public function findWithoutChildren(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.children', 'children')
            ->where('children.id IS NULL')
            ->andWhere('c.isActive = true')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories avec icône manquante
     */
    public function findWithoutIcon(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.icon IS NULL OR c.icon = :empty')
            ->setParameter('empty', '')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les catégories avec image manquante
     */
    public function findWithoutImage(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.image IS NULL OR c.image = :empty')
            ->setParameter('empty', '')
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ========================================
    // EXPORT
    // ========================================

    /**
     * Exporte toutes les catégories au format tableau
     */
    public function exportToArray(): array
    {
        $categories = $this->findAll();
        
        return array_map(function(ServiceCategory $category) {
            return $category->toArray();
        }, $categories);
    }

    /**
     * Exporte l'arborescence au format JSON
     */
    public function exportTreeToArray(): array
    {
        $rootCategories = $this->findRootCategoriesWithChildren();
        
        return array_map(function(ServiceCategory $category) {
            return $category->toTree();
        }, $rootCategories);
    }

    /**
     * Exporte les catégories en CSV
     */
    public function exportToCsv(array $categories): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Nom',
            'Slug',
            'Description',
            'Parent',
            'Position',
            'Icône',
            'Actif',
            'Nb Demandes',
            'Créé le'
        ]);

        // Données
        foreach ($categories as $category) {
            fputcsv($handle, [
                $category->getId(),
                $category->getName(),
                $category->getSlug(),
                $category->getDescription(),
                $category->getParent() ? $category->getParent()->getName() : 'Aucun',
                $category->getPosition(),
                $category->getIcon() ?? 'Non définie',
                $category->isActive() ? 'Oui' : 'Non',
                $category->getRequestCount(),
                $category->getCreatedAt()->format('d/m/Y H:i')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    // ========================================
    // UTILITAIRES
    // ========================================

    /**
     * Réorganise les positions des catégories
     */
    public function reorderPositions(array $categoryIds): void
    {
        $position = 1;
        foreach ($categoryIds as $categoryId) {
            $category = $this->find($categoryId);
            if ($category) {
                $category->setPosition($position);
                $position++;
            }
        }

        $this->getEntityManager()->flush();
    }

    public function save(ServiceCategory $category, bool $flush = false): void
    {
        $this->getEntityManager()->persist($category);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(ServiceCategory $category, bool $flush = false): void
    {
        $this->getEntityManager()->remove($category);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}