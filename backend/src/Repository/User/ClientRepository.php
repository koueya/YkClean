<?php
// src/Repository/ClientRepository.php

namespace App\Repository\User;

use App\Entity\User\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Client>
 */
class ClientRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Client::class);
    }

    /**
     * Trouve tous les clients actifs
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les meilleurs clients (par nombre de réservations)
     */
    public function findTopClients(int $limit = 10): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.totalBookings', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients par dépenses totales
     */
    public function findByTotalSpent(string $minAmount): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.totalSpent >= :minAmount')
            ->setParameter('minAmount', $minAmount)
            ->orderBy('c.totalSpent', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients actifs (avec réservations récentes)
     */
    public function findActiveClients(\DateTimeInterface $since = null): array
    {
        $since = $since ?? (new \DateTime())->modify('-3 months');

        return $this->createQueryBuilder('c')
            ->innerJoin('c.bookings', 'b')
            ->andWhere('b.createdAt >= :since')
            ->andWhere('c.isActive = :active')
            ->setParameter('since', $since)
            ->setParameter('active', true)
            ->groupBy('c.id')
            ->orderBy('COUNT(b.id)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les nouveaux clients
     */
    public function findNewClients(int $days = 30): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('c')
            ->andWhere('c.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients sans réservation
     */
    public function findWithoutBookings(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('b.id IS NULL')
            ->andWhere('c.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les clients inactifs (aucune réservation depuis X jours)
     */
    public function findInactive(int $days = 90): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('c')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->andWhere(
                '(b.id IS NULL) OR ' .
                '(SELECT MAX(b2.createdAt) FROM App\Entity\Booking b2 WHERE b2.client = c) < :date'
            )
            ->setParameter('active', true)
            ->setParameter('date', $date)
            ->groupBy('c.id')
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Statistiques clients
     */
    public function getStatistics(): array
    {
        $qb = $this->createQueryBuilder('c');
        
        return [
            'total' => $qb->select('COUNT(c.id)')
                ->getQuery()
                ->getSingleScalarResult(),
            
            'active' => $qb->select('COUNT(c.id)')
                ->andWhere('c.isActive = :active')
                ->setParameter('active', true)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'verified' => $qb->select('COUNT(c.id)')
                ->andWhere('c.isVerified = :verified')
                ->setParameter('verified', true)
                ->getQuery()
                ->getSingleScalarResult(),
            
            'with_bookings' => $qb->select('COUNT(DISTINCT c.id)')
                ->innerJoin('c.bookings', 'b')
                ->getQuery()
                ->getSingleScalarResult(),
            
            'without_bookings' => $qb->select('COUNT(c.id)')
                ->leftJoin('c.bookings', 'b')
                ->andWhere('b.id IS NULL')
                ->getQuery()
                ->getSingleScalarResult(),
            
            'total_spent' => $qb->select('SUM(c.totalSpent)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
            
            'average_spent' => $qb->select('AVG(c.totalSpent)')
                ->andWhere('c.totalBookings > 0')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'total_bookings' => $qb->select('SUM(c.totalBookings)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,

            'average_bookings' => $qb->select('AVG(c.totalBookings)')
                ->getQuery()
                ->getSingleScalarResult() ?? 0,
        ];
    }

    /**
     * Clients par ville
     */
    public function findByCity(string $city): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.city LIKE :city')
            ->andWhere('c.isActive = :active')
            ->setParameter('city', '%' . $city . '%')
            ->setParameter('active', true)
            ->orderBy('c.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition géographique des clients
     */
    public function getGeographicDistribution(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.city, c.postalCode, COUNT(c.id) as count')
            ->andWhere('c.city IS NOT NULL')
            ->groupBy('c.city', 'c.postalCode')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Clients par méthode de paiement préférée
     */
    public function getPaymentMethodDistribution(): array
    {
        return $this->createQueryBuilder('c')
            ->select('c.preferredPaymentMethod, COUNT(c.id) as count')
            ->andWhere('c.preferredPaymentMethod IS NOT NULL')
            ->groupBy('c.preferredPaymentMethod')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Clients VIP (dépenses élevées et beaucoup de réservations)
     */
    public function findVIPClients(
        string $minSpent = '500.00',
        int $minBookings = 5
    ): array {
        return $this->createQueryBuilder('c')
            ->andWhere('c.totalSpent >= :minSpent')
            ->andWhere('c.totalBookings >= :minBookings')
            ->andWhere('c.isActive = :active')
            ->setParameter('minSpent', $minSpent)
            ->setParameter('minBookings', $minBookings)
            ->setParameter('active', true)
            ->orderBy('c.totalSpent', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Taux de rétention (clients avec au moins 2 réservations)
     */
    public function getRetentionRate(): float
    {
        $total = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $retained = $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.totalBookings >= :minBookings')
            ->setParameter('minBookings', 2)
            ->getQuery()
            ->getSingleScalarResult();

        return round(($retained / $total) * 100, 2);
    }

    /**
     * Valeur vie client moyenne (Customer Lifetime Value)
     */
    public function getAverageLifetimeValue(): string
    {
        $result = $this->createQueryBuilder('c')
            ->select('AVG(c.totalSpent)')
            ->andWhere('c.totalBookings > 0')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Clients par tranche de dépenses
     */
    public function getSpendingDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN total_spent = 0 THEN "0€"
                    WHEN total_spent < 100 THEN "0-100€"
                    WHEN total_spent < 500 THEN "100-500€"
                    WHEN total_spent < 1000 THEN "500-1000€"
                    ELSE "1000€+"
                END as spending_range,
                COUNT(*) as count
            FROM clients
            GROUP BY spending_range
            ORDER BY 
                CASE spending_range
                    WHEN "0€" THEN 1
                    WHEN "0-100€" THEN 2
                    WHEN "100-500€" THEN 3
                    WHEN "500-1000€" THEN 4
                    WHEN "1000€+" THEN 5
                END
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Croissance mensuelle des nouveaux clients
     */
    public function getMonthlyGrowth(int $year): array
    {
        return $this->createQueryBuilder('c')
            ->select('MONTH(c.createdAt) as month, COUNT(c.id) as count')
            ->andWhere('YEAR(c.createdAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de clients
     */
    public function search(string $searchTerm): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere(
                'c.firstName LIKE :term OR ' .
                'c.lastName LIKE :term OR ' .
                'c.email LIKE :term OR ' .
                'c.phone LIKE :term OR ' .
                'c.city LIKE :term'
            )
            ->setParameter('term', '%' . $searchTerm . '%')
            ->orderBy('c.lastName', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Clients avec des réservations non évaluées
     */
    public function findWithPendingReviews(): array
    {
        return $this->createQueryBuilder('c')
            ->innerJoin('c.bookings', 'b')
            ->leftJoin('b.review', 'r')
            ->andWhere('b.status = :status')
            ->andWhere('r.id IS NULL')
            ->setParameter('status', 'completed')
            ->groupBy('c.id')
            ->orderBy('MAX(b.completedAt)', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Exporte les clients en CSV
     */
    public function exportToCsv(array $clients): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Email',
            'Prénom',
            'Nom',
            'Téléphone',
            'Ville',
            'Total Réservations',
            'Total Dépensé (€)',
            'Méthode Paiement Préférée',
            'Inscrit le',
            'Dernière Connexion'
        ]);

        // Données
        foreach ($clients as $client) {
            fputcsv($handle, [
                $client->getId(),
                $client->getEmail(),
                $client->getFirstName(),
                $client->getLastName(),
                $client->getPhone(),
                $client->getCity(),
                $client->getTotalBookings(),
                $client->getTotalSpent(),
                $client->getPreferredPaymentMethod() ?? 'Non défini',
                $client->getCreatedAt()->format('d/m/Y'),
                $client->getLastLoginAt() ? $client->getLastLoginAt()->format('d/m/Y H:i') : 'Jamais'
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Clients à risque de désabonnement (churn)
     */
    public function findAtRiskOfChurn(int $inactiveDays = 60): array
    {
        $date = (new \DateTime())->modify("-{$inactiveDays} days");

        return $this->createQueryBuilder('c')
            ->leftJoin('c.bookings', 'b')
            ->andWhere('c.isActive = :active')
            ->andWhere('c.totalBookings > 0')
            ->andWhere(
                '(SELECT MAX(b2.createdAt) FROM App\Entity\Booking b2 WHERE b2.client = c) < :date'
            )
            ->setParameter('active', true)
            ->setParameter('date', $date)
            ->groupBy('c.id')
            ->orderBy('c.totalSpent', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Panier moyen par client
     */
    public function getAverageBasket(): string
    {
        $result = $this->createQueryBuilder('c')
            ->select('AVG(c.totalSpent / NULLIF(c.totalBookings, 0))')
            ->andWhere('c.totalBookings > 0')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ?? '0.00';
    }

    /**
     * Segmentation RFM (Recency, Frequency, Monetary)
     */
    public function getRFMSegmentation(): array
    {
        // Cette méthode nécessiterait une logique plus complexe pour calculer les scores RFM de chaque client
        $clients = $this->findAllActive();
        $segments = [];

        foreach ($clients as $client) {
            $lastBooking = $client->getLastBookingDate();
            $recency = $lastBooking 
                ? (new \DateTime())->diff($lastBooking)->days 
                : 999;
            
            $frequency = $client->getTotalBookings();
            $monetary = (float)$client->getTotalSpent();

            // Scoring simple (à affiner selon les besoins)
            $recencyScore = $recency < 30 ? 5 : ($recency < 90 ? 3 : 1);
            $frequencyScore = $frequency > 10 ? 5 : ($frequency > 5 ? 3 : 1);
            $monetaryScore = $monetary > 1000 ? 5 : ($monetary > 500 ? 3 : 1);

            $totalScore = $recencyScore + $frequencyScore + $monetaryScore;

            $segment = match(true) {
                $totalScore >= 13 => 'Champions',
                $totalScore >= 10 => 'Loyaux',
                $totalScore >= 7 => 'Potentiels',
                $totalScore >= 4 => 'À risque',
                default => 'Perdus'
            };

            if (!isset($segments[$segment])) {
                $segments[$segment] = 0;
            }
            $segments[$segment]++;
        }

        return $segments;
    }
}