<?php
// src/Repository/ServiceTypeRepository.php

namespace App\Repository;

use App\Entity\Service\ServiceType;
use App\Entity\Service\ServiceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceType>
 */
class ServiceTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceType::class);
    }

    /**
     * Trouve tous les types de service actifs
     */
    public function findActive(): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('st.displayOrder', 'ASC')
            ->addOrderBy('st.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de service par catégorie
     */
    public function findByCategory(ServiceCategory $category, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('st')
            ->andWhere('st.category = :category')
            ->setParameter('category', $category)
            ->orderBy('st.displayOrder', 'ASC')
            ->addOrderBy('st.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('st.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les types de service par catégorie ID
     */
    public function findByCategoryId(int $categoryId, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('st')
            ->andWhere('st.category = :categoryId')
            ->setParameter('categoryId', $categoryId)
            ->orderBy('st.displayOrder', 'ASC')
            ->addOrderBy('st.name', 'ASC');

        if ($activeOnly) {
            $qb->andWhere('st.isActive = :active')
               ->setParameter('active', true);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les types de service qui nécessitent un équipement
     */
    public function findRequiringEquipment(): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.requiresEquipment = :requires')
            ->andWhere('st.isActive = :active')
            ->setParameter('requires', true)
            ->setParameter('active', true)
            ->orderBy('st.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de service par plage de prix
     */
    public function findByPriceRange(string $minPrice, string $maxPrice): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.basePrice >= :minPrice')
            ->andWhere('st.basePrice <= :maxPrice')
            ->andWhere('st.basePrice IS NOT NULL')
            ->andWhere('st.isActive = :active')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->setParameter('active', true)
            ->orderBy('st.basePrice', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de service les plus populaires
     */
    public function findMostPopular(int $limit = 10): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('st.popularityScore', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de types de service par terme
     */
    public function search(string $searchTerm): array
    {
        return $this->createQueryBuilder('st')
            ->leftJoin('st.category', 'c')
            ->andWhere('st.name LIKE :term OR st.description LIKE :term OR c.name LIKE :term')
            ->andWhere('st.isActive = :active')
            ->setParameter('term', '%' . $searchTerm . '%')
            ->setParameter('active', true)
            ->orderBy('st.popularityScore', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de service par durée estimée
     */
    public function findByDurationRange(int $minDuration, int $maxDuration): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.estimatedDuration >= :minDuration')
            ->andWhere('st.estimatedDuration <= :maxDuration')
            ->andWhere('st.estimatedDuration IS NOT NULL')
            ->andWhere('st.isActive = :active')
            ->setParameter('minDuration', $minDuration)
            ->setParameter('maxDuration', $maxDuration)
            ->setParameter('active', true)
            ->orderBy('st.estimatedDuration', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les types de service par catégorie
     */
    public function countByCategory(ServiceCategory $category): int
    {
        return $this->createQueryBuilder('st')
            ->select('COUNT(st.id)')
            ->andWhere('st.category = :category')
            ->andWhere('st.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les types de service avec options additionnelles
     */
    public function findWithAdditionalOptions(): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.additionalOptions IS NOT NULL')
            ->andWhere('st.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('st.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des types de service
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('st');

        return [
            'total' => $qb->select('COUNT(st.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'active' => $qb->select('COUNT(st.id)')
                ->andWhere('st.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'inactive' => $qb->select('COUNT(st.id)')
                ->andWhere('st.isActive = :active')
                ->setParameter('active', false)
                ->getQuery()
                ->getSingleScalarResult(),

            'with_base_price' => $qb->select('COUNT(st.id)')
                ->andWhere('st.basePrice IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult(),

            'requiring_equipment' => $qb->select('COUNT(st.id)')
                ->andWhere('st.requiresEquipment = :requires')
                ->setParameter('requires', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'with_options' => $qb->select('COUNT(st.id)')
                ->andWhere('st.additionalOptions IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult(),

            'average_price' => $qb->select('AVG(st.basePrice)')
                ->andWhere('st.basePrice IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_duration' => $qb->select('AVG(st.estimatedDuration)')
                ->andWhere('st.estimatedDuration IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,

            'total_popularity' => $qb->select('SUM(st.popularityScore)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,
        ];
    }

    /**
     * Trouve les types de service récemment ajoutés
     */
    public function findRecent(int $limit = 10): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('st.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les types de service par unité de mesure
     */
    public function findByUnit(string $unit): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.unit = :unit')
            ->andWhere('st.isActive = :active')
            ->setParameter('unit', $unit)
            ->setParameter('active', true)
            ->orderBy('st.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recommandations de types de service basées sur la popularité et la catégorie
     */
    public function findRecommendations(ServiceCategory $category, int $limit = 5): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.category = :category')
            ->andWhere('st.isActive = :active')
            ->setParameter('category', $category)
            ->setParameter('active', true)
            ->orderBy('st.popularityScore', 'DESC')
            ->addOrderBy('st.basePrice', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prix moyen par catégorie
     */
    public function getAveragePriceByCategory(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                c.name as category_name,
                c.id as category_id,
                AVG(st.base_price) as average_price,
                COUNT(st.id) as count
            FROM service_types st
            INNER JOIN service_categories c ON st.category_id = c.id
            WHERE st.base_price IS NOT NULL
            AND st.is_active = 1
            GROUP BY c.id, c.name
            ORDER BY average_price DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Types de service sans prix défini
     */
    public function findWithoutBasePrice(): array
    {
        return $this->createQueryBuilder('st')
            ->andWhere('st.basePrice IS NULL')
            ->andWhere('st.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('st.category', 'ASC')
            ->addOrderBy('st.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}