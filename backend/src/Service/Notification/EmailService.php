<?php

namespace App\Service;

use App\Entity\User\User;
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

    public function __construct(
        MailerInterface $mailer,
        LoggerInterface $logger,
        string $fromEmail = 'noreply@serviceplatform.com',
        string $fromName = 'Service Platform'
    ) {
        $this->mailer = $mailer;
        $this->logger = $logger;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
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
                    ->htmlTemplate('emails/new_service_request.html.twig')
                    ->context([
                        'prestataire' => $prestataire,
                        'serviceRequest' => $serviceRequest,
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
                ->htmlTemplate('emails/new_quote.html.twig')
                ->context([
                    'client' => $client,
                    'quote' => $quote,
                    'serviceRequest' => $quote->getServiceRequest(),
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
                ->htmlTemplate('emails/booking_confirmation_client.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
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
                ->htmlTemplate('emails/booking_confirmation_prestataire.html.twig')
                ->context([
                    'booking' => $booking,
                    'prestataire' => $prestataire,
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
                ->htmlTemplate('emails/booking_reminder_client.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
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
                ->htmlTemplate('emails/booking_reminder_prestataire.html.twig')
                ->context([
                    'booking' => $booking,
                    'prestataire' => $prestataire,
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
                ->htmlTemplate('emails/booking_cancelled.html.twig')
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
                ->htmlTemplate('emails/booking_cancelled.html.twig')
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
                ->htmlTemplate('emails/replacement_request.html.twig')
                ->context([
                    'booking' => $originalBooking,
                    'prestataire' => $replacementPrestataire,
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
                ->htmlTemplate('emails/replacement_confirmed.html.twig')
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
            $email = (new TemplatedEmail())
                ->from(new Address($this->fromEmail, $this->fromName))
                ->to(new Address($user->getEmail(), $user->getFullName()))
                ->subject('Réinitialisation de votre mot de passe')
                ->htmlTemplate('emails/password_reset.html.twig')
                ->context([
                    'user' => $user,
                    'resetToken' => $resetToken,
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
                ->htmlTemplate('emails/review_request.html.twig')
                ->context([
                    'booking' => $booking,
                    'client' => $client,
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
                ->htmlTemplate('emails/payment_confirmation.html.twig')
                ->context([
                    'booking' => $booking,
                    'prestataire' => $prestataire,
                    'amount' => $amount,
                ]);

            $this->mailer->send($email);
        } catch (\Exception $e) {
            $this->logger->error('Failed to send payment confirmation', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}