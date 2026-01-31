<?php

namespace App\Service\Quote;

use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceRequest;
use App\Entity\Quote\Quote;
use App\Entity\Booking\Booking;
use App\Repository\Quote\QuoteRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Repository\Booking\BookingRepository;
use App\Service\Notification\NotificationService;
use App\Service\Booking\BookingService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des devis
 * 
 * Gère le cycle de vie complet des devis :
 * - Création et validation
 * - Acceptation/Rejet
 * - Retrait et expiration
 * - Statistiques et comparaisons
 */
class QuoteService
{
    private const STATUS_PENDING = 'pending';
    private const STATUS_ACCEPTED = 'accepted';
    private const STATUS_REJECTED = 'rejected';
    private const STATUS_EXPIRED = 'expired';
    private const STATUS_WITHDRAWN = 'withdrawn';

    private const VALID_STATUSES = [
        self::STATUS_PENDING,
        self::STATUS_ACCEPTED,
        self::STATUS_REJECTED,
        self::STATUS_EXPIRED,
        self::STATUS_WITHDRAWN
    ];

    // Durée de validité d'un devis en jours
    private const QUOTE_VALIDITY_DAYS = 7;

    // Nombre maximum de devis qu'un prestataire peut soumettre simultanément
    private const MAX_PENDING_QUOTES_PER_PRESTATAIRE = 20;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuoteRepository $quoteRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private BookingRepository $bookingRepository,
        private NotificationService $notificationService,
        private BookingService $bookingService,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée un nouveau devis
     */
    public function createQuote(Prestataire $prestataire, int $serviceRequestId, array $data): Quote
    {
        $serviceRequest = $this->getServiceRequestById($serviceRequestId);

        $this->logger->info('Creating quote', [
            'prestataireId' => $prestataire->getId(),
            'serviceRequestId' => $serviceRequestId
        ]);

        // Vérifications préalables
        $this->validateQuoteCreation($prestataire, $serviceRequest);

        // Vérifier que le prestataire n'a pas déjà soumis un devis pour cette demande
        $existingQuote = $this->quoteRepository->findOneBy([
            'prestataire' => $prestataire,
            'serviceRequest' => $serviceRequest
        ]);

        if ($existingQuote && $existingQuote->getStatus() === self::STATUS_PENDING) {
            throw new BadRequestHttpException('Vous avez déjà soumis un devis pour cette demande.');
        }

        // Validation des données du devis
        $this->validateQuoteData($data);

        $quote = new Quote();
        $quote->setServiceRequest($serviceRequest);
        $quote->setPrestataire($prestataire);
        $quote->setAmount($data['amount']);
        $quote->setProposedDate(new \DateTime($data['proposedDate']));
        $quote->setProposedDuration($data['proposedDuration']);
        
        if (isset($data['description'])) {
            $quote->setDescription($data['description']);
        }

        if (isset($data['conditions'])) {
            $quote->setConditions($data['conditions']);
        }

        $quote->setStatus(self::STATUS_PENDING);
        $quote->setCreatedAt(new \DateTimeImmutable());

        // Date de validité
        $validUntil = new \DateTimeImmutable();
        $validUntil = $validUntil->modify('+' . self::QUOTE_VALIDITY_DAYS . ' days');
        $quote->setValidUntil($validUntil);

        $this->entityManager->persist($quote);

        // Mettre à jour le statut de la demande de service si nécessaire
        if ($serviceRequest->getStatus() === 'open') {
            $serviceRequest->setStatus('quoted');
        }

        $this->entityManager->flush();

        $this->logger->info('Quote created', [
            'quoteId' => $quote->getId()
        ]);

        // Notifier le client
        $this->notificationService->notifyNewQuote($serviceRequest->getClient(), $quote);

        return $quote;
    }

