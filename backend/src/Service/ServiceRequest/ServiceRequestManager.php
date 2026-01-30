<?php

namespace App\Service\ServiceRequest;

use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceRequest;
use App\Entity\Quote\Quote;
use App\Repository\Service\ServiceRequestRepository;
use App\Repository\Quote\QuoteRepository;
use App\Repository\User\PrestataireRepository;
use App\Service\Notification\NotificationService;
use App\Service\Matching\MatchingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;

class ServiceRequestManager
{
    private const STATUS_OPEN = 'open';
    private const STATUS_QUOTED = 'quoted';
    private const STATUS_IN_PROGRESS = 'in_progress';
    private const STATUS_COMPLETED = 'completed';
    private const STATUS_CANCELLED = 'cancelled';
    private const STATUS_EXPIRED = 'expired';

    private const VALID_STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_QUOTED,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_EXPIRED
    ];

    private const VALID_CATEGORIES = [
        'nettoyage',
        'repassage',
        'menage_complet',
        'vitres',
        'jardinage',
        'bricolage',
        'garde_enfants',
        'aide_personne_agee',
        'cours_particuliers',
        'autre'
    ];

    private const VALID_FREQUENCIES = [
        'ponctuel',
        'hebdomadaire',
        'bi_hebdomadaire',
        'mensuel'
    ];

    // Durée de validité d'une demande en jours
    private const REQUEST_VALIDITY_DAYS = 30;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRequestRepository $serviceRequestRepository,
        private QuoteRepository $quoteRepository,
        private PrestataireRepository $prestataireRepository,
        private NotificationService $notificationService,
        private MatchingService $matchingService,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée une nouvelle demande de service
     */
    public function createServiceRequest(Client $client, array $data): ServiceRequest
    {
        $this->logger->info('Creating service request', [
            'clientId' => $client->getId(),
            'category' => $data['category'] ?? null
        ]);

        // Validation des données
        $this->validateServiceRequestData($data);

        $serviceRequest = new ServiceRequest();
        $serviceRequest->setClient($client);
        $serviceRequest->setCategory($data['category']);
        $serviceRequest->setDescription($data['description']);
        $serviceRequest->setAddress($data['address']);
        $serviceRequest->setPreferredDate(new \DateTime($data['preferredDate']));
        
        // Dates alternatives optionnelles
        if (isset($data['alternativeDates']) && is_array($data['alternativeDates'])) {
            $alternativeDates = array_map(
                fn($date) => new \DateTime($date),
                $data['alternativeDates']
            );
            $serviceRequest->setAlternativeDates($alternativeDates);
        }

        // Durée estimée
        if (isset($data['duration'])) {
            $serviceRequest->setDuration($data['duration']);
        }

        // Fréquence
        $frequency = $data['frequency'] ?? 'ponctuel';
        $this->validateFrequency($frequency);
        $serviceRequest->setFrequency($frequency);

        // Budget
        if (isset($data['budget'])) {
            $serviceRequest->setBudget($data['budget']);
        }

        $serviceRequest->setStatus(self::STATUS_OPEN);
        $serviceRequest->setCreatedAt(new \DateTimeImmutable());
        
        // Date d'expiration
        $expiresAt = new \DateTimeImmutable();
        $expiresAt = $expiresAt->modify('+' . self::REQUEST_VALIDITY_DAYS . ' days');
        $serviceRequest->setExpiresAt($expiresAt);

        $this->entityManager->persist($serviceRequest);
        $this->entityManager->flush();

        $this->logger->info('Service request created', [
            'serviceRequestId' => $serviceRequest->getId()
        ]);

        // Notifier les prestataires correspondants
        $this->notifyMatchingPrestataires($serviceRequest);

        return $serviceRequest;
    }

    /**
     * Met à jour une demande de service
     */
    public function updateServiceRequest(int $requestId, Client $client, array $data): ServiceRequest
    {
        $serviceRequest = $this->getServiceRequestById($requestId);

        // Vérifier que c'est bien le client qui a créé la demande
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à cette demande.');
        }

        // Vérifier que la demande peut être modifiée
        if (!$this->canBeUpdated($serviceRequest)) {
            throw new BadRequestHttpException('Cette demande ne peut plus être modifiée.');
        }

        $this->logger->info('Updating service request', [
            'serviceRequestId' => $requestId
        ]);

        // Mise à jour des champs autorisés
        if (isset($data['description'])) {
            $serviceRequest->setDescription($data['description']);
        }

        if (isset($data['address'])) {
            $serviceRequest->setAddress($data['address']);
        }

        if (isset($data['preferredDate'])) {
            $serviceRequest->setPreferredDate(new \DateTime($data['preferredDate']));
        }

        if (isset($data['alternativeDates'])) {
            $alternativeDates = array_map(
                fn($date) => new \DateTime($date),
                $data['alternativeDates']
            );
            $serviceRequest->setAlternativeDates($alternativeDates);
        }

        if (isset($data['duration'])) {
            $serviceRequest->setDuration($data['duration']);
        }

        if (isset($data['budget'])) {
            $serviceRequest->setBudget($data['budget']);
        }

        $this->entityManager->flush();

        $this->logger->info('Service request updated', [
            'serviceRequestId' => $requestId
        ]);

        return $serviceRequest;
    }

    /**
     * Annule une demande de service
     */
    public function cancelServiceRequest(int $requestId, Client $client, string $reason = null): ServiceRequest
    {
        $serviceRequest = $this->getServiceRequestById($requestId);

        // Vérifier que c'est bien le client qui a créé la demande
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à cette demande.');
        }

        // Vérifier que la demande peut être annulée
        if (!$this->canBeCancelled($serviceRequest)) {
            throw new BadRequestHttpException('Cette demande ne peut plus être annulée.');
        }

        $this->logger->info('Cancelling service request', [
            'serviceRequestId' => $requestId,
            'reason' => $reason
        ]);

        $serviceRequest->setStatus(self::STATUS_CANCELLED);
        $serviceRequest->setCancellationReason($reason);
        $serviceRequest->setCancelledAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notifier les prestataires qui ont soumis des devis
        $this->notifyPrestatairesOfCancellation($serviceRequest);

        return $serviceRequest;
    }

    /**
     * Supprime une demande de service
     */
    public function deleteServiceRequest(int $requestId, Client $client): void
    {
        $serviceRequest = $this->getServiceRequestById($requestId);

        // Vérifier que c'est bien le client qui a créé la demande
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à cette demande.');
        }

        // Vérifier qu'il n'y a pas de devis acceptés
        $acceptedQuotes = $this->quoteRepository->findBy([
            'serviceRequest' => $serviceRequest,
            'status' => 'accepted'
        ]);

        if (count($acceptedQuotes) > 0) {
            throw new BadRequestHttpException(
                'Impossible de supprimer une demande avec un devis accepté.'
            );
        }

        $this->logger->warning('Deleting service request', [
            'serviceRequestId' => $requestId
        ]);

        $this->entityManager->remove($serviceRequest);
        $this->entityManager->flush();
    }

    /**
     * Récupère une demande de service par son ID
     */
    public function getServiceRequestById(int $requestId): ServiceRequest
    {
        $serviceRequest = $this->serviceRequestRepository->find($requestId);

        if (!$serviceRequest) {
            throw new NotFoundHttpException('Demande de service non trouvée.');
        }

        return $serviceRequest;
    }

    /**
     * Récupère toutes les demandes d'un client
     */
    public function getClientRequests(Client $client, array $filters = []): array
    {
        $queryBuilder = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->orderBy('sr.createdAt', 'DESC');

        // Filtres
        if (isset($filters['status'])) {
            $queryBuilder->andWhere('sr.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['category'])) {
            $queryBuilder->andWhere('sr.category = :category')
                ->setParameter('category', $filters['category']);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère les demandes actives d'un client
     */
    public function getActiveRequests(Client $client): array
    {
        return $this->serviceRequestRepository->findBy(
            ['client' => $client, 'status' => [self::STATUS_OPEN, self::STATUS_QUOTED]],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Récupère les demandes disponibles pour un prestataire
     */
    public function getAvailableRequestsForPrestataire(Prestataire $prestataire): array
    {
        // Utiliser le service de matching pour trouver les demandes pertinentes
        return $this->matchingService->findMatchingRequests($prestataire);
    }

    /**
     * Récupère les devis pour une demande
     */
    public function getQuotesForRequest(int $requestId): array
    {
        $serviceRequest = $this->getServiceRequestById($requestId);

        return $this->quoteRepository->findBy(
            ['serviceRequest' => $serviceRequest],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Compte les devis pour une demande
     */
    public function countQuotesForRequest(int $requestId): int
    {
        $serviceRequest = $this->getServiceRequestById($requestId);

        return $this->quoteRepository->count([
            'serviceRequest' => $serviceRequest
        ]);
    }

    /**
     * Récupère les devis en attente pour une demande
     */
    public function getPendingQuotesForRequest(int $requestId): array
    {
        $serviceRequest = $this->getServiceRequestById($requestId);

        return $this->quoteRepository->findBy([
            'serviceRequest' => $serviceRequest,
            'status' => 'pending'
        ]);
    }

    /**
     * Change le statut d'une demande
     */
    public function changeStatus(int $requestId, string $newStatus): ServiceRequest
    {
        $serviceRequest = $this->getServiceRequestById($requestId);

        if (!in_array($newStatus, self::VALID_STATUSES, true)) {
            throw new BadRequestHttpException('Statut invalide.');
        }

        $this->logger->info('Changing service request status', [
            'serviceRequestId' => $requestId,
            'oldStatus' => $serviceRequest->getStatus(),
            'newStatus' => $newStatus
        ]);

        $serviceRequest->setStatus($newStatus);
        $this->entityManager->flush();

        return $serviceRequest;
    }

    /**
     * Marque une demande comme expirée
     */
    public function expireRequest(ServiceRequest $serviceRequest): void
    {
        if ($serviceRequest->getStatus() === self::STATUS_OPEN) {
            $this->logger->info('Expiring service request', [
                'serviceRequestId' => $serviceRequest->getId()
            ]);

            $serviceRequest->setStatus(self::STATUS_EXPIRED);
            $this->entityManager->flush();

            // Notifier le client
            $this->notificationService->notifyRequestExpired($serviceRequest);
        }
    }

    /**
     * Traite les demandes expirées (commande à exécuter périodiquement)
     */
    public function processExpiredRequests(): int
    {
        $now = new \DateTimeImmutable();
        
        $expiredRequests = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->where('sr.status = :status')
            ->andWhere('sr.expiresAt < :now')
            ->setParameter('status', self::STATUS_OPEN)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($expiredRequests as $request) {
            $this->expireRequest($request);
            $count++;
        }

        $this->logger->info('Processed expired requests', ['count' => $count]);

        return $count;
    }

    /**
     * Recherche de demandes de service
     */
    public function searchRequests(array $criteria, int $page = 1, int $limit = 20): array
    {
        $queryBuilder = $this->serviceRequestRepository->createQueryBuilder('sr');
        $offset = ($page - 1) * $limit;

        // Filtres
        if (isset($criteria['status'])) {
            $queryBuilder->andWhere('sr.status = :status')
                ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['category'])) {
            $queryBuilder->andWhere('sr.category = :category')
                ->setParameter('category', $criteria['category']);
        }

        if (isset($criteria['frequency'])) {
            $queryBuilder->andWhere('sr.frequency = :frequency')
                ->setParameter('frequency', $criteria['frequency']);
        }

        if (isset($criteria['min_budget'])) {
            $queryBuilder->andWhere('sr.budget >= :minBudget')
                ->setParameter('minBudget', $criteria['min_budget']);
        }

        if (isset($criteria['max_budget'])) {
            $queryBuilder->andWhere('sr.budget <= :maxBudget')
                ->setParameter('maxBudget', $criteria['max_budget']);
        }

        if (isset($criteria['from_date'])) {
            $queryBuilder->andWhere('sr.preferredDate >= :fromDate')
                ->setParameter('fromDate', new \DateTime($criteria['from_date']));
        }

        if (isset($criteria['to_date'])) {
            $queryBuilder->andWhere('sr.preferredDate <= :toDate')
                ->setParameter('toDate', new \DateTime($criteria['to_date']));
        }

        $requests = $queryBuilder
            ->orderBy('sr.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'requests' => $requests,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }

    /**
     * Récupère les statistiques des demandes
     */
    public function getRequestStatistics(Client $client = null): array
    {
        $queryBuilder = $this->serviceRequestRepository->createQueryBuilder('sr');

        if ($client) {
            $queryBuilder->where('sr.client = :client')
                ->setParameter('client', $client);
        }

        $total = (clone $queryBuilder)
            ->select('COUNT(sr.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $byStatus = [];
        foreach (self::VALID_STATUSES as $status) {
            $count = (clone $queryBuilder)
                ->select('COUNT(sr.id)')
                ->andWhere('sr.status = :status')
                ->setParameter('status', $status)
                ->getQuery()
                ->getSingleScalarResult();
            
            $byStatus[$status] = $count;
        }

        $byCategory = [];
        foreach (self::VALID_CATEGORIES as $category) {
            $count = (clone $queryBuilder)
                ->select('COUNT(sr.id)')
                ->andWhere('sr.category = :category')
                ->setParameter('category', $category)
                ->getQuery()
                ->getSingleScalarResult();
            
            if ($count > 0) {
                $byCategory[$category] = $count;
            }
        }

        return [
            'total' => $total,
            'byStatus' => $byStatus,
            'byCategory' => $byCategory
        ];
    }

    /**
     * Vérifie si une demande peut être mise à jour
     */
    private function canBeUpdated(ServiceRequest $serviceRequest): bool
    {
        // Seules les demandes ouvertes ou avec devis peuvent être modifiées
        return in_array($serviceRequest->getStatus(), [self::STATUS_OPEN, self::STATUS_QUOTED], true);
    }

    /**
     * Vérifie si une demande peut être annulée
     */
    private function canBeCancelled(ServiceRequest $serviceRequest): bool
    {
        // On ne peut pas annuler une demande déjà annulée, expirée ou terminée
        return !in_array(
            $serviceRequest->getStatus(),
            [self::STATUS_CANCELLED, self::STATUS_EXPIRED, self::STATUS_COMPLETED],
            true
        );
    }

    /**
     * Valide les données d'une demande de service
     */
    private function validateServiceRequestData(array $data): void
    {
        // Catégorie requise
        if (!isset($data['category']) || empty($data['category'])) {
            throw new BadRequestHttpException('La catégorie est requise.');
        }

        if (!in_array($data['category'], self::VALID_CATEGORIES, true)) {
            throw new BadRequestHttpException('Catégorie invalide.');
        }

        // Description requise
        if (!isset($data['description']) || empty($data['description'])) {
            throw new BadRequestHttpException('La description est requise.');
        }

        if (strlen($data['description']) < 20) {
            throw new BadRequestHttpException('La description doit contenir au moins 20 caractères.');
        }

        // Adresse requise
        if (!isset($data['address']) || empty($data['address'])) {
            throw new BadRequestHttpException('L\'adresse est requise.');
        }

        // Date préférée requise
        if (!isset($data['preferredDate']) || empty($data['preferredDate'])) {
            throw new BadRequestHttpException('La date préférée est requise.');
        }

        // Vérifier que la date est dans le futur
        $preferredDate = new \DateTime($data['preferredDate']);
        $now = new \DateTime();
        
        if ($preferredDate < $now) {
            throw new BadRequestHttpException('La date préférée doit être dans le futur.');
        }

        // Vérifier la fréquence si fournie
        if (isset($data['frequency'])) {
            $this->validateFrequency($data['frequency']);
        }

        // Vérifier le budget si fourni
        if (isset($data['budget']) && $data['budget'] < 0) {
            throw new BadRequestHttpException('Le budget ne peut pas être négatif.');
        }
    }

    /**
     * Valide une fréquence
     */
    private function validateFrequency(string $frequency): void
    {
        if (!in_array($frequency, self::VALID_FREQUENCIES, true)) {
            throw new BadRequestHttpException('Fréquence invalide.');
        }
    }

    /**
     * Notifie les prestataires correspondants d'une nouvelle demande
     */
    private function notifyMatchingPrestataires(ServiceRequest $serviceRequest): void
    {
        try {
            $matchingPrestataires = $this->matchingService->findMatchingPrestataires($serviceRequest);

            foreach ($matchingPrestataires as $prestataire) {
                $this->notificationService->notifyNewServiceRequest($prestataire, $serviceRequest);
            }

            $this->logger->info('Notified matching prestataires', [
                'serviceRequestId' => $serviceRequest->getId(),
                'prestatairesCount' => count($matchingPrestataires)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to notify matching prestataires', [
                'serviceRequestId' => $serviceRequest->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Notifie les prestataires de l'annulation d'une demande
     */
    private function notifyPrestatairesOfCancellation(ServiceRequest $serviceRequest): void
    {
        try {
            $quotes = $this->quoteRepository->findBy([
                'serviceRequest' => $serviceRequest,
                'status' => 'pending'
            ]);

            foreach ($quotes as $quote) {
                $this->notificationService->notifyRequestCancelled(
                    $quote->getPrestataire(),
                    $serviceRequest
                );
            }

            $this->logger->info('Notified prestataires of cancellation', [
                'serviceRequestId' => $serviceRequest->getId(),
                'quotesCount' => count($quotes)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to notify prestataires of cancellation', [
                'serviceRequestId' => $serviceRequest->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }
}