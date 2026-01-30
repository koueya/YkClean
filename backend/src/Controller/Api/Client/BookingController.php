<?php

namespace App\Controller\Api\Client;

use App\Entity\Booking\Booking;
use App\Entity\Rating\Review;
use App\Entity\User\Client;
use App\Repository\BookingRepository;
use App\Service\BookingService;
use App\Service\NotificationService;
use App\Service\PaymentService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/client/bookings')]
#[IsGranted('ROLE_CLIENT')]
class BookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private BookingService $bookingService,
        private NotificationService $notificationService,
        private PaymentService $paymentService,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Get all bookings for the authenticated client
     */
    #[Route('', name: 'api_client_bookings_list', methods: ['GET'])]
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
        $startDate = $request->query->get('startDate');
        $endDate = $request->query->get('endDate');
        $sortBy = $request->query->get('sortBy', 'scheduledDate');
        $sortOrder = strtoupper($request->query->get('sortOrder', 'DESC'));

        // Build query
        $queryBuilder = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->setParameter('client', $client);

        if ($status) {
            $queryBuilder->andWhere('b.status = :status')
                ->setParameter('status', $status);
        }

        if ($startDate) {
            try {
                $start = new \DateTimeImmutable($startDate);
                $queryBuilder->andWhere('b.scheduledDate >= :startDate')
                    ->setParameter('startDate', $start);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Invalid startDate format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        if ($endDate) {
            try {
                $end = new \DateTimeImmutable($endDate);
                $queryBuilder->andWhere('b.scheduledDate <= :endDate')
                    ->setParameter('endDate', $end);
            } catch (\Exception $e) {
                return $this->json([
                    'error' => 'Invalid endDate format'
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Sorting
        $allowedSortFields = ['scheduledDate', 'createdAt', 'status', 'amount'];
        if (in_array($sortBy, $allowedSortFields)) {
            $queryBuilder->orderBy('b.' . $sortBy, $sortOrder === 'ASC' ? 'ASC' : 'DESC');
        }

        // Count total
        $totalQuery = clone $queryBuilder;
        $total = count($totalQuery->getQuery()->getResult());

        // Get paginated results
        $bookings = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Booking $booking) {
            return $this->formatBooking($booking);
        }, $bookings);

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
     * Get upcoming bookings
     */
    #[Route('/upcoming', name: 'api_client_bookings_upcoming', methods: ['GET'])]
    public function upcoming(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $now = new \DateTimeImmutable();
        
        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('now', $now)
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->orderBy('b.scheduledDate', 'ASC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Booking $booking) {
            return $this->formatBooking($booking);
        }, $bookings);

        return $this->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get booking history (completed and cancelled)
     */
    #[Route('/history', name: 'api_client_bookings_history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $offset = ($page - 1) * $limit;

        $queryBuilder = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['completed', 'cancelled'])
            ->orderBy('b.scheduledDate', 'DESC');

        $total = count($queryBuilder->getQuery()->getResult());

        $bookings = $queryBuilder
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $data = array_map(function (Booking $booking) {
            return $this->formatBooking($booking, true);
        }, $bookings);

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
     * Get a specific booking by ID
     */
    #[Route('/{id}', name: 'api_client_booking_get', methods: ['GET'])]
    public function get(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $booking = $this->bookingRepository->find($id);

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

        return $this->json([
            'success' => true,
            'data' => $this->formatBooking($booking, true)
        ]);
    }

    /**
     * Cancel a booking
     */
    #[Route('/{id}/cancel', name: 'api_client_booking_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $booking = $this->bookingRepository->find($id);

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

        // Check if booking can be cancelled
        if (!in_array($booking->getStatus(), ['scheduled', 'confirmed'])) {
            return $this->json([
                'error' => 'Booking cannot be cancelled'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $reason = $data['reason'] ?? 'Client cancelled';

        // Check cancellation policy (24h before)
        $now = new \DateTimeImmutable();
        $scheduledDate = $booking->getScheduledDate();
        $hoursDifference = ($scheduledDate->getTimestamp() - $now->getTimestamp()) / 3600;

        $cancellationFee = 0;
        $refundAmount = $booking->getAmount();

        if ($hoursDifference < 24) {
            // Less than 24 hours: apply cancellation fee
            $cancellationFee = $booking->getAmount() * 0.5; // 50% fee
            $refundAmount = $booking->getAmount() - $cancellationFee;
            
            $cancellationPolicy = 'Late cancellation (less than 24h): 50% cancellation fee applied';
        } else {
            $cancellationPolicy = 'Full refund - cancelled more than 24h in advance';
        }

        try {
            $this->entityManager->beginTransaction();

            // Update booking status
            $booking->setStatus('cancelled');
            $booking->setCancellationReason($reason);
            $booking->setCancellationPolicy($cancellationPolicy);
            $booking->setCancellationFee($cancellationFee);
            $booking->setRefundAmount($refundAmount);
            $booking->setCancelledAt(new \DateTimeImmutable());
            $booking->setCancelledBy('client');

            // If recurrent, handle the recurrence
            if ($booking->getRecurrence()) {
                $cancelFutureBookings = $data['cancelFutureBookings'] ?? false;
                
                if ($cancelFutureBookings) {
                    $this->bookingService->cancelRecurrentBookings($booking->getRecurrence(), $reason);
                }
            }

            $this->entityManager->flush();

            // Process refund if applicable
            if ($refundAmount > 0) {
                try {
                    $this->paymentService->processRefund($booking, $refundAmount);
                } catch (\Exception $e) {
                    // Log error but don't fail the cancellation
                }
            }

            // Notify prestataire
            try {
                $this->notificationService->notifyBookingCancelled($booking);
            } catch (\Exception $e) {
                // Log error but don't fail
            }

            $this->entityManager->commit();

            return $this->json([
                'success' => true,
                'message' => 'Booking cancelled successfully',
                'data' => [
                    'booking' => $this->formatBooking($booking),
                    'refund' => [
                        'amount' => $refundAmount,
                        'cancellationFee' => $cancellationFee,
                        'policy' => $cancellationPolicy
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            $this->entityManager->rollback();
            
            return $this->json([
                'error' => 'Failed to cancel booking',
                'message' => $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Confirm a booking (client confirms the appointment)
     */
    #[Route('/{id}/confirm', name: 'api_client_booking_confirm', methods: ['POST'])]
    public function confirm(int $id): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $booking = $this->bookingRepository->find($id);

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

        // Check status
        if ($booking->getStatus() !== 'scheduled') {
            return $this->json([
                'error' => 'Booking is not in scheduled status'
            ], Response::HTTP_BAD_REQUEST);
        }

        $booking->setStatus('confirmed');
        $booking->setConfirmedAt(new \DateTimeImmutable());
        
        $this->entityManager->flush();

        // Notify prestataire
        try {
            $this->notificationService->notifyBookingConfirmed($booking);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Booking confirmed successfully',
            'data' => $this->formatBooking($booking)
        ]);
    }

    /**
     * Reschedule a booking
     */
    #[Route('/{id}/reschedule', name: 'api_client_booking_reschedule', methods: ['POST'])]
    public function reschedule(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $booking = $this->bookingRepository->find($id);

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

        // Can only reschedule scheduled or confirmed bookings
        if (!in_array($booking->getStatus(), ['scheduled', 'confirmed'])) {
            return $this->json([
                'error' => 'Booking cannot be rescheduled'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['newDate'])) {
            return $this->json([
                'error' => 'New date is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $newDate = new \DateTimeImmutable($data['newDate']);
        } catch (\Exception $e) {
            return $this->json([
                'error' => 'Invalid date format'
            ], Response::HTTP_BAD_REQUEST);
        }

        $newTime = $data['newTime'] ?? null;
        $reason = $data['reason'] ?? 'Client requested reschedule';

        // Check if new date is available for prestataire
        $isAvailable = $this->bookingService->checkPrestataireAvailability(
            $booking->getPrestataire(),
            $newDate,
            $newTime,
            $booking->getDuration()
        );

        if (!$isAvailable) {
            return $this->json([
                'error' => 'Prestataire is not available at the requested time'
            ], Response::HTTP_CONFLICT);
        }

        // Store old date for notification
        $oldDate = $booking->getScheduledDate();
        $oldTime = $booking->getScheduledTime();

        // Update booking
        $booking->setScheduledDate($newDate);
        
        if ($newTime) {
            $booking->setScheduledTime($newTime);
        }
        
        $booking->setRescheduledAt(new \DateTimeImmutable());
        $booking->setRescheduleReason($reason);
        $booking->setRescheduledBy('client');
        
        // Mark as pending confirmation from prestataire
        $booking->setStatus('scheduled');
        
        $this->entityManager->flush();

        // Notify prestataire
        try {
            $this->notificationService->notifyBookingRescheduled($booking, $oldDate, $oldTime);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Reschedule request sent to prestataire',
            'data' => $this->formatBooking($booking)
        ]);
    }

    /**
     * Add a review for a completed booking
     */
    #[Route('/{id}/review', name: 'api_client_booking_review', methods: ['POST'])]
    public function addReview(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $booking = $this->bookingRepository->find($id);

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

        // Can only review completed bookings
        if ($booking->getStatus() !== 'completed') {
            return $this->json([
                'error' => 'Can only review completed bookings'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Check if already reviewed
        if ($booking->getReview()) {
            return $this->json([
                'error' => 'Booking already has a review'
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['rating']) || !isset($data['comment'])) {
            return $this->json([
                'error' => 'Rating and comment are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $rating = (int) $data['rating'];
        
        if ($rating < 1 || $rating > 5) {
            return $this->json([
                'error' => 'Rating must be between 1 and 5'
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
            $review->setQualityRating((int) $data['qualityRating']);
        }
        if (isset($data['punctualityRating'])) {
            $review->setPunctualityRating((int) $data['punctualityRating']);
        }
        if (isset($data['professionalismRating'])) {
            $review->setProfessionalismRating((int) $data['professionalismRating']);
        }
        if (isset($data['communicationRating'])) {
            $review->setCommunicationRating((int) $data['communicationRating']);
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
            'message' => 'Review submitted successfully',
            'data' => [
                'review' => [
                    'id' => $review->getId(),
                    'rating' => $review->getRating(),
                    'comment' => $review->getComment(),
                    'createdAt' => $review->getCreatedAt()?->format('c'),
                ]
            ]
        ], Response::HTTP_CREATED);
    }

    /**
     * Report an issue with a booking
     */
    #[Route('/{id}/report-issue', name: 'api_client_booking_report_issue', methods: ['POST'])]
    public function reportIssue(int $id, Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $booking = $this->bookingRepository->find($id);

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

        $data = json_decode($request->getContent(), true);

        if (!isset($data['issueType']) || !isset($data['description'])) {
            return $this->json([
                'error' => 'Issue type and description are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        $booking->setHasIssue(true);
        $booking->setIssueType($data['issueType']);
        $booking->setIssueDescription($data['description']);
        $booking->setIssueReportedAt(new \DateTimeImmutable());

        if (isset($data['photos']) && is_array($data['photos'])) {
            $booking->setIssuePhotos($data['photos']);
        }

        $this->entityManager->flush();

        // Notify admin and prestataire
        try {
            $this->notificationService->notifyBookingIssue($booking);
        } catch (\Exception $e) {
            // Log error but don't fail
        }

        return $this->json([
            'success' => true,
            'message' => 'Issue reported successfully. Our team will review it shortly.'
        ]);
    }

    /**
     * Get booking statistics
     */
    #[Route('/stats', name: 'api_client_bookings_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $allBookings = $this->bookingRepository->findBy(['client' => $client]);

        $stats = [
            'totalBookings' => count($allBookings),
            'completedBookings' => 0,
            'cancelledBookings' => 0,
            'upcomingBookings' => 0,
            'totalSpent' => 0,
            'averageBookingAmount' => 0,
            'totalHoursBooked' => 0,
            'reviewsGiven' => 0,
            'averageRatingGiven' => 0,
        ];

        $now = new \DateTimeImmutable();
        $totalRating = 0;

        foreach ($allBookings as $booking) {
            switch ($booking->getStatus()) {
                case 'completed':
                    $stats['completedBookings']++;
                    $stats['totalSpent'] += $booking->getAmount();
                    $stats['totalHoursBooked'] += $booking->getDuration();
                    
                    if ($booking->getReview()) {
                        $stats['reviewsGiven']++;
                        $totalRating += $booking->getReview()->getRating();
                    }
                    break;
                    
                case 'cancelled':
                    $stats['cancelledBookings']++;
                    break;
                    
                case 'scheduled':
                case 'confirmed':
                    if ($booking->getScheduledDate() >= $now) {
                        $stats['upcomingBookings']++;
                    }
                    break;
            }
        }

        if ($stats['completedBookings'] > 0) {
            $stats['averageBookingAmount'] = round($stats['totalSpent'] / $stats['completedBookings'], 2);
        }

        if ($stats['reviewsGiven'] > 0) {
            $stats['averageRatingGiven'] = round($totalRating / $stats['reviewsGiven'], 2);
        }

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Format booking data for response
     */
    private function formatBooking(Booking $booking, bool $detailed = false): array
    {
        $prestataire = $booking->getPrestataire();

        $data = [
            'id' => $booking->getId(),
            'scheduledDate' => $booking->getScheduledDate()?->format('c'),
            'scheduledTime' => $booking->getScheduledTime(),
            'duration' => $booking->getDuration(),
            'address' => $booking->getAddress(),
            'amount' => $booking->getAmount(),
            'status' => $booking->getStatus(),
            'createdAt' => $booking->getCreatedAt()?->format('c'),
            'prestataire' => [
                'id' => $prestataire->getId(),
                'firstName' => $prestataire->getFirstName(),
                'lastName' => $prestataire->getLastName(),
                'phone' => $prestataire->getPhone(),
                'averageRating' => $prestataire->getAverageRating(),
                'profilePicture' => $prestataire->getProfilePicture(),
            ],
            'hasReview' => $booking->getReview() !== null,
            'isRecurrent' => $booking->getRecurrence() !== null,
        ];

        if ($detailed) {
            $data['serviceCategory'] = $booking->getServiceCategory();
            $data['specialInstructions'] = $booking->getSpecialInstructions();
            $data['accessCode'] = $booking->getAccessCode();
            
            if ($booking->getActualStartTime()) {
                $data['actualStartTime'] = $booking->getActualStartTime()?->format('c');
            }
            
            if ($booking->getActualEndTime()) {
                $data['actualEndTime'] = $booking->getActualEndTime()?->format('c');
            }
            
            if ($booking->getCompletionNotes()) {
                $data['completionNotes'] = $booking->getCompletionNotes();
            }

            if ($booking->getStatus() === 'cancelled') {
                $data['cancellationReason'] = $booking->getCancellationReason();
                $data['cancelledAt'] = $booking->getCancelledAt()?->format('c');
                $data['cancelledBy'] = $booking->getCancelledBy();
                $data['cancellationFee'] = $booking->getCancellationFee();
                $data['refundAmount'] = $booking->getRefundAmount();
            }

            if ($booking->getRescheduledAt()) {
                $data['rescheduledAt'] = $booking->getRescheduledAt()?->format('c');
                $data['rescheduleReason'] = $booking->getRescheduleReason();
            }

            if ($booking->getReview()) {
                $review = $booking->getReview();
                $data['review'] = [
                    'id' => $review->getId(),
                    'rating' => $review->getRating(),
                    'comment' => $review->getComment(),
                    'createdAt' => $review->getCreatedAt()?->format('c'),
                ];
            }

            if ($booking->getRecurrence()) {
                $recurrence = $booking->getRecurrence();
                $data['recurrence'] = [
                    'id' => $recurrence->getId(),
                    'frequency' => $recurrence->getFrequency(),
                    'dayOfWeek' => $recurrence->getDayOfWeek(),
                    'endDate' => $recurrence->getEndDate()?->format('c'),
                ];
            }

            if ($booking->hasIssue()) {
                $data['issue'] = [
                    'type' => $booking->getIssueType(),
                    'description' => $booking->getIssueDescription(),
                    'reportedAt' => $booking->getIssueReportedAt()?->format('c'),
                ];
            }

            // Payment info
            if ($booking->getPayment()) {
                $payment = $booking->getPayment();
                $data['payment'] = [
                    'id' => $payment->getId(),
                    'status' => $payment->getStatus(),
                    'method' => $payment->getMethod(),
                    'paidAt' => $payment->getPaidAt()?->format('c'),
                ];
            }
        }

        return $data;
    }
}