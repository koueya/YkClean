<?php
// src/Repository/ServiceRequestRepository.php

namespace App\Repository\Service;

use App\Entity\Service\ServiceRequest;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceCategory;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ServiceRequest>
 */
class ServiceRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ServiceRequest::class);
    }

    /**
     * Trouve toutes les demandes par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('sr')
            ->andWhere('sr.status = :status')
            ->setParameter('status', $status)
            ->orderBy('sr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par client
     */
    public function findByClient(Client $client, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('sr')
            ->andWhere('sr.client = :client')
            ->setParameter('client', $client)
            ->orderBy('sr.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('sr.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les demandes en attente pour un client
     */
    public function findPendingByClient(Client $client): array
    {
        return $this->createQueryBuilder('sr')
            ->andWhere('sr.client = :client')
            ->andWhere('sr.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', 'pending')
            ->orderBy('sr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes récentes d'un client
     */
    public function findRecentByClient(Client $client, int $limit = 5): array
    {
        return $this->createQueryBuilder('sr')
            ->andWhere('sr.client = :client')
            ->setParameter('client', $client)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par catégorie
     */
    public function findByCategory(ServiceCategory $category, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('sr')
            ->andWhere('sr.category = :category')
            ->setParameter('category', $category)
            ->orderBy('sr.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('sr.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les demandes urgentes (date souhaitée proche)
     */
    public function findUrgent(int $days = 3): array
    {
        $maxDate = (new \DateTime())->modify("+{$days} days");

        return $this->createQueryBuilder('sr')
            ->andWhere('sr.status = :status')
            ->andWhere('sr.desiredDate <= :maxDate')
            ->andWhere('sr.desiredDate >= :now')
            ->setParameter('status', 'pending')
            ->setParameter('maxDate', $maxDate)
            ->setParameter('now', new \DateTime())
            ->orderBy('sr.desiredDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes sans devis
     */
    public function findWithoutQuotes(): array
    {
        return $this->createQueryBuilder('sr')
            ->leftJoin('sr.quotes', 'q')
            ->andWhere('sr.status = :status')
            ->andWhere('q.id IS NULL')
            ->setParameter('status', 'pending')
            ->orderBy('sr.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes avec des devis en attente
     */
    public function findWithPendingQuotes(): array
    {
        return $this->createQueryBuilder('sr')
            ->innerJoin('sr.quotes', 'q')
            ->andWhere('sr.status = :status')
            ->andWhere('q.status = :quoteStatus')
            ->setParameter('status', 'pending')
            ->setParameter('quoteStatus', 'sent')
            ->groupBy('sr.id')
            ->orderBy('sr.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes par ville
     */
    public function findByCity(string $city, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('sr')
            ->andWhere('sr.city LIKE :city')
            ->setParameter('city', '%' . $city . '%')
            ->orderBy('sr.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('sr.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les demandes par code postal
     */
    public function findByPostalCode(string $postalCode, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('sr')
            ->andWhere('sr.postalCode = :postalCode')
            ->setParameter('postalCode', $postalCode)
            ->orderBy('sr.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('sr.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Recherche de demandes avec filtres avancés
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('sr')
            ->orderBy('sr.createdAt', 'DESC');

        if (!empty($criteria['search'])) {
            $qb->andWhere(
                'sr.title LIKE :search OR ' .
                'sr.description LIKE :search OR ' .
                'sr.city LIKE :search OR ' .
                'sr.address LIKE :search'
            )
            ->setParameter('search', '%' . $criteria['search'] . '%');
        }

        if (!empty($criteria['status'])) {
            $qb->andWhere('sr.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('sr.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        if (!empty($criteria['client'])) {
            $qb->andWhere('sr.client = :client')
               ->setParameter('client', $criteria['client']);
        }

        if (!empty($criteria['city'])) {
            $qb->andWhere('sr.city LIKE :city')
               ->setParameter('city', '%' . $criteria['city'] . '%');
        }

        if (!empty($criteria['postal_code'])) {
            $qb->andWhere('sr.postalCode = :postalCode')
               ->setParameter('postalCode', $criteria['postal_code']);
        }

        if (!empty($criteria['start_date'])) {
            $qb->andWhere('sr.createdAt >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (!empty($criteria['end_date'])) {
            $qb->andWhere('sr.createdAt <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        if (!empty($criteria['min_budget'])) {
            $qb->andWhere('sr.estimatedBudget >= :minBudget')
               ->setParameter('minBudget', $criteria['min_budget']);
        }

        if (!empty($criteria['max_budget'])) {
            $qb->andWhere('sr.estimatedBudget <= :maxBudget')
               ->setParameter('maxBudget', $criteria['max_budget']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les demandes entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('sr')
            ->andWhere('sr.createdAt >= :startDate')
            ->andWhere('sr.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('sr.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('sr.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les demandes par statut
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->andWhere('sr.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Statistiques des demandes
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('sr');

        return [
            'total' => (clone $qb)->select('COUNT(sr.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'pending' => $this->countByStatus('pending'),
            
            'quoted' => $this->countByStatus('quoted'),
            
            'accepted' => $this->countByStatus('accepted'),
            
            'in_progress' => $this->countByStatus('in_progress'),
            
            'completed' => $this->countByStatus('completed'),
            
            'cancelled' => $this->countByStatus('cancelled'),
            
            'expired' => $this->countByStatus('expired'),

            'average_budget' => (clone $qb)->select('AVG(sr.estimatedBudget)')
                ->andWhere('sr.estimatedBudget IS NOT NULL')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'with_quotes' => (clone $qb)->select('COUNT(DISTINCT sr.id)')
                ->innerJoin('sr.quotes', 'q')
                ->getQuery()
                ->getSingleScalarResult(),

            'without_quotes' => (clone $qb)->select('COUNT(sr.id)')
                ->leftJoin('sr.quotes', 'q')
                ->andWhere('q.id IS NULL')
                ->andWhere('sr.status = :status')
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getSingleScalarResult(),
        ];
    }

    /**
     * Répartition par statut
     */
    public function getStatusDistribution(): array
    {
        return $this->createQueryBuilder('sr')
            ->select('sr.status, COUNT(sr.id) as count')
            ->groupBy('sr.status')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition par catégorie
     */
    public function getCategoryDistribution(): array
    {
        return $this->createQueryBuilder('sr')
            ->select('c.name, COUNT(sr.id) as count')
            ->innerJoin('sr.category', 'c')
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
        return $this->createQueryBuilder('sr')
            ->select('sr.city, sr.postalCode, COUNT(sr.id) as count')
            ->andWhere('sr.city IS NOT NULL')
            ->groupBy('sr.city', 'sr.postalCode')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes par mois
     */
    public function getMonthlyDistribution(int $year): array
    {
        return $this->createQueryBuilder('sr')
            ->select('MONTH(sr.createdAt) as month, COUNT(sr.id) as count')
            ->andWhere('YEAR(sr.createdAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Taux de conversion (demandes -> devis acceptés)
     */
    public function getConversionRate(): float
    {
        $total = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $converted = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->andWhere('sr.status IN (:statuses)')
            ->setParameter('statuses', ['accepted', 'in_progress', 'completed'])
            ->getQuery()
            ->getSingleScalarResult();

        return round(($converted / $total) * 100, 2);
    }

    /**
     * Temps moyen de réponse (création -> premier devis)
     */
    public function getAverageResponseTime(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                TIMESTAMPDIFF(HOUR, sr.created_at, 
                    (SELECT MIN(q.created_at) 
                     FROM quotes q 
                     WHERE q.service_request_id = sr.id)
                )
            ) as avg_hours
            FROM service_requests sr
            WHERE EXISTS (
                SELECT 1 FROM quotes q WHERE q.service_request_id = sr.id
            )
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return (float)($result->fetchOne() ?? 0);
    }

    /**
     * Demandes les plus anciennes sans réponse
     */
    public function findOldestWithoutResponse(int $limit = 10): array
    {
        return $this->createQueryBuilder('sr')
            ->leftJoin('sr.quotes', 'q')
            ->andWhere('sr.status = :status')
            ->andWhere('q.id IS NULL')
            ->setParameter('status', 'pending')
            ->orderBy('sr.createdAt', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Demandes expirées (date souhaitée dépassée)
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('sr')
            ->andWhere('sr.status IN (:statuses)')
            ->andWhere('sr.desiredDate < :now')
            ->setParameter('statuses', ['pending', 'quoted'])
            ->setParameter('now', new \DateTime())
            ->orderBy('sr.desiredDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre moyen de devis par demande
     */
    public function getAverageQuotesPerRequest(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(quote_count) as avg_quotes
            FROM (
                SELECT COUNT(q.id) as quote_count
                FROM service_requests sr
                LEFT JOIN quotes q ON q.service_request_id = sr.id
                GROUP BY sr.id
            ) as subquery
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return round((float)($result->fetchOne() ?? 0), 2);
    }

    /**
     * Demandes par tranche de budget
     */
    public function getBudgetDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN estimated_budget IS NULL THEN "Non spécifié"
                    WHEN estimated_budget < 100 THEN "0-100€"
                    WHEN estimated_budget < 300 THEN "100-300€"
                    WHEN estimated_budget < 500 THEN "300-500€"
                    WHEN estimated_budget < 1000 THEN "500-1000€"
                    ELSE "1000€+"
                END as budget_range,
                COUNT(*) as count
            FROM service_requests
            GROUP BY budget_range
            ORDER BY 
                CASE budget_range
                    WHEN "Non spécifié" THEN 0
                    WHEN "0-100€" THEN 1
                    WHEN "100-300€" THEN 2
                    WHEN "300-500€" THEN 3
                    WHEN "500-1000€" THEN 4
                    WHEN "1000€+" THEN 5
                END
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Demandes proches géographiquement
     */
    public function findNearby(
        float $latitude,
        float $longitude,
        float $radius = 10, // en km
        ?string $status = null
    ): array {
        // Formule de Haversine
        $sql = '
            SELECT sr.*,
            (6371 * acos(
                cos(radians(:latitude)) * 
                cos(radians(sr.latitude)) * 
                cos(radians(sr.longitude) - radians(:longitude)) + 
                sin(radians(:latitude)) * 
                sin(radians(sr.latitude))
            )) AS distance
            FROM service_requests sr
            WHERE sr.latitude IS NOT NULL
            AND sr.longitude IS NOT NULL
        ';

        if ($status) {
            $sql .= ' AND sr.status = :status';
        }

        $sql .= ' HAVING distance <= :radius';
        $sql .= ' ORDER BY distance ASC';

        $conn = $this->getEntityManager()->getConnection();
        $stmt = $conn->prepare($sql);
        
        $params = [
            'latitude' => $latitude,
            'longitude' => $longitude,
            'radius' => $radius
        ];

        if ($status) {
            $params['status'] = $status;
        }

        $result = $stmt->executeQuery($params);
        $requestIds = array_column($result->fetchAllAssociative(), 'id');

        if (empty($requestIds)) {
            return [];
        }

        return $this->createQueryBuilder('sr')
            ->andWhere('sr.id IN (:ids)')
            ->setParameter('ids', $requestIds)
            ->getQuery()
            ->getResult();
    }

    /**
     * Exporte les demandes en CSV
     */
    public function exportToCsv(array $requests): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'Référence',
            'Client',
            'Catégorie',
            'Titre',
            'Description',
            'Ville',
            'Code Postal',
            'Budget Estimé (€)',
            'Date Souhaitée',
            'Statut',
            'Nb Devis',
            'Créé le'
        ]);

        // Données
        foreach ($requests as $request) {
            fputcsv($handle, [
                $request->getReferenceNumber(),
                $request->getClient()->getFullName(),
                $request->getCategory()->getName(),
                $request->getTitle(),
                substr($request->getDescription(), 0, 100) . '...',
                $request->getCity(),
                $request->getPostalCode(),
                $request->getEstimatedBudget() ?? 'Non spécifié',
                $request->getDesiredDate() ? $request->getDesiredDate()->format('d/m/Y') : 'Flexible',
                ucfirst($request->getStatus()),
                count($request->getQuotes()),
                $request->getCreatedAt()->format('d/m/Y H:i')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Demandes récentes (derniers jours)
     */
    public function findRecent(int $days = 7, int $limit = 20): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('sr')
            ->andWhere('sr.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Taux d'abandon (demandes annulées ou expirées)
     */
    public function getAbandonmentRate(): float
    {
        $total = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $abandoned = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->andWhere('sr.status IN (:statuses)')
            ->setParameter('statuses', ['cancelled', 'expired'])
            ->getQuery()
            ->getSingleScalarResult();

        return round(($abandoned / $total) * 100, 2);
    }

    /**
     * Délai moyen de réalisation (demande -> service complété)
     */
    public function getAverageCompletionTime(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                TIMESTAMPDIFF(DAY, sr.created_at, b.completed_at)
            ) as avg_days
            FROM service_requests sr
            INNER JOIN bookings b ON b.service_request_id = sr.id
            WHERE b.status = "completed"
            AND b.completed_at IS NOT NULL
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return round((float)($result->fetchOne() ?? 0), 2);
    }

    /**
     * Demandes par jour de la semaine
     */
    public function getWeekdayDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                DAYNAME(created_at) as day_name,
                DAYOFWEEK(created_at) as day_number,
                COUNT(*) as count
            FROM service_requests
            GROUP BY day_name, day_number
            ORDER BY day_number
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Demandes par heure de la journée
     */
    public function getHourlyDistribution(): array
    {
        return $this->createQueryBuilder('sr')
            ->select('HOUR(sr.createdAt) as hour, COUNT(sr.id) as count')
            ->groupBy('hour')
            ->orderBy('hour', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Top catégories par période
     */
    public function getTopCategoriesByPeriod(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        int $limit = 10
    ): array {
        return $this->createQueryBuilder('sr')
            ->select('c.name, COUNT(sr.id) as count')
            ->innerJoin('sr.category', 'c')
            ->andWhere('sr.createdAt >= :startDate')
            ->andWhere('sr.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->groupBy('c.id')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Clients les plus actifs (par nombre de demandes)
     */
    public function getMostActiveClients(int $limit = 10): array
    {
        return $this->createQueryBuilder('sr')
            ->select('c.id, c.firstName, c.lastName, COUNT(sr.id) as request_count')
            ->innerJoin('sr.client', 'c')
            ->groupBy('c.id')
            ->orderBy('request_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Villes les plus actives
     */
    public function getMostActiveCities(int $limit = 10): array
    {
        return $this->createQueryBuilder('sr')
            ->select('sr.city, COUNT(sr.id) as count')
            ->andWhere('sr.city IS NOT NULL')
            ->groupBy('sr.city')
            ->orderBy('count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}