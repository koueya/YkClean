<?php

namespace App\Controller\Api\Client;

use App\Entity\Booking\Booking;
use App\Entity\Quote\Quote;
use App\Entity\User\Client;
use App\Repository\Booking\BookingRepository;
use App\Repository\Quote\QuoteRepository;                    // ✅ AJOUTÉ
use App\Repository\Service\ServiceRequestRepository;         // ✅ AJOUTÉ
use App\Service\Booking\BookingService;                      // ✅ AJOUTÉ (renommé de BookingManager)
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;                                  // ✅ AJOUTÉ

#[Route('/api/client/quotes')]
#[IsGranted('ROLE_CLIENT')]
class QuoteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuoteRepository $quoteRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private BookingService $bookingService,
        private NotificationService $notificationService,
        private LoggerInterface $logger                         // ✅ AJOUTÉ
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
            ->leftJoin('q.prestataire', 'p')
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

        // Count total
        $totalQuery = clone $queryBuilder;
        $total = count($totalQuery->getQuery()->getResult());

        // Apply sorting and pagination
        $quotes = $queryBuilder
            ->orderBy('q.' . $sortBy, $sortOrder)
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

        // Check ownership
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
     * Get all quotes for a specific service request
     */
    #[Route('/service-request/{serviceRequestId}', name: 'api_client_quotes_by_service_request', methods: ['GET'])]
    public function getByServiceRequest(int $serviceRequestId): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($serviceRequestId);

        if (!$serviceRequest) {
            return $this->json([
                'error' => 'Service request not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $quotes = $this->quoteRepository->findByServiceRequest($serviceRequest);

        $data = array_map(function (Quote $quote) {
            return $this->formatQuote($quote);
        }, $quotes);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Accept a quote
     */
    #[Route('/{id}/accept', name: 'api_client_quote_accept', methods: ['POST'])]
    public function accept(int $id): JsonResponse
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

        // Can only accept pending quotes
        if ($quote->getStatus() !== 'pending') {
            return $this->json([
                'error' => 'Only pending quotes can be accepted'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if quote has expired
        if ($quote->getValidUntil() && $quote->getValidUntil() < new \DateTimeImmutable()) {
            return $this->json([
                'error' => 'This quote has expired'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Update quote status
            $quote->setStatus('accepted');
            $quote->setAcceptedAt(new \DateTimeImmutable());

            // Reject all other quotes for this service request
            $serviceRequest = $quote->getServiceRequest();
            $otherQuotes = $this->quoteRepository->createQueryBuilder('q')
                ->where('q.serviceRequest = :serviceRequest')
                ->andWhere('q.id != :quoteId')
                ->andWhere('q.status = :status')
                ->setParameter('serviceRequest', $serviceRequest)
                ->setParameter('quoteId', $quote->getId())
                ->setParameter('status', 'pending')
                ->getQuery()
                ->getResult();

            foreach ($otherQuotes as $otherQuote) {
                $otherQuote->setStatus('rejected');
                $otherQuote->setRejectionReason('Another quote was accepted');
            }

            // Update service request status
            $serviceRequest->setStatus('in_progress');

            // Create booking from quote
            $booking = $this->bookingService->createFromQuote($quote, $client);

            $this->entityManager->flush();

            // Send notifications
            try {
                $this->notificationService->notifyQuoteAccepted($quote);
                $this->notificationService->notifyBookingCreated($booking);
                
                // Notify rejected prestataires
                foreach ($otherQuotes as $otherQuote) {
                    $this->notificationService->notifyQuoteRejected($otherQuote);
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed to send quote acceptance notifications', [
                    'quote_id' => $quote->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Quote accepted successfully',
                'data' => [
                    'quote' => $this->formatQuote($quote),
                    'booking' => [
                        'id' => $booking->getId(),
                        'scheduledDate' => $booking->getScheduledDate()?->format('c'),
                        'status' => $booking->getStatus()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to accept quote', [
                'quote_id' => $id,
                'error' => $e->getMessage()
            ]);

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

        // Can only reject pending quotes
        if ($quote->getStatus() !== 'pending') {
            return $this->json([
                'error' => 'Only pending quotes can be rejected'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? 'No reason provided';

        try {
            $quote->setStatus('rejected');
            $quote->setRejectionReason($reason);
            $quote->setRejectedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            // Notify prestataire
            try {
                $this->notificationService->notifyQuoteRejected($quote);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send quote rejection notification', [
                    'quote_id' => $quote->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Quote rejected successfully'
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to reject quote', [
                'quote_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to reject quote'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Request modification to a quote
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

        try {
            // Store modification request
            $quote->setModificationRequested(true);
            $quote->setModificationMessage($data['message']);
            $quote->setModificationRequestedAt(new \DateTimeImmutable());

            $this->entityManager->flush();

            // Notify prestataire
            try {
                $this->notificationService->notifyQuoteModificationRequested($quote);
            } catch (\Exception $e) {
                $this->logger->error('Failed to send modification request notification', [
                    'quote_id' => $quote->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            return $this->json([
                'success' => true,
                'message' => 'Modification request sent to prestataire',
                'data' => $this->formatQuote($quote)
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to request quote modification', [
                'quote_id' => $id,
                'error' => $e->getMessage()
            ]);

            return $this->json([
                'error' => 'Failed to request modification'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Compare quotes for a service request
     */
    #[Route('/compare/{serviceRequestId}', name: 'api_client_quotes_compare', methods: ['GET'])]
    public function compare(int $serviceRequestId): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($serviceRequestId);

        if (!$serviceRequest) {
            return $this->json([
                'error' => 'Service request not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($serviceRequest->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        $quotes = $this->quoteRepository->findByServiceRequest($serviceRequest);

        // Format quotes for comparison
        $comparison = array_map(function (Quote $quote) {
            $prestataire = $quote->getPrestataire();
            
            return [
                'id' => $quote->getId(),
                'amount' => $quote->getAmount(),
                'proposedDate' => $quote->getProposedDate()?->format('c'),
                'proposedDuration' => $quote->getProposedDuration(),
                'status' => $quote->getStatus(),
                'description' => $quote->getDescription(),
                'validUntil' => $quote->getValidUntil()?->format('c'),
                'createdAt' => $quote->getCreatedAt()?->format('c'),
                'prestataire' => [
                    'id' => $prestataire->getId(),
                    'firstName' => $prestataire->getFirstName(),
                    'lastName' => $prestataire->getLastName(),
                    'averageRating' => $prestataire->getAverageRating(),
                    'totalReviews' => $prestataire->getTotalReviews(),
                    'completedBookings' => $prestataire->getCompletedBookingsCount(),
                ],
            ];
        }, $quotes);

        return $this->json([
            'success' => true,
            'data' => [
                'serviceRequest' => [
                    'id' => $serviceRequest->getId(),
                    'category' => $serviceRequest->getCategory()?->getName(),
                    'description' => $serviceRequest->getDescription(),
                ],
                'quotes' => $comparison,
                'stats' => [
                    'totalQuotes' => count($quotes),
                    'averageAmount' => count($quotes) > 0 ? array_sum(array_column($comparison, 'amount')) / count($quotes) : 0,
                    'minAmount' => count($quotes) > 0 ? min(array_column($comparison, 'amount')) : 0,
                    'maxAmount' => count($quotes) > 0 ? max(array_column($comparison, 'amount')) : 0,
                ]
            ]
        ]);
    }

    /**
     * Format quote for JSON response
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
            'status' => $quote->getStatus(),
            'validUntil' => $quote->getValidUntil()?->format('c'),
            'createdAt' => $quote->getCreatedAt()?->format('c'),
            'prestataire' => [
                'id' => $prestataire->getId(),
                'firstName' => $prestataire->getFirstName(),
                'lastName' => $prestataire->getLastName(),
                'averageRating' => $prestataire->getAverageRating(),
            ],
        ];

        if ($detailed) {
            $data['description'] = $quote->getDescription();
            $data['conditions'] = $quote->getConditions();
            $data['acceptedAt'] = $quote->getAcceptedAt()?->format('c');
            $data['rejectedAt'] = $quote->getRejectedAt()?->format('c');
            $data['rejectionReason'] = $quote->getRejectionReason();
            $data['modificationRequested'] = $quote->isModificationRequested();
            $data['modificationMessage'] = $quote->getModificationMessage();
            $data['serviceRequest'] = [
                'id' => $serviceRequest->getId(),
                'category' => $serviceRequest->getCategory()?->getName(),
                'description' => $serviceRequest->getDescription(),
                'preferredDate' => $serviceRequest->getPreferredDate()?->format('c'),
            ];
        }

        return $data;
    }
}