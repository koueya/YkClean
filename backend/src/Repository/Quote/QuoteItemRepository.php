<?php
// src/Repository/Quote/QuoteItemRepository.php

namespace App\Repository\Quote;

use App\Entity\Quote\QuoteItem;
use App\Entity\Quote\Quote;
use App\Entity\Service\ServiceSubcategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuoteItem>
 */
class QuoteItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuoteItem::class);
    }

    // ============================================
    // RECHERCHE PAR DEVIS
    // ============================================

    /**
     * Trouve tous les items d'un devis
     */
    public function findByQuote(Quote $quote): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.quote = :quote')
            ->setParameter('quote', $quote)
            ->orderBy('qi.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les items obligatoires d'un devis
     */
    public function findMandatoryByQuote(Quote $quote): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.quote = :quote')
            ->andWhere('qi.isOptional = :optional')
            ->setParameter('quote', $quote)
            ->setParameter('optional', false)
            ->orderBy('qi.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les items optionnels d'un devis
     */
    public function findOptionalByQuote(Quote $quote): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.quote = :quote')
            ->andWhere('qi.isOptional = :optional')
            ->setParameter('quote', $quote)
            ->setParameter('optional', true)
            ->orderBy('qi.displayOrder', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR SOUS-CATÉGORIE
    // ============================================

    /**
     * Trouve les items par sous-catégorie de service
     */
    public function findBySubcategory(ServiceSubcategory $subcategory): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.subcategory = :subcategory')
            ->setParameter('subcategory', $subcategory)
            ->orderBy('qi.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // CALCULS ET STATISTIQUES
    // ============================================

    /**
     * Calcule le total d'un devis (items obligatoires uniquement)
     */
    public function calculateQuoteTotal(Quote $quote, bool $includeOptional = false): string
    {
        $qb = $this->createQueryBuilder('qi')
            ->select('SUM(qi.totalPrice)')
            ->andWhere('qi.quote = :quote')
            ->setParameter('quote', $quote);

        if (!$includeOptional) {
            $qb->andWhere('qi.isOptional = :optional')
               ->setParameter('optional', false);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        
        return $result !== null ? (string) round((float) $result, 2) : '0.00';
    }

    /**
     * Calcule la durée totale estimée d'un devis
     */
    public function calculateTotalDuration(Quote $quote, bool $includeOptional = false): int
    {
        $qb = $this->createQueryBuilder('qi')
            ->select('SUM(qi.estimatedDuration * qi.quantity)')
            ->andWhere('qi.quote = :quote')
            ->andWhere('qi.estimatedDuration IS NOT NULL')
            ->setParameter('quote', $quote);

        if (!$includeOptional) {
            $qb->andWhere('qi.isOptional = :optional')
               ->setParameter('optional', false);
        }

        $result = $qb->getQuery()->getSingleScalarResult();
        
        return $result !== null ? (int) $result : 0;
    }

    /**
     * Compte le nombre d'items dans un devis
     */
    public function countByQuote(Quote $quote, ?bool $isOptional = null): int
    {
        $qb = $this->createQueryBuilder('qi')
            ->select('COUNT(qi.id)')
            ->andWhere('qi.quote = :quote')
            ->setParameter('quote', $quote);

        if ($isOptional !== null) {
            $qb->andWhere('qi.isOptional = :optional')
               ->setParameter('optional', $isOptional);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Trouve le dernier ordre d'affichage pour un devis
     */
    public function findMaxDisplayOrder(Quote $quote): int
    {
        $result = $this->createQueryBuilder('qi')
            ->select('MAX(qi.displayOrder)')
            ->andWhere('qi.quote = :quote')
            ->setParameter('quote', $quote)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (int) $result : 0;
    }

    /**
     * Trouve un item par sa référence interne
     */
    public function findByInternalReference(Quote $quote, string $reference): ?QuoteItem
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.quote = :quote')
            ->andWhere('qi.internalReference = :reference')
            ->setParameter('quote', $quote)
            ->setParameter('reference', $reference)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les items avec un prix unitaire dans une fourchette
     */
    public function findByPriceRange(Quote $quote, float $minPrice, float $maxPrice): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.quote = :quote')
            ->andWhere('CAST(qi.unitPrice AS DECIMAL(10,2)) >= :minPrice')
            ->andWhere('CAST(qi.unitPrice AS DECIMAL(10,2)) <= :maxPrice')
            ->setParameter('quote', $quote)
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->orderBy('qi.unitPrice', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // OPÉRATIONS DE MASSE
    // ============================================

    /**
     * Réorganise l'ordre d'affichage des items
     */
    public function reorderItems(Quote $quote, array $itemIdsOrdered): void
    {
        $position = 1;
        foreach ($itemIdsOrdered as $itemId) {
            $this->createQueryBuilder('qi')
                ->update()
                ->set('qi.displayOrder', ':order')
                ->andWhere('qi.id = :id')
                ->andWhere('qi.quote = :quote')
                ->setParameter('order', $position)
                ->setParameter('id', $itemId)
                ->setParameter('quote', $quote)
                ->getQuery()
                ->execute();
            
            $position++;
        }
    }

    /**
     * Supprime tous les items optionnels d'un devis
     */
    public function deleteOptionalItems(Quote $quote): int
    {
        return $this->createQueryBuilder('qi')
            ->delete()
            ->andWhere('qi.quote = :quote')
            ->andWhere('qi.isOptional = :optional')
            ->setParameter('quote', $quote)
            ->setParameter('optional', true)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // STATISTIQUES AVANCÉES
    // ============================================

    /**
     * Retourne le détail des montants par sous-catégorie
     */
    public function getAmountsBySubcategory(Quote $quote): array
    {
        return $this->createQueryBuilder('qi')
            ->select('s.name as subcategoryName, SUM(qi.totalPrice) as total')
            ->leftJoin('qi.subcategory', 's')
            ->andWhere('qi.quote = :quote')
            ->andWhere('qi.isOptional = :optional')
            ->setParameter('quote', $quote)
            ->setParameter('optional', false)
            ->groupBy('s.id')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne les items avec le prix total le plus élevé
     */
    public function findMostExpensiveItems(Quote $quote, int $limit = 5): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.quote = :quote')
            ->setParameter('quote', $quote)
            ->orderBy('CAST(qi.totalPrice AS DECIMAL(10,2))', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un devis contient des items optionnels
     */
    public function hasOptionalItems(Quote $quote): bool
    {
        $count = $this->createQueryBuilder('qi')
            ->select('COUNT(qi.id)')
            ->andWhere('qi.quote = :quote')
            ->andWhere('qi.isOptional = :optional')
            ->setParameter('quote', $quote)
            ->setParameter('optional', true)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    // ============================================
    // PERSISTENCE
    // ============================================

    public function save(QuoteItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(QuoteItem $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }
}