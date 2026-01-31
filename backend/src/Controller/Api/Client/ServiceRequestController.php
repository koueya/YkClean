<?php

namespace App\Controller\Api\Client;

use App\Entity\Service\ServiceRequest;
use App\Entity\User\Client;
use App\Repository\Rating\ReviewRepository;
use App\Repository\Booking\BookingRepository;
use App\Repository\User\PrestataireRepository;
use App\Service\Booking\BookingService;
use App\Service\Notification\NotificationService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/client/service-requests')]
#[IsGranted('ROLE_CLIENT')]
class ServiceRequestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRequestRepository $serviceRequestRepository,
        private ValidatorInterface $validator,
        private NotificationService $notificationService,
        private MatchingService $matchingService
    ) {
    }

    /**
     * Get all service requests for the authenticated client
     */
    #[Route('', name: 'api_client_service_requests_list', methods: ['GET'])]
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
        $category = $request->query->get('category');
        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = strtoupper($request->query->get('sortOrder', 'DESC'));

        // Build query
        $queryBuilder = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client);

        if ($status) {
            $queryBuilder->andWhere('sr.status = :status')
                ->setParameter('status', $status);
        }

        if ($category) {
            $queryBuilder->andWhere('sr.category = :category')
                ->setParameter('category', $category);
        }

        // Sorting
        $allowedSortFields = ['createdAt', 'preferredDate', 'status', 'budget'];
        if (in_array($sortBy, $allowedSortFields)) {
            $queryBuilder->orderBy('sr.' . $sortBy, $sortOrder === 'ASC' ? 'ASC' : 'DESC');
        }

        // Count total
        $totalQuery = clone $queryBuilder;
        $total = count($totalQuery->getQuery()->getResult());

        // Get paginated results
        $serviceRequests = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (ServiceRequest $sr) {
            return $this->formatServiceRequest($sr);
        }, $serviceRequests);

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
     * Get a specific service request by ID
     */
    #[Route('/{id}', name: 'api_client_service_request_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($id);

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

        return $this->json([
            'success' => true,
            'data' => $this->formatServiceRequest($serviceRequest, true)
        ]);
    }

    /**
     * Create a new service request
     */
    #[Route('', name: 'api_client_service_request_create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'error' => 'Invalid JSON format'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate required fields
        $requiredFields = ['category', 'description', 'address', 'preferredDate'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'error' => "Field '{$field}' is required"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Create service request
        $serviceRequest = new ServiceRequest();
        $serviceRequest->setClient($client);
        $serviceRequest->setCategory($data['category']);
        $serviceRequest->setDescription($data['description']);
        $serviceRequest->setAddress($data['address']);
        
        // Parse preferred date
        try {
            $preferredDate = new \DateTimeImmutable($data['preferredDate']);
            $serviceRequest->setPreferredDate($preferredDate);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Invalid date format for preferredDate'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Optional fields
        if (isset($data['alternativeDates']) && is_array($data['alternativeDates'])) {
            $alternativeDates = [];
            foreach ($data['alternativeDates'] as $dateStr) {
                try {
                    $alternativeDates[] = new \DateTimeImmutable($dateStr);
                } catch (\Exception $e) {
                    // Skip invalid dates
                }
            }
            $serviceRequest->setAlternativeDates($alternativeDates);
        }

        if (isset($data['duration'])) {
            $serviceRequest->setDuration((float) $data['duration']);
        }

        if (isset($data['frequency'])) {
            $serviceRequest->setFrequency($data['frequency']);
        }

        if (isset($data['budget'])) {
            $serviceRequest->setBudget((float) $data['budget']);
        }

        if (isset($data['specificRequirements'])) {
            $serviceRequest->setSpecificRequirements($data['specificRequirements']);
        }

        if (isset($data['surfaceArea'])) {
            $serviceRequest->setSurfaceArea((int) $data['surfaceArea']);
        }

        if (isset($data['numberOfRooms'])) {
            $serviceRequest->setNumberOfRooms((int) $data['numberOfRooms']);
        }

        if (isset($data['hasPets'])) {
            $serviceRequest->setHasPets((bool) $data['hasPets']);
        }

        // Set default values
        $serviceRequest->setStatus('open');
        $serviceRequest->setCreatedAt(new \DateTimeImmutable());
        
        // Set expiration date (7 days from now)
        $expiresAt = new \DateTimeImmutable('+7 days');
        $serviceRequest->setExpiresAt($expiresAt);

        // Validate
        $errors = $this->validator->validate($serviceRequest);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Validation failed',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($serviceRequest);
        $this->entityManager->flush();

        // Notify matching prestataires
        try {
            $this->matchingService->notifyMatchingPrestataires($serviceRequest);
        } catch (\Exception $e) {
            // Log error but don't fail the request
            // TODO: Add logging
        }

        return $this->json([
            'success' => true,
            'message' => 'Service request created successfully',
            'data' => $this->formatServiceRequest($serviceRequest, true)
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a service request
     */
    #[Route('/{id}', name: 'api_client_service_request_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($id);

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

        // Can only update open requests
        if ($serviceRequest->getStatus() !== 'open') {
            return $this->json([
                'error' => 'Can only update open service requests'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'error' => 'Invalid JSON format'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update allowed fields
        if (isset($data['description'])) {
            $serviceRequest->setDescription($data['description']);
        }

        if (isset($data['address'])) {
            $serviceRequest->setAddress($data['address']);
        }

        if (isset($data['preferredDate'])) {
            try {
                $preferredDate = new \DateTimeImmutable($data['preferredDate']);
                $serviceRequest->setPreferredDate($preferredDate);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Invalid date format for preferredDate'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if (isset($data['alternativeDates']) && is_array($data['alternativeDates'])) {
            $alternativeDates = [];
            foreach ($data['alternativeDates'] as $dateStr) {
                try {
                    $alternativeDates[] = new \DateTimeImmutable($dateStr);
                } catch (\Exception $e) {
                    // Skip invalid dates
                }
            }
            $serviceRequest->setAlternativeDates($alternativeDates);
        }

        if (isset($data['duration'])) {
            $serviceRequest->setDuration((float) $data['duration']);
        }

        if (isset($data['frequency'])) {
            $serviceRequest->setFrequency($data['frequency']);
        }

        if (isset($data['budget'])) {
            $serviceRequest->setBudget((float) $data['budget']);
        }

        if (isset($data['specificRequirements'])) {
            $serviceRequest->setSpecificRequirements($data['specificRequirements']);
        }

        if (isset($data['surfaceArea'])) {
            $serviceRequest->setSurfaceArea((int) $data['surfaceArea']);
        }

        if (isset($data['numberOfRooms'])) {
            $serviceRequest->setNumberOfRooms((int) $data['numberOfRooms']);
        }

        if (isset($data['hasPets'])) {
            $serviceRequest->setHasPets((bool) $data['hasPets']);
        }

        $serviceRequest->setUpdatedAt(new \DateTimeImmutable());

        // Validate
        $errors = $this->validator->validate($serviceRequest);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Validation failed',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Service request updated successfully',
            'data' => $this->formatServiceRequest($serviceRequest, true)
        ]);
    }

    /**
     * Cancel a service request
     */
    #[Route('/{id}/cancel', name: 'api_client_service_request_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($id);

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

        // Check if can be cancelled
        if (in_array($serviceRequest->getStatus(), ['completed', 'cancelled'])) {
            return $this->json([
                'error' => 'Service request cannot be cancelled'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        $serviceRequest->setStatus('cancelled');
        $serviceRequest->setCancellationReason($reason);
        $serviceRequest->setCancelledAt(new \DateTimeImmutable());
        $serviceRequest->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notify prestataires who sent quotes
        try {
            $this->notificationService->notifyServiceRequestCancelled($serviceRequest);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Service request cancelled successfully',
            'data' => $this->formatServiceRequest($serviceRequest)
        ]);
    }

    /**
     * Delete a service request (soft delete)
     */
    #[Route('/{id}', name: 'api_client_service_request_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($id);

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

        // Can only delete cancelled or expired requests
        if (!in_array($serviceRequest->getStatus(), ['cancelled', 'expired'])) {
            return $this->json([
                'error' => 'Can only delete cancelled or expired service requests'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Soft delete
        $serviceRequest->setDeletedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Service request deleted successfully'
        ]);
    }

    /**
     * Get quotes for a service request
     */
    #[Route('/{id}/quotes', name: 'api_client_service_request_quotes', methods: ['GET'])]
    public function getQuotes(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($id);

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

        $quotes = $serviceRequest->getQuotes();
        $quotesData = [];

        foreach ($quotes as $quote) {
            $prestataire = $quote->getPrestataire();
            
            $quotesData[] = [
                'id' => $quote->getId(),
                'amount' => $quote->getAmount(),
                'proposedDate' => $quote->getProposedDate()?->format('c'),
                'proposedDuration' => $quote->getProposedDuration(),
                'description' => $quote->getDescription(),
                'conditions' => $quote->getConditions(),
                'status' => $quote->getStatus(),
                'validUntil' => $quote->getValidUntil()?->format('c'),
                'createdAt' => $quote->getCreatedAt()?->format('c'),
                'prestataire' => [
                    'id' => $prestataire->getId(),
                    'firstName' => $prestataire->getFirstName(),
                    'lastName' => $prestataire->getLastName(),
                    'averageRating' => $prestataire->getAverageRating(),
                    'completedBookings' => $prestataire->getCompletedBookings(),
                    'profilePicture' => $prestataire->getProfilePicture(),
                ]
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $quotesData
        ]);
    }

    /**
     * Reopen an expired service request
     */
    #[Route('/{id}/reopen', name: 'api_client_service_request_reopen', methods: ['POST'])]
    public function reopen(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $serviceRequest = $this->serviceRequestRepository->find($id);

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

        // Can only reopen expired or cancelled requests
        if (!in_array($serviceRequest->getStatus(), ['expired', 'cancelled'])) {
            return $this->json([
                'error' => 'Can only reopen expired or cancelled service requests'
            ], Response::HTTP_BAD_REQUEST);
        }

        $serviceRequest->setStatus('open');
        $serviceRequest->setExpiresAt(new \DateTimeImmutable('+7 days'));
        $serviceRequest->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notify matching prestataires again
        try {
            $this->matchingService->notifyMatchingPrestataires($serviceRequest);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Service request reopened successfully',
            'data' => $this->formatServiceRequest($serviceRequest, true)
        ]);
    }

    /**
     * Format service request data for response
     */
    private function formatServiceRequest(ServiceRequest $serviceRequest, bool $detailed = false): array
    {
        $data = [
            'id' => $serviceRequest->getId(),
            'category' => $serviceRequest->getCategory(),
            'description' => $serviceRequest->getDescription(),
            'address' => $serviceRequest->getAddress(),
            'preferredDate' => $serviceRequest->getPreferredDate()?->format('c'),
            'duration' => $serviceRequest->getDuration(),
            'frequency' => $serviceRequest->getFrequency(),
            'budget' => $serviceRequest->getBudget(),
            'status' => $serviceRequest->getStatus(),
            'createdAt' => $serviceRequest->getCreatedAt()?->format('c'),
            'expiresAt' => $serviceRequest->getExpiresAt()?->format('c'),
            'quotesCount' => count($serviceRequest->getQuotes()),
        ];

        if ($detailed) {
            $data['alternativeDates'] = array_map(
                fn($date) => $date->format('c'),
                $serviceRequest->getAlternativeDates() ?? []
            );
            $data['specificRequirements'] = $serviceRequest->getSpecificRequirements();
            $data['surfaceArea'] = $serviceRequest->getSurfaceArea();
            $data['numberOfRooms'] = $serviceRequest->getNumberOfRooms();
            $data['hasPets'] = $serviceRequest->getHasPets();
            $data['updatedAt'] = $serviceRequest->getUpdatedAt()?->format('c');
            
            if ($serviceRequest->getStatus() === 'cancelled') {
                $data['cancellationReason'] = $serviceRequest->getCancellationReason();
                $data['cancelledAt'] = $serviceRequest->getCancelledAt()?->format('c');
            }
        }

        return $data;
    }
}