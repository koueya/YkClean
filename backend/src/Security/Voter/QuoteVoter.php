<?php

namespace App\Security\Voter;

use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Quote\Quote;
use App\Entity\Service\ServiceRequest;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class QuoteVoter extends Voter
{
    // Permissions pour les devis
    public const VIEW = 'QUOTE_VIEW';
    public const CREATE = 'QUOTE_CREATE';
    public const EDIT = 'QUOTE_EDIT';
    public const DELETE = 'QUOTE_DELETE';
    public const ACCEPT = 'QUOTE_ACCEPT';
    public const REJECT = 'QUOTE_REJECT';
    public const WITHDRAW = 'QUOTE_WITHDRAW';
    public const VIEW_LIST = 'QUOTE_VIEW_LIST';
    public const COMPARE = 'QUOTE_COMPARE';
    public const NEGOTIATE = 'QUOTE_NEGOTIATE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        $attributes = [
            self::VIEW,
            self::CREATE,
            self::EDIT,
            self::DELETE,
            self::ACCEPT,
            self::REJECT,
            self::WITHDRAW,
            self::VIEW_LIST,
            self::COMPARE,
            self::NEGOTIATE,
        ];

        if (!in_array($attribute, $attributes)) {
            return false;
        }

        // CREATE peut avoir un ServiceRequest en subject
        if ($attribute === self::CREATE) {
            return $subject instanceof ServiceRequest || $subject === null;
        }

        // VIEW_LIST et COMPARE peuvent avoir un ServiceRequest
        if (in_array($attribute, [self::VIEW_LIST, self::COMPARE])) {
            return $subject instanceof ServiceRequest;
        }

        // Les autres attributs nécessitent un Quote
        return $subject instanceof Quote;
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

        return match ($attribute) {
            self::VIEW => $this->canView($subject, $user),
            self::CREATE => $this->canCreate($subject, $user),
            self::EDIT => $this->canEdit($subject, $user),
            self::DELETE => $this->canDelete($subject, $user),
            self::ACCEPT => $this->canAccept($subject, $user),
            self::REJECT => $this->canReject($subject, $user),
            self::WITHDRAW => $this->canWithdraw($subject, $user),
            self::VIEW_LIST => $this->canViewList($subject, $user),
            self::COMPARE => $this->canCompare($subject, $user),
            self::NEGOTIATE => $this->canNegotiate($subject, $user),
            default => false,
        };
    }

    /**
     * Vérifier si l'utilisateur peut voir un devis
     */
    private function canView(Quote $quote, User $user): bool
    {
        // Le prestataire qui a créé le devis peut le voir
        if ($quote->getPrestataire()->getId() === $user->getId()) {
            return true;
        }

        // Le client qui a fait la demande peut voir le devis
        $serviceRequest = $quote->getServiceRequest();
        if ($serviceRequest && $serviceRequest->getClient()->getId() === $user->getId()) {
            return true;
        }

        return false;
    }

    /**
     * Vérifier si l'utilisateur peut créer un devis
     */
    private function canCreate(?ServiceRequest $serviceRequest, User $user): bool
    {
        // Doit être un prestataire
        if (!($user instanceof Prestataire)) {
            return false;
        }

        // Le prestataire doit être actif et approuvé
        if (!$user->isActive() || !$user->isApproved()) {
            return false;
        }

        // Si une ServiceRequest est fournie, vérifier les conditions
        if ($serviceRequest) {
            // La demande doit être ouverte
            if ($serviceRequest->getStatus() !== 'open') {
                return false;
            }

            // Vérifier que le prestataire n'a pas déjà envoyé un devis
            foreach ($serviceRequest->getQuotes() as $existingQuote) {
                if ($existingQuote->getPrestataire()->getId() === $user->getId()) {
                    return false; // Déjà envoyé un devis
                }
            }

            // Vérifier que le prestataire offre ce service
            $category = $serviceRequest->getCategory();
            if ($category && !$user->getServiceCategories()->contains($category)) {
                return false;
            }

            // Vérifier que la demande n'est pas expirée
            if ($serviceRequest->getExpiresAt() && $serviceRequest->getExpiresAt() < new \DateTime()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut modifier un devis
     */
    private function canEdit(Quote $quote, User $user): bool
    {
        // Seul le prestataire qui a créé le devis peut le modifier
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

        // Vérifier que la demande de service est encore ouverte
        $serviceRequest = $quote->getServiceRequest();
        if ($serviceRequest && $serviceRequest->getStatus() !== 'open') {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut supprimer un devis
     */
    private function canDelete(Quote $quote, User $user): bool
    {
        // Seul le prestataire qui a créé le devis peut le supprimer
        if ($quote->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Ne peut supprimer que les devis en attente ou rejetés
        $allowedStatuses = ['pending', 'rejected', 'expired'];
        if (!in_array($quote->getStatus(), $allowedStatuses)) {
            return false;
        }

        // Ne peut pas supprimer un devis si un booking associé existe
        if ($quote->getBooking()) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut accepter un devis
     */
    private function canAccept(Quote $quote, User $user): bool
    {
        // Seul le client qui a fait la demande peut accepter
        $serviceRequest = $quote->getServiceRequest();
        if (!$serviceRequest || $serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // Le devis doit être en attente
        if ($quote->getStatus() !== 'pending') {
            return false;
        }

        // Le devis ne doit pas être expiré
        if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTime()) {
            return false;
        }

        // La demande de service doit être ouverte
        if ($serviceRequest->getStatus() !== 'open') {
            return false;
        }

        // Vérifier que le client n'a pas déjà accepté un autre devis
        foreach ($serviceRequest->getQuotes() as $otherQuote) {
            if ($otherQuote->getStatus() === 'accepted') {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut rejeter un devis
     */
    private function canReject(Quote $quote, User $user): bool
    {
        // Seul le client qui a fait la demande peut rejeter
        $serviceRequest = $quote->getServiceRequest();
        if (!$serviceRequest || $serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // Le devis doit être en attente
        if ($quote->getStatus() !== 'pending') {
            return false;
        }

        // La demande de service doit être ouverte
        if ($serviceRequest->getStatus() !== 'open') {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut retirer un devis
     */
    private function canWithdraw(Quote $quote, User $user): bool
    {
        // Seul le prestataire qui a créé le devis peut le retirer
        if ($quote->getPrestataire()->getId() !== $user->getId()) {
            return false;
        }

        // Le devis doit être en attente
        if ($quote->getStatus() !== 'pending') {
            return false;
        }

        // Ne peut pas retirer si le client a déjà accepté
        if ($quote->getStatus() === 'accepted') {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut voir la liste des devis d'une demande
     */
    private function canViewList(ServiceRequest $serviceRequest, User $user): bool
    {
        // Le client qui a créé la demande peut voir tous les devis
        if ($serviceRequest->getClient()->getId() === $user->getId()) {
            return true;
        }

        // Un prestataire peut voir la liste s'il a envoyé un devis
        if ($user instanceof Prestataire) {
            foreach ($serviceRequest->getQuotes() as $quote) {
                if ($quote->getPrestataire()->getId() === $user->getId()) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Vérifier si l'utilisateur peut comparer les devis
     */
    private function canCompare(ServiceRequest $serviceRequest, User $user): bool
    {
        // Seul le client peut comparer les devis de sa demande
        if (!($user instanceof Client)) {
            return false;
        }

        if ($serviceRequest->getClient()->getId() !== $user->getId()) {
            return false;
        }

        // Il doit y avoir au moins 2 devis
        if ($serviceRequest->getQuotes()->count() < 2) {
            return false;
        }

        return true;
    }

    /**
     * Vérifier si l'utilisateur peut négocier un devis
     */
    private function canNegotiate(Quote $quote, User $user): bool
    {
        $serviceRequest = $quote->getServiceRequest();

        // Le client peut négocier les devis de sa demande
        if ($user instanceof Client) {
            if (!$serviceRequest || $serviceRequest->getClient()->getId() !== $user->getId()) {
                return false;
            }

            // Le devis doit être en attente
            if ($quote->getStatus() !== 'pending') {
                return false;
            }

            // Le devis ne doit pas être expiré
            if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTime()) {
                return false;
            }

            return true;
        }

        // Le prestataire peut négocier ses propres devis
        if ($user instanceof Prestataire) {
            if ($quote->getPrestataire()->getId() !== $user->getId()) {
                return false;
            }

            // Le devis doit être en attente
            if ($quote->getStatus() !== 'pending') {
                return false;
            }

            return true;
        }

        return false;
    }

    /**
     * Vérifier si le prestataire a déjà envoyé un devis pour cette demande
     */
    private function hasAlreadyQuoted(ServiceRequest $serviceRequest, Prestataire $prestataire): bool
    {
        foreach ($serviceRequest->getQuotes() as $quote) {
            if ($quote->getPrestataire()->getId() === $prestataire->getId()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Vérifier si le devis est encore dans les délais
     */
    private function isWithinTimeLimit(Quote $quote): bool
    {
        // Vérifier l'expiration du devis
        if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTime()) {
            return false;
        }

        // Vérifier l'expiration de la demande de service
        $serviceRequest = $quote->getServiceRequest();
        if ($serviceRequest && $serviceRequest->getExpiresAt()) {
            if ($serviceRequest->getExpiresAt() < new \DateTime()) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifier si le montant du devis est raisonnable
     */
    private function isReasonableAmount(Quote $quote): bool
    {
        $serviceRequest = $quote->getServiceRequest();
        
        // Si un budget est spécifié, le devis ne doit pas être trop élevé
        if ($serviceRequest && $serviceRequest->getBudget()) {
            $budget = $serviceRequest->getBudget();
            $quoteAmount = $quote->getAmount();

            // Autoriser jusqu'à 50% au-dessus du budget
            $maxAmount = $budget * 1.5;

            if ($quoteAmount > $maxAmount) {
                return false;
            }
        }

        return true;
    }

    /**
     * Vérifier les conditions pour l'auto-expiration d'un devis
     */
    public function shouldAutoExpire(Quote $quote): bool
    {
        // Un devis expire automatiquement si:
        
        // 1. La date de validité est dépassée
        if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTime()) {
            return true;
        }

        // 2. La demande de service est fermée ou expirée
        $serviceRequest = $quote->getServiceRequest();
        if ($serviceRequest) {
            if (in_array($serviceRequest->getStatus(), ['completed', 'cancelled'])) {
                return true;
            }

            if ($serviceRequest->getExpiresAt() && $serviceRequest->getExpiresAt() < new \DateTime()) {
                return true;
            }
        }

        // 3. Un autre devis a été accepté
        if ($serviceRequest) {
            foreach ($serviceRequest->getQuotes() as $otherQuote) {
                if ($otherQuote->getId() !== $quote->getId() && $otherQuote->getStatus() === 'accepted') {
                    return true;
                }
            }
        }

        return false;
    }
}