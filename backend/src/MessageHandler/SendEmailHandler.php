<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Part\DataPart;
use Symfony\Component\Mime\Part\File;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmailHandler
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private string $defaultFromEmail;
    private string $defaultFromName;
    private int $maxRetries;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger,
        string $defaultFromEmail = 'noreply@serviceplatform.com',
        string $defaultFromName = 'Service Platform',
        int $maxRetries = 3
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->defaultFromEmail = $defaultFromEmail;
        $this->defaultFromName = $defaultFromName;
        $this->maxRetries = $maxRetries;
    }

    /**
     * Traiter le message d'email
     */
    public function __invoke(SendEmailMessage $message): void
    {
        $to = $message->getTo();
        $subject = $message->getSubject();
        $template = $message->getTemplate();
        $context = $message->getContext();
        $attachments = $message->getAttachments();

        $this->logger->info('Processing email message', [
            'to' => $to,
            'subject' => $subject,
            'template' => $template,
        ]);

        try {
            // Valider l'adresse email
            if (!$this->isValidEmail($to)) {
                $this->logger->error('Invalid email address', [
                    'to' => $to,
                ]);
                throw new \InvalidArgumentException('Invalid email address: ' . $to);
            }

            // Déterminer l'expéditeur
            $fromEmail = $message->getFromEmail() ?? $this->defaultFromEmail;
            $fromName = $message->getFromName() ?? $this->defaultFromName;

            // Créer l'email
            $email = (new TemplatedEmail())
                ->from(new Address($fromEmail, $fromName))
                ->to($to)
                ->subject($subject)
                ->htmlTemplate($template)
                ->context($context);

            // Ajouter les pièces jointes
            if (!empty($attachments)) {
                foreach ($attachments as $attachment) {
                    $this->attachFile($email, $attachment);
                }
            }

            // Définir la priorité de l'email
            $priority = $message->getPriority();
            if ($priority !== null) {
                $email->priority($this->convertPriorityToSymfony($priority));
            }

            // Envoyer l'email
            $this->mailer->send($email);

            $this->logger->info('Email sent successfully', [
                'to' => $to,
                'subject' => $subject,
            ]);

        } catch (\Symfony\Component\Mailer\Exception\TransportExceptionInterface $e) {
            $this->logger->error('Mail transport error', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
            ]);

            // Relancer l'exception pour que Messenger puisse réessayer
            throw $e;

        } catch (\Twig\Error\Error $e) {
            $this->logger->error('Template rendering error', [
                'template' => $template,
                'error' => $e->getMessage(),
            ]);

            // Ne pas réessayer si c'est une erreur de template
            throw new \RuntimeException('Email template error: ' . $e->getMessage(), 0, $e);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send email', [
                'to' => $to,
                'subject' => $subject,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Attacher un fichier à l'email
     */
    private function attachFile(TemplatedEmail $email, $attachment): void
    {
        try {
            if (is_string($attachment)) {
                // Simple chemin de fichier
                if (!file_exists($attachment)) {
                    $this->logger->warning('Attachment file not found', [
                        'file' => $attachment,
                    ]);
                    return;
                }

                $email->attachFromPath($attachment);

            } elseif (is_array($attachment)) {
                // Attachment avec options (path, name, mime)
                $path = $attachment['path'] ?? null;
                $name = $attachment['name'] ?? null;
                $mime = $attachment['mime'] ?? null;

                if (!$path || !file_exists($path)) {
                    $this->logger->warning('Attachment file not found', [
                        'file' => $path,
                    ]);
                    return;
                }

                if ($name && $mime) {
                    $email->attach(
                        file_get_contents($path),
                        $name,
                        $mime
                    );
                } elseif ($name) {
                    $email->attachFromPath($path, $name);
                } else {
                    $email->attachFromPath($path);
                }
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to attach file', [
                'attachment' => $attachment,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Valider une adresse email
     */
    private function isValidEmail(string $email): bool
    {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Convertir la priorité en format Symfony
     */
    private function convertPriorityToSymfony(int $priority): int
    {
        // Priority scale:
        // 1 = highest (Symfony: 1)
        // 3 = high (Symfony: 2)
        // 5 = normal (Symfony: 3) - default
        // 7 = low (Symfony: 4)
        // 9 = lowest (Symfony: 5)

        return match (true) {
            $priority >= 8 => 1,  // Highest
            $priority >= 6 => 2,  // High
            $priority >= 4 => 3,  // Normal
            $priority >= 2 => 4,  // Low
            default => 5,         // Lowest
        };
    }

    /**
     * Extraire le nom à partir de l'adresse email
     */
    private function extractNameFromEmail(string $email): string
    {
        $parts = explode('@', $email);
        if (count($parts) === 2) {
            return ucwords(str_replace(['.', '_', '-'], ' ', $parts[0]));
        }
        return $email;
    }

    /**
     * Créer un email de test pour vérification
     */
    public function sendTestEmail(string $to): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->defaultFromEmail, $this->defaultFromName))
                ->to($to)
                ->subject('Test Email - Service Platform')
                ->htmlTemplate('emails/test.html.twig')
                ->context([
                    'test_date' => new \DateTime(),
                    'recipient' => $to,
                ]);

            $this->mailer->send($email);

            $this->logger->info('Test email sent', [
                'to' => $to,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send test email', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Envoyer un email simple sans template
     */
    public function sendSimpleEmail(
        string $to,
        string $subject,
        string $htmlContent,
        ?string $textContent = null
    ): void {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->defaultFromEmail, $this->defaultFromName))
                ->to($to)
                ->subject($subject)
                ->html($htmlContent);

            if ($textContent) {
                $email->text($textContent);
            }

            $this->mailer->send($email);

            $this->logger->info('Simple email sent', [
                'to' => $to,
                'subject' => $subject,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to send simple email', [
                'to' => $to,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Envoyer un email en copie multiple
     */
    public function sendBulkEmail(
        array $recipients,
        string $subject,
        string $template,
        array $context
    ): array {
        $results = [
            'success' => 0,
            'failed' => 0,
            'errors' => [],
        ];

        foreach ($recipients as $recipient) {
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->defaultFromEmail, $this->defaultFromName))
                    ->to($recipient)
                    ->subject($subject)
                    ->htmlTemplate($template)
                    ->context($context);

                $this->mailer->send($email);
                $results['success']++;

            } catch (\Exception $e) {
                $results['failed']++;
                $results['errors'][$recipient] = $e->getMessage();

                $this->logger->error('Failed to send bulk email', [
                    'to' => $recipient,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $this->logger->info('Bulk email completed', [
            'total' => count($recipients),
            'success' => $results['success'],
            'failed' => $results['failed'],
        ]);

        return $results;
    }

    /**
     * Créer un email avec en-têtes personnalisés
     */
    private function addCustomHeaders(TemplatedEmail $email, array $headers): void
    {
        foreach ($headers as $name => $value) {
            $email->getHeaders()->addTextHeader($name, $value);
        }
    }

    /**
     * Ajouter une signature HTML à l'email
     */
    private function addSignature(array $context): array
    {
        $signature = <<<HTML
        <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
            <p style="color: #666; font-size: 14px;">
                Cordialement,<br>
                L'équipe Service Platform
            </p>
            <p style="color: #999; font-size: 12px;">
                Cet email a été envoyé automatiquement. Merci de ne pas y répondre directement.
            </p>
        </div>
HTML;

        return array_merge($context, ['email_signature' => $signature]);
    }

    /**
     * Vérifier si l'email doit être envoyé (selon préférences utilisateur)
     */
    private function shouldSendEmail(string $emailType, array $userPreferences): bool
    {
        // Vérifier si l'utilisateur a désactivé ce type d'email
        if (isset($userPreferences['disabled_emails'])) {
            return !in_array($emailType, $userPreferences['disabled_emails']);
        }

        // Vérifier si l'utilisateur a désactivé tous les emails
        if (isset($userPreferences['email_enabled'])) {
            return $userPreferences['email_enabled'];
        }

        // Par défaut, envoyer l'email
        return true;
    }

    /**
     * Logger les statistiques d'envoi
     */
    private function logEmailStats(string $to, string $subject, bool $success): void
    {
        $stats = [
            'timestamp' => new \DateTime(),
            'to' => $to,
            'subject' => $subject,
            'success' => $success,
        ];

        // Ici on pourrait enregistrer dans une table de statistiques
        $this->logger->info('Email stats', $stats);
    }

    /**
     * Nettoyer le contenu HTML pour éviter les problèmes
     */
    private function sanitizeHtml(string $html): string
    {
        // Supprimer les scripts
        $html = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', '', $html);

        // Supprimer les styles inline potentiellement dangereux
        $html = preg_replace('/on\w+\s*=\s*["\'][^"\']*["\']/i', '', $html);

        return $html;
    }

    /**
     * Générer une version texte à partir du HTML
     */
    private function htmlToText(string $html): string
    {
        // Supprimer les balises HTML
        $text = strip_tags($html);

        // Convertir les entités HTML
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        // Nettoyer les espaces multiples
        $text = preg_replace('/\s+/', ' ', $text);

        // Nettoyer les lignes vides multiples
        $text = preg_replace('/\n{3,}/', "\n\n", $text);

        return trim($text);
    }

    /**
     * Ajouter un tracking pixel pour suivre l'ouverture de l'email
     */
    private function addTrackingPixel(TemplatedEmail $email, string $trackingId): void
    {
        $trackingUrl = sprintf(
            'https://yourplatform.com/email-tracking/%s/open.gif',
            $trackingId
        );

        $email->getHeaders()->addTextHeader(
            'X-Tracking-ID',
            $trackingId
        );

        // Ajouter le pixel dans le contexte pour l'inclure dans le template
        $context = $email->getContext();
        $context['tracking_pixel'] = sprintf(
            '<img src="%s" width="1" height="1" alt="" />',
            $trackingUrl
        );
        $email->context($context);
    }
}