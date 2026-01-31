<?php

namespace App\Service\Notification;

use App\Entity\User\User;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\Service\ServiceRequest;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Psr\Log\LoggerInterface;

class EmailService
{
    private MailerInterface $mailer;
    private LoggerInterface $logger;
    private string $fromEmail;
    private string $fromName;
    private string $appUrl;
    private string $adminEmail;

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger,
        string $fromEmail = 'noreply@serviceplatform.com',
        string $fromName = 'Service Platform',
        string $appUrl = 'https://serviceplatform.com',
        string $adminEmail = 'admin@serviceplatform.com'
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->appUrl = $appUrl;
        $this->adminEmail = $adminEmail;
    }

    /**
     * Envoi email de vérification d'adresse email
     */
    public function sendEmailVerification(User $user): void
    {
        try {
            $verificationUrl = $this->appUrl . '/api/prestataire/verify-email/' . $user->getEmailVerificationToken();

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Vérifiez votre adresse email')
                ->htmlTemplate('emails/email_verification.html.twig')
                ->context([
                    'user' => $user,
                    'verification_url' => $verificationUrl,
                    'token' => $user->getEmailVerificationToken(),
                    'expires_at' => $user->getEmailVerificationTokenExpiresAt(),
                ]);

            $this->mailer->send($email);
            $this->logger->info('Email verification sent', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send email verification', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Envoi email de bienvenue après inscription
     */
    public function sendWelcomeEmail(User $user): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Bienvenue sur Service Platform')
                ->htmlTemplate('emails/welcome.html.twig')
                ->context([
                    'user' => $user,
                    'dashboard_url' => $this->appUrl . '/dashboard',
                ]);

            $this->mailer->send($email);
            $this->logger->info('Welcome email sent', ['user_id' => $user->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send welcome email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification de nouvelle inscription de prestataire à l'admin
     */
    public function sendNewPrestataireRegistrationNotification(Prestataire $prestataire): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($this->adminEmail, 'Admin'))
                ->subject('Nouvelle inscription prestataire en attente d\'approbation')
                ->htmlTemplate('emails/admin/new_prestataire_registration.html.twig')
                ->context([
                    'prestataire' => $prestataire,
                    'review_url' => $this->appUrl . '/admin/prestataires/' . $prestataire->getId(),
                    'registration_date' => new \DateTime(),
                ]);

            $this->mailer->send($email);
            $this->logger->info('New prestataire registration notification sent to admin', [
                'prestataire_id' => $prestataire->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send new prestataire registration notification', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification d'approbation de compte prestataire
     */
    public function sendPrestataireApprovalEmail(Prestataire $prestataire): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Votre compte prestataire a été approuvé !')
                ->htmlTemplate('emails/prestataire/account_approved.html.twig')
                ->context([
                    'prestataire' => $prestataire,
                    'dashboard_url' => $this->appUrl . '/prestataire/dashboard',
                    'approved_at' => new \DateTime(),
                ]);

            $this->mailer->send($email);
            $this->logger->info('Prestataire approval email sent', [
                'prestataire_id' => $prestataire->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send prestataire approval email', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification de rejet de compte prestataire
     */
    public function sendPrestataireRejectionEmail(Prestataire $prestataire, string $reason = ''): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Votre demande d\'inscription a été refusée')
                ->htmlTemplate('emails/prestataire/account_rejected.html.twig')
                ->context([
                    'prestataire' => $prestataire,
                    'reason' => $reason,
                    'contact_url' => $this->appUrl . '/contact',
                    'rejected_at' => new \DateTime(),
                ]);

            $this->mailer->send($email);
            $this->logger->info('Prestataire rejection email sent', [
                'prestataire_id' => $prestataire->getId()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send prestataire rejection email', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification de nouvelle demande de service aux prestataires
     */
    public function sendNewServiceRequestNotification(ServiceRequest $serviceRequest, array $prestataires): void
    {
        foreach ($prestataires as $prestataire) {
            try {
                $email = (new TemplatedEmail())
                    ->from(new Address($this->fromEmail, $this->fromName))
                    ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                    ->subject('Nouvelle demande de service dans votre zone')
                    ->htmlTemplate('emails/prestataire/new_service_request.html.twig')
                    ->context([
                        'prestataire' => $prestataire,
                        'serviceRequest' => $serviceRequest,
                        'request_url' => $this->appUrl . '/prestataire/requests/' . $serviceRequest->getId(),
                    ]);

                $this->mailer->send($email);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send service request notification', [
                    'prestataire_id' => $prestataire->getId(),
                    'service_request_id' => $serviceRequest->getId(),
                    'error' => $e->getMessage()
                ]);
            }
        }
    }

    /**
     * Notification de nouveau devis au client
     */
    public function sendNewQuoteNotification(Quote $quote): void
    {
        try {
            $client = $quote->getServiceRequest()->getClient();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject('Nouveau devis reçu pour votre demande')
                ->htmlTemplate('emails/client/new_quote.html.twig')
                ->context([
                    'client' => $client,
                    'quote' => $quote,
                    'serviceRequest' => $quote->getServiceRequest(),
                    'prestataire' => $quote->getPrestataire(),
                    'quote_url' => $this->appUrl . '/client/quotes/' . $quote->getId(),
                ]);

            $this->mailer->send($email);
            $this->logger->info('Quote notification sent', ['quote_id' => $quote->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send quote notification', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification d'acceptation de devis au prestataire
     */
    public function sendQuoteAcceptedNotification(Quote $quote): void
    {
        try {
            $prestataire = $quote->getPrestataire();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Votre devis a été accepté !')
                ->htmlTemplate('emails/prestataire/quote_accepted.html.twig')
                ->context([
                    'prestataire' => $prestataire,
                    'quote' => $quote,
                    'client' => $quote->getServiceRequest()->getClient(),
                    'booking_url' => $this->appUrl . '/prestataire/bookings',
                ]);

            $this->mailer->send($email);
            $this->logger->info('Quote accepted notification sent', ['quote_id' => $quote->getId()]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send quote accepted notification', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Confirmation de réservation
     */
    public function sendBookingConfirmation(Booking $booking): void
    {
        // Email au client
        try {
            $client = $booking->getClient();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject('Confirmation de votre réservation')
                ->htmlTemplate('emails/booking/confirmation_client.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
                    'prestataire' => $booking->getPrestataire(),
                    'booking_url' => $this->appUrl . '/client/bookings/' . $booking->getId(),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send booking confirmation to client', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }

        // Email au prestataire
        try {
            $prestataire = $booking->getPrestataire();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Nouvelle réservation confirmée')
                ->htmlTemplate('emails/booking/confirmation_prestataire.html.twig')
                ->context([
                    'booking' => $booking,
                    'prestataire' => $prestataire,
                    'client' => $booking->getClient(),
                    'booking_url' => $this->appUrl . '/prestataire/bookings/' . $booking->getId(),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send booking confirmation to prestataire', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Rappel de réservation (24h avant)
     */
    public function sendBookingReminder(Booking $booking): void
    {
        // Rappel au client
        try {
            $client = $booking->getClient();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject('Rappel : Votre service demain')
                ->htmlTemplate('emails/booking/reminder_client.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
                    'prestataire' => $booking->getPrestataire(),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send booking reminder to client', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }

        // Rappel au prestataire
        try {
            $prestataire = $booking->getPrestataire();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Rappel : Service prévu demain')
                ->htmlTemplate('emails/booking/reminder_prestataire.html.twig')
                ->context([
                    'booking' => $booking,
                    'prestataire' => $prestataire,
                    'client' => $booking->getClient(),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send booking reminder to prestataire', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification d'annulation
     */
    public function sendBookingCancellation(Booking $booking, string $cancelledBy): void
    {
        try {
            $client = $booking->getClient();
            $prestataire = $booking->getPrestataire();

            // Email au client
            $clientEmail = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject('Annulation de votre réservation')
                ->htmlTemplate('emails/booking/cancelled.html.twig')
                ->context([
                    'booking' => $booking,
                    'recipient' => $client,
                    'cancelledBy' => $cancelledBy,
                ]);

            // Email au prestataire
            $prestataireEmail = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Annulation de réservation')
                ->htmlTemplate('emails/booking/cancelled.html.twig')
                ->context([
                    'booking' => $booking,
                    'recipient' => $prestataire,
                    'cancelledBy' => $cancelledBy,
                ]);

            $this->mailer->send($clientEmail);
            $this->mailer->send($prestataireEmail);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send cancellation emails', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification de demande de remplacement
     */
    public function sendReplacementRequest(Booking $originalBooking, User $replacementPrestataire): void
    {
        try {
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($replacementPrestataire->getEmail(), $replacementPrestataire->getFullName()))
                ->subject('Demande de remplacement')
                ->htmlTemplate('emails/replacement/request.html.twig')
                ->context([
                    'booking' => $originalBooking,
                    'prestataire' => $replacementPrestataire,
                    'respond_url' => $this->appUrl . '/prestataire/replacements',
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send replacement request', [
                'booking_id' => $originalBooking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification de remplacement confirmé au client
     */
    public function sendReplacementConfirmed(Booking $booking, User $newPrestataire): void
    {
        try {
            $client = $booking->getClient();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject('Changement de prestataire pour votre réservation')
                ->htmlTemplate('emails/replacement/confirmed.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
                    'newPrestataire' => $newPrestataire,
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send replacement confirmation', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Email de réinitialisation de mot de passe
     */
    public function sendPasswordResetEmail(User $user, string $resetToken): void
    {
        try {
            $resetUrl = $this->appUrl . '/reset-password/' . $resetToken;

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'resetToken' => $resetToken,
                    'resetUrl' => $resetUrl,
                    'expiresAt' => new \DateTime('+1 hour'),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send password reset email', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Demande d'avis après service
     */
    public function sendReviewRequest(Booking $booking): void
    {
        try {
            $client = $booking->getClient();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject('Donnez votre avis sur le service')
                ->htmlTemplate('emails/review/request.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
                    'prestataire' => $booking->getPrestataire(),
                    'review_url' => $this->appUrl . '/client/bookings/' . $booking->getId() . '/review',
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send review request', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification de paiement reçu
     */
    public function sendPaymentConfirmation(Booking $booking, float $amount): void
    {
        try {
            $prestataire = $booking->getPrestataire();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Paiement reçu')
                ->htmlTemplate('emails/payment/confirmation.html.twig')
                ->context([
                    'booking' => $booking,
                    'prestataire' => $prestataire,
                    'amount' => $amount,
                    'paid_at' => new \DateTime(),
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment confirmation', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification d'échec de paiement
     */
    public function sendPaymentFailedNotification(Booking $booking, string $reason): void
    {
        try {
            $client = $booking->getClient();
            
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($client->getEmail(), $client->getFullName()))
                ->subject('Échec de paiement')
                ->htmlTemplate('emails/payment/failed.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
                    'reason' => $reason,
                    'retry_url' => $this->appUrl . '/client/bookings/' . $booking->getId() . '/payment',
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment failed notification', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notification d'expiration de document
     */
    public function sendDocumentExpiringNotification(Prestataire $prestataire, string $documentType, \DateTimeInterface $expiryDate): void
    {
        try {
            $daysRemaining = (new \DateTime())->diff($expiryDate)->days;

            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($prestataire->getEmail(), $prestataire->getFullName()))
                ->subject('Document bientôt expiré')
                ->htmlTemplate('emails/prestataire/document_expiring.html.twig')
                ->context([
                    'prestataire' => $prestataire,
                    'documentType' => $documentType,
                    'expiryDate' => $expiryDate,
                    'daysRemaining' => $daysRemaining,
                    'update_url' => $this->appUrl . '/prestataire/documents',
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send document expiring notification', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}