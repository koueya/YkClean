<?php
// src/Repository/Document/DocumentRepository.php

namespace App\Repository\Document;

use App\Entity\Document\Document;
use App\Entity\User\Admin;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\User\User;
use App\Enum\DocumentStatus;
use App\Enum\DocumentType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Document
 * 
 * Gestion des documents des utilisateurs (Clients et Prestataires)
 * 
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Persiste un document
     */
    public function save(Document $document, bool $flush = false): void
    {
        $this->getEntityManager()->persist($document);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un document
     */
    public function remove(Document $document, bool $flush = false): void
    {
        $this->getEntityManager()->remove($document);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ============================================
    // RECHERCHE PAR UTILISATEUR (POLYMORPHE)
    // ============================================

    /**
     * Trouve tous les documents d'un utilisateur (Client ou Prestataire)
     */
    public function findByUser(User $user, ?DocumentType $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->orderBy('d.uploadedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les documents d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?DocumentType $type = null): array
    {
        return $this->findByUser($prestataire, $type);
    }

    /**
     * Trouve tous les documents d'un client
     */
    public function findByClient(Client $client, ?DocumentType $type = null): array
    {
        return $this->findByUser($client, $type);
    }

    /**
     * Trouve le dernier document d'un type pour un utilisateur
     */
    public function findLatestByUserAndType(User $user, DocumentType $type): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('d.uploadedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // RECHERCHE PAR TYPE D'UTILISATEUR
    // ============================================

    /**
     * Trouve tous les documents des clients
     */
    public function findAllClientDocuments(?DocumentType $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.userType = :userType')
            ->setParameter('userType', 'client')
            ->orderBy('d.uploadedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve tous les documents des prestataires
     */
    public function findAllPrestataireDocuments(?DocumentType $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.userType = :userType')
            ->setParameter('userType', 'prestataire')
            ->orderBy('d.uploadedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // RECHERCHE PAR STATUT
    // ============================================

    /**
     * Trouve les documents par statut
     */
    public function findByStatus(
        DocumentStatus $status, 
        ?DocumentType $type = null,
        ?string $userType = null
    ): array {
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', $status)
            ->orderBy('d.uploadedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        if ($userType) {
            $qb->andWhere('d.userType = :userType')
                ->setParameter('userType', $userType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents en attente de vérification
     */
    public function findPendingDocuments(
        ?DocumentType $type = null,
        ?string $userType = null,
        ?int $limit = null
    ): array {
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->andWhere('d.requiresVerification = true')
            ->setParameter('status', DocumentStatus::PENDING)
            ->orderBy('d.uploadedAt', 'ASC'); // Les plus anciens en premier

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        if ($userType) {
            $qb->andWhere('d.userType = :userType')
                ->setParameter('userType', $userType);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents vérifiés d'un utilisateur
     */
    public function findVerifiedByUser(User $user, ?DocumentType $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', DocumentStatus::APPROVED)
            ->orderBy('d.verifiedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents rejetés d'un utilisateur
     */
    public function findRejectedByUser(User $user, ?DocumentType $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', DocumentStatus::REJECTED)
            ->orderBy('d.uploadedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    // ============================================
    // GESTION DES EXPIRATIONS
    // ============================================

    /**
     * Trouve les documents expirés
     */
    public function findExpired(?string $userType = null): array
    {
        $now = new \DateTimeImmutable();

        $qb = $this->createQueryBuilder('d')
            ->where('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt < :now')
            ->andWhere('d.status = :status')
            ->setParameter('now', $now)
            ->setParameter('status', DocumentStatus::APPROVED)
            ->orderBy('d.expiresAt', 'ASC');

        if ($userType) {
            $qb->andWhere('d.userType = :userType')
                ->setParameter('userType', $userType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents expirant dans X jours
     */
    public function findExpiringInDays(int $days = 30, ?string $userType = null): array
    {
        $now = new \DateTimeImmutable();
        $endDate = $now->modify("+{$days} days");

        $qb = $this->createQueryBuilder('d')
            ->where('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt BETWEEN :now AND :endDate')
            ->andWhere('d.status = :status')
            ->setParameter('now', $now)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', DocumentStatus::APPROVED)
            ->orderBy('d.expiresAt', 'ASC');

        if ($userType) {
            $qb->andWhere('d.userType = :userType')
                ->setParameter('userType', $userType);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents d'un utilisateur qui expirent bientôt
     */
    public function findExpiringForUser(User $user, int $days = 30): array
    {
        $now = new \DateTimeImmutable();
        $endDate = $now->modify("+{$days} days");

        return $this->createQueryBuilder('d')
            ->where('d.user = :user')
            ->andWhere('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt BETWEEN :now AND :endDate')
            ->andWhere('d.status = :status')
            ->setParameter('user', $user)
            ->setParameter('now', $now)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', DocumentStatus::APPROVED)
            ->orderBy('d.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Marque les documents expirés
     */
    public function markExpiredDocuments(): int
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('d')
            ->update()
            ->set('d.status', ':expiredStatus')
            ->where('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt < :now')
            ->andWhere('d.status = :approvedStatus')
            ->setParameter('expiredStatus', DocumentStatus::EXPIRED)
            ->setParameter('now', $now)
            ->setParameter('approvedStatus', DocumentStatus::APPROVED)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // VALIDATION ET VÉRIFICATION
    // ============================================

    /**
     * Vérifie si un utilisateur a tous les documents requis
     */
    public function hasAllRequiredDocuments(User $user): bool
    {
        if ($user instanceof Prestataire) {
            return $this->hasAllRequiredPrestataireDocuments($user);
        } elseif ($user instanceof Client) {
            return $this->hasAllRequiredClientDocuments($user);
        }

        return false;
    }

    /**
     * Vérifie si un prestataire a tous les documents obligatoires
     */
    public function hasAllRequiredPrestataireDocuments(Prestataire $prestataire): bool
    {
        $requiredTypes = [
            DocumentType::IDENTITY_CARD,
            DocumentType::KBIS,
            DocumentType::INSURANCE,
        ];

        foreach ($requiredTypes as $type) {
            $document = $this->findLatestByUserAndType($prestataire, $type);

            if (!$document || $document->getStatus() !== DocumentStatus::APPROVED) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifie si un client a tous les documents obligatoires
     * (Peut varier selon les services demandés)
     */
    public function hasAllRequiredClientDocuments(Client $client): bool
    {
        // Pour l'instant, aucun document obligatoire pour les clients
        // Peut être ajusté selon les besoins
        return true;
    }

    /**
     * Compte les documents par statut pour un utilisateur
     */
    public function countByStatusForUser(User $user, DocumentStatus $status): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.user = :user')
            ->andWhere('d.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un utilisateur a un document spécifique vérifié
     */
    public function hasVerifiedDocument(User $user, DocumentType $type): bool
    {
        $document = $this->findLatestByUserAndType($user, $type);

        return $document && $document->getStatus() === DocumentStatus::APPROVED;
    }

    // ============================================
    // VÉRIFICATION AUTOMATIQUE
    // ============================================

    /**
     * Trouve les documents vérifiés automatiquement
     */
    public function findAutoVerified(?string $userType = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.isAutoVerified = true')
            ->orderBy('d.uploadedAt', 'DESC');

        if ($userType) {
            $qb->andWhere('d.userType = :userType')
                ->setParameter('userType', $userType);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents avec un score de vérification faible
     */
    public function findWithLowVerificationScore(int $threshold = 70): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.isAutoVerified = true')
            ->andWhere('d.autoVerificationScore < :threshold')
            ->setParameter('threshold', $threshold)
            ->orderBy('d.autoVerificationScore', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents par ID externe (Stripe, etc.)
     */
    public function findByExternalId(string $externalId): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.externalId = :externalId')
            ->setParameter('externalId', $externalId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Obtient les statistiques de documents pour un utilisateur
     */
    public function getStatisticsForUser(User $user): array
    {
        $total = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $pending = $this->countByStatusForUser($user, DocumentStatus::PENDING);
        $approved = $this->countByStatusForUser($user, DocumentStatus::APPROVED);
        $rejected = $this->countByStatusForUser($user, DocumentStatus::REJECTED);
        $expired = $this->countByStatusForUser($user, DocumentStatus::EXPIRED);

        $completionRate = $total > 0 ? round(($approved / $total) * 100, 2) : 0;

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'expired' => $expired,
            'completionRate' => $completionRate,
        ];
    }

    /**
     * Obtient les statistiques globales des documents
     */
    public function getGlobalStatistics(?string $userType = null): array
    {
        $qb = $this->createQueryBuilder('d');

        if ($userType) {
            $qb->where('d.userType = :userType')
               ->setParameter('userType', $userType);
        }

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', DocumentStatus::PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $approved = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', DocumentStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();

        $rejected = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', DocumentStatus::REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        $expired = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', DocumentStatus::EXPIRED)
            ->getQuery()
            ->getSingleScalarResult();

        $autoVerified = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.isAutoVerified = true')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'pending' => $pending,
            'approved' => $approved,
            'rejected' => $rejected,
            'expired' => $expired,
            'autoVerified' => $autoVerified,
            'userType' => $userType,
        ];
    }

    /**
     * Compte les documents par type
     */
    public function countByType(?string $userType = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->select('d.type, COUNT(d.id) as count')
            ->groupBy('d.type');

        if ($userType) {
            $qb->where('d.userType = :userType')
               ->setParameter('userType', $userType);
        }

        $result = $qb->getQuery()->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['type']->value] = (int) $row['count'];
        }

        return $counts;
    }

    // ============================================
    // ADMINISTRATION
    // ============================================

    /**
     * Trouve les documents vérifiés par un admin
     */
    public function findVerifiedByAdmin(Admin $admin, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.verifiedBy = :admin')
            ->setParameter('admin', $admin)
            ->orderBy('d.verifiedAt', 'DESC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les statistiques de vérification d'un admin
     */
    public function getAdminVerificationStats(Admin $admin): array
    {
        $total = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.verifiedBy = :admin')
            ->setParameter('admin', $admin)
            ->getQuery()
            ->getSingleScalarResult();

        $approved = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.verifiedBy = :admin')
            ->andWhere('d.status = :status')
            ->setParameter('admin', $admin)
            ->setParameter('status', DocumentStatus::APPROVED)
            ->getQuery()
            ->getSingleScalarResult();

        $rejected = (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.verifiedBy = :admin')
            ->andWhere('d.status = :status')
            ->setParameter('admin', $admin)
            ->setParameter('status', DocumentStatus::REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'approved' => $approved,
            'rejected' => $rejected,
            'approvalRate' => $total > 0 ? round(($approved / $total) * 100, 2) : 0,
        ];
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche avancée de documents avec multiples critères
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('d');

        // Filtre par type d'utilisateur
        if (isset($criteria['user_type'])) {
            $qb->andWhere('d.userType = :userType')
               ->setParameter('userType', $criteria['user_type']);
        }

        // Filtre par utilisateur spécifique
        if (isset($criteria['user_id'])) {
            $qb->andWhere('d.user = :userId')
               ->setParameter('userId', $criteria['user_id']);
        }

        // Filtre par type de document
        if (isset($criteria['type'])) {
            $qb->andWhere('d.type = :type')
               ->setParameter('type', $criteria['type']);
        }

        // Filtre par statut
        if (isset($criteria['status'])) {
            $qb->andWhere('d.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        // Filtre par statuts multiples
        if (isset($criteria['statuses']) && is_array($criteria['statuses'])) {
            $qb->andWhere('d.status IN (:statuses)')
               ->setParameter('statuses', $criteria['statuses']);
        }

        // Filtre par vérification requise
        if (isset($criteria['requires_verification'])) {
            $qb->andWhere('d.requiresVerification = :requiresVerification')
               ->setParameter('requiresVerification', $criteria['requires_verification']);
        }

        // Filtre par vérification automatique
        if (isset($criteria['is_auto_verified'])) {
            $qb->andWhere('d.isAutoVerified = :isAutoVerified')
               ->setParameter('isAutoVerified', $criteria['is_auto_verified']);
        }

        // Filtre par date de téléchargement
        if (isset($criteria['uploaded_after'])) {
            $qb->andWhere('d.uploadedAt >= :uploadedAfter')
               ->setParameter('uploadedAfter', $criteria['uploaded_after']);
        }

        if (isset($criteria['uploaded_before'])) {
            $qb->andWhere('d.uploadedAt <= :uploadedBefore')
               ->setParameter('uploadedBefore', $criteria['uploaded_before']);
        }

        // Filtre par date d'expiration
        if (isset($criteria['expires_after'])) {
            $qb->andWhere('d.expiresAt >= :expiresAfter')
               ->setParameter('expiresAfter', $criteria['expires_after']);
        }

        if (isset($criteria['expires_before'])) {
            $qb->andWhere('d.expiresAt <= :expiresBefore')
               ->setParameter('expiresBefore', $criteria['expires_before']);
        }

        // Filtre par admin vérificateur
        if (isset($criteria['verified_by'])) {
            $qb->andWhere('d.verifiedBy = :verifiedBy')
               ->setParameter('verifiedBy', $criteria['verified_by']);
        }

        // Filtre par nom de fichier
        if (isset($criteria['filename'])) {
            $qb->andWhere('d.fileName LIKE :filename')
               ->setParameter('filename', '%' . $criteria['filename'] . '%');
        }

        // Tri
        $orderBy = $criteria['order_by'] ?? 'uploadedAt';
        $orderDirection = $criteria['order_direction'] ?? 'DESC';
        
        $qb->orderBy('d.' . $orderBy, $orderDirection);

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
    // NETTOYAGE ET MAINTENANCE
    // ============================================

    /**
     * Supprime les documents rejetés anciens
     */
    public function cleanupOldRejectedDocuments(int $days = 90): int
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('d')
            ->delete()
            ->where('d.status = :status')
            ->andWhere('d.uploadedAt < :date')
            ->setParameter('status', DocumentStatus::REJECTED)
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les documents orphelins (utilisateur supprimé)
     */
    public function findOrphanDocuments(): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.user IS NULL')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // QUERY BUILDER PERSONNALISÉ
    // ============================================

    /**
     * Crée un QueryBuilder de base avec les jointures courantes
     */
    public function createBaseQueryBuilder(string $alias = 'd'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias . '.user', 'u')
            ->leftJoin($alias . '.verifiedBy', 'admin');
    }
}