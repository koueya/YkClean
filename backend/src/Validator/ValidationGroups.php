<?php

namespace App\Validator;

use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceRequest;
use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\Payment\Payment;
use Symfony\Component\Security\Core\Security;

/**
 * Classe pour gérer les groupes de validation dynamiques
 * Permet de déterminer quels groupes de validation appliquer selon le contexte
 */
class ValidationGroups
{
    public function __construct(
        private Security $security
    ) {
    }

    /**
     * Groupes de validation pour l'inscription d'un utilisateur
     */
    public function getRegistrationGroups(object $object): array
    {
        $groups = ['Default', 'registration'];

        if ($object instanceof Client) {
            $groups[] = 'client:registration';
        } elseif ($object instanceof Prestataire) {
            $groups[] = 'prestataire:registration';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la mise à jour du profil
     */
    public function getProfileUpdateGroups(object $object): array
    {
        $groups = ['Default', 'profile:update'];

        if ($object instanceof Client) {
            $groups[] = 'client:update';
        } elseif ($object instanceof Prestataire) {
            $groups[] = 'prestataire:update';
            
            // Si le prestataire est déjà approuvé, on ne peut plus modifier certains champs
            if ($object->isApproved()) {
                $groups[] = 'prestataire:approved';
            }
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la création d'une demande de service
     */
    public function getServiceRequestCreationGroups(ServiceRequest $serviceRequest): array
    {
        $groups = ['Default', 'service_request:create'];

        // Validation différente selon le type de fréquence
        if ($serviceRequest->getFrequency() === 'ponctuel') {
            $groups[] = 'service_request:one_time';
        } else {
            $groups[] = 'service_request:recurring';
        }

        // Validation selon la catégorie de service
        $category = $serviceRequest->getCategory();
        if ($category) {
            switch ($category->getSlug()) {
                case 'nettoyage':
                    $groups[] = 'service_request:cleaning';
                    break;
                case 'repassage':
                    $groups[] = 'service_request:ironing';
                    break;
                case 'combine':
                    $groups[] = 'service_request:combined';
                    break;
            }
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la mise à jour d'une demande de service
     */
    public function getServiceRequestUpdateGroups(ServiceRequest $serviceRequest): array
    {
        $groups = ['Default', 'service_request:update'];

        // Si des devis ont déjà été soumis, certains champs ne peuvent plus être modifiés
        if ($serviceRequest->getQuotes()->count() > 0) {
            $groups[] = 'service_request:has_quotes';
        }

        // Si un devis a été accepté, la modification est très limitée
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getStatus() === 'accepted') {
                $groups[] = 'service_request:quote_accepted';
                break;
            }
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la création d'un devis
     */
    public function getQuoteCreationGroups(Quote $quote): array
    {
        $groups = ['Default', 'quote:create'];

        $serviceRequest = $quote->getServiceRequest();
        
        // Validation selon la fréquence du service
        if ($serviceRequest && $serviceRequest->getFrequency() !== 'ponctuel') {
            $groups[] = 'quote:recurring';
        }

        // Validation selon la catégorie
        if ($serviceRequest && $serviceRequest->getCategory()) {
            $groups[] = 'quote:' . $serviceRequest->getCategory()->getSlug();
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la mise à jour d'un devis
     */
    public function getQuoteUpdateGroups(Quote $quote): array
    {
        $groups = ['Default', 'quote:update'];

        // Un devis accepté ne peut plus être modifié
        if ($quote->getStatus() === 'accepted') {
            $groups[] = 'quote:accepted';
        }

        // Un devis expiré a des règles différentes
        if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTime()) {
            $groups[] = 'quote:expired';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la création d'une réservation
     */
    public function getBookingCreationGroups(Booking $booking): array
    {
        $groups = ['Default', 'booking:create'];

        // Validation selon le type de réservation
        if ($booking->getRecurrence()) {
            $groups[] = 'booking:recurring';
        } else {
            $groups[] = 'booking:one_time';
        }

        // Validation selon le statut
        if ($booking->getStatus() === 'pending') {
            $groups[] = 'booking:pending';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la mise à jour d'une réservation
     */
    public function getBookingUpdateGroups(Booking $booking): array
    {
        $groups = ['Default', 'booking:update'];

        $status = $booking->getStatus();

        // Groupes selon le statut actuel
        switch ($status) {
            case 'pending':
                $groups[] = 'booking:update_pending';
                break;
            case 'confirmed':
                $groups[] = 'booking:update_confirmed';
                break;
            case 'in_progress':
                $groups[] = 'booking:update_in_progress';
                break;
            case 'completed':
                $groups[] = 'booking:update_completed';
                break;
            case 'cancelled':
                $groups[] = 'booking:update_cancelled';
                break;
        }

        // Si un remplacement existe
        if ($booking->getReplacement()) {
            $groups[] = 'booking:has_replacement';
        }

        // Vérifier qui effectue la modification
        $currentUser = $this->security->getUser();
        if ($currentUser instanceof Client) {
            $groups[] = 'booking:client_update';
        } elseif ($currentUser instanceof Prestataire) {
            $groups[] = 'booking:prestataire_update';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour l'annulation d'une réservation
     */
    public function getBookingCancellationGroups(Booking $booking): array
    {
        $groups = ['Default', 'booking:cancel'];

        $currentUser = $this->security->getUser();
        
        // Règles différentes selon qui annule
        if ($currentUser instanceof Client) {
            $groups[] = 'booking:cancel_by_client';
        } elseif ($currentUser instanceof Prestataire) {
            $groups[] = 'booking:cancel_by_prestataire';
        }

        // Vérifier le délai d'annulation
        $scheduledDateTime = new \DateTime(
            $booking->getScheduledDate()->format('Y-m-d') . ' ' . 
            $booking->getScheduledTime()->format('H:i:s')
        );
        $now = new \DateTime();
        $hoursUntilBooking = ($scheduledDateTime->getTimestamp() - $now->getTimestamp()) / 3600;

        if ($hoursUntilBooking < 24) {
            $groups[] = 'booking:late_cancellation';
        } else {
            $groups[] = 'booking:early_cancellation';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour un paiement
     */
    public function getPaymentGroups(Payment $payment): array
    {
        $groups = ['Default', 'payment:create'];

        $paymentMethod = $payment->getPaymentMethod();
        
        // Validation selon la méthode de paiement
        switch ($paymentMethod) {
            case 'card':
                $groups[] = 'payment:card';
                break;
            case 'bank_transfer':
                $groups[] = 'payment:bank_transfer';
                break;
            case 'wallet':
                $groups[] = 'payment:wallet';
                break;
        }

        // Validation selon le type de paiement
        if ($payment->getType() === 'deposit') {
            $groups[] = 'payment:deposit';
        } elseif ($payment->getType() === 'full') {
            $groups[] = 'payment:full';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour un remboursement
     */
    public function getRefundGroups(Payment $payment): array
    {
        $groups = ['Default', 'payment:refund'];

        // Vérifier le délai depuis le paiement
        $paymentDate = $payment->getCreatedAt();
        $now = new \DateTime();
        $daysSincePayment = $now->diff($paymentDate)->days;

        if ($daysSincePayment <= 7) {
            $groups[] = 'refund:immediate';
        } elseif ($daysSincePayment <= 30) {
            $groups[] = 'refund:standard';
        } else {
            $groups[] = 'refund:late';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour l'onboarding d'un prestataire
     */
    public function getPrestataireOnboardingGroups(Prestataire $prestataire): array
    {
        $groups = ['Default', 'prestataire:onboarding'];

        // Étapes de validation selon le statut d'onboarding
        $onboardingStep = $prestataire->getOnboardingStep() ?? 'personal_info';

        switch ($onboardingStep) {
            case 'personal_info':
                $groups[] = 'onboarding:step1';
                break;
            case 'professional_info':
                $groups[] = 'onboarding:step2';
                break;
            case 'documents':
                $groups[] = 'onboarding:step3';
                break;
            case 'bank_account':
                $groups[] = 'onboarding:step4';
                break;
            case 'services':
                $groups[] = 'onboarding:step5';
                break;
        }

        return $groups;
    }

    /**
     * Groupes de validation pour l'approbation d'un prestataire par un admin
     */
    public function getPrestataireApprovalGroups(Prestataire $prestataire): array
    {
        $groups = ['Default', 'prestataire:approval'];

        // Vérifier que tous les documents requis sont présents
        if (!$prestataire->getKbis()) {
            $groups[] = 'approval:missing_kbis';
        }

        if (!$prestataire->getInsurance()) {
            $groups[] = 'approval:missing_insurance';
        }

        if (!$prestataire->getStripeConnectedAccountId()) {
            $groups[] = 'approval:missing_stripe';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la modification du mot de passe
     */
    public function getPasswordChangeGroups(User $user): array
    {
        $groups = ['Default', 'password:change'];

        // Si c'est un changement suite à un oubli
        if ($user->getPasswordResetToken()) {
            $groups[] = 'password:reset';
        } else {
            $groups[] = 'password:update';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la vérification du compte
     */
    public function getAccountVerificationGroups(User $user): array
    {
        $groups = ['Default', 'account:verification'];

        if ($user instanceof Prestataire) {
            $groups[] = 'prestataire:verification';
        } elseif ($user instanceof Client) {
            $groups[] = 'client:verification';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour la disponibilité d'un prestataire
     */
    public function getAvailabilityGroups(object $availability): array
    {
        $groups = ['Default', 'availability:create'];

        // Validation différente pour disponibilités récurrentes vs ponctuelles
        if (method_exists($availability, 'isRecurring') && $availability->isRecurring()) {
            $groups[] = 'availability:recurring';
        } else {
            $groups[] = 'availability:one_time';
        }

        return $groups;
    }

    /**
     * Groupes de validation pour un avis/review
     */
    public function getReviewGroups(object $review): array
    {
        $groups = ['Default', 'review:create'];

        // Vérifier que la réservation est complétée
        if (method_exists($review, 'getBooking')) {
            $booking = $review->getBooking();
            if ($booking && $booking->getStatus() !== 'completed') {
                $groups[] = 'review:booking_not_completed';
            }
        }

        return $groups;
    }

    /**
     * Groupes de validation pour une demande de remplacement
     */
    public function getReplacementGroups(object $replacement): array
    {
        $groups = ['Default', 'replacement:create'];

        $currentUser = $this->security->getUser();

        if ($currentUser instanceof Prestataire) {
            $groups[] = 'replacement:by_prestataire';
        }

        // Vérifier le délai avant la réservation
        if (method_exists($replacement, 'getOriginalBooking')) {
            $booking = $replacement->getOriginalBooking();
            if ($booking) {
                $scheduledDateTime = new \DateTime(
                    $booking->getScheduledDate()->format('Y-m-d') . ' ' . 
                    $booking->getScheduledTime()->format('H:i:s')
                );
                $now = new \DateTime();
                $hoursUntilBooking = ($scheduledDateTime->getTimestamp() - $now->getTimestamp()) / 3600;

                if ($hoursUntilBooking < 48) {
                    $groups[] = 'replacement:urgent';
                } else {
                    $groups[] = 'replacement:advance';
                }
            }
        }

        return $groups;
    }

    /**
     * Méthode utilitaire pour obtenir les groupes de validation selon le contexte
     */
    public function getValidationGroups(object $object, string $context = 'create'): array
    {
        return match (true) {
            $object instanceof ServiceRequest && $context === 'create' 
                => $this->getServiceRequestCreationGroups($object),
            $object instanceof ServiceRequest && $context === 'update' 
                => $this->getServiceRequestUpdateGroups($object),
            $object instanceof Quote && $context === 'create' 
                => $this->getQuoteCreationGroups($object),
            $object instanceof Quote && $context === 'update' 
                => $this->getQuoteUpdateGroups($object),
            $object instanceof Booking && $context === 'create' 
                => $this->getBookingCreationGroups($object),
            $object instanceof Booking && $context === 'update' 
                => $this->getBookingUpdateGroups($object),
            $object instanceof Booking && $context === 'cancel' 
                => $this->getBookingCancellationGroups($object),
            $object instanceof Payment && $context === 'create' 
                => $this->getPaymentGroups($object),
            $object instanceof Payment && $context === 'refund' 
                => $this->getRefundGroups($object),
            $object instanceof Prestataire && $context === 'onboarding' 
                => $this->getPrestataireOnboardingGroups($object),
            $object instanceof Prestataire && $context === 'approval' 
                => $this->getPrestataireApprovalGroups($object),
            $object instanceof User && $context === 'registration' 
                => $this->getRegistrationGroups($object),
            $object instanceof User && $context === 'profile_update' 
                => $this->getProfileUpdateGroups($object),
            $object instanceof User && $context === 'password_change' 
                => $this->getPasswordChangeGroups($object),
            default => ['Default'],
        };
    }
}