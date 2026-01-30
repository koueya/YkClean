<?php

namespace App\Service\Notification;

use App\Entity\Notification\Notification;
use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\Service\ServiceRequest;
use App\Repository\Notification\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Mime\Address;
use Twig\Environment;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des notifications multi-canaux
 * Gère les emails, SMS, push notifications et notifications in-app
 */
class NotificationService
{
    // Types de notifications
    public const TYPE_BOOKING_CONFIRMED = 'booking_confirmed';
    public const TYPE_BOOKING_CANCELLED = 'booking_cancelled';
    public const TYPE_BOOKING_REMINDER = 'booking_reminder';
    public const TYPE_BOOKING_COMPLETED = 'booking_completed';
    public const TYPE_QUOTE_RECEIVED = 'quote_received';
    public const TYPE_QUOTE_ACCEPTED = 'quote_accepted';
    public const TYPE_QUOTE_REJECTED = 'quote_rejected';
    public const TYPE_SERVICE_REQUEST_NEW = 'service_request_new';
    public const TYPE_REPLACEMENT_NEEDED = 'replacement_needed';
    public const TYPE_REPLACEMENT_FOUND = 'replacement_found';
    public const TYPE_PAYMENT_RECEIVED = 'payment_received';
    public const TYPE_PAYMENT_FAILED = 'payment_failed';
    public const TYPE_REVIEW_REQUEST = 'review_request';
    public const TYPE_DOCUMENT_EXPIRING = 'document_expiring';
    public const TYPE_AVAILABILITY_CONFLICT = 'availability_conflict';
    public const TYPE_PRESTATAIRE_APPROVED = 'prestataire_approved';
    public const TYPE_PRESTATAIRE_REJECTED = 'prestataire_rejected';

    // Canaux de notification
    public const CHANNEL_EMAIL = 'email';
    public const CHANNEL_SMS = 'sms';
    public const CHANNEL_PUSH = 'push';
    public const CHANNEL_IN_APP = 'in_app';

    // Priorités
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';
    public const PRIORITY_URGENT = 'urgent';

