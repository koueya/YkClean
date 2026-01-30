<?php
// src/Repository/QuoteItemRepository.php

namespace App\Repository;

use App\Entity\QuoteItem;
use App\Entity\Quote;
use App\Entity\ServiceType;
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

    /**
     * Trouve les items d'un devis
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

    /**
     * Trouve les items par type de service
     */
    public function findByServiceType(ServiceType $serviceType): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.serviceType = :serviceType')
            ->setParameter('serviceType', $serviceType)
            ->orderBy('qi.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Calcule le total d'un devis
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

        return $qb->getQuery()->getSingleScalarResult() ?? '0.00';
    }

    /**
     * Calcule la durée totale d'un devis
     */
    public function calculateQuoteDuration(Quote $quote, bool $includeOptional = false): int
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

        return (int)($qb->getQuery()->getSingleScalarResult() ?? 0);
    }

    /**
     * Compte les items d'un devis
     */
    public function countByQuote(Quote $quote, ?bool $optionalOnly = null): int
    {
        $qb = $this->createQueryBuilder('qi')
            ->select('COUNT(qi.id)')
            ->andWhere('qi.quote = :quote')
            ->setParameter('quote', $quote);

        if ($optionalOnly !== null) {
            $qb->andWhere('qi.isOptional = :optional')
               ->setParameter('optional', $optionalOnly);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Trouve les items avec options
     */
    public function findWithOptions(): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.options IS NOT NULL')
            ->orderBy('qi.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des items de devis
     */
    public function getItemStatistics(): array
    {
        $qb = $this->createQueryBuilder('qi');

        return [
            'total_items' => $qb->select('COUNT(qi.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'mandatory_items' => $qb->select('COUNT(qi.id)')
                ->andWhere('qi.isOptional = :optional')
                ->setParameter('optional', false)
                ->getQuery()
                ->getSingleScalarResult(),

            'optional_items' => $qb->select('COUNT(qi.id)')
                ->andWhere('qi.isOptional = :optional')
                ->setParameter('optional', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'average_unit_price' => $qb->select('AVG(qi.unitPrice)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_total_price' => $qb->select('AVG(qi.totalPrice)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'average_quantity' => $qb->select('AVG(qi.quantity)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,

            'total_value' => $qb->select('SUM(qi.totalPrice)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Items les plus utilisés
     */
    public function findMostUsedItems(int $limit = 10): array
    {
        return $this->createQueryBuilder('qi')
            ->select('qi.description, COUNT(qi.id) as usage_count, AVG(qi.unitPrice) as avg_price')
            ->groupBy('qi.description')
            ->orderBy('usage_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Items par plage de prix
     */
    public function findByPriceRange(string $minPrice, string $maxPrice): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.totalPrice >= :minPrice')
            ->andWhere('qi.totalPrice <= :maxPrice')
            ->setParameter('minPrice', $minPrice)
            ->setParameter('maxPrice', $maxPrice)
            ->orderBy('qi.totalPrice', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prix moyen par type de service
     */
    public function getAveragePriceByServiceType(): array
    {
        return $this->createQueryBuilder('qi')
            ->select('st.name as service_name, AVG(qi.unitPrice) as avg_price, COUNT(qi.id) as count')
            ->innerJoin('qi.serviceType', 'st')
            ->groupBy('st.id')
            ->orderBy('avg_price', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les items avec des durées estimées longues
     */
    public function findLongDurationItems(int $minDuration): array
    {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.estimatedDuration >= :minDuration')
            ->setParameter('minDuration', $minDuration)
            ->orderBy('qi.estimatedDuration', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition des quantités
     */
    public function getQuantityDistribution(): array
    {
        return $this->createQueryBuilder('qi')
            ->select('qi.quantity, COUNT(qi.id) as count')
            ->groupBy('qi.quantity')
            ->orderBy('qi.quantity', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Items créés dans une période
     */
    public function findCreatedBetween(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('qi')
            ->andWhere('qi.createdAt >= :startDate')
            ->andWhere('qi.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('qi.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Somme des prix par unité
     */
    public function getTotalPriceByUnit(): array
    {
        return $this->createQueryBuilder('qi')
            ->select('qi.unit, SUM(qi.totalPrice) as total, COUNT(qi.id) as count')
            ->andWhere('qi.unit IS NOT NULL')
            ->groupBy('qi.unit')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();
    }
}