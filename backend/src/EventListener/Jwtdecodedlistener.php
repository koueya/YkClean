<?php

namespace App\EventListener;

use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTDecodedEvent;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * JWTDecodedListener
 * 
 * Événement déclenché après le décodage du JWT mais AVANT validation
 * Permet de valider des informations supplémentaires (IP, User-Agent, etc.)
 */
class JWTDecodedListener
{
    private RequestStack $requestStack;

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    /**
     * Événement déclenché après décodage du JWT
     */
    public function onJWTDecoded(JWTDecodedEvent $event): void
    {
        $request = $this->requestStack->getCurrentRequest();
        
        if (!$request) {
            return;
        }

        $payload = $event->getPayload();

        // Vérification de l'IP (optionnel - décommenter si nécessaire)
        // Utile pour empêcher l'utilisation du token depuis une autre IP
        /*
        if (isset($payload['ip']) && $payload['ip'] !== $request->getClientIp()) {
            $event->markAsInvalid();
            return;
        }
        */

        // Vérification du User-Agent (optionnel - décommenter si nécessaire)
        // Utile pour empêcher l'utilisation du token depuis un autre appareil
        /*
        if (isset($payload['userAgent']) && 
            $payload['userAgent'] !== $request->headers->get('User-Agent')) {
            $event->markAsInvalid();
            return;
        }
        */

        // Vérification personnalisée : empêcher les tokens trop anciens
        if (isset($payload['createdAt'])) {
            $tokenAge = time() - $payload['createdAt'];
            $maxAge = 30 * 24 * 3600; // 30 jours
            
            if ($tokenAge > $maxAge) {
                $event->markAsInvalid();
                return;
            }
        }

        // Vérifier que l'utilisateur est toujours actif
        // Cette vérification sera faite plus tard dans JWTAuthenticatedListener
        // mais on peut déjà vérifier le statut dans le payload
        if (isset($payload['isActive']) && !$payload['isActive']) {
            $event->markAsInvalid();
            return;
        }

        // Vérifier que l'utilisateur est vérifié (optionnel)
        // Décommenter si vous voulez forcer la vérification de l'email
        /*
        if (isset($payload['isVerified']) && !$payload['isVerified']) {
            $event->markAsInvalid();
            return;
        }
        */
    }
}