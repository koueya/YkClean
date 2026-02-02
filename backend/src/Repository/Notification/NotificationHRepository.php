<?php
// src/Repository/Notification/NotificationRepository.php

namespace App\Repository\Notification;

use App\Entity\Notification\Notification;
use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Repository pour l'entité Notification
 * Gère toutes les requêtes liées aux notifications multi-canaux
 * 
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    /**
     * Persiste une notification
     */
    public function save(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->persist($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Supprime une notification
     */
    public function remove(Notification $notification, bool $flush = false): void
    {
        $this->getEntityManager()->remove($notification);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    // ============================================
    // RECHERCHE PAR UTILISATEUR
    // ============================================

    /**
     * Trouve toutes les notifications d'un utilisateur
     */
    public function findByUser(
        User $user,
        ?string $type = null,
        ?bool $isRead = null,
        int $limit = 50
    ): array {
        $qb = $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($type !== null) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $type);
        }

        if ($isRead !== null) {
            $qb->andWhere('n.isRead = :isRead')
               ->setParameter('isRead', $isRead);
        }

        // Exclure les notifications expirées
        $qb->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
           ->setParameter('now', new \DateTimeImmutable());

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les notifications non lues d'un utilisateur
     */
    public function findUnreadByUser(User $user, int $limit = 50): array
    {
        return $this->findByUser($user, null, false, $limit);
    }

    /**
     * Trouve les notifications lues d'un utilisateur
     */
    public function findReadByUser(User $user, int $limit = 50): array
    {
        return $this->findByUser($user, null, true, $limit);
    }

    /**
     * Compte les notifications non lues d'un utilisateur
     */
    public function countUnreadByUser(User $user): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Trouve les notifications récentes d'un utilisateur
     */
    public function findRecentByUser(User $user, int $hours = 24, int $limit = 20): array
    {
        $since = new \DateTimeImmutable("-{$hours} hours");

        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.createdAt >= :since')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('since', $since)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR TYPE
    // ============================================

    /**
     * Trouve les notifications par type
     */
    public function findByType(
        string $type,
        ?User $user = null,
        int $limit = 50
    ): array {
        $qb = $this->createQueryBuilder('n')
            ->where('n.type = :type')
            ->setParameter('type', $type)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('n.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Compte les notifications par type pour un utilisateur
     */
    public function countByTypeForUser(User $user, string $type): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->andWhere('n.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->getQuery()
            ->getSingleScalarResult();
    }

    // ============================================
    // RECHERCHE PAR STATUT
    // ============================================

    /**
     * Trouve les notifications par statut
     */
    public function findByStatus(string $status, int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->setParameter('status', $status)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notifications en attente d'envoi
     */
    public function findPending(int $limit = 100): array
    {
        return $this->findByStatus('pending', $limit);
    }

    /**
     * Trouve les notifications en échec
     */
    public function findFailed(int $limit = 100): array
    {
        return $this->findByStatus('failed', $limit);
    }

    /**
     * Trouve les notifications programmées (scheduled) prêtes à être envoyées
     */
    public function findDueNotifications(\DateTimeInterface $now = null): array
    {
        $now = $now ?? new \DateTimeImmutable();

        return $this->createQueryBuilder('n')
            ->where('n.status = :status')
            ->andWhere('n.scheduledAt IS NOT NULL')
            ->andWhere('n.scheduledAt <= :now')
            ->setParameter('status', 'scheduled')
            ->setParameter('now', $now)
            ->orderBy('n.scheduledAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR PRIORITÉ
    // ============================================

    /**
     * Trouve les notifications par priorité
     */
    public function findByPriority(
        string $priority,
        ?User $user = null,
        int $limit = 50
    ): array {
        $qb = $this->createQueryBuilder('n')
            ->where('n.priority = :priority')
            ->setParameter('priority', $priority)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($user) {
            $qb->andWhere('n.user = :user')
               ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Trouve les notifications urgentes non lues
     */
    public function findUrgentUnread(int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.priority = :priority')
            ->andWhere('n.isRead = false')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('priority', 'urgent')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notifications urgentes non lues pour un utilisateur
     */
    public function findUrgentUnreadForUser(User $user): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.user = :user')
            ->andWhere('n.priority = :priority')
            ->andWhere('n.isRead = false')
            ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
            ->setParameter('user', $user)
            ->setParameter('priority', 'urgent')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // RECHERCHE PAR CANAL
    // ============================================

    /**
     * Trouve les notifications par canal d'envoi
     */
    public function findByChannel(string $channel, int $limit = 100): array
    {
        return $this->createQueryBuilder('n')
            ->where('JSON_CONTAINS(n.channels, :channel) = 1')
            ->setParameter('channel', json_encode($channel))
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les notifications envoyées par email
     */
    public function findEmailNotifications(int $limit = 100): array
    {
        return $this->findByChannel('email', $limit);
    }

    /**
     * Trouve les notifications envoyées par SMS
     */
    public function findSmsNotifications(int $limit = 100): array
    {
        return $this->findByChannel('sms', $limit);
    }

    /**
     * Trouve les notifications push
     */
    public function findPushNotifications(int $limit = 100): array
    {
        return $this->findByChannel('push', $limit);
    }

    // ============================================
    // GESTION DES LECTURES
    // ============================================

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(int $notificationId): bool
    {
        $notification = $this->find($notificationId);

        if (!$notification) {
            return false;
        }

        $notification->setRead(true);
        $notification->setReadAt(new \DateTimeImmutable());

        $this->getEntityManager()->flush();

        return true;
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsReadForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.user = :user')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    /**
     * Marque toutes les notifications d'un type comme lues pour un utilisateur
     */
    public function markAllAsReadByType(User $user, string $type): int
    {
        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', 'true')
            ->set('n.readAt', ':now')
            ->where('n.user = :user')
            ->andWhere('n.type = :type')
            ->andWhere('n.isRead = false')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->setParameter('now', new \DateTimeImmutable())
            ->getQuery()
            ->execute();
    }

    // ============================================
    // GESTION DES EXPIRATIONS
    // ============================================

    /**
     * Trouve les notifications expirées
     */
    public function findExpired(): array
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('n')
            ->where('n.expiresAt IS NOT NULL')
            ->andWhere('n.expiresAt < :now')
            ->setParameter('now', $now)
            ->orderBy('n.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Supprime les notifications expirées
     */
    public function deleteExpired(): int
    {
        $now = new \DateTimeImmutable();

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.expiresAt IS NOT NULL')
            ->andWhere('n.expiresAt < :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->execute();
    }

    /**
     * Trouve les notifications expirant bientôt
     */
    public function findExpiringSoon(int $hours = 24): array
    {
        $now = new \DateTimeImmutable();
        $threshold = $now->modify("+{$hours} hours");

        return $this->createQueryBuilder('n')
            ->where('n.expiresAt IS NOT NULL')
            ->andWhere('n.expiresAt BETWEEN :now AND :threshold')
            ->setParameter('now', $now)
            ->setParameter('threshold', $threshold)
            ->orderBy('n.expiresAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    // ============================================
    // NETTOYAGE ET MAINTENANCE
    // ============================================

    /**
     * Supprime les anciennes notifications lues
     */
    public function deleteOldReadNotifications(int $days = 30): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.isRead = true')
            ->andWhere('n.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les anciennes notifications
     */
    public function deleteOlderThan(\DateTimeInterface $date): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.createdAt < :date')
            ->setParameter('date', $date)
            ->getQuery()
            ->execute();
    }

    /**
     * Supprime les notifications d'un utilisateur
     */
    public function deleteForUser(User $user): int
    {
        return $this->createQueryBuilder('n')
            ->delete()
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    /**
     * Archive les anciennes notifications
     * (Marque comme archivées au lieu de supprimer)
     */
    public function archiveOldNotifications(int $days = 90): int
    {
        $cutoffDate = new \DateTimeImmutable("-{$days} days");

        return $this->createQueryBuilder('n')
            ->update()
            ->set('n.isArchived', 'true')
            ->where('n.isArchived = false')
            ->andWhere('n.createdAt < :cutoffDate')
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();
    }

    // ============================================
    // STATISTIQUES
    // ============================================

    /**
     * Obtient les statistiques de notifications pour un utilisateur
     */
    public function getStatisticsForUser(User $user): array
    {
        $total = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();

        $unread = $this->countUnreadByUser($user);

        $byType = $this->createQueryBuilder('n')
            ->select('n.type, COUNT(n.id) as count')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->groupBy('n.type')
            ->getQuery()
            ->getResult();

        $typeStats = [];
        foreach ($byType as $stat) {
            $typeStats[$stat['type']] = (int) $stat['count'];
        }

        $byPriority = $this->createQueryBuilder('n')
            ->select('n.priority, COUNT(n.id) as count')
            ->where('n.user = :user')
            ->setParameter('user', $user)
            ->groupBy('n.priority')
            ->getQuery()
            ->getResult();

        $priorityStats = [];
        foreach ($byPriority as $stat) {
            $priorityStats[$stat['priority']] = (int) $stat['count'];
        }

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
            'readRate' => $total > 0 ? round((($total - $unread) / $total) * 100, 2) : 0,
            'byType' => $typeStats,
            'byPriority' => $priorityStats,
        ];
    }

    /**
     * Obtient les statistiques globales des notifications
     */
    public function getGlobalStatistics(): array
    {
        $total = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $unread = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.isRead = false')
            ->getQuery()
            ->getSingleScalarResult();

        $sent = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.status = :status')
            ->setParameter('status', 'sent')
            ->getQuery()
            ->getSingleScalarResult();

        $failed = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.status = :status')
            ->setParameter('status', 'failed')
            ->getQuery()
            ->getSingleScalarResult();

        $pending = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.status = :status')
            ->setParameter('status', 'pending')
            ->getQuery()
            ->getSingleScalarResult();

        $byType = $this->createQueryBuilder('n')
            ->select('n.type, COUNT(n.id) as count')
            ->groupBy('n.type')
            ->getQuery()
            ->getResult();

        $typeStats = [];
        foreach ($byType as $stat) {
            $typeStats[$stat['type']] = (int) $stat['count'];
        }

        return [
            'total' => $total,
            'unread' => $unread,
            'read' => $total - $unread,
            'sent' => $sent,
            'failed' => $failed,
            'pending' => $pending,
            'successRate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            'byType' => $typeStats,
        ];
    }

    /**
     * Compte les notifications par statut
     */
    public function countByStatus(): array
    {
        $result = $this->createQueryBuilder('n')
            ->select('n.status, COUNT(n.id) as count')
            ->groupBy('n.status')
            ->getQuery()
            ->getResult();

        $counts = [];
        foreach ($result as $row) {
            $counts[$row['status']] = (int) $row['count'];
        }

        return $counts;
    }

    /**
     * Obtient le taux de lecture moyen
     */
    public function getAverageReadRate(): float
    {
        $total = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0;
        }

        $read = (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.isRead = true')
            ->getQuery()
            ->getSingleScalarResult();

        return round(($read / $total) * 100, 2);
    }

    /**
     * Obtient le temps moyen avant lecture
     */
    public function getAverageTimeToRead(): ?int
    {
        $result = $this->createQueryBuilder('n')
            ->select('AVG(TIMESTAMPDIFF(MINUTE, n.createdAt, n.readAt)) as avgMinutes')
            ->where('n.isRead = true')
            ->andWhere('n.readAt IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? (int) $result : null;
    }

    // ============================================
    // RECHERCHE AVANCÉE
    // ============================================

    /**
     * Recherche avancée de notifications avec multiples critères
     */
    public function search(array $criteria): array
    {
        $qb = $this->createQueryBuilder('n');

        // Filtre par utilisateur
        if (isset($criteria['user_id'])) {
            $qb->andWhere('n.user = :userId')
               ->setParameter('userId', $criteria['user_id']);
        }

        // Filtre par type
        if (isset($criteria['type'])) {
            $qb->andWhere('n.type = :type')
               ->setParameter('type', $criteria['type']);
        }

        // Filtre par types multiples
        if (isset($criteria['types']) && is_array($criteria['types'])) {
            $qb->andWhere('n.type IN (:types)')
               ->setParameter('types', $criteria['types']);
        }

        // Filtre par statut
        if (isset($criteria['status'])) {
            $qb->andWhere('n.status = :status')
               ->setParameter('status', $criteria['status']);
        }

        // Filtre par priorité
        if (isset($criteria['priority'])) {
            $qb->andWhere('n.priority = :priority')
               ->setParameter('priority', $criteria['priority']);
        }

        // Filtre par lecture
        if (isset($criteria['is_read'])) {
            $qb->andWhere('n.isRead = :isRead')
               ->setParameter('isRead', $criteria['is_read']);
        }

        // Filtre par date de création
        if (isset($criteria['created_after'])) {
            $qb->andWhere('n.createdAt >= :createdAfter')
               ->setParameter('createdAfter', $criteria['created_after']);
        }

        if (isset($criteria['created_before'])) {
            $qb->andWhere('n.createdAt <= :createdBefore')
               ->setParameter('createdBefore', $criteria['created_before']);
        }

        // Filtre par canal
        if (isset($criteria['channel'])) {
            $qb->andWhere('JSON_CONTAINS(n.channels, :channel) = 1')
               ->setParameter('channel', json_encode($criteria['channel']));
        }

        // Exclure les expirées (par défaut)
        if (!isset($criteria['include_expired']) || !$criteria['include_expired']) {
            $qb->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
               ->setParameter('now', new \DateTimeImmutable());
        }

        // Tri
        $orderBy = $criteria['order_by'] ?? 'createdAt';
        $orderDirection = $criteria['order_direction'] ?? 'DESC';
        
        $qb->orderBy('n.' . $orderBy, $orderDirection);

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
    public function createBaseQueryBuilder(string $alias = 'n'): QueryBuilder
    {
        return $this->createQueryBuilder($alias)
            ->leftJoin($alias . '.user', 'u');
    }
}