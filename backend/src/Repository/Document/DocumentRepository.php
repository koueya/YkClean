<?php

namespace App\Repository\Document;

use App\Entity\Document\Document;
use App\Entity\User\Prestataire;
use App\Entity\User\Admin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Document
 * 
 * Gestion des documents des prestataires (KBIS, assurance, identité, diplômes)
 * 
 * @extends ServiceEntityRepository<Document>
 */
class DocumentRepository extends ServiceEntityRepository
{
    // Types de documents
    public const TYPE_IDENTITY_CARD = 'identity_card';
    public const TYPE_KBIS = 'kbis';
    public const TYPE_INSURANCE = 'insurance';
    public const TYPE_DIPLOMA = 'diploma';
    public const TYPE_CRIMINAL_RECORD = 'criminal_record';
    public const TYPE_TAX_CERTIFICATE = 'tax_certificate';
    public const TYPE_BANK_DETAILS = 'bank_details';
    public const TYPE_OTHER = 'other';

    // Statuts de document
    public const STATUS_PENDING = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';
    public const STATUS_EXPIRED = 'expired';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Document::class);
    }

    /**
     * Trouve tous les documents d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('d.uploadedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents par statut
     */
    public function findByStatus(string $status, ?string $type = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', $status)
            ->orderBy('d.uploadedAt', 'DESC');

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents en attente de vérification
     */
    public function findPendingDocuments(?string $type = null, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->orderBy('d.uploadedAt', 'ASC'); // Les plus anciens en premier

        if ($type) {
            $qb->andWhere('d.type = :type')
                ->setParameter('type', $type);
        }

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les documents vérifiés d'un prestataire
     */
    public function findVerifiedByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->andWhere('d.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', self::STATUS_VERIFIED)
            ->orderBy('d.verifiedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents rejetés d'un prestataire
     */
    public function findRejectedByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->andWhere('d.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', self::STATUS_REJECTED)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents expirés ou qui vont expirer
     */
    public function findExpiredOrExpiring(\DateTimeInterface $date = null): array
    {
        $date = $date ?? new \DateTime();

        return $this->createQueryBuilder('d')
            ->where('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt <= :date')
            ->andWhere('d.status = :status')
            ->setParameter('date', $date)
            ->setParameter('status', self::STATUS_VERIFIED)
            ->orderBy('d.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les documents qui vont expirer dans X jours
     */
    public function findExpiringInDays(int $days = 30): array
    {
        $startDate = new \DateTime();
        $endDate = new \DateTime("+{$days} days");

        return $this->createQueryBuilder('d')
            ->where('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt BETWEEN :startDate AND :endDate')
            ->andWhere('d.status = :status')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->setParameter('status', self::STATUS_VERIFIED)
            ->orderBy('d.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le dernier document d'un type pour un prestataire
     */
    public function findLatestByType(Prestataire $prestataire, string $type): ?Document
    {
        return $this->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->andWhere('d.type = :type')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('type', $type)
            ->orderBy('d.uploadedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un prestataire a tous les documents requis
     */
    public function hasAllRequiredDocuments(Prestataire $prestataire): bool
    {
        $requiredTypes = [
            self::TYPE_IDENTITY_CARD,
            self::TYPE_KBIS,
            self::TYPE_INSURANCE,
        ];

        foreach ($requiredTypes as $type) {
            $document = $this->createQueryBuilder('d')
                ->where('d.prestataire = :prestataire')
                ->andWhere('d.type = :type')
                ->andWhere('d.status = :status')
                ->setParameter('prestataire', $prestataire)
                ->setParameter('type', $type)
                ->setParameter('status', self::STATUS_VERIFIED)
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            if (!$document) {
                return false;
            }
        }

        return true;
    }

    /**
     * Compte les documents par statut pour un prestataire
     */
    public function countByStatus(Prestataire $prestataire, string $status): int
    {
        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.prestataire = :prestataire')
            ->andWhere('d.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Obtient les statistiques des documents d'un prestataire
     */
    public function getStatisticsByPrestataire(Prestataire $prestataire): array
    {
        $qb = $this->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $verified = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', self::STATUS_VERIFIED)
            ->getQuery()
            ->getSingleScalarResult();

        $rejected = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->andWhere('d.status = :status')
            ->setParameter('status', self::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'pending' => $pending,
            'verified' => $verified,
            'rejected' => $rejected,
            'completion_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Obtient les statistiques globales des documents
     */
    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('d');

        $total = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        $verified = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', self::STATUS_VERIFIED)
            ->getQuery()
            ->getSingleScalarResult();

        $rejected = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->where('d.status = :status')
            ->setParameter('status', self::STATUS_REJECTED)
            ->getQuery()
            ->getSingleScalarResult();

        $expired = (int) (clone $qb)
            ->select('COUNT(d.id)')
            ->where('d.expiresAt IS NOT NULL')
            ->andWhere('d.expiresAt < :now')
            ->andWhere('d.status = :status')
            ->setParameter('now', new \DateTime())
            ->setParameter('status', self::STATUS_VERIFIED)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'pending' => $pending,
            'verified' => $verified,
            'rejected' => $rejected,
            'expired' => $expired,
        ];
    }

    /**
     * Trouve les documents vérifiés par un admin
     */
    public function findVerifiedByAdmin(Admin $admin): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.verifiedBy = :admin')
            ->setParameter('admin', $admin)
            ->orderBy('d.verifiedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte les documents vérifiés par un admin
     */
    public function countVerifiedByAdmin(Admin $admin, ?\DateTimeInterface $since = null): int
    {
        $qb = $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.verifiedBy = :admin')
            ->setParameter('admin', $admin);

        if ($since) {
            $qb->andWhere('d.verifiedAt >= :since')
                ->setParameter('since', $since);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Trouve les documents uploadés dans une période
     */
    public function findUploadedBetween(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('d')
            ->where('d.uploadedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche de documents avec critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('d');

        if (isset($criteria['prestataire_id'])) {
            $qb->andWhere('d.prestataire = :prestataireId')
                ->setParameter('prestataireId', $criteria['prestataire_id']);
        }

        if (isset($criteria['type'])) {
            if (is_array($criteria['type'])) {
                $qb->andWhere('d.type IN (:types)')
                    ->setParameter('types', $criteria['type']);
            } else {
                $qb->andWhere('d.type = :type')
                    ->setParameter('type', $criteria['type']);
            }
        }

        if (isset($criteria['status'])) {
            if (is_array($criteria['status'])) {
                $qb->andWhere('d.status IN (:statuses)')
                    ->setParameter('statuses', $criteria['status']);
            } else {
                $qb->andWhere('d.status = :status')
                    ->setParameter('status', $criteria['status']);
            }
        }

        if (isset($criteria['verified_by'])) {
            $qb->andWhere('d.verifiedBy = :verifiedBy')
                ->setParameter('verifiedBy', $criteria['verified_by']);
        }

        if (isset($criteria['start_date'])) {
            $qb->andWhere('d.uploadedAt >= :startDate')
                ->setParameter('startDate', $criteria['start_date']);
        }

        if (isset($criteria['end_date'])) {
            $qb->andWhere('d.uploadedAt <= :endDate')
                ->setParameter('endDate', $criteria['end_date']);
        }

        if (isset($criteria['expiring_before'])) {
            $qb->andWhere('d.expiresAt IS NOT NULL')
                ->andWhere('d.expiresAt <= :expiringBefore')
                ->setParameter('expiringBefore', $criteria['expiring_before']);
        }

        if (isset($criteria['min_file_size'])) {
            $qb->andWhere('d.fileSize >= :minFileSize')
                ->setParameter('minFileSize', $criteria['min_file_size']);
        }

        if (isset($criteria['max_file_size'])) {
            $qb->andWhere('d.fileSize <= :maxFileSize')
                ->setParameter('maxFileSize', $criteria['max_file_size']);
        }

        $qb->orderBy('d.uploadedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les doublons de documents
     */
    public function findDuplicates(Prestataire $prestataire, string $type): array
    {
        return $this->createQueryBuilder('d')
            ->where('d.prestataire = :prestataire')
            ->andWhere('d.type = :type')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('type', $type)
            ->orderBy('d.uploadedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les anciens documents (garde le plus récent)
     */
    public function deleteOldDocuments(Prestataire $prestataire, string $type, int $keepLatest = 1): int
    {
        $documents = $this->findDuplicates($prestataire, $type);

        if (count($documents) <= $keepLatest) {
            return 0;
        }

        // Garder les N plus récents
        $toKeep = array_slice($documents, 0, $keepLatest);
        $toDelete = array_slice($documents, $keepLatest);

        $deletedCount = 0;
        foreach ($toDelete as $document) {
            $this->getEntityManager()->remove($document);
            $deletedCount++;
        }

        $this->getEntityManager()->flush();

        return $deletedCount;
    }

    /**
     * Obtient la taille totale des fichiers d'un prestataire
     */
    public function getTotalFileSize(Prestataire $prestataire): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.fileSize)')
            ->where('d.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Obtient la taille totale de tous les fichiers
     */
    public function getTotalStorageSize(): int
    {
        $result = $this->createQueryBuilder('d')
            ->select('SUM(d.fileSize)')
            ->getQuery()
            ->getSingleScalarResult();

        return (int) ($result ?? 0);
    }

    /**
     * Trouve les prestataires avec documents manquants
     */
    public function findPrestatairesWithMissingDocuments(): array
    {
        // Requête complexe pour trouver les prestataires qui n'ont pas tous les documents requis
        $requiredTypes = [
            self::TYPE_IDENTITY_CARD,
            self::TYPE_KBIS,
            self::TYPE_INSURANCE,
        ];

        $qb = $this->getEntityManager()->createQueryBuilder();
        
        return $qb->select('DISTINCT p')
            ->from('App\Entity\User\Prestataire', 'p')
            ->leftJoin('p.documents', 'd', 'WITH', 'd.status = :verified')
            ->where(
                $qb->expr()->notIn('p.id',
                    $this->getEntityManager()->createQueryBuilder()
                        ->select('DISTINCT p2.id')
                        ->from('App\Entity\User\Prestataire', 'p2')
                        ->join('p2.documents', 'd2')
                        ->where('d2.status = :verified')
                        ->andWhere('d2.type IN (:requiredTypes)')
                        ->groupBy('p2.id')
                        ->having('COUNT(DISTINCT d2.type) = :requiredCount')
                        ->getDQL()
                )
            )
            ->setParameter('verified', self::STATUS_VERIFIED)
            ->setParameter('requiredTypes', $requiredTypes)
            ->setParameter('requiredCount', count($requiredTypes))
            ->getQuery()
            ->getResult();
    }

    /**
     * Sauvegarde un document
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
}