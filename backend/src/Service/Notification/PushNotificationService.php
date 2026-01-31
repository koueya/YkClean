<?php

namespace App\Service\Notification;

use App\Entity\User\User;
use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\Service\ServiceRequest;
use App\Entity\Notification\Notification;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PushNotificationService
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;
    private string $fcmServerKey;
    private string $fcmUrl;

    public function __construct(
        HttpClientInterface $httpClient,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        string $fcmServerKey = ''
    ) {
        $this->httpClient = $httpClient;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->fcmServerKey = $fcmServerKey;
        $this->fcmUrl = 'https://fcm.googleapis.com/fcm/send';
    }

    /**
     * Envoie une notification push à un utilisateur
     */
    public function sendNotification(
        User $user,
        string $title,
        string $body,
        array $data = [],
        string $type = 'general'
    ): bool {
        $deviceToken = $user->getFcmToken();
        
        if (!$deviceToken) {
            $this->logger->warning('No FCM token found for user', [
                'user_id' => $user->getId()
            ]);
            return false;
        }

        try {
            // Enregistrer la notification en base
            $notification = new Notification();
            $notification->setUser($user);
            $notification->setTitle($title);
            $notification->setBody($body);
            $notification->setType($type);
            $notification->setData($data);
            $notification->setIsRead(false);
            $notification->setCreatedAt(new \DateTime());

            $this->entityManager->persist($notification);
            $this->entityManager->flush();

            // Envoyer via FCM
            $payload = [
                'to' => $deviceToken,
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                    'sound' => 'default',
                    'badge' => $this->getUnreadCount($user),
                ],
                'data' => array_merge($data, [
                    'notification_id' => $notification->getId(),
                    'type' => $type,
                    'created_at' => $notification->getCreatedAt()->format('c'),
                ]),
                'priority' => 'high',
            ];

            $response = $this->httpClient->request('POST', $this->fcmUrl, [
                'headers' => [
                    'Authorization' => 'key=' . $this->fcmServerKey,
                    'Content-Type' => 'application/json',
                ],
                'json' => $payload,
            ]);

            $statusCode = $response->getStatusCode();
            $content = $response->toArray(false);

            if ($statusCode === 200 && isset($content['success']) && $content['success'] > 0) {
                $notification->setIsSent(true);
                $notification->setSentAt(new \DateTime());
                $this->entityManager->flush();

                $this->logger->info('Push notification sent successfully', [
                    'user_id' => $user->getId(),
                    'notification_id' => $notification->getId(),
                ]);

                return true;
            } else {
                $this->logger->error('Failed to send push notification', [
                    'user_id' => $user->getId(),
                    'status_code' => $statusCode,
                    'response' => $content,
                ]);

                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Exception while sending push notification', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Envoie une notification push à plusieurs utilisateurs
     */
    public function sendBulkNotification(
        array $users,
        string $title,
        string $body,
        array $data = [],
        string $type = 'general'
    ): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'no_token' => 0,
        ];

        foreach ($users as $user) {
            if (!$user->getFcmToken()) {
                $results['no_token']++;
                continue;
            }

            $sent = $this->sendNotification($user, $title, $body, $data, $type);
            
            if ($sent) {
                $results['success']++;
            } else {
                $results['failed']++;
            }
        }

        return $results;
    }

    /**
     * Notification de nouvelle demande de service
     */
    public function notifyNewServiceRequest(ServiceRequest $serviceRequest, array $prestataires): void
    {
        $title = 'Nouvelle demande de service';
        $body = sprintf(
            '%s recherche un prestataire pour %s',
            $serviceRequest->getClient()->getFirstName(),
            $serviceRequest->getCategory()->getName()
        );

        $data = [
            'service_request_id' => $serviceRequest->getId(),
            'category' => $serviceRequest->getCategory()->getName(),
            'address' => $serviceRequest->getAddress(),
            'screen' => 'ServiceRequestDetails',
        ];

        $this->sendBulkNotification($prestataires, $title, $body, $data, 'new_service_request');
    }

    /**
     * Notification de nouveau devis
     */
    public function notifyNewQuote(Quote $quote): void
    {
        $client = $quote->getServiceRequest()->getClient();
        $prestataire = $quote->getPrestataire();

        $title = 'Nouveau devis reçu';
        $body = sprintf(
            '%s vous a envoyé un devis de %.2f€',
            $prestataire->getFirstName(),
            $quote->getAmount()
        );

        $data = [
            'quote_id' => $quote->getId(),
            'service_request_id' => $quote->getServiceRequest()->getId(),
            'amount' => $quote->getAmount(),
            'prestataire_id' => $prestataire->getId(),
            'screen' => 'QuoteDetails',
        ];

        $this->sendNotification($client, $title, $body, $data, 'new_quote');
    }

    /**
     * Notification de devis accepté
     */
    public function notifyQuoteAccepted(Quote $quote): void
    {
        $prestataire = $quote->getPrestataire();

        $title = 'Devis accepté !';
        $body = 'Votre devis a été accepté. Une réservation a été créée.';

        $data = [
            'quote_id' => $quote->getId(),
            'booking_id' => $quote->getBooking()?->getId(),
            'screen' => 'BookingDetails',
        ];

        $this->sendNotification($prestataire, $title, $body, $data, 'quote_accepted');
    }

    /**
     * Notification de confirmation de réservation
     */
    public function notifyBookingConfirmed(Booking $booking): void
    {
        $client = $booking->getClient();
        $prestataire = $booking->getPrestataire();

        // Notification au client
        $clientTitle = 'Réservation confirmée';
        $clientBody = sprintf(
            'Votre service avec %s est confirmé pour le %s',
            $prestataire->getFirstName(),
            $booking->getScheduledDate()->format('d/m/Y à H:i')
        );

        $clientData = [
            'booking_id' => $booking->getId(),
            'screen' => 'BookingDetails',
        ];

        $this->sendNotification($client, $clientTitle, $clientBody, $clientData, 'booking_confirmed');

        // Notification au prestataire
        $prestataireTitle = 'Nouvelle réservation';
        $prestataireBody = sprintf(
            'Réservation confirmée avec %s pour le %s',
            $client->getFirstName(),
            $booking->getScheduledDate()->format('d/m/Y à H:i')
        );

        $prestataireData = [
            'booking_id' => $booking->getId(),
            'screen' => 'BookingDetails',
        ];

        $this->sendNotification($prestataire, $prestataireTitle, $prestataireBody, $prestataireData, 'booking_confirmed');
    }

    /**
     * Rappel de réservation (24h avant)
     */
    public function notifyBookingReminder(Booking $booking): void
    {
        $client = $booking->getClient();
        $prestataire = $booking->getPrestataire();

        // Rappel au client
        $clientTitle = 'Rappel : Service demain';
        $clientBody = sprintf(
            'Votre service avec %s est prévu demain à %s',
            $prestataire->getFirstName(),
            $booking->getScheduledTime()->format('H:i')
        );

        $data = [
            'booking_id' => $booking->getId(),
            'screen' => 'BookingDetails',
        ];

        $this->sendNotification($client, $clientTitle, $clientBody, $data, 'booking_reminder');

        // Rappel au prestataire
        $prestataireTitle = 'Rappel : Service demain';
        $prestataireBody = sprintf(
            'Service prévu demain à %s chez %s',
            $booking->getScheduledTime()->format('H:i'),
            $client->getFirstName()
        );

        $this->sendNotification($prestataire, $prestataireTitle, $prestataireBody, $data, 'booking_reminder');
    }

    /**
     * Notification d'annulation
     */
    public function notifyBookingCancelled(Booking $booking, string $cancelledBy): void
    {
        $client = $booking->getClient();
        $prestataire = $booking->getPrestataire();

        $title = 'Réservation annulée';
        $body = sprintf(
            'La réservation du %s a été annulée',
            $booking->getScheduledDate()->format('d/m/Y')
        );

        $data = [
            'booking_id' => $booking->getId(),
            'cancelled_by' => $cancelledBy,
            'screen' => 'BookingsList',
        ];

        // Notifier le client si ce n'est pas lui qui a annulé
        if ($cancelledBy !== 'client') {
            $this->sendNotification($client, $title, $body, $data, 'booking_cancelled');
        }

        // Notifier le prestataire si ce n'est pas lui qui a annulé
        if ($cancelledBy !== 'prestataire') {
            $this->sendNotification($prestataire, $title, $body, $data, 'booking_cancelled');
        }
    }

    /**
     * Notification de demande de remplacement
     */
    public function notifyReplacementRequest(Booking $booking, User $replacementPrestataire): void
    {
        $title = 'Demande de remplacement';
        $body = sprintf(
            'Remplacement demandé pour un service le %s',
            $booking->getScheduledDate()->format('d/m/Y à H:i')
        );

        $data = [
            'booking_id' => $booking->getId(),
            'screen' => 'ReplacementRequest',
        ];

        $this->sendNotification($replacementPrestataire, $title, $body, $data, 'replacement_request');
    }

    /**
     * Notification de remplacement confirmé
     */
    public function notifyReplacementConfirmed(Booking $booking, User $newPrestataire): void
    {
        $client = $booking->getClient();

        $title = 'Changement de prestataire';
        $body = sprintf(
            '%s remplacera le prestataire initial pour votre service',
            $newPrestataire->getFirstName()
        );

        $data = [
            'booking_id' => $booking->getId(),
            'new_prestataire_id' => $newPrestataire->getId(),
            'screen' => 'BookingDetails',
        ];

        $this->sendNotification($client, $title, $body, $data, 'replacement_confirmed');
    }

    /**
     * Notification de service imminent (2h avant)
     */
    public function notifyServiceImminent(Booking $booking): void
    {
        $client = $booking->getClient();
        $prestataire = $booking->getPrestataire();

        // Notification au prestataire
        $title = 'Service dans 2 heures';
        $body = sprintf(
            'Rendez-vous chez %s dans 2 heures',
            $client->getFirstName()
        );

        $data = [
            'booking_id' => $booking->getId(),
            'address' => $booking->getAddress(),
            'screen' => 'BookingDetails',
        ];

        $this->sendNotification($prestataire, $title, $body, $data, 'service_imminent');
    }

    /**
     * Notification de service commencé
     */
    public function notifyServiceStarted(Booking $booking): void
    {
        $client = $booking->getClient();

        $title = 'Service commencé';
        $body = sprintf(
            '%s a commencé le service',
            $booking->getPrestataire()->getFirstName()
        );

        $data = [
            'booking_id' => $booking->getId(),
            'screen' => 'BookingDetails',
        ];

        $this->sendNotification($client, $title, $body, $data, 'service_started');
    }

    /**
     * Notification de service terminé
     */
    public function notifyServiceCompleted(Booking $booking): void
    {
        $client = $booking->getClient();

        $title = 'Service terminé';
        $body = 'Le service est terminé. N\'oubliez pas de laisser un avis !';

        $data = [
            'booking_id' => $booking->getId(),
            'screen' => 'ReviewForm',
        ];

        $this->sendNotification($client, $title, $body, $data, 'service_completed');
    }

    /**
     * Notification de nouveau message/chat
     */
    public function notifyNewMessage(User $recipient, User $sender, string $message): void
    {
        $title = 'Nouveau message';
        $body = sprintf(
            '%s : %s',
            $sender->getFirstName(),
            substr($message, 0, 50) . (strlen($message) > 50 ? '...' : '')
        );

        $data = [
            'sender_id' => $sender->getId(),
            'screen' => 'Chat',
        ];

        $this->sendNotification($recipient, $title, $body, $data, 'new_message');
    }

    /**
     * Notification de paiement reçu
     */
    public function notifyPaymentReceived(Booking $booking, float $amount): void
    {
        $prestataire = $booking->getPrestataire();

        $title = 'Paiement reçu';
        $body = sprintf(
            'Vous avez reçu un paiement de %.2f€',
            $amount
        );

        $data = [
            'booking_id' => $booking->getId(),
            'amount' => $amount,
            'screen' => 'Earnings',
        ];

        $this->sendNotification($prestataire, $title, $body, $data, 'payment_received');
    }

    /**
     * Notification de nouvel avis reçu
     */
    public function notifyNewReview(User $prestataire, float $rating, string $comment): void
    {
        $title = 'Nouvel avis reçu';
        $body = sprintf(
            'Vous avez reçu un avis : %s/5 étoiles',
            $rating
        );

        $data = [
            'rating' => $rating,
            'screen' => 'Reviews',
        ];

        $this->sendNotification($prestataire, $title, $body, $data, 'new_review');
    }

    /**
     * Enregistrer le token FCM d'un utilisateur
     */
    public function registerDeviceToken(User $user, string $fcmToken): void
    {
        $user->setFcmToken($fcmToken);
        $this->entityManager->flush();

        $this->logger->info('FCM token registered', [
            'user_id' => $user->getId(),
        ]);
    }

    /**
     * Supprimer le token FCM d'un utilisateur (déconnexion)
     */
    public function unregisterDeviceToken(User $user): void
    {
        $user->setFcmToken(null);
        $this->entityManager->flush();

        $this->logger->info('FCM token unregistered', [
            'user_id' => $user->getId(),
        ]);
    }

    /**
     * Obtenir le nombre de notifications non lues
     */
    private function getUnreadCount(User $user): int
    {
        return $this->entityManager
            ->getRepository(Notification::class)
            ->count([
                'user' => $user,
                'isRead' => false,
            ]);
    }

    /**
     * Marquer une notification comme lue
     */
    public function markAsRead(int $notificationId): void
    {
        $notification = $this->entityManager
            ->getRepository(Notification::class)
            ->find($notificationId);

        if ($notification) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());
            $this->entityManager->flush();
        }
    }

    /**
     * Marquer toutes les notifications comme lues
     */
    public function markAllAsRead(User $user): void
    {
        $notifications = $this->entityManager
            ->getRepository(Notification::class)
            ->findBy([
                'user' => $user,
                'isRead' => false,
            ]);

        foreach ($notifications as $notification) {
            $notification->setIsRead(true);
            $notification->setReadAt(new \DateTime());
        }

        $this->entityManager->flush();
    }
}