<?php

namespace App\Security\Voter;

use App\Entity\User\User;
use App\Entity\User\Prestataire;
use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PrestataireVoter extends Voter
{
    // Permissions pour les prestataires
    public const VIEW = 'PRESTATAIRE_VIEW';
    public const EDIT = 'PRESTATAIRE_EDIT';
    public const DELETE = 'PRESTATAIRE_DELETE';
    public const MANAGE_AVAILABILITY = 'PRESTATAIRE_MANAGE_AVAILABILITY';
    public const VIEW_EARNINGS = 'PRESTATAIRE_VIEW_EARNINGS';
    public const WITHDRAW = 'PRESTATAIRE_WITHDRAW';
    
    // Permissions pour les bookings
    public const VIEW_BOOKING = 'PRESTATAIRE_VIEW_BOOKING';
    public const UPDATE_BOOKING = 'PRESTATAIRE_UPDATE_BOOKING';
    public const CANCEL_BOOKING = 'PRESTATAIRE_CANCEL_BOOKING';
    public const START_SERVICE = 'PRESTATAIRE_START_SERVICE';
    public const COMPLETE_SERVICE = 'PRESTATAIRE_COMPLETE_SERVICE';
    
    // Permissions pour les devis
    public const CREATE_QUOTE = 'PRESTATAIRE_CREATE_QUOTE';
    public const VIEW_QUOTE = 'PRESTATAIRE_VIEW_QUOTE';
    public const EDIT_QUOTE = 'PRESTATAIRE_EDIT_QUOTE';
    public const DELETE_QUOTE = 'PRESTATAIRE_DELETE_QUOTE';
    
    // Permissions pour les remplacements
    public const REQUEST_REPLACEMENT = 'PRESTATAIRE_REQUEST_REPLACEMENT';
    public const ACCEPT_REPLACEMENT = 'PRESTATAIRE_ACCEPT_REPLACEMENT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Vérifier si l'attribut est supporté
        $prestataireAttributes = [
            self::VIEW,
            self::EDIT,
            self::DELETE,
            self::MANAGE_AVAILABILITY,
            self::VIEW_EARNINGS,
            self::WITHDRAW,
        ];

        $bookingAttributes = [
            self::VIEW_BOOKING,
            self::UPDATE_BOOKING,
            self::CANCEL_BOOKING,
            self::START_SERVICE,
            self::COMPLETE_SERVICE,
        ];

        $quoteAttributes = [
            self::CREATE_QUOTE,
            self::VIEW_QUOTE,
            self::EDIT_QUOTE,
            self::DELETE_QUOTE,
        ];

        $replacementAttributes = [
            self::REQUEST_REPLACEMENT,
            self::ACCEPT_REPLACEMENT,
        ];

        if (in_array($attribute, $prestataireAttributes)) {
            return $subject instanceof Prestataire;
        }

        if (in_array($attribute, $bookingAttributes)) {
            return $subject instanceof Booking;
        }

        if (in_array($attribute, $quoteAttributes)) {
            return $subject instanceof Quote || $subject === null;
        }

        if (in_array($attribute, $replacementAttributes)) {
            return $subject instanceof Booking;
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être authentifié
        if (!$user instanceof User) {
            return false;
        }

        // Les admins ont tous les droits
        if (in_array('ROLE_ADMIN', $user->getRoles())) {
            return true;
        }

        // Vérifier selon l'attribut
        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::MANAGE_AVAILABILITY => $this->canManageAvailability($subject, $user),
            self::VIEW_EARNINGS => $this->canViewEarnings($subject, $user),
            self::WITHDRAW => $this->canWithdraw($subject, $user),
            
            self::VIEW_BOOKING => $this->canViewBooking($subject, $user),
            self::UPDATE_BOOKING => $this->canUpdateBooking($subject, $user),
            self::CANCEL_BOOKING => $this->canCancelBooking($subject, $user),
            self::START_SERVICE => $this->canStartService($subject, $user),
            self::COMPLETE_SERVICE => $this->canCompleteService($subject, $user),
            
            self::CREATE_QUOTE => $this->canCreateQuote($user),
            self::VIEW_QUOTE => $this->canViewQuote($subject, $user),
            self::EDIT_QUOTE => $this->canEditQuote($subject, $user),
            self::DELETE_QUOTE => $this->canDeleteQuote($subject, $user),
            
            self::REQUEST_REPLACEMENT => $this->canRequestReplacement($subject, $user),
            self::ACCEPT_REPLACEMENT => $this->canAcceptReplacement($subject, $user),
            
            default => false,
        };
    }

    /**
     * Vérifier si l'utilisateur peut voir le profil du prestataire
     */
    private function canView(Prestataire $prestataire, User $user): bool
    {
        // Le prestataire peut voir son propre profil
        if ($user->getId() === $prestataire->getId()) {
            return true;
        }

        // Les autres utilisateurs peuvent voir les profils publics uniquement
        return $prestataire->isActive() && $prestataire->isApproved();
    }

    /**
     * Vérifier si l'utilisateur peut modifier le profil du prestataire
     */
    private function canEdit(Prestataire $prestataire, User $user): bool
    {
        // Seul le prestataire peut modifier son propre profil
        return $user->getId() === $prestataire->getId();
    }

    /**
     * Vérifier si l'utilisateur peut supprimer le prestataire
     */
    private function canDelete(Prestataire $prestataire, User $user): bool
    {
        // Seul le prestataire peut supprimer son compte (et les admins via le check global)
        return $user->getId() === $prestataire->getId();
    }

    /**
     * Vérifier si l'utilisateur peut gérer les disponibilités
     */
    private function canManageAvailability(Prestataire $prestataire, User $user): bool
    {
        return $user->getId() === $prestataire->getId() && 
               in_array('ROLE_PRESTATAIRE', $user->getRoles());
    }

    /**
     * Vérifier si l'utilisateur peut voir les revenus
     */
    private function canViewEarnings(Prestataire $prestataire, User $user): bool
    {
        return $user->getId() === $prestataire->getId() && 
               in_array('ROLE_PRESTATAIRE', $user->getRoles());
    }

    /**
     * Vérifier si l'utilisateur peut effectuer un retrait
     */
    private function canWithdraw(Prestataire $prestataire, User $user): bool
    {
        // Doit être le prestataire lui-même
        if ($user->getId() !== $prestataire->getId()) {
            return false;
        }

        // Le compte Stripe Connect doit être actif
        if ($prestataire->getStripeAccountStatus() !== 'active') {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut voir une réservation
     */
    private function canViewBooking(Booking $booking, User $user): bool
    {
        // Le prestataire peut voir ses propres réservations
        return $booking->getPrestataire()->getId() === $user->getId();
    }

    /**
     * Vérifier si l'utilisateur peut modifier une réservation
     */
    private function canUpdateBooking(Booking $booking, User $user): bool
    {
        // Le prestataire ne peut modifier que ses propres réservations
        if ($booking->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Ne peut pas modifier une réservation terminée ou annulée
        $forbiddenStatuses = ['completed', 'cancelled'];
        if (in_array($booking->getStatus(), $forbiddenStatuses)) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut annuler une réservation
     */
    private function canCancelBooking(Booking $booking, User $user): bool
    {
        // Le prestataire peut annuler ses propres réservations
        if ($booking->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Ne peut pas annuler une réservation déjà terminée ou annulée
        $forbiddenStatuses = ['completed', 'cancelled'];
        if (in_array($booking->getStatus(), $forbiddenStatuses)) {
            return false;
        }

        // Vérifier le délai d'annulation (par exemple 24h avant)
        $scheduledDate = $booking->getScheduledDate();
        $now = new \DateTime();
        $minCancellationTime = clone $scheduledDate;
        $minCancellationTime->modify('-24 hours');

        if ($now > $minCancellationTime) {
            // Trop tard pour annuler sans pénalité
            // On peut autoriser mais avec des conditions spéciales
            return true; // À ajuster selon la logique métier
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut démarrer un service
     */
    private function canStartService(Booking $booking, User $user): bool
    {
        // Doit être le prestataire assigné
        if ($booking->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Le statut doit être "confirmed"
        if ($booking->getStatus() !== 'confirmed') {
            return false;
        }

        // Vérifier qu'on est proche de l'heure de début (par exemple dans les 2h)
        $scheduledDateTime = $booking->getScheduledDate();
        $now = new \DateTime();
        $startWindow = clone $scheduledDateTime;
        $startWindow->modify('-2 hours');
        $endWindow = clone $scheduledDateTime;
        $endWindow->modify('+30 minutes');

        if ($now < $startWindow || $now > $endWindow) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut terminer un service
     */
    private function canCompleteService(Booking $booking, User $user): bool
    {
        // Doit être le prestataire assigné
        if ($booking->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Le service doit avoir été démarré
        if ($booking->getStatus() !== 'in_progress') {
            return false;
        }

        // Le service doit avoir commencé
        if (!$booking->getActualStartTime()) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut créer un devis
     */
    private function canCreateQuote(User $user): bool
    {
        // Doit avoir le rôle prestataire
        if (!in_array('ROLE_PRESTATAIRE', $user->getRoles())) {
            return false;
        }

        // Le compte doit être actif et approuvé
        if ($user instanceof Prestataire) {
            return $user->isActive() && $user->isApproved();
        }

        return false;
    }

    /**
     * Vérifier si l'utilisateur peut voir un devis
     */
    private function canViewQuote(Quote $quote, User $user): bool
    {
        // Le prestataire peut voir ses propres devis
        return $quote->getPrestataire()->getId() === $user->getId();
    }

    /**
     * Vérifier si l'utilisateur peut modifier un devis
     */
    private function canEditQuote(Quote $quote, User $user): bool
    {
        // Le prestataire peut modifier ses propres devis
        if ($quote->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Ne peut modifier que les devis en attente
        if ($quote->getStatus() !== 'pending') {
            return false;
        }

        // Vérifier que le devis n'est pas expiré
        if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTime()) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut supprimer un devis
     */
    private function canDeleteQuote(Quote $quote, User $user): bool
    {
        // Le prestataire peut supprimer ses propres devis
        if ($quote->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Ne peut supprimer que les devis en attente ou rejetés
        $allowedStatuses = ['pending', 'rejected'];
        return in_array($quote->getStatus(), $allowedStatuses);
    }

    /**
     * Vérifier si l'utilisateur peut demander un remplacement
     */
    private function canRequestReplacement(Booking $booking, User $user): bool
    {
        // Doit être le prestataire assigné
        if ($booking->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Ne peut demander un remplacement que pour une réservation confirmée
        if ($booking->getStatus() !== 'confirmed') {
            return false;
        }

        // Vérifier qu'il reste suffisamment de temps (par exemple 48h avant)
        $scheduledDate = $booking->getScheduledDate();
        $now = new \DateTime();
        $minReplacementTime = clone $scheduledDate;
        $minReplacementTime->modify('-48 hours');

        if ($now > $minReplacementTime) {
            return false; // Trop tard pour demander un remplacement
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut accepter un remplacement
     */
    private function canAcceptReplacement(Booking $booking, User $user): bool
    {
        // Doit être un prestataire actif et approuvé
        if (!($user instanceof Prestataire) || !$user->isApproved() || !$user->isActive()) {
            return false;
        }

        // Ne peut pas être le prestataire original
        if ($booking->getPrestataire()->getId() === $user->getId()) {
            return false;
        }

        // Vérifier que le prestataire offre le service demandé
        $category = $booking->getServiceRequest()?->getCategory();
        if ($category && !$user->getServiceCategories()->contains($category)) {
            return false;
        }

        // Vérifier la disponibilité du prestataire
        // (à implémenter selon la logique métier)

        return true;
    }
}