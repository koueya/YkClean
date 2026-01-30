<?php

namespace App\Controller\Api\Client;

use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\User\Client;
use App\Repository\QuoteRepository;
use App\Repository\ServiceRequestRepository;
use App\Service\BookingService;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/client/quotes')]
#[IsGranted('ROLE_CLIENT')]
class QuoteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuoteRepository $quoteRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private BookingService $bookingService,
        private NotificationService $notificationService
    ) {
    }

    /**
     * Get all quotes for the authenticated client
     */
    #[Route('', name: 'api_client_quotes_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        // Pagination
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        // Filters
        $status = $request->query->get('status');
        $serviceRequestId = $request->query->get('serviceRequestId');
        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = strtoupper($request->query->get('sortOrder', 'DESC'));

        // Build query
        $queryBuilder = $this->quoteRepository->createQueryBuilder('q')
            ->leftJoin('q.serviceRequest', 'sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client);

        if ($status) {
            $queryBuilder->andWhere('q.status = :status')
                ->setParameter('status', $status);
        }

        if ($serviceRequestId) {
            $queryBuilder->andWhere('sr.id = :serviceRequestId')
                ->setParameter('serviceRequestId', $serviceRequestId);
        }

        // Filter out expired quotes
        $includeExpired = $request->query->get('includeExpired', 'false') === 'true';
        if (!$includeExpired) {
            $queryBuilder->andWhere('q.validUntil > :now OR q.validUntil IS NULL')
                ->setParameter('now', new \DateTimeImmutable());
        }

        // Sorting
        $allowedSortFields = ['createdAt', 'amount', 'proposedDate', 'status'];
        if (in_array($sortBy, $allowedSortFields)) {
            $queryBuilder->orderBy('q.' . $sortBy, $sortOrder === 'ASC' ? 'ASC' : 'DESC');
        }

        // Count total
        $totalQuery = clone $queryBuilder;
        $total = count($totalQuery->getQuery()->getResult());

        // Get paginated results
        $quotes = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Quote $quote) {
            return $this->formatQuote($quote);
        }, $quotes);

        return $this->json([
            'success' => true,
            'data' => $data,
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Get a specific quote by ID
     */
    #[Route('/{id}', name: 'api_client_quote_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => 'Quote not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership through service request
        if ($quote->getServiceRequest()->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $this->formatQuote($quote, true)
        ]);
    }

    /**
     * Accept a quote and create a booking
     */
    #[Route('/{id}/accept', name: 'api_client_quote_accept', methods: ['POST'])]
    public function accept(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => 'Quote not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($quote->getServiceRequest()->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if quote is still valid
        if ($quote->getStatus() !== 'pending') {
            return $this->json([
                'error' => 'Quote is no longer available'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if quote has expired
        if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTimeImmutable()) {
            return $this->json([
                'error' => 'Quote has expired'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if service request already has an accepted quote
        $serviceRequest = $quote->getServiceRequest();
        if ($serviceRequest->getStatus() === 'in_progress' || $serviceRequest->getStatus() === 'completed') {
            return $this->json([
                'error' => 'Service request already has an accepted quote'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        
        // Optional: client can specify a different date if alternatives were provided
        $scheduledDate = $quote->getProposedDate();
        if (isset($data['scheduledDate'])) {
            try {
                $scheduledDate = new \DateTimeImmutable($data['scheduledDate']);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Invalid scheduled date format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Optional: client can specify time
        $scheduledTime = $data['scheduledTime'] ?? null;

        try {
            // Begin transaction
            $this->entityManager->beginTransaction();

            // Update quote status
            $quote->setStatus('accepted');
            $quote->setAcceptedAt(new \DateTimeImmutable());

            // Create booking
            $booking = $this->bookingService->createBookingFromQuote(
                $quote,
                $scheduledDate,
                $scheduledTime
            );

            // Update service request status
            $serviceRequest->setStatus('in_progress');
            $serviceRequest->setUpdatedAt(new \DateTimeImmutable());

            // Reject all other pending quotes for this service request
            foreach ($serviceRequest->getQuotes() as $otherQuote) {
                if ($otherQuote->getId() !== $quote->getId() && $otherQuote->getStatus() === 'pending') {
                    $otherQuote->setStatus('rejected');
                    $otherQuote->setRejectionReason('Client accepted another quote');
                }
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            // Send notifications
            try {
                // Notify the selected prestataire
                $this->notificationService->notifyQuoteAccepted($quote);
                
                // Notify rejected prestataires
                foreach ($serviceRequest->getQuotes() as $rejectedQuote) {
                    if ($rejectedQuote->getId() !== $quote->getId() && $rejectedQuote->getStatus() === 'rejected') {
                        $this->notificationService->notifyQuoteRejected($rejectedQuote);
                    }
                }
            } catch (\Exception $e) {
                // Log error but don't fail the request
            }

            return $this->json([
                'success' => true,
                'message' => 'Quote accepted and booking created successfully',
                'data' => [
                    'quote' => $this->formatQuote($quote),
                    'booking' => [
                        'id' => $booking->getId(),
                        'scheduledDate' => $booking->getScheduledDate()?->format('c'),
                        'scheduledTime' => $booking->getScheduledTime(),
                        'status' => $booking->getStatus(),
                        'amount' => $booking->getAmount(),
                    ]
                ]
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            return $this->json([
                'error' => 'Failed to accept quote',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Reject a quote
     */
    #[Route('/{id}/reject', name: 'api_client_quote_reject', methods: ['POST'])]
    public function reject(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => 'Quote not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($quote->getServiceRequest()->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if quote can be rejected
        if ($quote->getStatus() !== 'pending') {
            return $this->json([
                'error' => 'Quote cannot be rejected'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $reason = $data['reason'] ?? 'Client rejected the quote';

        $quote->setStatus('rejected');
        $quote->setRejectionReason($reason);
        $quote->setRejectedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notify prestataire
        try {
            $this->notificationService->notifyQuoteRejected($quote);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Quote rejected successfully',
            'data' => $this->formatQuote($quote)
        ]);
    }

    /**
     * Compare multiple quotes
     */
    #[Route('/compare', name: 'api_client_quotes_compare', methods: ['POST'])]
    public function compare(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['quoteIds']) || !is_array($data['quoteIds']) || empty($data['quoteIds'])) {
            return $this->json([
                'error' => 'Quote IDs array is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Limit comparison to 5 quotes
        if (count($data['quoteIds']) > 5) {
            return $this->json([
                'error' => 'Can only compare up to 5 quotes at once'
            ], Response::HTTP_BAD_REQUEST);
        }

        $quotes = $this->quoteRepository->findBy(['id' => $data['quoteIds']]);

        if (empty($quotes)) {
            return $this->json([
                'error' => 'No quotes found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Verify ownership and that all quotes are for the same service request
        $serviceRequestId = null;
        foreach ($quotes as $quote) {
            if ($quote->getServiceRequest()->getClient()->getId() !== $client->getId()) {
                return $this->json([
                    'error' => 'Access denied to one or more quotes'
                ], Response::HTTP_FORBIDDEN);
            }

            if ($serviceRequestId === null) {
                $serviceRequestId = $quote->getServiceRequest()->getId();
            } elseif ($serviceRequestId !== $quote->getServiceRequest()->getId()) {
                return $this->json([
                    'error' => 'All quotes must be from the same service request'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Format quotes for comparison
        $comparison = [
            'serviceRequest' => [
                'id' => $serviceRequestId,
                'category' => $quotes[0]->getServiceRequest()->getCategory(),
                'description' => $quotes[0]->getServiceRequest()->getDescription(),
            ],
            'quotes' => array_map(function (Quote $quote) {
                return $this->formatQuote($quote, true);
            }, $quotes),
            'statistics' => [
                'averageAmount' => $this->calculateAverage($quotes, 'getAmount'),
                'lowestAmount' => $this->calculateMin($quotes, 'getAmount'),
                'highestAmount' => $this->calculateMax($quotes, 'getAmount'),
                'averageDuration' => $this->calculateAverage($quotes, 'getProposedDuration'),
            ]
        ];

        return $this->json([
            'success' => true,
            'data' => $comparison
        ]);
    }

    /**
     * Request quote modification
     */
    #[Route('/{id}/request-modification', name: 'api_client_quote_request_modification', methods: ['POST'])]
    public function requestModification(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $quote = $this->quoteRepository->find($id);

        if (!$quote) {
            return $this->json([
                'error' => 'Quote not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($quote->getServiceRequest()->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Can only request modification for pending quotes
        if ($quote->getStatus() !== 'pending') {
            return $this->json([
                'error' => 'Can only request modification for pending quotes'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['message']) || empty($data['message'])) {
            return $this->json([
                'error' => 'Modification message is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Store modification request
        $quote->setModificationRequested(true);
        $quote->setModificationMessage($data['message']);
        $quote->setModificationRequestedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notify prestataire
        try {
            $this->notificationService->notifyQuoteModificationRequested($quote);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Modification request sent to prestataire',
            'data' => $this->formatQuote($quote)
        ]);
    }

    /**
     * Get quote statistics for client
     */
    #[Route('/stats', name: 'api_client_quotes_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        // Get all quotes for this client
        $allQuotes = $this->quoteRepository->createQueryBuilder('q')
            ->leftJoin('q.serviceRequest', 'sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->getResult();

        $stats = [
            'totalQuotes' => count($allQuotes),
            'pendingQuotes' => 0,
            'acceptedQuotes' => 0,
            'rejectedQuotes' => 0,
            'expiredQuotes' => 0,
            'averageQuotesPerRequest' => 0,
            'averageResponseTime' => null, // TODO: Calculate based on quote creation time vs service request creation time
        ];

        foreach ($allQuotes as $quote) {
            switch ($quote->getStatus()) {
                case 'pending':
                    // Check if expired
                    if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTimeImmutable()) {
                        $stats['expiredQuotes']++;
                    } else {
                        $stats['pendingQuotes']++;
                    }
                    break;
                case 'accepted':
                    $stats['acceptedQuotes']++;
                    break;
                case 'rejected':
                    $stats['rejectedQuotes']++;
                    break;
            }
        }

        // Calculate average quotes per request
        $serviceRequestsCount = $this->serviceRequestRepository->count(['client' => $client]);
        if ($serviceRequestsCount > 0) {
            $stats['averageQuotesPerRequest'] = round($stats['totalQuotes'] / $serviceRequestsCount, 2);
        }

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Format quote data for response
     */
    private function formatQuote(Quote $quote, bool $detailed = false): array
    {
        $prestataire = $quote->getPrestataire();
        $serviceRequest = $quote->getServiceRequest();

        $data = [
            'id' => $quote->getId(),
            'amount' => $quote->getAmount(),
            'proposedDate' => $quote->getProposedDate()?->format('c'),
            'proposedDuration' => $quote->getProposedDuration(),
            'description' => $quote->getDescription(),
            'status' => $quote->getStatus(),
            'validUntil' => $quote->getValidUntil()?->format('c'),
            'isExpired' => $quote->getValidUntil() && $quote->getValidUntil() < new \DateTimeImmutable(),
            'createdAt' => $quote->getCreatedAt()?->format('c'),
            'prestataire' => [
                'id' => $prestataire->getId(),
                'firstName' => $prestataire->getFirstName(),
                'lastName' => $prestataire->getLastName(),
                'averageRating' => $prestataire->getAverageRating(),
                'completedBookings' => $prestataire->getCompletedBookings(),
                'profilePicture' => $prestataire->getProfilePicture(),
            ],
            'serviceRequest' => [
                'id' => $serviceRequest->getId(),
                'category' => $serviceRequest->getCategory(),
                'description' => $serviceRequest->getDescription(),
            ]
        ];

        if ($detailed) {
            $data['conditions'] = $quote->getConditions();
            $data['alternativeDates'] = array_map(
                fn($date) => $date->format('c'),
                $quote->getAlternativeDates() ?? []
            );
            $data['includesProducts'] = $quote->getIncludesProducts();
            $data['equipmentNeeded'] = $quote->getEquipmentNeeded();
            
            if ($quote->getStatus() === 'accepted') {
                $data['acceptedAt'] = $quote->getAcceptedAt()?->format('c');
            }
            
            if ($quote->getStatus() === 'rejected') {
                $data['rejectionReason'] = $quote->getRejectionReason();
                $data['rejectedAt'] = $quote->getRejectedAt()?->format('c');
            }

            if ($quote->isModificationRequested()) {
                $data['modificationRequested'] = true;
                $data['modificationMessage'] = $quote->getModificationMessage();
                $data['modificationRequestedAt'] = $quote->getModificationRequestedAt()?->format('c');
            }

            // Add more prestataire details
            $data['prestataire']['phone'] = $prestataire->getPhone();
            $data['prestataire']['serviceCategories'] = $prestataire->getServiceCategories();
            $data['prestataire']['hourlyRate'] = $prestataire->getHourlyRate();
            $data['prestataire']['isVerified'] = $prestataire->isVerified();
        }

        return $data;
    }

    /**
     * Calculate average of a property across quotes
     */
    private function calculateAverage(array $quotes, string $method): ?float
    {
        $values = array_filter(array_map(fn($q) => $q->$method(), $quotes));
        return empty($values) ? null : round(array_sum($values) / count($values), 2);
    }

    /**
     * Calculate minimum of a property across quotes
     */
    private function calculateMin(array $quotes, string $method): ?float
    {
        $values = array_filter(array_map(fn($q) => $q->$method(), $quotes));
        return empty($values) ? null : min($values);
    }

    /**
     * Calculate maximum of a property across quotes
     */
    private function calculateMax(array $quotes, string $method): ?float
    {
        $values = array_filter(array_map(fn($q) => $q->$method(), $quotes));
        return empty($values) ? null : max($values);
    }
}