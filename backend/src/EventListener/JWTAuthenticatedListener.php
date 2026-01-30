<?php

namespace App\EventListener;

use App\Entity\User\User;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTAuthenticatedEvent;
use Psr\Log\LoggerInterface;

/**
 * JWTAuthenticatedListener
 * 
 * Événement déclenché après qu'un JWT a été validé avec succès
 * Permet de mettre à jour les informations de l'utilisateur
 */
class JWTAuthenticatedListener
{
    private EntityManagerInterface $entityManager;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    /**
     * Événement déclenché après authentification JWT réussie
     */
    public function onJWTAuthenticated(JWTAuthenticatedEvent $event): void
    {
        $token = $event->getToken();
        $payload = $event->getPayload();
        
        // Récupérer l'utilisateur
        $user = $token->getUser();
        
        if (!$user instanceof User) {
            return;
        }

        // Mettre à jour la dernière connexion
        $user->setLastLoginAt(new \DateTime());
        
        // Mettre à jour l'IP de dernière connexion (si disponible dans le payload)
        if (isset($payload['ip'])) {
            $user->setLastLoginIp($payload['ip']);
        }

        // Incrémenter le compteur de connexions
        $user->incrementLoginCount();

        // Persister les modifications
        try {
            $this->entityManager->flush();
            
            $this->logger->info('User authenticated via JWT', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'user_type' => $payload['userType'] ?? 'unknown',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update user login info', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }
}