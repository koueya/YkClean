<?php

namespace App\Security\Voter;

use App\Entity\Service\ServiceRequest;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\User\Admin;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class ServiceRequestVoter extends Voter
{
    // Constantes pour les permissions
    public const VIEW = 'SERVICE_REQUEST_VIEW';
    public const CREATE = 'SERVICE_REQUEST_CREATE';
    public const EDIT = 'SERVICE_REQUEST_EDIT';
    public const DELETE = 'SERVICE_REQUEST_DELETE';
    public const CANCEL = 'SERVICE_REQUEST_CANCEL';
    public const REOPEN = 'SERVICE_REQUEST_REOPEN';
    public const CLOSE = 'SERVICE_REQUEST_CLOSE';
    public const VIEW_QUOTES = 'SERVICE_REQUEST_VIEW_QUOTES';
    public const ACCEPT_QUOTE = 'SERVICE_REQUEST_ACCEPT_QUOTE';
    public const REJECT_QUOTE = 'SERVICE_REQUEST_REJECT_QUOTE';
    public const EXTEND_VALIDITY = 'SERVICE_REQUEST_EXTEND_VALIDITY';
    public const CHANGE_BUDGET = 'SERVICE_REQUEST_CHANGE_BUDGET';
    public const UPDATE_DETAILS = 'SERVICE_REQUEST_UPDATE_DETAILS';

    protected function supports(string $attribute, mixed $subject): bool
    {
        $supportedAttributes = [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::CANCEL,
            self::REOPEN,
            self::CLOSE,
            self::VIEW_QUOTES,
            self::ACCEPT_QUOTE,
            self::REJECT_QUOTE,
            self::EXTEND_VALIDITY,
            self::CHANGE_BUDGET,
            self::UPDATE_DETAILS,
        ];

        if (!in_array($attribute, $supportedAttributes)) {
            return false;
        }

        // Pour CREATE, le sujet peut être null
        if ($attribute === self::CREATE) {
            return true;
        }

        return $subject instanceof ServiceRequest;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        // L'utilisateur doit être authentifié
        if (!$user instanceof UserInterface) {
            return false;
        }

        // Les admins ont tous les droits
        if ($user instanceof Admin) {
            return true;
        }

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::CREATE => $this->canCreate($user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::CANCEL => $this->canCancel($subject, $user),
            self::REOPEN => $this->canReopen($subject, $user),
            self::CLOSE => $this->canClose($subject, $user),
            self::VIEW_QUOTES => $this->canViewQuotes($subject, $user),
            self::ACCEPT_QUOTE => $this->canAcceptQuote($subject, $user),
            self::REJECT_QUOTE => $this->canRejectQuote($subject, $user),
            self::EXTEND_VALIDITY => $this->canExtendValidity($subject, $user),
            self::CHANGE_BUDGET => $this->canChangeBudget($subject, $user),
            self::UPDATE_DETAILS => $this->canUpdateDetails($subject, $user),
            default => false,
        };
    }

    private function canView(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Un client peut voir ses propres demandes
        if ($user instanceof Client) {
            return $serviceRequest->getClient()->getId() === $user->getId();
        }

        // Un prestataire peut voir les demandes selon certains critères
        if ($user instanceof Prestataire) {
            return $this->prestataireCanViewServiceRequest($serviceRequest, $user);
        }

        return false;
    }

    private function canCreate(UserInterface $user): bool
    {
        // Seuls les clients peuvent créer des demandes de service
        if (!$user instanceof Client) {
            return false;
        }

        // Vérifier que le client a vérifié son email
        if (!$user->isVerified()) {
            return false;
        }

        // Vérifier que le compte est actif
        if (!$user->isActive()) {
            return false;
        }

        return true;
    }

    private function canEdit(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Seul le client propriétaire peut modifier sa demande
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // On ne peut modifier que les demandes ouvertes ou en cours de cotation
        $editableStatuses = ['open', 'quoting'];
        if (!in_array($serviceRequest->getStatus(), $editableStatuses)) {
            return false;
        }

        // On ne peut pas modifier si des devis ont déjà été acceptés
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getStatus() === 'accepted') {
                return false;
            }
        }

        return true;
    }

    private function canDelete(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Seul le client propriétaire peut supprimer sa demande
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // On ne peut supprimer que les demandes qui n'ont pas de devis accepté
        $deletableStatuses = ['open', 'quoting'];
        if (!in_array($serviceRequest->getStatus(), $deletableStatuses)) {
            return false;
        }

        // Vérifier qu'aucun devis n'a été accepté
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getStatus() === 'accepted') {
                return false;
            }
        }

        return true;
    }

    private function canCancel(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Le client peut annuler sa demande
        if ($user instanceof Client) {
            if ($serviceRequest->getClient()->getId() !== $user->getId()) {
                return false;
            }

            // On peut annuler une demande tant qu'elle n'est pas complétée
            $cancellableStatuses = ['open', 'quoting', 'in_progress'];
            if (!in_array($serviceRequest->getStatus(), $cancellableStatuses)) {
                return false;
            }

            // Si un booking existe, vérifier qu'il peut être annulé
            if ($serviceRequest->getBooking()) {
                $booking = $serviceRequest->getBooking();
                $bookingCancellableStatuses = ['pending', 'confirmed', 'proposed'];
                
                if (!in_array($booking->getStatus(), $bookingCancellableStatuses)) {
                    return false;
                }

                // Vérifier le délai d'annulation (24h minimum)
                $scheduledDateTime = new \DateTime(
                    $booking->getScheduledDate()->format('Y-m-d') . ' ' . 
                    $booking->getScheduledTime()->format('H:i:s')
                );
                $now = new \DateTime();
                $hoursUntilBooking = ($scheduledDateTime->getTimestamp() - $now->getTimestamp()) / 3600;

                if ($hoursUntilBooking < 24) {
                    return false;
                }
            }

            return true;
        }

        return false;
    }

    private function canReopen(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Le client peut rouvrir une demande annulée ou expirée
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // On peut rouvrir une demande annulée ou expirée
        $reopenableStatuses = ['cancelled', 'expired'];
        if (!in_array($serviceRequest->getStatus(), $reopenableStatuses)) {
            return false;
        }

        // On ne peut pas rouvrir si un booking a été complété
        if ($serviceRequest->getBooking() && 
            $serviceRequest->getBooking()->getStatus() === 'completed') {
            return false;
        }

        return true;
    }

    private function canClose(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Le client peut clôturer sa demande une fois le service complété
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // On peut clôturer si le booking est complété
        if (!$serviceRequest->getBooking() || 
            $serviceRequest->getBooking()->getStatus() !== 'completed') {
            return false;
        }

        $closableStatuses = ['in_progress', 'completed'];
        return in_array($serviceRequest->getStatus(), $closableStatuses);
    }

    private function canViewQuotes(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Le client propriétaire peut voir tous les devis
        if ($user instanceof Client) {
            return $serviceRequest->getClient()->getId() === $user->getId();
        }

        // Un prestataire peut voir uniquement son propre devis
        if ($user instanceof Prestataire) {
            foreach ($serviceRequest->getQuotes() as $quote) {
                if ($quote->getPrestataire()->getId() === $user->getId()) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    private function canAcceptQuote(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Seul le client propriétaire peut accepter un devis
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // La demande doit être ouverte ou en cours de cotation
        $acceptableStatuses = ['open', 'quoting'];
        if (!in_array($serviceRequest->getStatus(), $acceptableStatuses)) {
            return false;
        }

        // Il doit y avoir au moins un devis en attente
        $hasPendingQuotes = false;
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getStatus() === 'pending' && 
                (!$quote->getValidUntil() || $quote->getValidUntil() > new \DateTime())) {
                $hasPendingQuotes = true;
                break;
            }
        }

        if (!$hasPendingQuotes) {
            return false;
        }

        // Aucun devis ne doit avoir déjà été accepté
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getStatus() === 'accepted') {
                return false;
            }
        }

        return true;
    }

    private function canRejectQuote(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Seul le client propriétaire peut rejeter un devis
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // La demande doit être ouverte ou en cours de cotation
        $rejectableStatuses = ['open', 'quoting'];
        return in_array($serviceRequest->getStatus(), $rejectableStatuses);
    }

    private function canExtendValidity(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Seul le client propriétaire peut prolonger la validité
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // On peut prolonger une demande ouverte, en cours de cotation ou expirée
        $extendableStatuses = ['open', 'quoting', 'expired'];
        if (!in_array($serviceRequest->getStatus(), $extendableStatuses)) {
            return false;
        }

        // On ne peut pas prolonger si un devis a été accepté
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getStatus() === 'accepted') {
                return false;
            }
        }

        return true;
    }

    private function canChangeBudget(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Seul le client propriétaire peut modifier le budget
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // On peut modifier le budget tant que la demande est ouverte ou en cours de cotation
        $modifiableStatuses = ['open', 'quoting'];
        if (!in_array($serviceRequest->getStatus(), $modifiableStatuses)) {
            return false;
        }

        // Si des devis ont été soumis, on peut augmenter mais pas diminuer le budget
        // Cette logique sera gérée dans le service

        return true;
    }

    private function canUpdateDetails(ServiceRequest $serviceRequest, UserInterface $user): bool
    {
        // Seul le client propriétaire peut modifier les détails
        if (!$user instanceof Client) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // On peut modifier les détails (description, dates alternatives, etc.)
        // tant que la demande est ouverte ou en cours de cotation
        $modifiableStatuses = ['open', 'quoting'];
        if (!in_array($serviceRequest->getStatus(), $modifiableStatuses)) {
            return false;
        }

        // On ne peut pas modifier les détails majeurs si des devis ont été acceptés
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getStatus() === 'accepted') {
                return false;
            }
        }

        return true;
    }

    private function prestataireCanViewServiceRequest(
        ServiceRequest $serviceRequest, 
        Prestataire $prestataire
    ): bool {
        // Le prestataire peut voir une demande si :
        // 1. Il a soumis un devis pour cette demande
        // 2. La demande est ouverte, dans sa zone, et correspond à ses compétences

        // Vérifier si le prestataire a déjà soumis un devis
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getPrestataire()->getId() === $prestataire->getId()) {
                return true;
            }
        }

        // Vérifier si le prestataire peut voir les nouvelles demandes
        if (!$prestataire->isApproved() || !$prestataire->isActive()) {
            return false;
        }

        // La demande doit être ouverte ou en cours de cotation
        $viewableStatuses = ['open', 'quoting'];
        if (!in_array($serviceRequest->getStatus(), $viewableStatuses)) {
            return false;
        }

        // Vérifier que la demande n'est pas expirée
        if ($serviceRequest->getExpiresAt() && 
            $serviceRequest->getExpiresAt() < new \DateTime()) {
            return false;
        }

        // Vérifier que la catégorie correspond aux compétences du prestataire
        $serviceCategory = $serviceRequest->getCategory();
        if (!$prestataire->getServiceCategories()->contains($serviceCategory)) {
            return false;
        }

        // Vérifier la zone d'intervention
        // Cette partie nécessiterait un service de géolocalisation pour calculer la distance
        // Pour l'instant, on considère que c'est valide si les autres conditions sont remplies
        
        // Vérifier que le prestataire n'est pas déjà surbooké à cette date
        $preferredDate = $serviceRequest->getPreferredDate();
        if ($preferredDate) {
            // Cette vérification serait faite dans un service dédié
            // qui vérifie les disponibilités et les bookings existants
        }

        return true;
    }
}