    private array $config;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private NotificationRepository $notificationRepository,
        private MailerInterface $mailer,
        private Environment $twig,
        private EmailService $emailService,
        private SmsService $smsService,
        private PushNotificationService $pushService,
        private LoggerInterface $logger,
        private string $projectDir,
        private string $fromEmail,
        private string $fromName
    ) {
        // Configuration des types de notifications
        $this->config = [
            self::TYPE_BOOKING_CONFIRMED => [
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_PUSH, self::CHANNEL_IN_APP],
                'priority' => self::PRIORITY_HIGH,
                'template_email' => 'emails/booking/confirmed.html.twig',
                'template_push' => 'push/booking_confirmed.txt.twig'
            ],
            self::TYPE_BOOKING_REMINDER => [
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_PUSH],
                'priority' => self::PRIORITY_HIGH,
                'template_email' => 'emails/booking/reminder.html.twig',
                'template_sms' => 'sms/booking_reminder.txt.twig'
            ],
            self::TYPE_QUOTE_RECEIVED => [
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_PUSH, self::CHANNEL_IN_APP],
                'priority' => self::PRIORITY_MEDIUM,
                'template_email' => 'emails/quote/received.html.twig'
            ],
            self::TYPE_REPLACEMENT_NEEDED => [
                'channels' => [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_PUSH],
                'priority' => self::PRIORITY_URGENT,
                'template_email' => 'emails/replacement/needed.html.twig'
            ]
        ];
    }

    /**
     * Envoie une notification multi-canaux
     */
    public function send(
        User $user,
        string $type,
        array $data = [],
        ?array $channels = null,
        ?string $priority = null
    ): array {
        $config = $this->config[$type] ?? null;
        
        if (!$config) {
            throw new \InvalidArgumentException("Type de notification inconnu: {$type}");
        }

        // Utiliser les canaux par défaut si non spécifiés
        $channels = $channels ?? $config['channels'];
        $priority = $priority ?? $config['priority'];

        $results = [];
        $notification = null;

        // Créer une notification in-app si demandé
        if (in_array(self::CHANNEL_IN_APP, $channels)) {
            $notification = $this->createNotification($user, $type, $data, $priority);
            $results[self::CHANNEL_IN_APP] = ['success' => true, 'notification_id' => $notification->getId()];
        }

        // Envoyer email
        if (in_array(self::CHANNEL_EMAIL, $channels)) {
            $results[self::CHANNEL_EMAIL] = $this->sendEmail($user, $type, $data, $config);
        }

        // Envoyer SMS
        if (in_array(self::CHANNEL_SMS, $channels)) {
            $results[self::CHANNEL_SMS] = $this->sendSms($user, $type, $data, $config);
        }

        // Envoyer push notification
        if (in_array(self::CHANNEL_PUSH, $channels)) {
            $results[self::CHANNEL_PUSH] = $this->sendPush($user, $type, $data, $config);
        }

        $this->logger->info('Notification sent', [
            'user_id' => $user->getId(),
            'type' => $type,
            'channels' => $channels,
            'results' => $results
        ]);

        return $results;
    }

    /**
     * Notifications spécifiques pour les réservations
     */
    public function notifyBookingConfirmed(Booking $booking): array
    {
        $results = [];

        // Notifier le client
        $clientData = [
            'booking' => $booking,
            'prestataire' => $booking->getPrestataire(),
            'date' => $booking->getScheduledDateTime(),
            'address' => $booking->getAddress()
        ];
        
        $results['client'] = $this->send(
            $booking->getClient(),
            self::TYPE_BOOKING_CONFIRMED,
            $clientData
        );

        // Notifier le prestataire
        $prestataireData = [
            'booking' => $booking,
            'client' => $booking->getClient(),
            'date' => $booking->getScheduledDateTime(),
            'address' => $booking->getAddress()
        ];
        
        $results['prestataire'] = $this->send(
            $booking->getPrestataire(),
            self::TYPE_BOOKING_CONFIRMED,
            $prestataireData
        );

        return $results;
    }

    public function notifyBookingCancelled(Booking $booking, string $reason = ''): array
    {
        $results = [];

        $data = [
            'booking' => $booking,
            'reason' => $reason,
            'cancelled_at' => new \DateTime()
        ];

        $results['client'] = $this->send(
            $booking->getClient(),
            self::TYPE_BOOKING_CANCELLED,
            $data
        );

        $results['prestataire'] = $this->send(
            $booking->getPrestataire(),
            self::TYPE_BOOKING_CANCELLED,
            $data
        );

        return $results;
    }

    public function notifyBookingReminder(Booking $booking, int $hoursBefore): array
    {
        $results = [];

        $data = [
            'booking' => $booking,
            'hours_before' => $hoursBefore,
            'scheduled_time' => $booking->getScheduledDateTime()
        ];

        // Rappel au client
        $results['client'] = $this->send(
            $booking->getClient(),
            self::TYPE_BOOKING_REMINDER,
            $data,
            [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_PUSH]
        );

        // Rappel au prestataire
        $results['prestataire'] = $this->send(
            $booking->getPrestataire(),
            self::TYPE_BOOKING_REMINDER,
            $data,
            [self::CHANNEL_EMAIL, self::CHANNEL_PUSH]
        );

        return $results;
    }

    public function notifyBookingCompleted(Booking $booking): array
    {
        $results = [];

        $data = [
            'booking' => $booking,
            'completed_at' => new \DateTime()
        ];

        // Notifier le client et demander un avis
        $results['client'] = $this->send(
            $booking->getClient(),
            self::TYPE_BOOKING_COMPLETED,
            $data
        );

        // Notifier le prestataire
        $results['prestataire'] = $this->send(
            $booking->getPrestataire(),
            self::TYPE_BOOKING_COMPLETED,
            $data
        );

        return $results;
    }

    /**
     * Notifications spécifiques pour les devis
     */
    public function notifyQuoteReceived(Quote $quote): array
    {
        $data = [
            'quote' => $quote,
            'prestataire' => $quote->getPrestataire(),
            'service_request' => $quote->getServiceRequest(),
            'amount' => $quote->getAmount(),
            'valid_until' => $quote->getValidUntil()
        ];

        return $this->send(
            $quote->getServiceRequest()->getClient(),
            self::TYPE_QUOTE_RECEIVED,
            $data
        );
    }

    public function notifyQuoteAccepted(Quote $quote): array
    {
        $data = [
            'quote' => $quote,
            'client' => $quote->getServiceRequest()->getClient(),
            'accepted_at' => new \DateTime()
        ];

        return $this->send(
            $quote->getPrestataire(),
            self::TYPE_QUOTE_ACCEPTED,
            $data
        );
    }

    public function notifyQuoteRejected(Quote $quote, string $reason = ''): array
    {
        $data = [
            'quote' => $quote,
            'reason' => $reason,
            'rejected_at' => new \DateTime()
        ];

        return $this->send(
            $quote->getPrestataire(),
            self::TYPE_QUOTE_REJECTED,
            $data,
            [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP]
        );
    }

    /**
     * Notifications pour les demandes de service
     */
    public function notifyNewServiceRequest(ServiceRequest $serviceRequest, array $prestataires): array
    {
        $results = [];

        $data = [
            'service_request' => $serviceRequest,
            'client' => $serviceRequest->getClient(),
            'category' => $serviceRequest->getCategory(),
            'address' => $serviceRequest->getAddress(),
            'preferred_date' => $serviceRequest->getPreferredDate()
        ];

        foreach ($prestataires as $prestataire) {
            $results[$prestataire->getId()] = $this->send(
                $prestataire,
                self::TYPE_SERVICE_REQUEST_NEW,
                $data,
                [self::CHANNEL_EMAIL, self::CHANNEL_PUSH, self::CHANNEL_IN_APP]
            );
        }

        return $results;
    }

    /**
     * Notifications pour les remplacements
     */
    public function notifyReplacementNeeded(
        Booking $booking,
        Prestataire $originalPrestataire,
        array $potentialReplacements,
        string $reason
    ): array {
        $results = [];

        // Notifier le client
        $clientData = [
            'booking' => $booking,
            'original_prestataire' => $originalPrestataire,
            'reason' => $reason
        ];

        $results['client'] = $this->send(
            $booking->getClient(),
            self::TYPE_REPLACEMENT_NEEDED,
            $clientData,
            [self::CHANNEL_EMAIL, self::CHANNEL_SMS]
        );

        // Notifier les prestataires potentiels
        $prestataireData = [
            'booking' => $booking,
            'original_prestataire' => $originalPrestataire,
            'date' => $booking->getScheduledDateTime(),
            'reason' => $reason
        ];

        foreach ($potentialReplacements as $prestataire) {
            $results['replacement_' . $prestataire->getId()] = $this->send(
                $prestataire,
                self::TYPE_REPLACEMENT_NEEDED,
                $prestataireData,
                [self::CHANNEL_EMAIL, self::CHANNEL_SMS, self::CHANNEL_PUSH],
                self::PRIORITY_URGENT
            );
        }

        return $results;
    }

    public function notifyReplacementFound(
        Booking $booking,
        Prestataire $newPrestataire,
        Prestataire $originalPrestataire
    ): array {
        $results = [];

        $data = [
            'booking' => $booking,
            'new_prestataire' => $newPrestataire,
            'original_prestataire' => $originalPrestataire
        ];

        // Notifier le client
        $results['client'] = $this->send(
            $booking->getClient(),
            self::TYPE_REPLACEMENT_FOUND,
            $data
        );

        // Notifier le nouveau prestataire
        $results['new_prestataire'] = $this->send(
            $newPrestataire,
            self::TYPE_REPLACEMENT_FOUND,
            $data
        );

        // Notifier l'ancien prestataire
        $results['original_prestataire'] = $this->send(
            $originalPrestataire,
            self::TYPE_REPLACEMENT_FOUND,
            $data,
            [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP]
        );

        return $results;
    }

    /**
     * Notifications pour les paiements
     */
    public function notifyPaymentReceived(Booking $booking, float $amount): array
    {
        $results = [];

        $data = [
            'booking' => $booking,
            'amount' => $amount,
            'paid_at' => new \DateTime()
        ];

        // Notifier le client
        $results['client'] = $this->send(
            $booking->getClient(),
            self::TYPE_PAYMENT_RECEIVED,
            $data
        );

        // Notifier le prestataire
        $results['prestataire'] = $this->send(
            $booking->getPrestataire(),
            self::TYPE_PAYMENT_RECEIVED,
            $data
        );

        return $results;
    }

    public function notifyPaymentFailed(Booking $booking, string $reason): array
    {
        $data = [
            'booking' => $booking,
            'reason' => $reason,
            'failed_at' => new \DateTime()
        ];

        return $this->send(
            $booking->getClient(),
            self::TYPE_PAYMENT_FAILED,
            $data,
            [self::CHANNEL_EMAIL, self::CHANNEL_SMS],
            self::PRIORITY_URGENT
        );
    }

    /**
     * Notifications pour les avis
     */
    public function notifyReviewRequest(Booking $booking): array
    {
        $data = [
            'booking' => $booking,
            'prestataire' => $booking->getPrestataire(),
            'completed_at' => $booking->getActualEndTime()
        ];

        return $this->send(
            $booking->getClient(),
            self::TYPE_REVIEW_REQUEST,
            $data,
            [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP]
        );
    }

    /**
     * Notifications administratives
     */
    public function notifyDocumentExpiring(Prestataire $prestataire, string $documentType, \DateTimeInterface $expiryDate): array
    {
        $data = [
            'document_type' => $documentType,
            'expiry_date' => $expiryDate,
            'days_remaining' => (new \DateTime())->diff($expiryDate)->days
        ];

        return $this->send(
            $prestataire,
            self::TYPE_DOCUMENT_EXPIRING,
            $data,
            [self::CHANNEL_EMAIL, self::CHANNEL_IN_APP],
            self::PRIORITY_HIGH
        );
    }

    public function notifyPrestataireApproved(Prestataire $prestataire): array
    {
        $data = [
            'prestataire' => $prestataire,
            'approved_at' => new \DateTime()
        ];

        return $this->send(
            $prestataire,
            self::TYPE_PRESTATAIRE_APPROVED,
            $data
        );
    }

    public function notifyPrestataireRejected(Prestataire $prestataire, string $reason): array
    {
        $data = [
            'prestataire' => $prestataire,
            'reason' => $reason,
            'rejected_at' => new \DateTime()
        ];

        return $this->send(
            $prestataire,
            self::TYPE_PRESTATAIRE_REJECTED,
            $data
        );
    }

    /**
     * Notifications programmées (rappels automatiques)
     */
    public function scheduleBookingReminders(Booking $booking): void
    {
        // Rappel 24h avant
        $reminder24h = new \DateTime($booking->getScheduledDateTime()->format('Y-m-d H:i:s'));
        $reminder24h->modify('-24 hours');
        
        if ($reminder24h > new \DateTime()) {
            $this->scheduleNotification(
                $booking->getClient(),
                self::TYPE_BOOKING_REMINDER,
                ['booking' => $booking, 'hours_before' => 24],
                $reminder24h
            );

            $this->scheduleNotification(
                $booking->getPrestataire(),
                self::TYPE_BOOKING_REMINDER,
                ['booking' => $booking, 'hours_before' => 24],
                $reminder24h
            );
        }

        // Rappel 2h avant
        $reminder2h = new \DateTime($booking->getScheduledDateTime()->format('Y-m-d H:i:s'));
        $reminder2h->modify('-2 hours');
        
        if ($reminder2h > new \DateTime()) {
            $this->scheduleNotification(
                $booking->getClient(),
                self::TYPE_BOOKING_REMINDER,
                ['booking' => $booking, 'hours_before' => 2],
                $reminder2h,
                [self::CHANNEL_SMS, self::CHANNEL_PUSH]
            );

            $this->scheduleNotification(
                $booking->getPrestataire(),
                self::TYPE_BOOKING_REMINDER,
                ['booking' => $booking, 'hours_before' => 2],
                $reminder2h,
                [self::CHANNEL_PUSH]
            );
        }
    }

    /**
     * Programme une notification pour envoi futur
     */
    public function scheduleNotification(
        User $user,
        string $type,
        array $data,
        \DateTimeInterface $scheduledFor,
        ?array $channels = null
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setData($data);
        $notification->setScheduledFor($scheduledFor);
        $notification->setChannels($channels ?? $this->config[$type]['channels']);
        $notification->setStatus('scheduled');

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        $this->logger->info('Notification scheduled', [
            'user_id' => $user->getId(),
            'type' => $type,
            'scheduled_for' => $scheduledFor->format('Y-m-d H:i:s')
        ]);

        return $notification;
    }

    /**
     * Traite les notifications programmées (à appeler via cron)
     */
    public function processScheduledNotifications(): int
    {
        $notifications = $this->notificationRepository->findDueNotifications(new \DateTime());
        $processed = 0;

        foreach ($notifications as $notification) {
            try {
                $this->send(
                    $notification->getUser(),
                    $notification->getType(),
                    $notification->getData(),
                    $notification->getChannels()
                );

                $notification->setStatus('sent');
                $notification->setSentAt(new \DateTimeImmutable());
                $processed++;

            } catch (\Exception $e) {
                $notification->setStatus('failed');
                $notification->setErrorMessage($e->getMessage());
                
                $this->logger->error('Failed to send scheduled notification', [
                    'notification_id' => $notification->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }

        $this->entityManager->flush();

        $this->logger->info('Processed scheduled notifications', [
            'total' => count($notifications),
            'processed' => $processed
        ]);

        return $processed;
    }

    /**
     * Récupère les notifications non lues d'un utilisateur
     */
    public function getUnreadNotifications(User $user, int $limit = 20): array
    {
        return $this->notificationRepository->findUnreadByUser($user, $limit);
    }

    /**
     * Marque une notification comme lue
     */
    public function markAsRead(Notification $notification): void
    {
        if (!$notification->isRead()) {
            $notification->setRead(true);
            $notification->setReadAt(new \DateTimeImmutable());
            $this->entityManager->flush();
        }
    }

    /**
     * Marque toutes les notifications d'un utilisateur comme lues
     */
    public function markAllAsRead(User $user): int
    {
        return $this->notificationRepository->markAllAsReadForUser($user);
    }

    /**
     * Supprime les anciennes notifications
     */
    public function cleanOldNotifications(int $daysToKeep = 90): int
    {
        $cutoffDate = new \DateTime("-{$daysToKeep} days");
        return $this->notificationRepository->deleteOlderThan($cutoffDate);
    }

    /**
     * Compte les notifications non lues
     */
    public function countUnread(User $user): int
    {
        return $this->notificationRepository->countUnreadByUser($user);
    }

    // ============ MÉTHODES PRIVÉES - ENVOI ============

    /**
     * Crée une notification in-app
     */
    private function createNotification(
        User $user,
        string $type,
        array $data,
        string $priority
    ): Notification {
        $notification = new Notification();
        $notification->setUser($user);
        $notification->setType($type);
        $notification->setTitle($this->getNotificationTitle($type, $data));
        $notification->setMessage($this->getNotificationMessage($type, $data));
        $notification->setData($data);
        $notification->setPriority($priority);
        $notification->setRead(false);
        $notification->setStatus('sent');
        $notification->setSentAt(new \DateTimeImmutable());

        $this->entityManager->persist($notification);
        $this->entityManager->flush();

        return $notification;
    }

    /**
     * Envoie une notification par email
     */
    private function sendEmail(User $user, string $type, array $data, array $config): array
    {
        try {
            $template = $config['template_email'] ?? null;
            
            if (!$template) {
                throw new \Exception("Template email non configuré pour le type: {$type}");
            }

            $subject = $this->getEmailSubject($type, $data);
            
            $email = (new Email())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to($user->getEmail())
                ->subject($subject)
                ->html($this->twig->render($template, array_merge($data, ['user' => $user])));

            $this->mailer->send($email);

            return ['success' => true, 'message' => 'Email envoyé'];

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email notification', [
                'user_id' => $user->getId(),
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Envoie une notification par SMS
     */
    private function sendSms(User $user, string $type, array $data, array $config): array
    {
        try {
            if (!$user->getPhone()) {
                throw new \Exception("Numéro de téléphone non renseigné");
            }

            $template = $config['template_sms'] ?? null;
            $message = $template 
                ? $this->twig->render($template, $data)
                : $this->getNotificationMessage($type, $data);

            // Limiter à 160 caractères pour SMS
            $message = mb_substr($message, 0, 160);

            $this->smsService->send($user->getPhone(), $message);

            return ['success' => true, 'message' => 'SMS envoyé'];

        } catch (\Exception $e) {
            $this->logger->error('Failed to send SMS notification', [
                'user_id' => $user->getId(),
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Envoie une push notification
     */
    private function sendPush(User $user, string $type, array $data, array $config): array
    {
        try {
            $title = $this->getNotificationTitle($type, $data);
            $message = $this->getNotificationMessage($type, $data);

            $this->pushService->send($user, $title, $message, [
                'type' => $type,
                'data' => $data
            ]);

            return ['success' => true, 'message' => 'Push notification envoyée'];

        } catch (\Exception $e) {
            $this->logger->error('Failed to send push notification', [
                'user_id' => $user->getId(),
                'type' => $type,
                'error' => $e->getMessage()
            ]);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // ============ MÉTHODES UTILITAIRES ============

    /**
     * Génère le titre d'une notification
     */
    private function getNotificationTitle(string $type, array $data): string
    {
        return match($type) {
            self::TYPE_BOOKING_CONFIRMED => 'Réservation confirmée',
            self::TYPE_BOOKING_CANCELLED => 'Réservation annulée',
            self::TYPE_BOOKING_REMINDER => 'Rappel de réservation',
            self::TYPE_BOOKING_COMPLETED => 'Service terminé',
            self::TYPE_QUOTE_RECEIVED => 'Nouveau devis reçu',
            self::TYPE_QUOTE_ACCEPTED => 'Devis accepté',
            self::TYPE_QUOTE_REJECTED => 'Devis refusé',
            self::TYPE_SERVICE_REQUEST_NEW => 'Nouvelle demande de service',
            self::TYPE_REPLACEMENT_NEEDED => 'Remplacement nécessaire',
            self::TYPE_REPLACEMENT_FOUND => 'Remplacement trouvé',
            self::TYPE_PAYMENT_RECEIVED => 'Paiement reçu',
            self::TYPE_PAYMENT_FAILED => 'Échec du paiement',
            self::TYPE_REVIEW_REQUEST => 'Votre avis nous intéresse',
            self::TYPE_DOCUMENT_EXPIRING => 'Document bientôt expiré',
            self::TYPE_AVAILABILITY_CONFLICT => 'Conflit de disponibilité',
            self::TYPE_PRESTATAIRE_APPROVED => 'Compte approuvé',
            self::TYPE_PRESTATAIRE_REJECTED => 'Demande refusée',
            default => 'Notification'
        };
    }

    /**
     * Génère le message d'une notification
     */
    private function getNotificationMessage(string $type, array $data): string
    {
        return match($type) {
            self::TYPE_BOOKING_CONFIRMED => sprintf(
                'Votre réservation du %s est confirmée',
                $data['booking']->getScheduledDateTime()->format('d/m/Y à H:i')
            ),
            self::TYPE_BOOKING_REMINDER => sprintf(
                'Rappel: Rendez-vous dans %dh',
                $data['hours_before']
            ),
            self::TYPE_QUOTE_RECEIVED => sprintf(
                'Nouveau devis de %s pour %s€',
                $data['prestataire']->getFullName(),
                number_format($data['amount'], 2)
            ),
            self::TYPE_PAYMENT_RECEIVED => sprintf(
                'Paiement de %s€ reçu',
                number_format($data['amount'], 2)
            ),
            self::TYPE_DOCUMENT_EXPIRING => sprintf(
                'Votre %s expire dans %d jours',
                $data['document_type'],
                $data['days_remaining']
            ),
            default => 'Vous avez une nouvelle notification'
        };
    }

    /**
     * Génère le sujet d'un email
     */
    private function getEmailSubject(string $type, array $data): string
    {
        $appName = 'CleanService';
        $title = $this->getNotificationTitle($type, $data);
        
        return "[{$appName}] {$title}";
    }

    /**
     * Vérifie les préférences de notification d'un utilisateur
     */
    private function shouldSendToChannel(User $user, string $channel): bool
    {
        // À implémenter selon votre système de préférences utilisateur
        // Par exemple, vérifier si l'utilisateur a désactivé les emails
        return true;
    }
}