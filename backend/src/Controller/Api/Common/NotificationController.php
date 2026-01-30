<?php

namespace App\Controller\Api\Common;

use App\Service\NotificationService;
use App\Entity\User\User;
use App\Entity\Notification\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/api/notifications')]
#[IsGranted('ROLE_USER')]
class NotificationController extends AbstractController
{
    private NotificationService $notificationService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        NotificationService $notificationService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->notificationService = $notificationService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Récupérer les notifications de l'utilisateur
     */
    #[Route('', name: 'api_notifications_list', methods: ['GET'])]
    public function getNotifications(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $unreadOnly = $request->query->getBoolean('unread_only', false);
            $type = $request->query->get('type');
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

            $queryBuilder = $this->entityManager
                ->getRepository(Notification::class)
                ->createQueryBuilder('n')
                ->where('n.user = :user')
                ->setParameter('user', $user)
                ->orderBy('n.createdAt', 'DESC');

            // Filtrer par type si spécifié
            if ($type) {
                $queryBuilder
                    ->andWhere('n.type = :type')
                    ->setParameter('type', $type);
            }

            // Filtrer par non lues si demandé
            if ($unreadOnly) {
                $queryBuilder->andWhere('n.isRead = :isRead')
                    ->setParameter('isRead', false);
            }

            // Exclure les notifications expirées
            $queryBuilder
                ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
                ->setParameter('now', new \DateTime());

            // Compter le total
            $totalQuery = clone $queryBuilder;
            $total = $totalQuery->select('COUNT(n.id)')->getQuery()->getSingleScalarResult();

            // Appliquer la pagination
            $notifications = $queryBuilder
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return new JsonResponse([
                'success' => true,
                'data' => array_map(function (Notification $notification) {
                    return $this->formatNotification($notification);
                }, $notifications),
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => (int) ceil($total / $limit),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get notifications', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la récupération des notifications',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer le nombre de notifications non lues
     */
    #[Route('/unread-count', name: 'api_notifications_unread_count', methods: ['GET'])]
    public function getUnreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $count = $this->entityManager
                ->getRepository(Notification::class)
                ->createQueryBuilder('n')
                ->select('COUNT(n.id)')
                ->where('n.user = :user')
                ->andWhere('n.isRead = :isRead')
                ->andWhere('n.expiresAt IS NULL OR n.expiresAt > :now')
                ->setParameter('user', $user)
                ->setParameter('isRead', false)
                ->setParameter('now', new \DateTime())
                ->getQuery()
                ->getSingleScalarResult();

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'unreadCount' => (int) $count,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get unread count', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la récupération du compteur',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Marquer une notification comme lue
     */
    #[Route('/{id}/read', name: 'api_notifications_mark_read', methods: ['POST'])]
    public function markAsRead(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $notification = $this->entityManager
                ->getRepository(Notification::class)
                ->find($id);

            if (!$notification) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_NOT_FOUND,
                        'message' => 'Notification non trouvée',
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la notification appartient à l'utilisateur
            if ($notification->getUser()->getId() !== $user->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_FORBIDDEN,
                        'message' => 'Accès refusé',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            // Marquer comme lue
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());

            $this->entityManager->flush();

            $this->logger->info('Notification marked as read', [
                'notification_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Notification marquée comme lue',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to mark notification as read', [
                'notification_id' => $id,
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la mise à jour',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    #[Route('/read-all', name: 'api_notifications_mark_all_read', methods: ['POST'])]
    public function markAllAsRead(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $updated = $this->entityManager
                ->getRepository(Notification::class)
                ->createQueryBuilder('n')
                ->update()
                ->set('n.isRead', ':isRead')
                ->set('n.readAt', ':readAt')
                ->where('n.user = :user')
                ->andWhere('n.isRead = :currentRead')
                ->setParameter('isRead', true)
                ->setParameter('readAt', new \DateTime())
                ->setParameter('user', $user)
                ->setParameter('currentRead', false)
                ->getQuery()
                ->execute();

            $this->logger->info('All notifications marked as read', [
                'user_id' => $user->getId(),
                'updated_count' => $updated,
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => sprintf('%d notification(s) marquée(s) comme lue(s)', $updated),
                'data' => [
                    'updatedCount' => $updated,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to mark all notifications as read', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la mise à jour',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer une notification
     */
    #[Route('/{id}', name: 'api_notifications_delete', methods: ['DELETE'])]
    public function deleteNotification(int $id): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $notification = $this->entityManager
                ->getRepository(Notification::class)
                ->find($id);

            if (!$notification) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_NOT_FOUND,
                        'message' => 'Notification non trouvée',
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que la notification appartient à l'utilisateur
            if ($notification->getUser()->getId() !== $user->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_FORBIDDEN,
                        'message' => 'Accès refusé',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            $this->entityManager->remove($notification);
            $this->entityManager->flush();

            $this->logger->info('Notification deleted', [
                'notification_id' => $id,
                'user_id' => $user->getId(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Notification supprimée',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete notification', [
                'notification_id' => $id,
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la suppression',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer toutes les notifications lues
     */
    #[Route('/read', name: 'api_notifications_delete_read', methods: ['DELETE'])]
    public function deleteReadNotifications(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $deleted = $this->entityManager
                ->getRepository(Notification::class)
                ->createQueryBuilder('n')
                ->delete()
                ->where('n.user = :user')
                ->andWhere('n.isRead = :isRead')
                ->setParameter('user', $user)
                ->setParameter('isRead', true)
                ->getQuery()
                ->execute();

            $this->logger->info('Read notifications deleted', [
                'user_id' => $user->getId(),
                'deleted_count' => $deleted,
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => sprintf('%d notification(s) supprimée(s)', $deleted),
                'data' => [
                    'deletedCount' => $deleted,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete read notifications', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la suppression',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Enregistrer le token FCM pour les notifications push
     */
    #[Route('/push-token', name: 'api_notifications_register_token', methods: ['POST'])]
    public function registerPushToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['token']) || empty($data['token'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'Token requis',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            $token = $data['token'];
            $platform = $data['platform'] ?? 'unknown'; // ios, android, web
            $deviceId = $data['device_id'] ?? null;

            // Enregistrer le token
            $this->notificationService->registerPushToken($user, $token, $platform, $deviceId);

            $this->logger->info('Push token registered', [
                'user_id' => $user->getId(),
                'platform' => $platform,
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Token enregistré avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to register push token', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de l\'enregistrement du token',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprimer le token FCM (déconnexion)
     */
    #[Route('/push-token', name: 'api_notifications_unregister_token', methods: ['DELETE'])]
    public function unregisterPushToken(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);
            $token = $data['token'] ?? null;

            if ($token) {
                $this->notificationService->unregisterPushToken($user, $token);
            } else {
                // Supprimer tous les tokens de l'utilisateur
                $this->notificationService->unregisterAllPushTokens($user);
            }

            $this->logger->info('Push token unregistered', [
                'user_id' => $user->getId(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Token supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to unregister push token', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la suppression du token',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les préférences de notification
     */
    #[Route('/preferences', name: 'api_notifications_get_preferences', methods: ['GET'])]
    public function getPreferences(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $preferences = $this->notificationService->getNotificationPreferences($user);

            return new JsonResponse([
                'success' => true,
                'data' => $preferences,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get notification preferences', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la récupération des préférences',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Mettre à jour les préférences de notification
     */
    #[Route('/preferences', name: 'api_notifications_update_preferences', methods: ['PUT'])]
    public function updatePreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'JSON invalide',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->notificationService->updateNotificationPreferences($user, $data);

            $this->logger->info('Notification preferences updated', [
                'user_id' => $user->getId(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Préférences mises à jour avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update notification preferences', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la mise à jour des préférences',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Formater une notification pour la réponse JSON
     */
    private function formatNotification(Notification $notification): array
    {
        return [
            'id' => $notification->getId(),
            'type' => $notification->getType(),
            'title' => $notification->getTitle(),
            'message' => $notification->getMessage(),
            'isRead' => $notification->getIsRead(),
            'priority' => $notification->getPriority() ?? 'normal',
            'relatedEntityType' => $notification->getRelatedEntityType(),
            'relatedEntityId' => $notification->getRelatedEntityId(),
            'data' => $notification->getData(),
            'createdAt' => $notification->getCreatedAt()->format('c'),
            'readAt' => $notification->getReadAt()?->format('c'),
            'expiresAt' => $notification->getExpiresAt()?->format('c'),
        ];
    }
}