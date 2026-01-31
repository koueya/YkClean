<?php

namespace App\Controller\Api\Client;

use App\Entity\Rating\Review;
use App\Entity\User\Client;
use App\Repository\Booking\BookingRepository;
use App\Repository\Rating\ReviewRepository;
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

#[Route('/api/client/reviews')]
#[IsGranted('ROLE_CLIENT')]
class ReviewController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReviewRepository $reviewRepository,
        private BookingRepository $bookingRepository,
        private PrestataireRepository $prestataireRepository,
        private BookingService $bookingService,
        private NotificationService $notificationService,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Get all reviews written by the authenticated client
     */
    #[Route('', name: 'api_client_reviews_list', methods: ['GET'])]
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
        $rating = $request->query->get('rating');
        $prestataireId = $request->query->get('prestataireId');
        $sortBy = $request->query->get('sortBy', 'createdAt');
        $sortOrder = strtoupper($request->query->get('sortOrder', 'DESC'));

        // Build query
        $queryBuilder = $this->reviewRepository->createQueryBuilder('r')
            ->where('r.client = :client')
            ->setParameter('client', $client);

        if ($rating) {
            $queryBuilder->andWhere('r.rating = :rating')
                ->setParameter('rating', (int) $rating);
        }

        if ($prestataireId) {
            $queryBuilder->andWhere('r.prestataire = :prestataireId')
                ->setParameter('prestataireId', $prestataireId);
        }

        // Sorting
        $allowedSortFields = ['createdAt', 'rating', 'updatedAt'];
        if (in_array($sortBy, $allowedSortFields)) {
            $queryBuilder->orderBy('r.' . $sortBy, $sortOrder === 'ASC' ? 'ASC' : 'DESC');
        }

        // Count total
        $totalQuery = clone $queryBuilder;
        $total = count($totalQuery->getQuery()->getResult());

        // Get paginated results
        $reviews = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Review $review) {
            return $this->formatReview($review);
        }, $reviews);

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
     * Get a specific review by ID
     */
    #[Route('/{id}', name: 'api_client_review_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $review = $this->reviewRepository->find($id);

        if (!$review) {
            return $this->json([
                'error' => 'Review not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($review->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => $this->formatReview($review, true)
        ]);
    }

    /**
     * Create a new review
     */
    #[Route('', name: 'api_client_review_create', methods: ['POST'])]
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
        if (!isset($data['bookingId']) || !isset($data['rating']) || !isset($data['comment'])) {
            return $this->json([
                'error' => 'Booking ID, rating, and comment are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $booking = $this->bookingRepository->find($data['bookingId']);

        if (!$booking) {
            return $this->json([
                'error' => 'Booking not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($booking->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if booking is completed
        if ($booking->getStatus() !== 'completed') {
            return $this->json([
                'error' => 'Can only review completed bookings'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if booking already has a review
        if ($booking->getReview()) {
            return $this->json([
                'error' => 'This booking already has a review'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate rating
        $rating = (int) $data['rating'];
        if ($rating < 1 || $rating > 5) {
            return $this->json([
                'error' => 'Rating must be between 1 and 5'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate comment length
        if (strlen($data['comment']) < 10) {
            return $this->json([
                'error' => 'Comment must be at least 10 characters long'
            ], Response::HTTP_BAD_REQUEST);
        }

        if (strlen($data['comment']) > 1000) {
            return $this->json([
                'error' => 'Comment cannot exceed 1000 characters'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Create review
        $review = new Review();
        $review->setBooking($booking);
        $review->setClient($client);
        $review->setPrestataire($booking->getPrestataire());
        $review->setRating($rating);
        $review->setComment($data['comment']);
        $review->setCreatedAt(new \DateTimeImmutable());

        // Optional detailed ratings
        if (isset($data['qualityRating'])) {
            $qualityRating = (int) $data['qualityRating'];
            if ($qualityRating >= 1 && $qualityRating <= 5) {
                $review->setQualityRating($qualityRating);
            }
        }

        if (isset($data['punctualityRating'])) {
            $punctualityRating = (int) $data['punctualityRating'];
            if ($punctualityRating >= 1 && $punctualityRating <= 5) {
                $review->setPunctualityRating($punctualityRating);
            }
        }

        if (isset($data['professionalismRating'])) {
            $professionalismRating = (int) $data['professionalismRating'];
            if ($professionalismRating >= 1 && $professionalismRating <= 5) {
                $review->setProfessionalismRating($professionalismRating);
            }
        }

        if (isset($data['communicationRating'])) {
            $communicationRating = (int) $data['communicationRating'];
            if ($communicationRating >= 1 && $communicationRating <= 5) {
                $review->setCommunicationRating($communicationRating);
            }
        }

        // Optional: Would recommend
        if (isset($data['wouldRecommend'])) {
            $review->setWouldRecommend((bool) $data['wouldRecommend']);
        }

        // Optional: Photos
        if (isset($data['photos']) && is_array($data['photos'])) {
            $review->setPhotos($data['photos']);
        }

        // Validate
        $errors = $this->validator->validate($review);
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

        $this->entityManager->persist($review);
        $booking->setReview($review);
        $this->entityManager->flush();

        // Update prestataire average rating
        $this->bookingService->updatePrestataireRating($booking->getPrestataire());

        // Notify prestataire
        try {
            $this->notificationService->notifyNewReview($review);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Review created successfully',
            'data' => $this->formatReview($review, true)
        ], Response::HTTP_CREATED);
    }

    /**
     * Update a review
     */
    #[Route('/{id}', name: 'api_client_review_update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $review = $this->reviewRepository->find($id);

        if (!$review) {
            return $this->json([
                'error' => 'Review not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($review->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if review can be edited (within 48 hours of creation)
        $now = new \DateTimeImmutable();
        $createdAt = $review->getCreatedAt();
        $hoursSinceCreation = ($now->getTimestamp() - $createdAt->getTimestamp()) / 3600;

        if ($hoursSinceCreation > 48) {
            return $this->json([
                'error' => 'Reviews can only be edited within 48 hours of creation'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'error' => 'Invalid JSON format'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update rating if provided
        if (isset($data['rating'])) {
            $rating = (int) $data['rating'];
            if ($rating < 1 || $rating > 5) {
                return $this->json([
                    'error' => 'Rating must be between 1 and 5'
                ], Response::HTTP_BAD_REQUEST);
            }
            $review->setRating($rating);
        }

        // Update comment if provided
        if (isset($data['comment'])) {
            if (strlen($data['comment']) < 10) {
                return $this->json([
                    'error' => 'Comment must be at least 10 characters long'
                ], Response::HTTP_BAD_REQUEST);
            }
            if (strlen($data['comment']) > 1000) {
                return $this->json([
                    'error' => 'Comment cannot exceed 1000 characters'
                ], Response::HTTP_BAD_REQUEST);
            }
            $review->setComment($data['comment']);
        }

        // Update detailed ratings if provided
        if (isset($data['qualityRating'])) {
            $qualityRating = (int) $data['qualityRating'];
            if ($qualityRating >= 1 && $qualityRating <= 5) {
                $review->setQualityRating($qualityRating);
            }
        }

        if (isset($data['punctualityRating'])) {
            $punctualityRating = (int) $data['punctualityRating'];
            if ($punctualityRating >= 1 && $punctualityRating <= 5) {
                $review->setPunctualityRating($punctualityRating);
            }
        }

        if (isset($data['professionalismRating'])) {
            $professionalismRating = (int) $data['professionalismRating'];
            if ($professionalismRating >= 1 && $professionalismRating <= 5) {
                $review->setProfessionalismRating($professionalismRating);
            }
        }

        if (isset($data['communicationRating'])) {
            $communicationRating = (int) $data['communicationRating'];
            if ($communicationRating >= 1 && $communicationRating <= 5) {
                $review->setCommunicationRating($communicationRating);
            }
        }

        if (isset($data['wouldRecommend'])) {
            $review->setWouldRecommend((bool) $data['wouldRecommend']);
        }

        if (isset($data['photos']) && is_array($data['photos'])) {
            $review->setPhotos($data['photos']);
        }

        $review->setUpdatedAt(new \DateTimeImmutable());

        // Validate
        $errors = $this->validator->validate($review);
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

        // Update prestataire average rating
        $this->bookingService->updatePrestataireRating($review->getPrestataire());

        // Notify prestataire of update
        try {
            $this->notificationService->notifyReviewUpdated($review);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Review updated successfully',
            'data' => $this->formatReview($review, true)
        ]);
    }

    /**
     * Delete a review
     */
    #[Route('/{id}', name: 'api_client_review_delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $review = $this->reviewRepository->find($id);

        if (!$review) {
            return $this->json([
                'error' => 'Review not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($review->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if review can be deleted (within 7 days of creation)
        $now = new \DateTimeImmutable();
        $createdAt = $review->getCreatedAt();
        $daysSinceCreation = ($now->getTimestamp() - $createdAt->getTimestamp()) / 86400;

        if ($daysSinceCreation > 7) {
            return $this->json([
                'error' => 'Reviews can only be deleted within 7 days of creation'
            ], Response::HTTP_BAD_REQUEST);
        }

        $prestataire = $review->getPrestataire();
        $booking = $review->getBooking();

        // Remove review from booking
        if ($booking) {
            $booking->setReview(null);
        }

        $this->entityManager->remove($review);
        $this->entityManager->flush();

        // Update prestataire average rating
        $this->bookingService->updatePrestataireRating($prestataire);

        return $this->json([
            'success' => true,
            'message' => 'Review deleted successfully'
        ]);
    }

    /**
     * Get reviews pending to be written
     */
    #[Route('/pending', name: 'api_client_reviews_pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        // Find completed bookings without reviews
        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.status = :status')
            ->andWhere('b.review IS NULL')
            ->setParameter('client', $client)
            ->setParameter('status', 'completed')
            ->orderBy('b.scheduledDate', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();

        $data = array_map(function ($booking) {
            $prestataire = $booking->getPrestataire();
            
            return [
                'bookingId' => $booking->getId(),
                'scheduledDate' => $booking->getScheduledDate()?->format('c'),
                'serviceCategory' => $booking->getServiceCategory(),
                'amount' => $booking->getAmount(),
                'prestataire' => [
                    'id' => $prestataire->getId(),
                    'firstName' => $prestataire->getFirstName(),
                    'lastName' => $prestataire->getLastName(),
                    'profilePicture' => $prestataire->getProfilePicture(),
                    'averageRating' => $prestataire->getAverageRating(),
                ],
                'completedAt' => $booking->getActualEndTime()?->format('c'),
            ];
        }, $bookings);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get review statistics
     */
    #[Route('/stats', name: 'api_client_reviews_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $allReviews = $this->reviewRepository->findBy(['client' => $client]);

        $stats = [
            'totalReviews' => count($allReviews),
            'averageRatingGiven' => 0,
            'ratingDistribution' => [
                5 => 0,
                4 => 0,
                3 => 0,
                2 => 0,
                1 => 0,
            ],
            'averageQualityRating' => 0,
            'averagePunctualityRating' => 0,
            'averageProfessionalismRating' => 0,
            'averageCommunicationRating' => 0,
            'recommendationRate' => 0,
        ];

        if (count($allReviews) === 0) {
            return $this->json([
                'success' => true,
                'data' => $stats
            ]);
        }

        $totalRating = 0;
        $totalQuality = 0;
        $totalPunctuality = 0;
        $totalProfessionalism = 0;
        $totalCommunication = 0;
        $recommendCount = 0;
        $detailedRatingsCount = 0;

        foreach ($allReviews as $review) {
            $rating = $review->getRating();
            $totalRating += $rating;
            $stats['ratingDistribution'][$rating]++;

            if ($review->getQualityRating()) {
                $totalQuality += $review->getQualityRating();
                $detailedRatingsCount++;
            }

            if ($review->getPunctualityRating()) {
                $totalPunctuality += $review->getPunctualityRating();
            }

            if ($review->getProfessionalismRating()) {
                $totalProfessionalism += $review->getProfessionalismRating();
            }

            if ($review->getCommunicationRating()) {
                $totalCommunication += $review->getCommunicationRating();
            }

            if ($review->isWouldRecommend()) {
                $recommendCount++;
            }
        }

        $stats['averageRatingGiven'] = round($totalRating / count($allReviews), 2);

        if ($detailedRatingsCount > 0) {
            $stats['averageQualityRating'] = round($totalQuality / $detailedRatingsCount, 2);
            $stats['averagePunctualityRating'] = round($totalPunctuality / $detailedRatingsCount, 2);
            $stats['averageProfessionalismRating'] = round($totalProfessionalism / $detailedRatingsCount, 2);
            $stats['averageCommunicationRating'] = round($totalCommunication / $detailedRatingsCount, 2);
        }

        $stats['recommendationRate'] = round(($recommendCount / count($allReviews)) * 100, 1);

        // Get pending reviews count
        $pendingCount = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.client = :client')
            ->andWhere('b.status = :status')
            ->andWhere('b.review IS NULL')
            ->setParameter('client', $client)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        $stats['pendingReviews'] = (int) $pendingCount;

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Report a prestataire's response to review as inappropriate
     */
    #[Route('/{id}/report-response', name: 'api_client_review_report_response', methods: ['POST'])]
    public function reportResponse(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $review = $this->reviewRepository->find($id);

        if (!$review) {
            return $this->json([
                'error' => 'Review not found'
            ], Response::HTTP_NOT_FOUND);
        }

        // Check ownership
        if ($review->getClient()->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'Access denied'
            ], Response::HTTP_FORBIDDEN);
        }

        // Check if review has a response
        if (!$review->getPrestataireResponse()) {
            return $this->json([
                'error' => 'Review does not have a response to report'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $reason = $data['reason'] ?? 'Inappropriate response';

        $review->setResponseReported(true);
        $review->setResponseReportReason($reason);
        $review->setResponseReportedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        // Notify admin
        try {
            $this->notificationService->notifyReviewResponseReported($review);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Response reported successfully. Our team will review it.'
        ]);
    }

    /**
     * Get reviews by prestataire (to see reviews for a specific prestataire)
     */
    #[Route('/prestataire/{prestataireId}', name: 'api_client_reviews_by_prestataire', methods: ['GET'])]
    public function getByPrestataire(int $prestataireId, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $prestataire = $this->prestataireRepository->find($prestataireId);

        if (!$prestataire) {
            return $this->json([
                'error' => 'Prestataire not found'
            ], Response::HTTP_NOT_FOUND);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->reviewRepository->createQueryBuilder('r')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.createdAt', 'DESC');

        $total = count($queryBuilder->getQuery()->getResult());

        $reviews = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Review $review) use ($client) {
            $formattedReview = $this->formatReview($review, true);
            // Indicate if this is the current client's review
            $formattedReview['isMyReview'] = $review->getClient()->getId() === $client->getId();
            return $formattedReview;
        }, $reviews);

        return $this->json([
            'success' => true,
            'data' => $data,
            'prestataire' => [
                'id' => $prestataire->getId(),
                'firstName' => $prestataire->getFirstName(),
                'lastName' => $prestataire->getLastName(),
                'averageRating' => $prestataire->getAverageRating(),
                'totalReviews' => $total,
            ],
            'pagination' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $total,
                'totalPages' => ceil($total / $limit)
            ]
        ]);
    }

    /**
     * Format review data for response
     */
    private function formatReview(Review $review, bool $detailed = false): array
    {
        $prestataire = $review->getPrestataire();
        $booking = $review->getBooking();

        $data = [
            'id' => $review->getId(),
            'rating' => $review->getRating(),
            'comment' => $review->getComment(),
            'createdAt' => $review->getCreatedAt()?->format('c'),
            'prestataire' => [
                'id' => $prestataire->getId(),
                'firstName' => $prestataire->getFirstName(),
                'lastName' => $prestataire->getLastName(),
                'profilePicture' => $prestataire->getProfilePicture(),
            ],
            'booking' => [
                'id' => $booking->getId(),
                'scheduledDate' => $booking->getScheduledDate()?->format('c'),
                'serviceCategory' => $booking->getServiceCategory(),
            ],
        ];

        if ($detailed) {
            $data['qualityRating'] = $review->getQualityRating();
            $data['punctualityRating'] = $review->getPunctualityRating();
            $data['professionalismRating'] = $review->getProfessionalismRating();
            $data['communicationRating'] = $review->getCommunicationRating();
            $data['wouldRecommend'] = $review->isWouldRecommend();
            $data['photos'] = $review->getPhotos();
            $data['updatedAt'] = $review->getUpdatedAt()?->format('c');

            // Include prestataire response if exists
            if ($review->getPrestataireResponse()) {
                $data['prestataireResponse'] = [
                    'response' => $review->getPrestataireResponse(),
                    'respondedAt' => $review->getPrestataireRespondedAt()?->format('c'),
                ];
            }

            // Include report status if reported
            if ($review->isResponseReported()) {
                $data['responseReported'] = true;
                $data['responseReportReason'] = $review->getResponseReportReason();
            }
        }

        return $data;
    }
}