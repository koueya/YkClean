<?php

namespace App\EventListener;

use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * JWTCreatedListener
 * 
 * Personnalise le payload du token JWT lors de sa création
 * Ajoute des informations supplémentaires sur l'utilisateur
 */
class JWTCreatedListener
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Événement déclenché lors de la création du JWT
     */
    public function onJWTCreated(JWTCreatedEvent $event): void
    {
        $user = $event->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        // Récupérer le payload actuel
        $payload = $event->getData();

        // Ajouter les informations de base de l'utilisateur
        $payload['id'] = $user->getId();
        $payload['email'] = $user->getEmail();
        $payload['firstName'] = $user->getFirstName();
        $payload['lastName'] = $user->getLastName();
        $payload['fullName'] = $user->getFullName();
        $payload['roles'] = $user->getRoles();
        $payload['isVerified'] = $user->isVerified();
        $payload['isActive'] = $user->isActive();

        // Ajouter des informations spécifiques selon le type d'utilisateur
        if ($user instanceof Client) {
            $payload['userType'] = 'client';
            $payload['clientInfo'] = [
                'id' => $user->getId(),
                'phone' => $user->getPhone(),
                'address' => $user->getAddress(),
                'city' => $user->getCity(),
                'postalCode' => $user->getPostalCode(),
                'hasDefaultPaymentMethod' => $user->getDefaultPaymentMethodId() !== null,
                'stripeCustomerId' => $user->getStripeCustomerId(),
            ];
        } elseif ($user instanceof Prestataire) {
            $payload['userType'] = 'prestataire';
            $payload['prestataireInfo'] = [
                'id' => $user->getId(),
                'companyName' => $user->getCompanyName(),
                'siret' => $user->getSiret(),
                'phone' => $user->getPhone(),
                'isApproved' => $user->isApproved(),
                'isAvailable' => $user->isAvailable(),
                'averageRating' => $user->getAverageRating(),
                'totalReviews' => $user->getTotalReviews(),
                'stripeConnectedAccountId' => $user->getStripeConnectedAccountId(),
                'stripeAccountStatus' => $user->getStripeAccountStatus(),
            ];
        } else {
            $payload['userType'] = 'user';
        }

        // Ajouter des informations sur la requête (optionnel)
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $payload['ip'] = $request->getClientIp();
            $payload['userAgent'] = $request->headers->get('User-Agent');
        }

        // Ajouter un timestamp de création
        $payload['createdAt'] = time();

        // Personnaliser la durée de vie du token selon le type d'utilisateur
        // (Optionnel - nécessite configuration dans lexik_jwt_authentication.yaml)
        if ($user instanceof Prestataire) {
            // Les prestataires ont des tokens de plus longue durée
            $payload['exp'] = time() + (7 * 24 * 3600); // 7 jours
        } elseif ($user instanceof Client) {
            // Les clients ont des tokens standard
            $payload['exp'] = time() + (24 * 3600); // 24 heures
        }

        // Mettre à jour le payload
        $event->setData($payload);
    }
}