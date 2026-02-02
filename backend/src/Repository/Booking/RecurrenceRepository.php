<?php
// src/Repository/Booking/RecurrenceRepository.php

namespace App\Repository\Booking;

use App\Entity\Booking\Booking;
use App\Entity\Booking\Recurrence;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Recurrence
 * Gère toutes les requêtes liées aux récurrences de réservations
 * 
 * @extends ServiceEntityRepository<Recurrence>
 */
class RecurrenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Recurrence::class);
    }

    /**
     * Persiste une récurrence
     */
    public function save(Recurrence $recurrence, bool $flush = false): void
    {
        $this->getEntityManager()->persist($recurrence);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une récurrence
     */
    public function remove(Recurrence $recurrence, bool $flush = false): void
    {
        $this->getEntityManager()->remove($recurrence);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ============================================
    // RECHERCHE PAR CLIENT
    // ============================================

    /**
     * Trouve toutes les récurrences d'un client
     */
    public function findByClient(Client $client, ?bool $isActive = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.client = :client')
            ->setParameter('client', $client)
            ->orderBy('r.createdAt', 'DESC');

        if ($isActive !== null) {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les récurrences actives d'un client
     */
    public function findActiveByClient(Client $client): array
    {
        return $this->findByClient($client, true);
    }

    /**
     * Compte le nombre de récurrences actives pour un client
     */
    public function countActiveByClient(Client $client): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.client = :client')
            ->andWhere('r.isActive = true')
            ->setParameter('client', $client)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ============================================
    // RECHERCHE PAR PRESTATAIRE
    // ============================================

    /**
     * Trouve toutes les récurrences d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?bool $isActive = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.nextOccurrence', 'ASC');

        if ($isActive !== null) {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les récurrences actives d'un prestataire
     */
    public function findActiveByPrestataire(Prestataire $prestataire): array
    {
        return $this->findByPrestataire($prestataire, true);
    }

    /**
     * Compte le nombre de récurrences actives pour un prestataire
     */
    public function countActiveByPrestataire(Prestataire $prestataire): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.prestataire = :prestataire')
            ->andWhere('r.isActive = true')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ============================================
    // RECHERCHE PAR FRÉQUENCE
    // ============================================

    /**
     * Trouve les récurrences par fréquence
     */
    public function findByFrequency(string $frequency, ?bool $isActive = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.frequency = :frequency')
            ->setParameter('frequency', $frequency)
            ->orderBy('r.nextOccurrence', 'ASC');

        if ($isActive !== null) {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // GÉNÉRATION DES PROCHAINES OCCURRENCES
    // ============================================

    /**
     * Trouve les récurrences nécessitant la génération de la prochaine occurrence
     * (nextOccurrence <= aujourd'hui + X jours)
     */
    public function findRequiringNextOccurrence(int $daysInAdvance = 30): array
    {
        $targetDate = new \DateTimeImmutable("+{$daysInAdvance} days");

        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->andWhere('r.nextOccurrence <= :targetDate OR r.nextOccurrence IS NULL')
            ->andWhere('r.endDate IS NULL OR r.endDate >= :now')
            ->setParameter('targetDate', $targetDate)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('r.nextOccurrence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les récurrences dont la prochaine occurrence est dans les X prochains jours
     */
    public function findWithUpcomingOccurrence(int $days = 7): array
    {
        $startDate = new \DateTimeImmutable();
        $endDate = new \DateTimeImmutable("+{$days} days");

        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->andWhere('r.nextOccurrence BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('r.nextOccurrence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les récurrences à traiter aujourd'hui
     */
    public function findDueToday(): array
    {
        $today = new \DateTimeImmutable();
        $todayStart = $today->setTime(0, 0, 0);
        $todayEnd = $today->setTime(23, 59, 59);

        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->andWhere('r.nextOccurrence BETWEEN :start AND :end')
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->orderBy('r.nextOccurrence', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // GESTION DES RÉCURRENCES EXPIRÉES
    // ============================================

    /**
     * Trouve les récurrences expirées (endDate dépassée)
     */
    public function findExpired(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->andWhere('r.endDate IS NOT NULL')
            ->andWhere('r.endDate < :now')
            ->setParameter('now', $now)
            ->orderBy('r.endDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Désactive toutes les récurrences expirées
     */
    public function deactivateExpired(): int
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('r')
            ->update()
            ->set('r.isActive', 'false')
            ->where('r.isActive = true')
            ->andWhere('r.endDate IS NOT NULL')
            ->andWhere('r.endDate < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les récurrences arrivant bientôt à expiration
     */
    public function findExpiringSoon(int $days = 7): array
    {
        $now = new \DateTimeImmutable();
        $expirationDate = new \DateTimeImmutable("+{$days} days");

        return $this->createQueryBuilder('r')
            ->where('r.isActive = true')
            ->andWhere('r.endDate IS NOT NULL')
            ->andWhere('r.endDate BETWEEN :now AND :expirationDate')
            ->setParameter('now', $now)
            ->setParameter('expirationDate', $expirationDate)
            ->orderBy('r.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR JOUR DE LA SEMAINE/MOIS
    // ============================================

    /**
     * Trouve les récurrences hebdomadaires par jour de la semaine
     */
    public function findWeeklyByDayOfWeek(int $dayOfWeek, ?bool $isActive = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.frequency = :frequency')
            ->andWhere('r.dayOfWeek = :dayOfWeek')
            ->setParameter('frequency', Recurrence::FREQUENCY_WEEKLY)
            ->setParameter('dayOfWeek', $dayOfWeek);

        if ($isActive !== null) {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb->orderBy('r.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les récurrences mensuelles par jour du mois
     */
    public function findMonthlyByDayOfMonth(int $dayOfMonth, ?bool $isActive = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.frequency = :frequency')
            ->andWhere('r.dayOfMonth = :dayOfMonth')
            ->setParameter('frequency', Recurrence::FREQUENCY_MONTHLY)
            ->setParameter('dayOfMonth', $dayOfMonth);

        if ($isActive !== null) {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', $isActive);
        }

        return $qb->orderBy('r.time', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Compte les récurrences par fréquence
     */
    public function countByFrequency(): array
    {
        $result = $this->createQueryBuilder('r')
            ->select('r.frequency, COUNT(r.id) as count')
            ->where('r.isActive = true')
            ->groupBy('r.frequency')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['frequency']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Calcule le revenu mensuel récurrent total
     */
    public function getTotalMonthlyRecurringRevenue(): float
    {
        $weeklyRevenue = $this->getRevenueByFrequency(Recurrence::FREQUENCY_WEEKLY);
        $biweeklyRevenue = $this->getRevenueByFrequency(Recurrence::FREQUENCY_BIWEEKLY);
        $monthlyRevenue = $this->getRevenueByFrequency(Recurrence::FREQUENCY_MONTHLY);

        // Conversion en revenu mensuel
        $totalMonthly = ($weeklyRevenue * 4.33) + // 4.33 semaines par mois
                       ($biweeklyRevenue * 2.17) + // ~2.17 fois par mois
                       $monthlyRevenue;

        return round($totalMonthly, 2);
    }

    /**
     * Calcule le revenu total pour une fréquence donnée
     */
    private function getRevenueByFrequency(string $frequency): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.amount) as total')
            ->where('r.isActive = true')
            ->andWhere('r.frequency = :frequency')
            ->setParameter('frequency', $frequency)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Obtient les statistiques détaillées pour un prestataire
     */
    public function getStatsForPrestataire(Prestataire $prestataire): array
    {
        $activeRecurrences = $this->findActiveByPrestataire($prestataire);
        
        $stats = [
            'total_active' => count($activeRecurrences),
            'by_frequency' => [
                Recurrence::FREQUENCY_WEEKLY => 0,
                Recurrence::FREQUENCY_BIWEEKLY => 0,
                Recurrence::FREQUENCY_MONTHLY => 0,
            ],
            'monthly_revenue' => 0,
            'total_bookings' => 0,
        ];

        foreach ($activeRecurrences as $recurrence) {
            $frequency = $recurrence->getFrequency();
            
            if (isset($stats['by_frequency'][$frequency])) {
                $stats['by_frequency'][$frequency]++;
            }

            // Calcul du revenu mensuel estimé
            $amount = $recurrence->getAmount();
            switch ($frequency) {
                case Recurrence::FREQUENCY_WEEKLY:
                    $stats['monthly_revenue'] += $amount * 4.33;
                    break;
                case Recurrence::FREQUENCY_BIWEEKLY:
                    $stats['monthly_revenue'] += $amount * 2.17;
                    break;
                case Recurrence::FREQUENCY_MONTHLY:
                    $stats['monthly_revenue'] += $amount;
                    break;
            }

            // Compte le nombre total de réservations générées
            $stats['total_bookings'] += count($recurrence->getBookings());
        }

        $stats['monthly_revenue'] = round($stats['monthly_revenue'], 2);

        return $stats;
    }

    /**
     * Obtient les statistiques détaillées pour un client
     */
    public function getStatsForClient(Client $client): array
    {
        $activeRecurrences = $this->findActiveByClient($client);
        
        $stats = [
            'total_active' => count($activeRecurrences),
            'by_frequency' => [
                Recurrence::FREQUENCY_WEEKLY => 0,
                Recurrence::FREQUENCY_BIWEEKLY => 0,
                Recurrence::FREQUENCY_MONTHLY => 0,
            ],
            'monthly_cost' => 0,
            'total_bookings' => 0,
            'next_occurrences' => [],
        ];

        foreach ($activeRecurrences as $recurrence) {
            $frequency = $recurrence->getFrequency();
            
            if (isset($stats['by_frequency'][$frequency])) {
                $stats['by_frequency'][$frequency]++;
            }

            // Calcul du coût mensuel estimé
            $amount = $recurrence->getAmount();
            switch ($frequency) {
                case Recurrence::FREQUENCY_WEEKLY:
                    $stats['monthly_cost'] += $amount * 4.33;
                    break;
                case Recurrence::FREQUENCY_BIWEEKLY:
                    $stats['monthly_cost'] += $amount * 2.17;
                    break;
                case Recurrence::FREQUENCY_MONTHLY:
                    $stats['monthly_cost'] += $amount;
                    break;
            }

            // Compte le nombre total de réservations générées
            $stats['total_bookings'] += count($recurrence->getBookings());

            // Ajoute la prochaine occurrence
            if ($recurrence->getNextOccurrence()) {
                $stats['next_occurrences'][] = [
                    'id' => $recurrence->getId(),
                    'date' => $recurrence->getNextOccurrence(),
                    'prestataire' => $recurrence->getPrestataire()->getFullName(),
                ];
            }
        }

        $stats['monthly_cost'] = round($stats['monthly_cost'], 2);

        // Trier les prochaines occurrences par date
        usort($stats['next_occurrences'], function($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return $stats;
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche avancée de récurrences avec multiples critères
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('r');

        // Filtre par client
        if (isset($criteria['client_id'])) {
            $qb->andWhere('r.client = :clientId')
               ->setParameter('clientId', $criteria['client_id']);
        }

        // Filtre par prestataire
        if (isset($criteria['prestataire_id'])) {
            $qb->andWhere('r.prestataire = :prestataireId')
               ->setParameter('prestataireId', $criteria['prestataire_id']);
        }

        // Filtre par statut actif
        if (isset($criteria['is_active'])) {
            $qb->andWhere('r.isActive = :isActive')
               ->setParameter('isActive', $criteria['is_active']);
        }

        // Filtre par fréquence
        if (isset($criteria['frequency'])) {
            $qb->andWhere('r.frequency = :frequency')
               ->setParameter('frequency', $criteria['frequency']);
        }

        // Filtre par fréquences multiples
        if (isset($criteria['frequencies']) && is_array($criteria['frequencies'])) {
            $qb->andWhere('r.frequency IN (:frequencies)')
               ->setParameter('frequencies', $criteria['frequencies']);
        }

        // Filtre par catégorie de service
        if (isset($criteria['service_category'])) {
            $qb->andWhere('r.serviceCategory = :category')
               ->setParameter('category', $criteria['service_category']);
        }

        // Filtre par jour de la semaine
        if (isset($criteria['day_of_week'])) {
            $qb->andWhere('r.dayOfWeek = :dayOfWeek')
               ->setParameter('dayOfWeek', $criteria['day_of_week']);
        }

        // Filtre par jour du mois
        if (isset($criteria['day_of_month'])) {
            $qb->andWhere('r.dayOfMonth = :dayOfMonth')
               ->setParameter('dayOfMonth', $criteria['day_of_month']);
        }

        // Filtre par date de début
        if (isset($criteria['start_date_from'])) {
            $qb->andWhere('r.startDate >= :startDateFrom')
               ->setParameter('startDateFrom', $criteria['start_date_from']);
        }

        // Filtre par date de fin
        if (isset($criteria['end_date_before'])) {
            $qb->andWhere('r.endDate <= :endDateBefore OR r.endDate IS NULL')
               ->setParameter('endDateBefore', $criteria['end_date_before']);
        }

        // Filtre par montant minimum
        if (isset($criteria['min_amount'])) {
            $qb->andWhere('r.amount >= :minAmount')
               ->setParameter('minAmount', $criteria['min_amount']);
        }

        // Filtre par montant maximum
        if (isset($criteria['max_amount'])) {
            $qb->andWhere('r.amount <= :maxAmount')
               ->setParameter('maxAmount', $criteria['max_amount']);
        }

        // Filtre par ville
        if (isset($criteria['city'])) {
            $qb->andWhere('r.city LIKE :city')
               ->setParameter('city', '%' . $criteria['city'] . '%');
        }

        // Filtre par prochaine occurrence
        if (isset($criteria['next_occurrence_from'])) {
            $qb->andWhere('r.nextOccurrence >= :nextOccurrenceFrom')
               ->setParameter('nextOccurrenceFrom', $criteria['next_occurrence_from']);
        }

        if (isset($criteria['next_occurrence_to'])) {
            $qb->andWhere('r.nextOccurrence <= :nextOccurrenceTo')
               ->setParameter('nextOccurrenceTo', $criteria['next_occurrence_to']);
        }

        // Tri
        $orderBy = $criteria['order_by'] ?? 'nextOccurrence';
        $orderDirection = $criteria['order_direction'] ?? 'ASC';
        
        $qb->orderBy('r.' . $orderBy, $orderDirection);

        // Limite
        if (isset($criteria['limit'])) {
            $qb->setMaxResults($criteria['limit']);
        }

        // Offset
        if (isset($criteria['offset'])) {
            $qb->setFirstResult($criteria['offset']);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // MÉTHODES DE NETTOYAGE ET MAINTENANCE
    // ============================================

    /**
     * Trouve les récurrences inactives depuis X jours
     */
    public function findInactiveSince(int $days = 90): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('r')
            ->where('r.isActive = false')
            ->andWhere('r.updatedAt < :date')
            ->setParameter('date', $date)
            ->orderBy('r.updatedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nettoie les récurrences inactives anciennes
     */
    public function cleanupOldInactive(int $days = 180): int
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('r')
            ->delete()
            ->where('r.isActive = false')
            ->andWhere('r.updatedAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Met à jour la prochaine occurrence d'une récurrence
     */
    public function updateNextOccurrence(Recurrence $recurrence, \DateTimeInterface $nextOccurrence): void
    {
        $recurrence->setNextOccurrence($nextOccurrence);
        $this->getEntityManager()->flush();
    }

    // ============================================
    // QUERY BUILDER PERSONNALISÉ
    // ============================================

    /**
     * Crée un QueryBuilder de base avec les jointures courantes
     */
    public function createBaseQueryBuilder(string $alias = 'r'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias . '.client', 'c')
            ->leftJoin($alias . '.prestataire', 'p')
            ->leftJoin($alias . '.serviceCategory', 'sc')
            ->leftJoin($alias . '.bookings', 'b');
    }

    // ============================================
    // VALIDATION ET VÉRIFICATION
    // ============================================

    /**
     * Vérifie si un client a déjà une récurrence active 
     * avec un prestataire pour une même catégorie
     */
    public function hasActiveRecurrence(
        Client $client,
        Prestataire $prestataire,
        $serviceCategory
    ): bool {
        $count = $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.client = :client')
            ->andWhere('r.prestataire = :prestataire')
            ->andWhere('r.serviceCategory = :category')
            ->andWhere('r.isActive = true')
            ->setParameter('client', $client)
            ->setParameter('prestataire', $prestataire)
            ->setParameter('category', $serviceCategory)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Compte le nombre total de réservations générées par toutes les récurrences
     */
    public function getTotalGeneratedBookings(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(b.id)')
            ->leftJoin('r.bookings', 'b')
            ->where('r.isActive = true')
            ->getQuery()
            ->getSingleScalarResult();
    }
}