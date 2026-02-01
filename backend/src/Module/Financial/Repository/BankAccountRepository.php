<?php

namespace App\Financial\Repository;

use App\Financial\Entity\BankAccount;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité BankAccount (module Financial)
 * 
 * Gestion des comptes bancaires (IBAN/BIC) des prestataires
 * 
 * @extends ServiceEntityRepository<BankAccount>
 */
class BankAccountRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BankAccount::class);
    }

    /**
     * Trouve tous les comptes bancaires d'un prestataire
     */
    public function findByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('ba')
            ->where('ba.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('ba.isDefault', 'DESC')
            ->addOrderBy('ba.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve le compte bancaire par défaut d'un prestataire
     */
    public function findDefaultByPrestataire(Prestataire $prestataire): ?BankAccount
    {
        return $this->createQueryBuilder('ba')
            ->where('ba.prestataire = :prestataire')
            ->andWhere('ba.isDefault = :isDefault')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('isDefault', true)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve les comptes bancaires vérifiés d'un prestataire
     */
    public function findVerifiedByPrestataire(Prestataire $prestataire): array
    {
        return $this->createQueryBuilder('ba')
            ->where('ba.prestataire = :prestataire')
            ->andWhere('ba.isVerified = :isVerified')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('isVerified', true)
            ->orderBy('ba.isDefault', 'DESC')
            ->addOrderBy('ba.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes bancaires non vérifiés
     */
    public function findUnverified(?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('ba')
            ->where('ba.isVerified = :isVerified')
            ->setParameter('isVerified', false)
            ->orderBy('ba.createdAt', 'ASC');

        if ($limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve un compte bancaire par IBAN
     */
    public function findByIban(string $iban): ?BankAccount
    {
        return $this->createQueryBuilder('ba')
            ->where('ba.iban = :iban')
            ->setParameter('iban', $iban)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Vérifie si un IBAN existe déjà
     */
    public function ibanExists(string $iban, ?int $excludeId = null): bool
    {
        $qb = $this->createQueryBuilder('ba')
            ->select('COUNT(ba.id)')
            ->where('ba.iban = :iban')
            ->setParameter('iban', $iban);

        if ($excludeId) {
            $qb->andWhere('ba.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    /**
     * Compte les comptes bancaires d'un prestataire
     */
    public function countByPrestataire(Prestataire $prestataire): int
    {
        return (int) $this->createQueryBuilder('ba')
            ->select('COUNT(ba.id)')
            ->where('ba.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Compte les comptes bancaires vérifiés d'un prestataire
     */
    public function countVerifiedByPrestataire(Prestataire $prestataire): int
    {
        return (int) $this->createQueryBuilder('ba')
            ->select('COUNT(ba.id)')
            ->where('ba.prestataire = :prestataire')
            ->andWhere('ba.isVerified = :isVerified')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('isVerified', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Vérifie si un prestataire a au moins un compte vérifié
     */
    public function hasVerifiedAccount(Prestataire $prestataire): bool
    {
        return $this->countVerifiedByPrestataire($prestataire) > 0;
    }

    /**
     * Trouve les prestataires sans compte bancaire vérifié
     */
    public function findPrestatairesWithoutVerifiedAccount(): array
    {
        return $this->getEntityManager()->createQueryBuilder()
            ->select('DISTINCT p')
            ->from('App\Entity\User\Prestataire', 'p')
            ->leftJoin('p.bankAccounts', 'ba', 'WITH', 'ba.isVerified = :verified')
            ->where('ba.id IS NULL')
            ->setParameter('verified', true)
            ->getQuery()
            ->getResult();
    }

    /**
     * Définit un compte comme compte par défaut (et désactive les autres)
     */
    public function setAsDefault(BankAccount $bankAccount): void
    {
        // Désactiver tous les comptes par défaut du prestataire
        $this->createQueryBuilder('ba')
            ->update()
            ->set('ba.isDefault', ':false')
            ->where('ba.prestataire = :prestataire')
            ->setParameter('false', false)
            ->setParameter('prestataire', $bankAccount->getPrestataire())
            ->getQuery()
            ->execute();

        // Définir le compte actuel comme défaut
        $bankAccount->setIsDefault(true);
        $this->getEntityManager()->flush();
    }

    /**
     * Trouve les comptes créés entre deux dates
     */
    public function findCreatedBetween(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ba')
            ->where('ba.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ba.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes vérifiés entre deux dates
     */
    public function findVerifiedBetween(
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate
    ): array {
        return $this->createQueryBuilder('ba')
            ->where('ba.verifiedAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('ba.verifiedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les comptes en attente de vérification depuis plus de X jours
     */
    public function findPendingVerificationOlderThan(int $days): array
    {
        $date = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('ba')
            ->where('ba.isVerified = :isVerified')
            ->andWhere('ba.createdAt < :date')
            ->setParameter('isVerified', false)
            ->setParameter('date', $date)
            ->orderBy('ba.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Obtient les statistiques globales des comptes bancaires
     */
    public function getGlobalStatistics(): array
    {
        $qb = $this->createQueryBuilder('ba');

        $total = (int) (clone $qb)
            ->select('COUNT(ba.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $verified = (int) (clone $qb)
            ->select('COUNT(ba.id)')
            ->where('ba.isVerified = :isVerified')
            ->setParameter('isVerified', true)
            ->getQuery()
            ->getSingleScalarResult();

        $unverified = (int) (clone $qb)
            ->select('COUNT(ba.id)')
            ->where('ba.isVerified = :isVerified')
            ->setParameter('isVerified', false)
            ->getQuery()
            ->getSingleScalarResult();

        $defaults = (int) (clone $qb)
            ->select('COUNT(ba.id)')
            ->where('ba.isDefault = :isDefault')
            ->setParameter('isDefault', true)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'verified' => $verified,
            'unverified' => $unverified,
            'defaults' => $defaults,
            'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Recherche de comptes bancaires avec critères multiples
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('ba')
            ->leftJoin('ba.prestataire', 'p')
            ->addSelect('p');

        if (isset($criteria['prestataire_id'])) {
            $qb->andWhere('ba.prestataire = :prestataireId')
                ->setParameter('prestataireId', $criteria['prestataire_id']);
        }

        if (isset($criteria['is_verified'])) {
            $qb->andWhere('ba.isVerified = :isVerified')
                ->setParameter('isVerified', $criteria['is_verified']);
        }

        if (isset($criteria['is_default'])) {
            $qb->andWhere('ba.isDefault = :isDefault')
                ->setParameter('isDefault', $criteria['is_default']);
        }

        if (isset($criteria['holder_name'])) {
            $qb->andWhere('ba.holderName LIKE :holderName')
                ->setParameter('holderName', '%' . $criteria['holder_name'] . '%');
        }

        if (isset($criteria['iban'])) {
            $qb->andWhere('ba.iban LIKE :iban')
                ->setParameter('iban', '%' . $criteria['iban'] . '%');
        }

        if (isset($criteria['bic'])) {
            $qb->andWhere('ba.bic = :bic')
                ->setParameter('bic', $criteria['bic']);
        }

        if (isset($criteria['created_after'])) {
            $qb->andWhere('ba.createdAt >= :createdAfter')
                ->setParameter('createdAfter', $criteria['created_after']);
        }

        if (isset($criteria['created_before'])) {
            $qb->andWhere('ba.createdAt <= :createdBefore')
                ->setParameter('createdBefore', $criteria['created_before']);
        }

        if (isset($criteria['verified_after'])) {
            $qb->andWhere('ba.verifiedAt >= :verifiedAfter')
                ->setParameter('verifiedAfter', $criteria['verified_after']);
        }

        if (isset($criteria['verified_before'])) {
            $qb->andWhere('ba.verifiedAt <= :verifiedBefore')
                ->setParameter('verifiedBefore', $criteria['verified_before']);
        }

        $qb->orderBy('ba.createdAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Obtient les comptes groupés par BIC (banque)
     */
    public function getAccountsByBank(): array
    {
        $results = $this->createQueryBuilder('ba')
            ->select('ba.bic, COUNT(ba.id) as count')
            ->where('ba.isVerified = :isVerified')
            ->setParameter('isVerified', true)
            ->groupBy('ba.bic')
            ->orderBy('count', 'DESC')
            ->getQuery()
            ->getResult();

        $distribution = [];
        foreach ($results as $result) {
            $distribution[$result['bic']] = (int) $result['count'];
        }

        return $distribution;
    }

    /**
     * Obtient le délai moyen de vérification (en jours)
     */
    public function getAverageVerificationTime(): float
    {
        $result = $this->createQueryBuilder('ba')
            ->select('AVG(TIMESTAMPDIFF(DAY, ba.createdAt, ba.verifiedAt))')
            ->where('ba.isVerified = :isVerified')
            ->andWhere('ba.verifiedAt IS NOT NULL')
            ->setParameter('isVerified', true)
            ->getQuery()
            ->getSingleScalarResult();

        return round((float) ($result ?? 0), 1);
    }

    /**
     * Valide un IBAN (format français)
     */
    public function validateIban(string $iban): array
    {
        // Nettoyer l'IBAN (supprimer espaces)
        $iban = strtoupper(str_replace(' ', '', $iban));

        $errors = [];

        // Vérifier la longueur (27 caractères pour la France)
        if (!preg_match('/^FR\d{2}\d{10}[A-Z0-9]{11}$/', $iban)) {
            $errors[] = 'Format IBAN invalide. Format attendu : FR76 XXXX XXXX XXXX XXXX XXXX XXX';
        }

        // Vérifier la clé de contrôle (algorithme mod 97)
        if (empty($errors)) {
            $ibanCheck = substr($iban, 4) . substr($iban, 0, 4);
            $ibanCheck = str_replace(
                ['A','B','C','D','E','F','G','H','I','J','K','L','M','N','O','P','Q','R','S','T','U','V','W','X','Y','Z'],
                [10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35],
                $ibanCheck
            );

            if (bcmod($ibanCheck, '97') !== '1') {
                $errors[] = 'Clé de contrôle IBAN invalide';
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'formatted' => chunk_split($iban, 4, ' '),
        ];
    }

    /**
     * Valide un BIC
     */
    public function validateBic(string $bic): array
    {
        $bic = strtoupper(str_replace(' ', '', $bic));
        $errors = [];

        // Format BIC : 8 ou 11 caractères alphanumériques
        if (!preg_match('/^[A-Z]{6}[A-Z0-9]{2}([A-Z0-9]{3})?$/', $bic)) {
            $errors[] = 'Format BIC invalide. Format attendu : AAAABBCCXXX (8 ou 11 caractères)';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Masque un IBAN pour l'affichage
     */
    public function maskIban(string $iban): string
    {
        $iban = str_replace(' ', '', $iban);
        $length = strlen($iban);

        if ($length < 8) {
            return str_repeat('*', $length);
        }

        return substr($iban, 0, 4) . str_repeat('*', $length - 8) . substr($iban, -4);
    }

    /**
     * Sauvegarde un compte bancaire
     */
    public function save(BankAccount $bankAccount, bool $flush = false): void
    {
        $this->getEntityManager()->persist($bankAccount);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime un compte bancaire
     */
    public function remove(BankAccount $bankAccount, bool $flush = false): void
    {
        $this->getEntityManager()->remove($bankAccount);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime tous les comptes non vérifiés d'un prestataire (nettoyage)
     */
    public function removeUnverifiedByPrestataire(Prestataire $prestataire): int
    {
        $accounts = $this->createQueryBuilder('ba')
            ->where('ba.prestataire = :prestataire')
            ->andWhere('ba.isVerified = :isVerified')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('isVerified', false)
            ->getQuery()
            ->getResult();

        $count = count($accounts);

        foreach ($accounts as $account) {
            $this->getEntityManager()->remove($account);
        }

        $this->getEntityManager()->flush();

        return $count;
    }
}