<?php
// src/Repository/QuoteRepository.php

namespace App\Repository;

use App\Entity\Quote;
use App\Entity\ServiceRequest;
use App\Entity\Prestataire;
use App\Entity\Client;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Quote>
 */
class QuoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Quote::class);
    }

    /**
     * Trouve tous les devis par statut
     */
    public function findByStatus(string $status): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.status = :status')
            ->setParameter('status', $status)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les devis par prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $status = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('q.createdAt', 'DESC');

        if ($status) {
            $qb->andWhere('q.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les devis en attente d'un prestataire
     */
    public function findPendingByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.prestataire = :prestataire')
            ->andWhere('q.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'sent')
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les devis acceptés d'un prestataire
     */
    public function findAcceptedByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.prestataire = :prestataire')
            ->andWhere('q.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'accepted')
            ->orderBy('q.acceptedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les devis par demande de service
     */
    public function findByServiceRequest(ServiceRequest $serviceRequest): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.serviceRequest = :serviceRequest')
            ->setParameter('serviceRequest', $serviceRequest)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les devis récents d'un prestataire
     */
    public function findRecentByPrestataire(Prestataire $prestataire, int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('q.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les devis expirés
     */
    public function findExpired(): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.status = :status')
            ->andWhere('q.validUntil < :now')
            ->setParameter('status', 'sent')
            ->setParameter('now', new \DateTime())
            ->orderBy('q.validUntil', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les devis expirant bientôt
     */
    public function findExpiringSoon(int $days = 3): array
    {
        $now = new \DateTime();
        $maxDate = (clone $now)->modify("+{$days} days");

        return $this->createQueryBuilder('q')
            ->andWhere('q.status = :status')
            ->andWhere('q.validUntil BETWEEN :now AND :maxDate')
            ->setParameter('status', 'sent')
            ->setParameter('now', $now)
            ->setParameter('maxDate', $maxDate)
            ->orderBy('q.validUntil', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de devis avec filtres avancés
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('q')
            ->leftJoin('q.serviceRequest', 'sr')
            ->leftJoin('q.prestataire', 'p')
            ->orderBy('q.createdAt', 'DESC');

        if (!empty($criteria['status'])) {
            $qb->andWhere('q.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        if (!empty($criteria['prestataire'])) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $criteria['prestataire']);
        }

        if (!empty($criteria['service_request'])) {
            $qb->andWhere('q.serviceRequest = :serviceRequest')
               ->setParameter('serviceRequest', $criteria['service_request']);
        }

        if (!empty($criteria['min_amount'])) {
            $qb->andWhere('q.amount >= :minAmount')
               ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (!empty($criteria['max_amount'])) {
            $qb->andWhere('q.amount <= :maxAmount')
               ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (!empty($criteria['start_date'])) {
            $qb->andWhere('q.createdAt >= :startDate')
               ->setParameter('startDate', $criteria['start_date']);
        }

        if (!empty($criteria['end_date'])) {
            $qb->andWhere('q.createdAt <= :endDate')
               ->setParameter('endDate', $criteria['end_date']);
        }

        if (!empty($criteria['category'])) {
            $qb->andWhere('sr.category = :category')
               ->setParameter('category', $criteria['category']);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les devis par statut
     */
    public function countByStatus(string $status, ?Prestataire $prestataire = null): int
    {
        $qb = $this->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->andWhere('q.status = :status')
            ->setParameter('status', $status);

        if ($prestataire) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Statistiques des devis
     */
    public function getStatistics(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('q');

        if ($prestataire) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return [
            'total' => (clone $qb)->select('COUNT(q.id)')
                ->getQuery()
                ->getSingleScalarResult(),

            'draft' => $this->countByStatus('draft', $prestataire),
            
            'sent' => $this->countByStatus('sent', $prestataire),
            
            'accepted' => $this->countByStatus('accepted', $prestataire),
            
            'rejected' => $this->countByStatus('rejected', $prestataire),
            
            'expired' => $this->countByStatus('expired', $prestataire),

            'average_amount' => (clone $qb)->select('AVG(q.amount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'total_value' => (clone $qb)->select('SUM(q.amount)')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',

            'accepted_value' => (clone $qb)->select('SUM(q.amount)')
                ->andWhere('q.status = :status')
                ->setParameter('status', 'accepted')
                ->getQuery()
                ->getSingleScalarResult() ?? '0.00',
        ];
    }

    /**
     * Répartition par statut
     */
    public function getStatusDistribution(?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->select('q.status, COUNT(q.id) as count')
            ->groupBy('q.status')
            ->orderBy('count', 'DESC');

        if ($prestataire) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Taux d'acceptation
     */
    public function getAcceptanceRate(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('q');

        if ($prestataire) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $total = (clone $qb)->select('COUNT(q.id)')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('statuses', ['sent', 'accepted', 'rejected', 'expired'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $accepted = (clone $qb)->select('COUNT(q.id)')
            ->andWhere('q.status = :status')
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult();

        return round(($accepted / $total) * 100, 2);
    }

    /**
     * Taux de rejet
     */
    public function getRejectionRate(?Prestataire $prestataire = null): float
    {
        $qb = $this->createQueryBuilder('q');

        if ($prestataire) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        $total = (clone $qb)->select('COUNT(q.id)')
            ->andWhere('q.status IN (:statuses)')
            ->setParameter('statuses', ['sent', 'accepted', 'rejected', 'expired'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $rejected = (clone $qb)->select('COUNT(q.id)')
            ->andWhere('q.status = :status')
            ->setParameter('status', 'rejected')
            ->getQuery()
            ->getSingleScalarResult();

        return round(($rejected / $total) * 100, 2);
    }

    /**
     * Temps moyen de réponse (envoi -> acceptation/rejet)
     */
    public function getAverageResponseTime(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                TIMESTAMPDIFF(HOUR, created_at, 
                    COALESCE(accepted_at, rejected_at)
                )
            ) as avg_hours
            FROM quotes
            WHERE status IN ("accepted", "rejected")
            AND (accepted_at IS NOT NULL OR rejected_at IS NOT NULL)
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return round((float)($result->fetchOne() ?? 0), 2);
    }

    /**
     * Trouve les devis entre deux dates
     */
    public function findBetweenDates(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?Prestataire $prestataire = null
    ): array {
        $qb = $this->createQueryBuilder('q')
            ->andWhere('q.createdAt >= :startDate')
            ->andWhere('q.createdAt <= :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('q.createdAt', 'DESC');

        if ($prestataire) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Devis par mois
     */
    public function getMonthlyDistribution(int $year, ?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('q')
            ->select('MONTH(q.createdAt) as month, COUNT(q.id) as count')
            ->andWhere('YEAR(q.createdAt) = :year')
            ->setParameter('year', $year)
            ->groupBy('month')
            ->orderBy('month', 'ASC');

        if ($prestataire) {
            $qb->andWhere('q.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Montant moyen par statut
     */
    public function getAverageAmountByStatus(): array
    {
        return $this->createQueryBuilder('q')
            ->select('q.status, AVG(q.amount) as average_amount, COUNT(q.id) as count')
            ->groupBy('q.status')
            ->orderBy('average_amount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Devis par tranche de montant
     */
    public function getAmountDistribution(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                CASE 
                    WHEN amount < 50 THEN "0-50€"
                    WHEN amount < 100 THEN "50-100€"
                    WHEN amount < 200 THEN "100-200€"
                    WHEN amount < 500 THEN "200-500€"
                    WHEN amount < 1000 THEN "500-1000€"
                    ELSE "1000€+"
                END as amount_range,
                COUNT(*) as count,
                AVG(amount) as average_amount
            FROM quotes
            GROUP BY amount_range
            ORDER BY 
                CASE amount_range
                    WHEN "0-50€" THEN 1
                    WHEN "50-100€" THEN 2
                    WHEN "100-200€" THEN 3
                    WHEN "200-500€" THEN 4
                    WHEN "500-1000€" THEN 5
                    WHEN "1000€+" THEN 6
                END
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Prestataires avec le meilleur taux d'acceptation
     */
    public function getTopPrestatairesByAcceptanceRate(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                p.id,
                p.first_name,
                p.last_name,
                COUNT(q.id) as total_quotes,
                SUM(CASE WHEN q.status = "accepted" THEN 1 ELSE 0 END) as accepted_quotes,
                (SUM(CASE WHEN q.status = "accepted" THEN 1 ELSE 0 END) * 100.0 / COUNT(q.id)) as acceptance_rate
            FROM quotes q
            INNER JOIN prestataires p ON q.prestataire_id = p.id
            WHERE q.status IN ("sent", "accepted", "rejected", "expired")
            GROUP BY p.id
            HAVING total_quotes >= 5
            ORDER BY acceptance_rate DESC
            LIMIT :limit
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['limit' => $limit]);

        return $result->fetchAllAssociative();
    }

    /**
     * Devis les plus élevés
     */
    public function findHighestQuotes(int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->orderBy('q.amount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Devis avec le meilleur ratio prix/durée
     */
    public function findBestValueQuotes(int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.proposedDuration > 0')
            ->orderBy('(q.amount / q.proposedDuration)', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les demandes avec plusieurs devis concurrents
     */
    public function findWithMultipleQuotes(int $minQuotes = 3): array
    {
        return $this->createQueryBuilder('q')
            ->select('sr.id, COUNT(q.id) as quote_count')
            ->innerJoin('q.serviceRequest', 'sr')
            ->groupBy('sr.id')
            ->having('quote_count >= :minQuotes')
            ->setParameter('minQuotes', $minQuotes)
            ->orderBy('quote_count', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Écart de prix moyen entre les devis d'une même demande
     */
    public function getAveragePriceSpread(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                sr.id,
                sr.reference_number,
                COUNT(q.id) as quote_count,
                MIN(q.amount) as min_price,
                MAX(q.amount) as max_price,
                AVG(q.amount) as avg_price,
                (MAX(q.amount) - MIN(q.amount)) as price_spread
            FROM service_requests sr
            INNER JOIN quotes q ON q.service_request_id = sr.id
            GROUP BY sr.id
            HAVING quote_count > 1
            ORDER BY price_spread DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Devis avec notes/commentaires
     */
    public function findWithNotes(): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.notes IS NOT NULL')
            ->andWhere('q.notes != :empty')
            ->setParameter('empty', '')
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Délai moyen de validité des devis
     */
    public function getAverageValidityPeriod(): float
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT AVG(
                DATEDIFF(valid_until, created_at)
            ) as avg_days
            FROM quotes
            WHERE valid_until IS NOT NULL
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return round((float)($result->fetchOne() ?? 0), 2);
    }

    /**
     * Exporte les devis en CSV
     */
    public function exportToCsv(array $quotes): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'Référence Devis',
            'Référence Demande',
            'Prestataire',
            'Client',
            'Montant (€)',
            'Durée (min)',
            'Date Proposée',
            'Valide jusqu\'au',
            'Statut',
            'Créé le',
            'Accepté le',
            'Rejeté le'
        ]);

        // Données
        foreach ($quotes as $quote) {
            fputcsv($handle, [
                $quote->getReferenceNumber(),
                $quote->getServiceRequest()->getReferenceNumber(),
                $quote->getPrestataire()->getFullName(),
                $quote->getServiceRequest()->getClient()->getFullName(),
                $quote->getAmount(),
                $quote->getProposedDuration(),
                $quote->getProposedDate() ? $quote->getProposedDate()->format('d/m/Y H:i') : '',
                $quote->getValidUntil() ? $quote->getValidUntil()->format('d/m/Y') : '',
                ucfirst($quote->getStatus()),
                $quote->getCreatedAt()->format('d/m/Y H:i'),
                $quote->getAcceptedAt() ? $quote->getAcceptedAt()->format('d/m/Y H:i') : '',
                $quote->getRejectedAt() ? $quote->getRejectedAt()->format('d/m/Y H:i') : ''
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    /**
     * Devis récents (derniers jours)
     */
    public function findRecent(int $days = 7, int $limit = 20): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('q')
            ->andWhere('q.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('q.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Conversion devis -> réservations
     */
    public function getConversionToBooking(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                COUNT(q.id) as accepted_quotes,
                COUNT(b.id) as completed_bookings,
                (COUNT(b.id) * 100.0 / COUNT(q.id)) as conversion_rate
            FROM quotes q
            LEFT JOIN bookings b ON b.quote_id = q.id AND b.status = "completed"
            WHERE q.status = "accepted"
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAssociative();
    }

    /**
     * Catégories avec les devis les plus élevés
     */
    public function getHighestPaidCategories(int $limit = 10): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                c.id,
                c.name,
                AVG(q.amount) as average_quote,
                COUNT(q.id) as quote_count
            FROM quotes q
            INNER JOIN service_requests sr ON q.service_request_id = sr.id
            INNER JOIN service_categories c ON sr.category_id = c.id
            GROUP BY c.id
            ORDER BY average_quote DESC
            LIMIT :limit
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['limit' => $limit]);

        return $result->fetchAllAssociative();
    }

    /**
     * Devis avec la plus longue durée proposée
     */
    public function findLongestDuration(int $limit = 10): array
    {
        return $this->createQueryBuilder('q')
            ->andWhere('q.proposedDuration IS NOT NULL')
            ->orderBy('q.proposedDuration', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Analyse de la concurrence (devis perdus face à un concurrent)
     */
    public function getCompetitionAnalysis(Prestataire $prestataire): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                q_winner.prestataire_id as winner_id,
                p_winner.first_name,
                p_winner.last_name,
                COUNT(*) as times_won,
                AVG(q_loser.amount - q_winner.amount) as avg_price_difference
            FROM quotes q_loser
            INNER JOIN quotes q_winner ON q_loser.service_request_id = q_winner.service_request_id
            INNER JOIN prestataires p_winner ON q_winner.prestataire_id = p_winner.id
            WHERE q_loser.prestataire_id = :prestataireId
            AND q_loser.status = "rejected"
            AND q_winner.status = "accepted"
            GROUP BY q_winner.prestataire_id
            ORDER BY times_won DESC
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery(['prestataireId' => $prestataire->getId()]);

        return $result->fetchAllAssociative();
    }

    /**
     * Taux de transformation par jour de la semaine
     */
    public function getAcceptanceRateByWeekday(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        
        $sql = '
            SELECT 
                DAYNAME(created_at) as day_name,
                DAYOFWEEK(created_at) as day_number,
                COUNT(*) as total_quotes,
                SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) as accepted_quotes,
                (SUM(CASE WHEN status = "accepted" THEN 1 ELSE 0 END) * 100.0 / COUNT(*)) as acceptance_rate
            FROM quotes
            WHERE status IN ("sent", "accepted", "rejected", "expired")
            GROUP BY day_name, day_number
            ORDER BY day_number
        ';

        $stmt = $conn->prepare($sql);
        $result = $stmt->executeQuery();

        return $result->fetchAllAssociative();
    }

    /**
     * Devis en attente de réponse depuis plus de X jours
     */
    public function findPendingOlderThan(int $days = 7): array
    {
        $date = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('q')
            ->andWhere('q.status = :status')
            ->andWhere('q.createdAt < :date')
            ->setParameter('status', 'sent')
            ->setParameter('date', $date)
            ->orderBy('q.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}