    /**
     * Met à jour un devis (uniquement si pending)
     */
    public function updateQuote(int $quoteId, Prestataire $prestataire, array $data): Quote
    {
        $quote = $this->getQuoteById($quoteId);

        // Vérifier que c'est bien le prestataire qui a créé le devis
        if ($quote->getPrestataire()->getId() !== $prestataire->getId()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à ce devis.');
        }

        // Vérifier que le devis peut être modifié
        if ($quote->getStatus() !== self::STATUS_PENDING) {
            throw new BadRequestHttpException('Ce devis ne peut plus être modifié.');
        }

        $this->logger->info('Updating quote', [
            'quoteId' => $quoteId
        ]);

        // Mise à jour des champs autorisés
        if (isset($data['amount'])) {
            $this->validateAmount($data['amount']);
            $quote->setAmount($data['amount']);
        }

        if (isset($data['proposedDate'])) {
            $this->validateProposedDate($data['proposedDate']);
            $quote->setProposedDate(new \DateTime($data['proposedDate']));
        }

        if (isset($data['proposedDuration'])) {
            $this->validateDuration($data['proposedDuration']);
            $quote->setProposedDuration($data['proposedDuration']);
        }

        if (isset($data['description'])) {
            $quote->setDescription($data['description']);
        }

        if (isset($data['conditions'])) {
            $quote->setConditions($data['conditions']);
        }

        $this->entityManager->flush();

        $this->logger->info('Quote updated', [
            'quoteId' => $quoteId
        ]);

        // Notifier le client de la mise à jour
        $this->notificationService->notifyQuoteUpdated(
            $quote->getServiceRequest()->getClient(),
            $quote
        );

        return $quote;
    }

    /**
     * Retire un devis (prestataire)
     */
    public function withdrawQuote(int $quoteId, Prestataire $prestataire, string $reason = null): Quote
    {
        $quote = $this->getQuoteById($quoteId);

        // Vérifier que c'est bien le prestataire qui a créé le devis
        if ($quote->getPrestataire()->getId() !== $prestataire->getId()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à ce devis.');
        }

        // Vérifier que le devis peut être retiré
        if ($quote->getStatus() !== self::STATUS_PENDING) {
            throw new BadRequestHttpException('Ce devis ne peut plus être retiré.');
        }

        $this->logger->info('Withdrawing quote', [
            'quoteId' => $quoteId,
            'reason' => $reason
        ]);

        $quote->setStatus(self::STATUS_WITHDRAWN);
        $quote->setWithdrawalReason($reason);
        $quote->setWithdrawnAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notifier le client
        $this->notificationService->notifyQuoteWithdrawn(
            $quote->getServiceRequest()->getClient(),
            $quote
        );

        return $quote;
    }

    /**
     * Accepte un devis (client)
     */
    public function acceptQuote(int $quoteId, Client $client): Quote
    {
        $quote = $this->getQuoteById($quoteId);

        // Vérifier que c'est bien le client qui a créé la demande
        if ($quote->getServiceRequest()->getClient()->getId() !== $client->getId()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à ce devis.');
        }

        // Vérifier que le devis peut être accepté
        if ($quote->getStatus() !== self::STATUS_PENDING) {
            throw new BadRequestHttpException('Ce devis ne peut plus être accepté.');
        }

        // Vérifier que le devis n'a pas expiré
        if ($quote->getValidUntil() < new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Ce devis a expiré.');
        }

        $this->logger->info('Accepting quote', [
            'quoteId' => $quoteId,
            'clientId' => $client->getId()
        ]);

        $quote->setStatus(self::STATUS_ACCEPTED);
        $quote->setAcceptedAt(new \DateTimeImmutable());

        // Rejeter automatiquement les autres devis pour cette demande
        $this->rejectOtherQuotes($quote);

        // Mettre à jour le statut de la demande de service
        $serviceRequest = $quote->getServiceRequest();
        $serviceRequest->setStatus('in_progress');

        $this->entityManager->flush();

        // Créer la réservation automatiquement
        $booking = $this->bookingService->createBookingFromQuote($quote);

        $this->logger->info('Quote accepted and booking created', [
            'quoteId' => $quoteId,
            'bookingId' => $booking->getId()
        ]);

        // Notifier le prestataire
        $this->notificationService->notifyQuoteAccepted($quote->getPrestataire(), $quote);

        return $quote;
    }

    /**
     * Rejette un devis (client)
     */
    public function rejectQuote(int $quoteId, Client $client, string $reason = null): Quote
    {
        $quote = $this->getQuoteById($quoteId);

        // Vérifier que c'est bien le client qui a créé la demande
        if ($quote->getServiceRequest()->getClient()->getId() !== $client->getId()) {
            throw new AccessDeniedHttpException('Vous n\'avez pas accès à ce devis.');
        }

        // Vérifier que le devis peut être rejeté
        if ($quote->getStatus() !== self::STATUS_PENDING) {
            throw new BadRequestHttpException('Ce devis ne peut plus être rejeté.');
        }

        $this->logger->info('Rejecting quote', [
            'quoteId' => $quoteId,
            'reason' => $reason
        ]);

        $quote->setStatus(self::STATUS_REJECTED);
        $quote->setRejectionReason($reason);
        $quote->setRejectedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notifier le prestataire
        $this->notificationService->notifyQuoteRejected($quote->getPrestataire(), $quote);

        return $quote;
    }

