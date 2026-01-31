<?php
// src/Repository/PrestataireRepository.php

namespace App\Repository\User;

use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Prestataire>
 */
class PrestataireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Prestataire::class);
    }

    /**
     * Trouve tous les prestataires actifs
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve tous les prestataires approuvés et actifs
     */
    public function findAllApproved(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->orderBy('p.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires en attente d'approbation
     */
    public function findPendingApproval(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isApproved = :approved')
            ->andWhere('p.approvedAt IS NULL')
            ->setParameter('approved', false)
            ->orderBy('p.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires par catégorie
     */
    public function findByCategory(Category $category, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->innerJoin('p.categories', 'c')
            ->andWhere('c = :category')
            ->setParameter('category', $category);

        if ($activeOnly) {
            $qb->andWhere('p.isActive = :active')
               ->andWhere('p.isApproved = :approved')
               ->setParameter('active', true)
               ->setParameter('approved', true);
        }

        return $qb->orderBy('p.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires par ville
     */
    public function findByCity(string $city, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.city LIKE :city')
            ->setParameter('city', '%' . $city . '%');

        if ($activeOnly) {
            $qb->andWhere('p.isActive = :active')
               ->andWhere('p.isApproved = :approved')
               ->setParameter('active', true)
               ->setParameter('approved', true);
        }

        return $qb->orderBy('p.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires par code postal
     */
    public function findByPostalCode(string $postalCode, bool $activeOnly = true): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.postalCode = :postalCode')
            ->setParameter('postalCode', $postalCode);

        if ($activeOnly) {
            $qb->andWhere('p.isActive = :active')
               ->andWhere('p.isApproved = :approved')
               ->setParameter('active', true)
               ->setParameter('approved', true);
        }

        return $qb->orderBy('p.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de prestataires avec filtres avancés
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('active', true)
            ->setParameter('approved', true);

        if (!empty($criteria['search'])) {
            $qb->andWhere(
                'p.firstName LIKE :search OR ' .
                'p.lastName LIKE :search OR ' .
                'p.companyName LIKE :search OR ' .
                'p.bio LIKE :search'
            )
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['category'])) {
            $qb->innerJoin('p.categories', 'c')
               ->andWhere('c.id = :categoryId')
               ->setParameter('categoryId', $criteria['category']);
        }

        if (!empty($criteria['city'])) {
            $qb->andWhere('p.city LIKE :city')
               ->setParameter('city', '%' . $criteria['city'] . '%');
        }

        if (!empty($criteria['postal_code'])) {
            $qb->andWhere('p.postalCode = :postalCode')
               ->setParameter('postalCode', $criteria['postal_code']);
        }

        if (isset($criteria['min_rating'])) {
            $qb->andWhere('p.rating >= :minRating')
               ->setParameter('minRating', $criteria['min_rating']);
        }

        if (isset($criteria['min_experience'])) {
            $qb->andWhere('p.yearsOfExperience >= :minExperience')
               ->setParameter('minExperience', $criteria['min_experience']);
        }

        if (!empty($criteria['services'])) {
            $qb->innerJoin('p.services', 's')
               ->andWhere('s.id IN (:services)')
               ->setParameter('services', $criteria['services']);
        }

        // Tri
        $orderBy = $criteria['order_by'] ?? 'rating';
        $orderDirection = $criteria['order_direction'] ?? 'DESC';
        
        $qb->orderBy('p.' . $orderBy, $orderDirection);

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les meilleurs prestataires (par note)
     */
    public function findTopRated(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->andWhere('p.rating > 0')
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->orderBy('p.rating', 'DESC')
            ->addOrderBy('p.totalReviews', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires les plus actifs (par nombre de réservations)
     */
    public function findMostActive(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->orderBy('p.completedBookings', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires les mieux rémunérés
     */
    public function findTopEarners(int $limit = 10): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->orderBy('p.totalEarnings', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les nouveaux prestataires
     */
    public function findNewPrestataires(int $days = 30): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('p')
            ->andWhere('p.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires avec profil vérifié
     */
    public function findVerified(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->andWhere('p.isVerified = :verified')
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->setParameter('verified', true)
            ->orderBy('p.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les prestataires disponibles à une date donnée
     */
    public function findAvailableOnDate(
        \DateTimeInterface $date,
        ?Category $category = null
    ): array {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.absences', 'a')
            ->leftJoin('p.bookings', 'b')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->andWhere(
                'a.id IS NULL OR ' .
                '(a.startDate > :date OR a.endDate < :date)'
            )
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->setParameter('date', $date)
            ->groupBy('p.id');

        if ($category) {
            $qb->innerJoin('p.categories', 'c')
               ->andWhere('c = :category')
               ->setParameter('category', $category);
        }

        return $qb->orderBy('p.rating', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche par proximité géographique
     */
    public function findNearby(
        float $latitude,
        float $longitude,
        float $radius = 10, // en km
        ?Category $category = null
    ): array {
        // Formule de Haversine pour calculer la distance
        $sql = '
            SELECT p.*,
            (6371 * acos(
                cos(radians(:latitude)) * 
                cos(radians(p.latitude)) * 
                cos(radians(p.longitude) - radians(:longitude)) + 
                sin(radians(:latitude)) * 
                sin(radians(p.latitude))
            )) AS distance
            FROM prestataires p
        ';

        $conditions = [
            'p.is_active = 1',
            'p.is_approved = 1',
            'p.latitude IS NOT NULL',
            'p.longitude IS NOT NULL'
        ];

        if ($category) {
            $sql .= ' INNER JOIN prestataire_category pc ON p.id = pc.prestataire_id';
            $conditions[] = 'pc.category_id = ' . $category->getId();
        }

        $sql .= ' WHERE ' . implode(' AND ', $conditions);
        $sql .= ' HAVING distance <= :radius';
        $sql .= ' ORDER BY distance ASC';

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius
        ]);

        $prestataireIds = array_column($result->fetchAllAssociative(), 'id');

        if (empty($prestataireIds)) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->andWhere('p.id IN (:ids)')
            ->setParameter('ids', $prestataireIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques des prestataires
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('p');

        return [
            'total' => (clone $qb)->select('COUNT(p.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'active' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'approved' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.isApproved = :approved')
                ->setParameter('approved', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'pending_approval' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.isApproved = :approved')
                ->andWhere('p.approvedAt IS NULL')
                ->setParameter('approved', false)
                ->getQuery()
                ->getSingleScalarResult(),

            'verified' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.isVerified = :verified')
                ->setParameter('verified', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'with_insurance' => (clone $qb)->select('COUNT(p.id)')
                ->andWhere('p.hasInsurance = :insurance')
                ->setParameter('insurance', true)
                ->getQuery()
                ->getSingleScalarResult(),

            'average_rating' => (clone $qb)->select('AVG(p.rating)')
                ->andWhere('p.rating > 0')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,

            'total_bookings' => (clone $qb)->select('SUM(p.completedBookings)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,

            'total_earnings' => (clone $qb)->select('SUM(p.totalEarnings)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Répartition par catégorie
     */
    public function getCategoryDistribution(): array
    {
        return $this->createQueryBuilder('p')
            ->select('c.name, COUNT(p.id) as count')
            ->innerJoin('p.categories', 'c')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition géographique
     */
    public function getGeographicDistribution(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.city, p.postalCode, COUNT(p.id) as count')
            ->andWhere('p.city IS NOT NULL')
            ->andWhere('p.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('p.city', 'p.postalCode')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Croissance mensuelle
     */
    public function getMonthlyGrowth(int $year): array
    {
        return $this->createQueryBuilder('p')
            ->select('MONTH(p.createdAt) as month, COUNT(p.id) as count')
            ->andWhere('YEAR(p.createdAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Taux d'approbation
     */
    public function getApprovalRate(): float
    {
        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $approved = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($approved / $total) * 100, 2);
    }

    /**
     * Taux de vérification
     */
    public function getVerificationRate(): float
    {
        $total = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isApproved = :approved')
            ->setParameter('approved', true)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $verified = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->andWhere('p.isApproved = :approved')
            ->andWhere('p.isVerified = :verified')
            ->setParameter('approved', true)
            ->setParameter('verified', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($verified / $total) * 100, 2);
    }

    /**
     * Revenus moyens par prestataire
     */
    public function getAverageEarnings(): string
    {
        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.totalEarnings)')
            ->andWhere('p.completedBookings > 0')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Note moyenne par prestataire
     */
    public function getAverageRating(): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('AVG(p.rating)')
            ->andWhere('p.totalReviews > 0')
            ->getQuery()
            ->getSingleScalarResult();

        return round((float)($result ?? 0), 2);
    }

    /**
     * Prestataires inactifs (sans réservation depuis X jours)
     */
    public function findInactive(int $days = 90): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('p')
            ->leftJoin('p.bookings', 'b')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.isApproved = :approved')
            ->andWhere(
                '(p.completedBookings = 0) OR ' .
                '(SELECT MAX(b2.createdAt) FROM App\Entity\Booking b2 WHERE b2.prestataire = p) < :date'
            )
            ->setParameter('active', true)
            ->setParameter('approved', true)
            ->setParameter('date', $date)
            ->groupBy('p.id')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prestataires avec assurance expirée ou expirante
     */
    public function findWithExpiringInsurance(int $days = 30): array
    {
        $date = (new \DateTime())->modify("+{$days} days");

        return $this->createQueryBuilder('p')
            ->andWhere('p.hasInsurance = :insurance')
            ->andWhere('p.insuranceExpiryDate IS NOT NULL')
            ->andWhere('p.insuranceExpiryDate <= :date')
            ->setParameter('insurance', true)
            ->setParameter('date', $date)
            ->orderBy('p.insuranceExpiryDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Prestataires avec documents manquants
     */
    public function findWithMissingDocuments(): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.isApproved = :approved')
            ->andWhere(
                'p.idDocument IS NULL OR ' .
                'p.kbisDocument IS NULL OR ' .
                'p.insuranceDocument IS NULL'
            )
            ->setParameter('approved', true)
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Exporte les prestataires en CSV
     */
    public function exportToCsv(array $prestataires): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Email',
            'Prénom',
            'Nom',
            'Entreprise',
            'SIRET',
            'Téléphone',
            'Ville',
            'Note',
            'Réservations',
            'Revenus (€)',
            'Vérifié',
            'Approuvé',
            'Actif',
            'Inscrit le'
        ]);

        // Données
        foreach ($prestataires as $prestataire) {
            fputcsv($handle, [
                $prestataire->getId(),
                $prestataire->getEmail(),
                $prestataire->getFirstName(),
                $prestataire->getLastName(),
                $prestataire->getCompanyName(),
                $prestataire->getSiret(),
                $prestataire->getPhone(),
                $prestataire->getCity(),
                $prestataire->getRating(),
                $prestataire->getCompletedBookings(),
                $prestataire->getTotalEarnings(),
                $prestataire->isVerified() ? 'Oui' : 'Non',
                $prestataire->isApproved() ? 'Oui' : 'Non',
                $prestataire->isActive() ? 'Oui' : 'Non',
                $prestataire->getCreatedAt()->format('d/m/Y')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Prestataires par tranche d'expérience
     */
    public function getExperienceDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN years_of_experience < 1 THEN "< 1 an"
                    WHEN years_of_experience < 3 THEN "1-3 ans"
                    WHEN years_of_experience < 5 THEN "3-5 ans"
                    WHEN years_of_experience < 10 THEN "5-10 ans"
                    ELSE "10+ ans"
                END as experience_range,
                COUNT(*) as count
            FROM prestataires
            WHERE is_active = 1 AND is_approved = 1
            GROUP BY experience_range
            ORDER BY 
                CASE experience_range
                    WHEN "< 1 an" THEN 1
                    WHEN "1-3 ans" THEN 2
                    WHEN "3-5 ans" THEN 3
                    WHEN "5-10 ans" THEN 4
                    WHEN "10+ ans" THEN 5
                END
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Prestataires par tranche de revenus
     */
    public function getEarningsDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN total_earnings = 0 THEN "0€"
                    WHEN total_earnings < 1000 THEN "0-1000€"
                    WHEN total_earnings < 5000 THEN "1000-5000€"
                    WHEN total_earnings < 10000 THEN "5000-10000€"
                    ELSE "10000€+"
                END as earnings_range,
                COUNT(*) as count
            FROM prestataires
            WHERE is_active = 1 AND is_approved = 1
            GROUP BY earnings_range
            ORDER BY 
                CASE earnings_range
                    WHEN "0€" THEN 1
                    WHEN "0-1000€" THEN 2
                    WHEN "1000-5000€" THEN 3
                    WHEN "5000-10000€" THEN 4
                    WHEN "10000€+" THEN 5
                END
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Taux de complétion de profil
     */
    public function getProfileCompletionRate(Prestataire $prestataire): float
    {
        $fields = [
            'bio' => !empty($prestataire->getBio()),
            'phone' => !empty($prestataire->getPhone()),
            'address' => !empty($prestataire->getAddress()),
            'city' => !empty($prestataire->getCity()),
            'postal_code' => !empty($prestataire->getPostalCode()),
            'company_name' => !empty($prestataire->getCompanyName()),
            'siret' => !empty($prestataire->getSiret()),
            'years_of_experience' => $prestataire->getYearsOfExperience() !== null,
            'profile_image' => !empty($prestataire->getProfileImage()),
            'categories' => count($prestataire->getCategories()) > 0,
            'services' => count($prestataire->getServices()) > 0,
            'id_document' => !empty($prestataire->getIdDocument()),
            'kbis_document' => !empty($prestataire->getKbisDocument()),
            'insurance_document' => !empty($prestataire->getInsuranceDocument()),
        ];

        $completed = count(array_filter($fields));
        $total = count($fields);

        return round(($completed / $total) * 100, 2);
    }

    /**
     * Prestataires avec profil incomplet
     */
    public function findWithIncompleteProfile(float $minCompletion = 70): array
    {
        $prestataires = $this->findAllApproved();
        $incomplete = [];

        foreach ($prestataires as $prestataire) {
            $completion = $this->getProfileCompletionRate($prestataire);
            if ($completion < $minCompletion) {
                $incomplete[] = [
                    'prestataire' => $prestataire,
                    'completion' => $completion
                ];
            }
        }

        // Trier par taux de complétion croissant
        usort($incomplete, fn($a, $b) => $a['completion'] <=> $b['completion']);

        return $incomplete;
    }
}