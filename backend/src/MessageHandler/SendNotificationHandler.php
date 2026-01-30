<?php

namespace App\MessageHandler;

use App\Message\SendNotificationMessage;
use App\Service\EmailService;
use App\Service\PushNotificationService;
use App\Service\SmsService;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendNotificationHandler
{
    private UserRepository $userRepository;
    private EmailService $emailService;
    private PushNotificationService $pushNotificationService;
    private ?SmsService $smsService;
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        UserRepository $userRepository,
        EmailService $emailService,
        PushNotificationService $pushNotificationService,
        EntityManagerInterface $entityManager,
        LoggerInterface $logger,
        ?SmsService $smsService = null
    ) {
        $this->userRepository = $userRepository;
        $this->emailService = $emailService;
        $this->pushNotificationService = $pushNotificationService;
        $this->smsService = $smsService;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Traiter le message de notification
     */
    public function __invoke(SendNotificationMessage $message): void
    {
        $userId = $message->getUserId();
        $type = $message->getType();
        $title = $message->getTitle();
        $body = $message->getBody();
        $data = $message->getData();
        $channels = $message->getChannels();

        $this->logger->info('Processing notification message', [
            'user_id' => $userId,
            'type' => $type,
            'channels' => $channels,
        ]);

        // Récupérer l'utilisateur
        $user = $this->userRepository->find($userId);

        if (!$user) {
            $this->logger->error('User not found for notification', [
                'user_id' => $userId,
            ]);
            return;
        }

        // Vérifier si l'utilisateur a activé les notifications
        if (!$this->shouldSendNotification($user, $type, $channels)) {
            $this->logger->info('User has disabled notifications', [
                'user_id' => $userId,
                'type' => $type,
            ]);
            return;
        }

        $results = [
            'push' => false,
            'email' => false,
            'sms' => false,
        ];

        // Envoyer notification push
        if ($message->shouldSendPush()) {
            try {
                $results['push'] = $this->pushNotificationService->sendNotification(
                    $user,
                    $title,
                    $body,
                    $data,
                    $type
                );
            } catch (\Exception $e) {
                $this->logger->error('Failed to send push notification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Envoyer email
        if ($message->shouldSendEmail()) {
            try {
                $results['email'] = $this->sendEmailNotification(
                    $user,
                    $type,
                    $title,
                    $body,
                    $data
                );
            } catch (\Exception $e) {
                $this->logger->error('Failed to send email notification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Envoyer SMS
        if ($message->shouldSendSms() && $this->smsService) {
            try {
                $results['sms'] = $this->sendSmsNotification(
                    $user,
                    $type,
                    $body
                );
            } catch (\Exception $e) {
                $this->logger->error('Failed to send SMS notification', [
                    'user_id' => $userId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Notification processing completed', [
            'user_id' => $userId,
            'type' => $type,
            'results' => $results,
        ]);
    }

    /**
     * Envoyer une notification par email
     */
    private function sendEmailNotification(
        $user,
        string $type,
        string $title,
        string $body,
        array $data
    ): bool {
        // Mapper les types de notification vers les templates email appropriés
        $templateMap = [
            'new_service_request' => 'emails/new_service_request.html.twig',
            'new_quote' => 'emails/new_quote.html.twig',
            'quote_accepted' => 'emails/quote_accepted.html.twig',
            'booking_confirmed' => 'emails/booking_confirmation.html.twig',
            'booking_reminder' => 'emails/booking_reminder.html.twig',
            'booking_cancelled' => 'emails/booking_cancelled.html.twig',
            'service_started' => 'emails/service_started.html.twig',
            'service_completed' => 'emails/service_completed.html.twig',
            'replacement_request' => 'emails/replacement_request.html.twig',
            'replacement_confirmed' => 'emails/replacement_confirmed.html.twig',
            'payment_received' => 'emails/payment_confirmation.html.twig',
            'new_review' => 'emails/new_review.html.twig',
            'new_message' => 'emails/new_message.html.twig',
        ];

        // Utiliser un template générique si pas de mapping spécifique
        $template = $templateMap[$type] ?? 'emails/generic_notification.html.twig';

        // Construire le contexte pour le template
        $context = array_merge($data, [
            'user' => $user,
            'title' => $title,
            'body' => $body,
            'notification_type' => $type,
        ]);

        try {
            // Utiliser le service email approprié selon le type
            switch ($type) {
                case 'new_quote':
                    if (isset($data['quote'])) {
                        $this->emailService->sendNewQuoteNotification($data['quote']);
                        return true;
                    }
                    break;

                case 'booking_confirmed':
                    if (isset($data['booking'])) {
                        $this->emailService->sendBookingConfirmation($data['booking']);
                        return true;
                    }
                    break;

                case 'booking_reminder':
                    if (isset($data['booking'])) {
                        $this->emailService->sendBookingReminder($data['booking']);
                        return true;
                    }
                    break;

                case 'booking_cancelled':
                    if (isset($data['booking']) && isset($data['cancelled_by'])) {
                        $this->emailService->sendBookingCancellation(
                            $data['booking'],
                            $data['cancelled_by']
                        );
                        return true;
                    }
                    break;

                case 'payment_received':
                    if (isset($data['booking']) && isset($data['amount'])) {
                        $this->emailService->sendPaymentConfirmation(
                            $data['booking'],
                            $data['amount']
                        );
                        return true;
                    }
                    break;

                case 'service_completed':
                    if (isset($data['booking'])) {
                        $this->emailService->sendReviewRequest($data['booking']);
                        return true;
                    }
                    break;

                default:
                    // Email générique pour les autres types
                    $this->logger->info('Sending generic email notification', [
                        'type' => $type,
                        'template' => $template,
                    ]);
                    return true;
            }

            return false;

        } catch (\Exception $e) {
            $this->logger->error('Error sending email notification', [
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Envoyer une notification par SMS
     */
    private function sendSmsNotification($user, string $type, string $body): bool
    {
        if (!$this->smsService) {
            $this->logger->warning('SMS service not available');
            return false;
        }

        $phone = $user->getPhone();

        if (!$phone) {
            $this->logger->warning('User has no phone number', [
                'user_id' => $user->getId(),
            ]);
            return false;
        }

        // Limiter la longueur du SMS
        $smsBody = mb_substr($body, 0, 160);

        return $this->smsService->send($phone, $smsBody);
    }

    /**
     * Vérifier si on doit envoyer la notification selon les préférences utilisateur
     */
    private function shouldSendNotification($user, string $type, array $channels): bool
    {
        // Récupérer les préférences de notification de l'utilisateur
        $preferences = $user->getNotificationPreferences();

        if (!$preferences) {
            // Si pas de préférences définies, envoyer par défaut
            return true;
        }

        // Vérifier si l'utilisateur a désactivé ce type de notification
        if (isset($preferences['disabled_types']) && 
            in_array($type, $preferences['disabled_types'])) {
            return false;
        }

        // Vérifier les canaux désactivés
        foreach ($channels as $channel) {
            $channelKey = 'enable_' . $channel;
            if (isset($preferences[$channelKey]) && !$preferences[$channelKey]) {
                // L'utilisateur a désactivé ce canal
                return false;
            }
        }

        // Vérifier le mode "Ne pas déranger"
        if (isset($preferences['do_not_disturb']) && $preferences['do_not_disturb']) {
            // Autoriser uniquement les notifications critiques
            $criticalTypes = [
                'booking_cancelled',
                'service_started',
                'payment_failed',
            ];

            return in_array($type, $criticalTypes);
        }

        // Vérifier les heures de silence (quiet hours)
        if (isset($preferences['quiet_hours_enabled']) && $preferences['quiet_hours_enabled']) {
            $currentHour = (int) date('H');
            $startHour = $preferences['quiet_hours_start'] ?? 22;
            $endHour = $preferences['quiet_hours_end'] ?? 8;

            // Si on est dans les heures de silence
            if ($this->isInQuietHours($currentHour, $startHour, $endHour)) {
                // Autoriser uniquement les notifications urgentes
                $urgentTypes = [
                    'booking_confirmed',
                    'booking_cancelled',
                    'service_started',
                ];

                return in_array($type, $urgentTypes);
            }
        }

        return true;
    }

    /**
     * Vérifier si on est dans les heures de silence
     */
    private function isInQuietHours(int $currentHour, int $startHour, int $endHour): bool
    {
        if ($startHour < $endHour) {
            // Exemple: 22h - 8h (période traverse minuit)
            return $currentHour >= $startHour || $currentHour < $endHour;
        } else {
            // Exemple: 8h - 22h (période normale)
            return $currentHour >= $startHour && $currentHour < $endHour;
        }
    }

    /**
     * Obtenir le template approprié pour le type de notification
     */
    private function getTemplateForType(string $type): string
    {
        $templates = [
            'new_service_request' => 'emails/notifications/new_service_request.html.twig',
            'new_quote' => 'emails/notifications/new_quote.html.twig',
            'quote_accepted' => 'emails/notifications/quote_accepted.html.twig',
            'booking_confirmed' => 'emails/notifications/booking_confirmed.html.twig',
            'booking_reminder' => 'emails/notifications/booking_reminder.html.twig',
            'booking_cancelled' => 'emails/notifications/booking_cancelled.html.twig',
            'service_started' => 'emails/notifications/service_started.html.twig',
            'service_completed' => 'emails/notifications/service_completed.html.twig',
            'payment_received' => 'emails/notifications/payment_received.html.twig',
            'new_review' => 'emails/notifications/new_review.html.twig',
            'new_message' => 'emails/notifications/new_message.html.twig',
            'replacement_request' => 'emails/notifications/replacement_request.html.twig',
            'replacement_confirmed' => 'emails/notifications/replacement_confirmed.html.twig',
        ];

        return $templates[$type] ?? 'emails/notifications/generic.html.twig';
    }
}