    /**
     * Récupère un devis par son ID
     */
    public function getQuoteById(int $quoteId): Quote
    {
        $quote = $this->quoteRepository->find($quoteId);

        if (!$quote) {
            throw new NotFoundHttpException('Devis non trouvé.');
        }

        return $quote;
    }

    /**
     * Récupère tous les devis d'un prestataire
     */
    public function getPrestataireQuotes(Prestataire $prestataire, array $filters = []): array
    {
        $queryBuilder = $this->quoteRepository->createQueryBuilder('q')
            ->where('q.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('q.createdAt', 'DESC');

        // Filtres
        if (isset($filters['status'])) {
            $queryBuilder->andWhere('q.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $queryBuilder->andWhere('q.createdAt >= :fromDate')
                ->setParameter('fromDate', new \DateTime($filters['from_date']));
        }

        if (isset($filters['to_date'])) {
            $queryBuilder->andWhere('q.createdAt <= :toDate')
                ->setParameter('toDate', new \DateTime($filters['to_date']));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère les devis en attente d'un prestataire
     */
    public function getPendingQuotes(Prestataire $prestataire): array
    {
        return $this->quoteRepository->findBy(
            ['prestataire' => $prestataire, 'status' => self::STATUS_PENDING],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Récupère les devis pour une demande de service
     */
    public function getQuotesForServiceRequest(int $serviceRequestId): array
    {
        $serviceRequest = $this->getServiceRequestById($serviceRequestId);

        return $this->quoteRepository->findBy(
            ['serviceRequest' => $serviceRequest],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Récupère les devis en attente pour une demande
     */
    public function getPendingQuotesForServiceRequest(int $serviceRequestId): array
    {
        $serviceRequest = $this->getServiceRequestById($serviceRequestId);

        return $this->quoteRepository->findBy([
            'serviceRequest' => $serviceRequest,
            'status' => self::STATUS_PENDING
        ]);
    }

    /**
     * Compare plusieurs devis
     */
    public function compareQuotes(array $quoteIds): array
    {
        $quotes = [];
        foreach ($quoteIds as $quoteId) {
            $quotes[] = $this->getQuoteById($quoteId);
        }

        // Vérifier que tous les devis sont pour la même demande
        $firstServiceRequest = $quotes[0]->getServiceRequest();
        foreach ($quotes as $quote) {
            if ($quote->getServiceRequest()->getId() !== $firstServiceRequest->getId()) {
                throw new BadRequestHttpException('Les devis doivent être pour la même demande de service.');
            }
        }

        $comparison = [];
        foreach ($quotes as $quote) {
            $prestataire = $quote->getPrestataire();
            
            $comparison[] = [
                'quoteId' => $quote->getId(),
                'prestataire' => [
                    'id' => $prestataire->getId(),
                    'name' => $prestataire->getFirstName() . ' ' . $prestataire->getLastName(),
                    'rating' => $prestataire->getAverageRating(),
                    'reviewsCount' => $this->getReviewsCount($prestataire)
                ],
                'amount' => $quote->getAmount(),
                'proposedDate' => $quote->getProposedDate()->format('Y-m-d H:i'),
                'proposedDuration' => $quote->getProposedDuration(),
                'hourlyRate' => ($quote->getAmount() / $quote->getProposedDuration()) * 60,
                'description' => $quote->getDescription(),
                'conditions' => $quote->getConditions(),
                'createdAt' => $quote->getCreatedAt()->format('Y-m-d H:i:s'),
                'validUntil' => $quote->getValidUntil()->format('Y-m-d H:i:s')
            ];
        }

        // Trier par montant (du moins cher au plus cher)
        usort($comparison, fn($a, $b) => $a['amount'] <=> $b['amount']);

        return $comparison;
    }

    /**
     * Récupère les statistiques des devis pour un prestataire
     */
    public function getPrestataireQuoteStatistics(Prestataire $prestataire): array
    {
        $total = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        $byStatus = [];
        foreach (self::VALID_STATUSES as $status) {
            $count = $this->quoteRepository->createQueryBuilder('q')
                ->select('COUNT(q.id)')
                ->where('q.prestataire = :prestataire')
                ->andWhere('q.status = :status')
                ->setParameter('prestataire', $prestataire)
                ->setParameter('status', $status)
                ->getQuery()
                ->getSingleScalarResult();
            
            $byStatus[$status] = $count;
        }

        // Taux d'acceptation
        $acceptanceRate = $total > 0 ? round(($byStatus['accepted'] / $total) * 100, 2) : 0;

        // Montant moyen des devis acceptés
        $averageAcceptedAmount = $this->quoteRepository->createQueryBuilder('q')
            ->select('AVG(q.amount)')
            ->where('q.prestataire = :prestataire')
            ->andWhere('q.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'accepted')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'byStatus' => $byStatus,
            'acceptanceRate' => $acceptanceRate,
            'averageAcceptedAmount' => (float) $averageAcceptedAmount
        ];
    }

    /**
     * Marque un devis comme expiré
     */
    public function expireQuote(Quote $quote): void
    {
        if ($quote->getStatus() === self::STATUS_PENDING) {
            $this->logger->info('Expiring quote', [
                'quoteId' => $quote->getId()
            ]);

            $quote->setStatus(self::STATUS_EXPIRED);
            $this->entityManager->flush();

            // Notifier le prestataire
            $this->notificationService->notifyQuoteExpired($quote->getPrestataire(), $quote);
        }
    }

    /**
     * Traite les devis expirés (commande à exécuter périodiquement)
     */
    public function processExpiredQuotes(): int
    {
        $now = new \DateTimeImmutable();
        
        $expiredQuotes = $this->quoteRepository->createQueryBuilder('q')
            ->where('q.status = :status')
            ->andWhere('q.validUntil < :now')
            ->setParameter('status', self::STATUS_PENDING)
            ->setParameter('now', $now)
            ->getQuery()
            ->getResult();

        $count = 0;
        foreach ($expiredQuotes as $quote) {
            $this->expireQuote($quote);
            $count++;
        }

        $this->logger->info('Processed expired quotes', ['count' => $count]);

        return $count;
    }

    /**
     * Recherche de devis
     */
    public function searchQuotes(array $criteria, int $page = 1, int $limit = 20): array
    {
        $queryBuilder = $this->quoteRepository->createQueryBuilder('q');
        $offset = ($page - 1) * $limit;

        // Filtres
        if (isset($criteria['status'])) {
            $queryBuilder->andWhere('q.status = :status')
                ->setParameter('status', $criteria['status']);
        }

        if (isset($criteria['prestataireId'])) {
            $queryBuilder->andWhere('q.prestataire = :prestataireId')
                ->setParameter('prestataireId', $criteria['prestataireId']);
        }

        if (isset($criteria['serviceRequestId'])) {
            $queryBuilder->andWhere('q.serviceRequest = :serviceRequestId')
                ->setParameter('serviceRequestId', $criteria['serviceRequestId']);
        }

        if (isset($criteria['min_amount'])) {
            $queryBuilder->andWhere('q.amount >= :minAmount')
                ->setParameter('minAmount', $criteria['min_amount']);
        }

        if (isset($criteria['max_amount'])) {
            $queryBuilder->andWhere('q.amount <= :maxAmount')
                ->setParameter('maxAmount', $criteria['max_amount']);
        }

        if (isset($criteria['from_date'])) {
            $queryBuilder->andWhere('q.createdAt >= :fromDate')
                ->setParameter('fromDate', new \DateTime($criteria['from_date']));
        }

        if (isset($criteria['to_date'])) {
            $queryBuilder->andWhere('q.createdAt <= :toDate')
                ->setParameter('toDate', new \DateTime($criteria['to_date']));
        }

        $quotes = $queryBuilder
            ->orderBy('q.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'quotes' => $quotes,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }

    /**
     * Valide la création d'un devis
     */
    private function validateQuoteCreation(Prestataire $prestataire, ServiceRequest $serviceRequest): void
    {
        // Vérifier que le prestataire est approuvé
        if (!$prestataire->getIsApproved()) {
            throw new BadRequestHttpException('Votre compte doit être approuvé pour soumettre des devis.');
        }

        // Vérifier que le compte est actif
        if (!$prestataire->getIsActive()) {
            throw new BadRequestHttpException('Votre compte est désactivé.');
        }

        // Vérifier que la demande est ouverte
        if (!in_array($serviceRequest->getStatus(), ['open', 'quoted'], true)) {
            throw new BadRequestHttpException('Cette demande n\'est plus disponible.');
        }

        // Vérifier que la demande n'a pas expiré
        if ($serviceRequest->getExpiresAt() < new \DateTimeImmutable()) {
            throw new BadRequestHttpException('Cette demande a expiré.');
        }

        // Vérifier que le prestataire propose cette catégorie de service
        $prestataireCategories = $prestataire->getServiceCategories();
        if (!in_array($serviceRequest->getCategory(), $prestataireCategories, true)) {
            throw new BadRequestHttpException('Cette demande ne correspond pas à vos catégories de service.');
        }

        // Vérifier le nombre de devis en attente du prestataire
        $pendingQuotesCount = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(q.id)')
            ->where('q.prestataire = :prestataire')
            ->andWhere('q.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getSingleScalarResult();

        if ($pendingQuotesCount >= self::MAX_PENDING_QUOTES_PER_PRESTATAIRE) {
            throw new BadRequestHttpException(
                'Vous avez atteint le nombre maximum de devis en attente.'
            );
        }
    }

    /**
     * Valide les données d'un devis
     */
    private function validateQuoteData(array $data): void
    {
        // Montant requis
        if (!isset($data['amount']) || empty($data['amount'])) {
            throw new BadRequestHttpException('Le montant est requis.');
        }
        $this->validateAmount($data['amount']);

        // Date proposée requise
        if (!isset($data['proposedDate']) || empty($data['proposedDate'])) {
            throw new BadRequestHttpException('La date proposée est requise.');
        }
        $this->validateProposedDate($data['proposedDate']);

        // Durée proposée requise
        if (!isset($data['proposedDuration']) || empty($data['proposedDuration'])) {
            throw new BadRequestHttpException('La durée proposée est requise.');
        }
        $this->validateDuration($data['proposedDuration']);
    }

    /**
     * Valide le montant
     */
    private function validateAmount(float $amount): void
    {
        if ($amount <= 0) {
            throw new BadRequestHttpException('Le montant doit être positif.');
        }

        if ($amount < 10) {
            throw new BadRequestHttpException('Le montant minimum est de 10€.');
        }

        if ($amount > 10000) {
            throw new BadRequestHttpException('Le montant ne peut pas dépasser 10 000€.');
        }
    }

    /**
     * Valide la date proposée
     */
    private function validateProposedDate(string $dateString): void
    {
        try {
            $proposedDate = new \DateTime($dateString);
            $now = new \DateTime();

            // La date doit être dans le futur (au moins 24h)
            $minDate = clone $now;
            $minDate->modify('+24 hours');

            if ($proposedDate < $minDate) {
                throw new BadRequestHttpException('La date proposée doit être au moins 24 heures dans le futur.');
            }

            // Pas plus de 90 jours dans le futur
            $maxDate = clone $now;
            $maxDate->modify('+90 days');

            if ($proposedDate > $maxDate) {
                throw new BadRequestHttpException('La date proposée ne peut pas être à plus de 90 jours.');
            }

        } catch (\Exception $e) {
            throw new BadRequestHttpException('Le format de la date proposée est invalide.');
        }
    }

    /**
     * Valide la durée
     */
    private function validateDuration(int $duration): void
    {
        if ($duration < 30) {
            throw new BadRequestHttpException('La durée minimale est de 30 minutes.');
        }

        if ($duration > 480) {
            throw new BadRequestHttpException('La durée maximale est de 8 heures (480 minutes).');
        }
    }

    /**
     * Rejette automatiquement les autres devis pour une demande
     */
    private function rejectOtherQuotes(Quote $acceptedQuote): void
    {
        $otherQuotes = $this->quoteRepository->createQueryBuilder('q')
            ->where('q.serviceRequest = :serviceRequest')
            ->andWhere('q.id != :quoteId')
            ->andWhere('q.status = :status')
            ->setParameter('serviceRequest', $acceptedQuote->getServiceRequest())
            ->setParameter('quoteId', $acceptedQuote->getId())
            ->setParameter('status', self::STATUS_PENDING)
            ->getQuery()
            ->getResult();

        foreach ($otherQuotes as $quote) {
            $quote->setStatus(self::STATUS_REJECTED);
            $quote->setRejectionReason('Un autre devis a été accepté.');
            $quote->setRejectedAt(new \DateTimeImmutable());

            // Notifier les prestataires
            $this->notificationService->notifyQuoteRejected($quote->getPrestataire(), $quote);
        }
    }

    /**
     * Récupère une demande de service par son ID
     */
    private function getServiceRequestById(int $serviceRequestId): ServiceRequest
    {
        $serviceRequest = $this->serviceRequestRepository->find($serviceRequestId);

        if (!$serviceRequest) {
            throw new NotFoundHttpException('Demande de service non trouvée.');
        }

        return $serviceRequest;
    }

    /**
     * Compte le nombre d'avis d'un prestataire
     */
    private function getReviewsCount(Prestataire $prestataire): int
    {
        return $this->entityManager->createQueryBuilder()
            ->select('COUNT(r.id)')
            ->from('App\Entity\Rating\Review', 'r')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();
    }
}