<?php
// src/Repository/Planning/AbsenceRepository.php

namespace App\Repository\Planning;

use App\Entity\Planning\Absence;
use App\Entity\User\Admin;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Absence
 * Gère toutes les requêtes liées aux absences et indisponibilités des prestataires
 * 
 * @extends ServiceEntityRepository<Absence>
 */
class AbsenceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Absence::class);
    }

    /**
     * Persiste une absence
     */
    public function save(Absence $absence, bool $flush = false): void
    {
        $this->getEntityManager()->persist($absence);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une absence
     */
    public function remove(Absence $absence, bool $flush = false): void
    {
        $this->getEntityManager()->remove($absence);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ============================================
    // RECHERCHE PAR PRESTATAIRE
    // ============================================

    /**
     * Trouve toutes les absences d'un prestataire
     */
    public function findByPrestataire(
        Prestataire $prestataire,
        ?string $status = null,
        ?string $type = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('a.startDate', 'DESC');

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($type) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les absences actives (en cours) d'un prestataire
     */
    public function findActiveByPrestataire(Prestataire $prestataire): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :now')
            ->andWhere('a.endDate >= :now')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences futures d'un prestataire
     */
    public function findFutureByPrestataire(Prestataire $prestataire, int $limit = null): array
    {
        $now = new \DateTimeImmutable();
        
        $qb = $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.startDate > :now')
            ->andWhere('a.status IN (:validStatuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->setParameter('validStatuses', [
                Absence::STATUS_PENDING,
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->orderBy('a.startDate', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les absences passées d'un prestataire
     */
    public function findPastByPrestataire(Prestataire $prestataire, int $limit = 20): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.endDate < :now')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->orderBy('a.endDate', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les absences d'un prestataire
     */
    public function countByPrestataire(
        Prestataire $prestataire,
        ?string $status = null,
        ?string $type = null
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        if ($type) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $type);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    // ============================================
    // RECHERCHE PAR PÉRIODE
    // ============================================

    /**
     * Trouve les absences entre deux dates
     */
    public function findBetweenDates(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?string $status = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :endDate')
            ->andWhere('a.endDate >= :startDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('a.startDate', 'ASC');

        if ($status) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $status);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Vérifie si un prestataire est absent à une date donnée
     */
    public function isAbsentOnDate(
        Prestataire $prestataire,
        \DateTimeInterface $date
    ): bool {
        $count = $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :date')
            ->andWhere('a.endDate >= :date')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Trouve les absences se chevauchant avec une période
     */
    public function findOverlapping(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?int $excludeId = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.startDate <= :endDate')
            ->andWhere('a.endDate >= :startDate')
            ->andWhere('a.status != :cancelled')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('cancelled', Absence::STATUS_CANCELLED);

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
               ->setParameter('excludeId', $excludeId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les absences du jour
     */
    public function findToday(?Prestataire $prestataire = null): array
    {
        $today = new \DateTimeImmutable();
        $todayStart = $today->setTime(0, 0, 0);
        $todayEnd = $today->setTime(23, 59, 59);

        $qb = $this->createQueryBuilder('a')
            ->where('a.startDate <= :todayEnd')
            ->andWhere('a.endDate >= :todayStart')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('todayStart', $todayStart)
            ->setParameter('todayEnd', $todayEnd)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ]);

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR STATUT
    // ============================================

    /**
     * Trouve les absences par statut
     */
    public function findByStatus(string $status, ?Prestataire $prestataire = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', $status)
            ->orderBy('a.startDate', 'DESC');

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les absences en attente d'approbation
     */
    public function findPending(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.status = :status')
            ->setParameter('status', Absence::STATUS_PENDING)
            ->orderBy('a.createdAt', 'ASC'); // Les plus anciennes en premier

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les absences approuvées
     */
    public function findApproved(?Prestataire $prestataire = null): array
    {
        return $this->findByStatus(Absence::STATUS_APPROVED, $prestataire);
    }

    /**
     * Trouve les absences rejetées
     */
    public function findRejected(?Prestataire $prestataire = null): array
    {
        return $this->findByStatus(Absence::STATUS_REJECTED, $prestataire);
    }

    // ============================================
    // RECHERCHE PAR TYPE
    // ============================================

    /**
     * Trouve les absences par type
     */
    public function findByType(
        string $type,
        ?Prestataire $prestataire = null,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->where('a.type = :type')
            ->setParameter('type', $type)
            ->orderBy('a.startDate', 'DESC');

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les absences par type pour un prestataire
     */
    public function countByTypeForPrestataire(Prestataire $prestataire): array
    {
        $result = $this->createQueryBuilder('a')
            ->select('a.type, COUNT(a.id) as count')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->groupBy('a.type')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['type']] = (int) $row['count'];
        }

        return $counts;
    }

    // ============================================
    // GESTION DES REMPLACEMENTS
    // ============================================

    /**
     * Trouve les absences nécessitant un remplacement
     */
    public function findRequiringReplacement(?Prestataire $prestataire = null): array
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('a')
            ->where('a.requiresReplacement = true')
            ->andWhere('a.endDate >= :now')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('now', $now)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->orderBy('a.startDate', 'ASC');

        if ($prestataire) {
            $qb->andWhere('a.prestataire = :prestataire')
               ->setParameter('prestataire', $prestataire);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les absences qui affectent des réservations
     */
    public function findAffectingBookings(Prestataire $prestataire): array
    {
        $now = new \DateTimeImmutable();
        
        return $this->createQueryBuilder('a')
            ->innerJoin('a.affectedBookings', 'b')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.endDate >= :now')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', $now)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // VALIDATION
    // ============================================

    /**
     * Valide une période d'absence (vérifie les chevauchements)
     */
    public function validateAbsencePeriod(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
        ?int $excludeId = null
    ): array {
        $overlapping = $this->findOverlapping($prestataire, $startDate, $endDate, $excludeId);

        return [
            'is_valid' => empty($overlapping),
            'conflicts' => $overlapping,
            'conflict_count' => count($overlapping)
        ];
    }

    /**
     * Vérifie si une absence peut être approuvée
     */
    public function canApprove(Absence $absence): array
    {
        $errors = [];

        // Vérifier que l'absence n'est pas déjà terminée
        $now = new \DateTimeImmutable();
        if ($absence->getEndDate() < $now) {
            $errors[] = 'Cannot approve past absence';
        }

        // Vérifier les chevauchements
        $validation = $this->validateAbsencePeriod(
            $absence->getPrestataire(),
            $absence->getStartDate(),
            $absence->getEndDate(),
            $absence->getId()
        );

        if (!$validation['is_valid']) {
            $errors[] = 'Absence overlaps with existing absence(s)';
        }

        return [
            'can_approve' => empty($errors),
            'errors' => $errors
        ];
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Calcule le total de jours d'absence pour un prestataire
     */
    public function getTotalDaysForPrestataire(
        Prestataire $prestataire,
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): int {
        $qb = $this->createQueryBuilder('a')
            ->select('SUM(DATEDIFF(a.endDate, a.startDate) + 1)')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.status IN (:validStatuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('validStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ]);

        if ($startDate) {
            $qb->andWhere('a.startDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('a.endDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Obtient les statistiques d'absences pour un prestataire
     */
    public function getStatisticsForPrestataire(
        Prestataire $prestataire,
        ?int $year = null
    ): array {
        $year = $year ?? (int) date('Y');
        $startDate = new \DateTimeImmutable("{$year}-01-01");
        $endDate = new \DateTimeImmutable("{$year}-12-31");

        $totalDays = $this->getTotalDaysForPrestataire($prestataire, $startDate, $endDate);
        
        $byType = $this->createQueryBuilder('a')
            ->select('a.type, COUNT(a.id) as count, SUM(DATEDIFF(a.endDate, a.startDate) + 1) as total_days')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.startDate >= :startDate')
            ->andWhere('a.endDate <= :endDate')
            ->andWhere('a.status IN (:validStatuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('validStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->groupBy('a.type')
            ->getQuery()
            ->getResult();

        $typeStats = [];
        foreach ($byType as $stat) {
            $typeStats[$stat['type']] = [
                'count' => (int) $stat['count'],
                'total_days' => (int) $stat['total_days']
            ];
        }

        $total = $this->countByPrestataire($prestataire);
        $pending = $this->countByPrestataire($prestataire, Absence::STATUS_PENDING);
        $approved = $this->countByPrestataire($prestataire, Absence::STATUS_APPROVED);

        return [
            'year' => $year,
            'total_absences' => $total,
            'total_days' => $totalDays,
            'pending' => $pending,
            'approved' => $approved,
            'by_type' => $typeStats,
        ];
    }

    /**
     * Obtient les statistiques globales (admin)
     */
    public function getGlobalStatistics(): array
    {
        $now = new \DateTimeImmutable();

        $total = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.status = :status')
            ->setParameter('status', Absence::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $active = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.startDate <= :now')
            ->andWhere('a.endDate >= :now')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('now', $now)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->getQuery()
            ->getSingleScalarResult();

        $requiresReplacement = (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.requiresReplacement = true')
            ->andWhere('a.endDate >= :now')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('now', $now)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'pending' => $pending,
            'active' => $active,
            'requires_replacement' => $requiresReplacement,
        ];
    }

    /**
     * Compte les absences par prestataire (pour admin)
     */
    public function countByPrestataires(
        ?\DateTimeInterface $startDate = null,
        ?\DateTimeInterface $endDate = null
    ): array {
        $qb = $this->createQueryBuilder('a')
            ->select('p.id, p.firstName, p.lastName, COUNT(a.id) as absence_count, SUM(DATEDIFF(a.endDate, a.startDate) + 1) as total_days')
            ->innerJoin('a.prestataire', 'p')
            ->groupBy('p.id');

        if ($startDate) {
            $qb->andWhere('a.startDate >= :startDate')
               ->setParameter('startDate', $startDate);
        }

        if ($endDate) {
            $qb->andWhere('a.endDate <= :endDate')
               ->setParameter('endDate', $endDate);
        }

        return $qb->orderBy('total_days', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // ALERTES ET NOTIFICATIONS
    // ============================================

    /**
     * Trouve les absences se terminant bientôt
     */
    public function findEndingSoon(int $days = 7): array
    {
        $now = new \DateTimeImmutable();
        $threshold = $now->modify("+{$days} days");

        return $this->createQueryBuilder('a')
            ->where('a.endDate BETWEEN :now AND :threshold')
            ->andWhere('a.status IN (:activeStatuses)')
            ->setParameter('now', $now)
            ->setParameter('threshold', $threshold)
            ->setParameter('activeStatuses', [
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->orderBy('a.endDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences commençant bientôt
     */
    public function findStartingSoon(int $days = 7): array
    {
        $now = new \DateTimeImmutable();
        $threshold = $now->modify("+{$days} days");

        return $this->createQueryBuilder('a')
            ->where('a.startDate BETWEEN :now AND :threshold')
            ->andWhere('a.status IN (:validStatuses)')
            ->setParameter('now', $now)
            ->setParameter('threshold', $threshold)
            ->setParameter('validStatuses', [
                Absence::STATUS_PENDING,
                Absence::STATUS_APPROVED
            ])
            ->orderBy('a.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les absences longues (plus de X jours)
     */
    public function findLongAbsences(int $minDays = 7): array
    {
        return $this->createQueryBuilder('a')
            ->where('DATEDIFF(a.endDate, a.startDate) >= :minDays')
            ->andWhere('a.status IN (:validStatuses)')
            ->setParameter('minDays', $minDays)
            ->setParameter('validStatuses', [
                Absence::STATUS_PENDING,
                Absence::STATUS_APPROVED,
                Absence::STATUS_ACTIVE
            ])
            ->orderBy('a.startDate', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // ADMINISTRATION
    // ============================================

    /**
     * Approuve une absence
     */
    public function approve(Absence $absence, Admin $admin): void
    {
        $absence->setStatus(Absence::STATUS_APPROVED);
        $absence->setApprovedBy($admin);
        $absence->setApprovedAt(new \DateTimeImmutable());

        $this->getEntityManager()->flush();
    }

    /**
     * Rejette une absence
     */
    public function reject(Absence $absence, Admin $admin, string $reason): void
    {
        $absence->setStatus(Absence::STATUS_REJECTED);
        $absence->setApprovedBy($admin);
        $absence->setRejectionReason($reason);
        $absence->setApprovedAt(new \DateTimeImmutable());

        $this->getEntityManager()->flush();
    }

    /**
     * Trouve les absences approuvées par un admin
     */
    public function findApprovedByAdmin(Admin $admin, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.approvedBy = :admin')
            ->setParameter('admin', $admin)
            ->orderBy('a.approvedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // UTILITAIRES
    // ============================================

    /**
     * Trouve les périodes sans absence (disponibles)
     */
    public function findAvailablePeriods(
        Prestataire $prestataire,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        $absences = $this->findBetweenDates($prestataire, $startDate, $endDate, Absence::STATUS_APPROVED);

        if (empty($absences)) {
            return [[
                'start' => $startDate,
                'end' => $endDate,
                'duration' => $startDate->diff($endDate)->days + 1
            ]];
        }

        // Trier par date de début
        usort($absences, fn($a, $b) => $a->getStartDate() <=> $b->getStartDate());

        $periods = [];
        $currentDate = clone $startDate;

        foreach ($absences as $absence) {
            if ($absence->getStartDate() > $currentDate) {
                $periodEnd = (clone $absence->getStartDate())->modify('-1 day');
                $periods[] = [
                    'start' => clone $currentDate,
                    'end' => $periodEnd,
                    'duration' => $currentDate->diff($periodEnd)->days + 1
                ];
            }

            $nextDay = (clone $absence->getEndDate())->modify('+1 day');
            if ($nextDay > $currentDate) {
                $currentDate = $nextDay;
            }
        }

        // Période après la dernière absence
        if ($currentDate <= $endDate) {
            $periods[] = [
                'start' => clone $currentDate,
                'end' => clone $endDate,
                'duration' => $currentDate->diff($endDate)->days + 1
            ];
        }

        return $periods;
    }

    /**
     * Export CSV des absences
     */
    public function exportToCsv(array $absences): string
    {
        $handle = fopen('php://temp', 'r+');
        
        // En-têtes
        fputcsv($handle, [
            'ID',
            'Prestataire',
            'Type',
            'Statut',
            'Date début',
            'Date fin',
            'Durée (jours)',
            'Raison',
            'Remplacement requis',
            'Approuvé par',
            'Date création'
        ]);

        // Données
        foreach ($absences as $absence) {
            $duration = $absence->getStartDate()->diff($absence->getEndDate())->days + 1;
            
            fputcsv($handle, [
                $absence->getId(),
                $absence->getPrestataire()->getFullName(),
                $absence->getType(),
                $absence->getStatus(),
                $absence->getStartDate()->format('d/m/Y'),
                $absence->getEndDate()->format('d/m/Y'),
                $duration,
                $absence->getReason() ?? '',
                $absence->requiresReplacement() ? 'Oui' : 'Non',
                $absence->getApprovedBy()?->getFullName() ?? '',
                $absence->getCreatedAt()->format('d/m/Y H:i')
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return $csv;
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche avancée d'absences avec multiples critères
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('a');

        // Filtre par prestataire
        if (isset($criteria['prestataire_id'])) {
            $qb->andWhere('a.prestataire = :prestataireId')
               ->setParameter('prestataireId', $criteria['prestataire_id']);
        }

        // Filtre par type
        if (isset($criteria['type'])) {
            $qb->andWhere('a.type = :type')
               ->setParameter('type', $criteria['type']);
        }

        // Filtre par statut
        if (isset($criteria['status'])) {
            $qb->andWhere('a.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        // Filtre par statuts multiples
        if (isset($criteria['statuses']) && is_array($criteria['statuses'])) {
            $qb->andWhere('a.status IN (:statuses)')
               ->setParameter('statuses', $criteria['statuses']);
        }

        // Filtre par remplacement requis
        if (isset($criteria['requires_replacement'])) {
            $qb->andWhere('a.requiresReplacement = :requiresReplacement')
               ->setParameter('requiresReplacement', $criteria['requires_replacement']);
        }

        // Filtre par période
        if (isset($criteria['start_date_from'])) {
            $qb->andWhere('a.startDate >= :startDateFrom')
               ->setParameter('startDateFrom', $criteria['start_date_from']);
        }

        if (isset($criteria['start_date_to'])) {
            $qb->andWhere('a.startDate <= :startDateTo')
               ->setParameter('startDateTo', $criteria['start_date_to']);
        }

        if (isset($criteria['end_date_from'])) {
            $qb->andWhere('a.endDate >= :endDateFrom')
               ->setParameter('endDateFrom', $criteria['end_date_from']);
        }

        if (isset($criteria['end_date_to'])) {
            $qb->andWhere('a.endDate <= :endDateTo')
               ->setParameter('endDateTo', $criteria['end_date_to']);
        }

        // Filtre par durée minimale
        if (isset($criteria['min_duration_days'])) {
            $qb->andWhere('DATEDIFF(a.endDate, a.startDate) >= :minDuration')
               ->setParameter('minDuration', $criteria['min_duration_days']);
        }

        // Filtre par admin approbateur
        if (isset($criteria['approved_by'])) {
            $qb->andWhere('a.approvedBy = :approvedBy')
               ->setParameter('approvedBy', $criteria['approved_by']);
        }

        // Tri
        $orderBy = $criteria['order_by'] ?? 'startDate';
        $orderDirection = $criteria['order_direction'] ?? 'DESC';
        
        $qb->orderBy('a.' . $orderBy, $orderDirection);

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
    // QUERY BUILDER PERSONNALISÉ
    // ============================================

    /**
     * Crée un QueryBuilder de base avec les jointures courantes
     */
    public function createBaseQueryBuilder(string $alias = 'a'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias . '.prestataire', 'p')
            ->leftJoin($alias . '.approvedBy', 'admin')
            ->leftJoin($alias . '.affectedBookings', 'b');
    }
}