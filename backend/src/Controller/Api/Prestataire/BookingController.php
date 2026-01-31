<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\Booking\Booking;
use App\Entity\User\Prestataire;
use App\Enum\BookingStatus;
use App\Repository\Booking\BookingRepository;
use App\Security\Voter\PrestataireVoter;
use App\Service\Booking\BookingService;
use App\Service\Notification\NotificationService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/bookings', name: 'api_prestataire_booking_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class BookingController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private BookingRepository $bookingRepository,
        private BookingService $bookingService,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Liste toutes les réservations du prestataire
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        // Filtres
        $status = $request->query->get('status');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.serviceRequest', 'sr')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('c', 'sr', 'cat')
            ->where('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        // Filtrer par statut
        if ($status) {
            $statuses = explode(',', $status);
            $qb->andWhere('b.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        // Filtrer par période
        if ($startDate) {
            $qb->andWhere('b.scheduledDate >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $qb->andWhere('b.scheduledDate <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        // Pagination
        $total = count($qb->getQuery()->getResult());
        $bookings = $qb->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $bookings,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['booking:read', 'booking:list']]);
    }

    /**
     * Récupère les réservations à venir
     */
    #[Route('/upcoming', name: 'upcoming', methods: ['GET'])]
    public function upcoming(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.serviceRequest', 'sr')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('c', 'sr', 'cat')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere('b.scheduledDate >= :today')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('statuses', [
                BookingStatus::PENDING->value,
                BookingStatus::CONFIRMED->value,
                BookingStatus::IN_PROGRESS->value,
            ])
            ->setParameter('today', new \DateTime('today'))
            ->orderBy('b.scheduledDate', 'ASC')
            ->addOrderBy('b.scheduledTime', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $bookings,
        ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
    }

    /**
     * Récupère les réservations du jour
     */
    #[Route('/today', name: 'today', methods: ['GET'])]
    public function today(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $today = new \DateTime('today');

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.serviceRequest', 'sr')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('c', 'sr', 'cat')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate = :today')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->setParameter('statuses', [
                BookingStatus::CONFIRMED->value,
                BookingStatus::IN_PROGRESS->value,
            ])
            ->orderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $bookings,
        ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
    }

    /**
     * Récupère les réservations en attente d'acceptation
     */
    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.serviceRequest', 'sr')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('c', 'sr', 'cat')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', BookingStatus::PENDING->value)
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $bookings,
            'count' => count($bookings),
        ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
    }

    /**
     * Récupère l'historique des réservations
     */
    #[Route('/history', name: 'history', methods: ['GET'])]
    public function history(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.serviceRequest', 'sr')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('c', 'sr', 'cat')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('statuses', [
                BookingStatus::COMPLETED->value,
                BookingStatus::CANCELLED->value,
                BookingStatus::NO_SHOW->value,
            ]);

        $total = count($qb->getQuery()->getResult());
        $bookings = $qb->orderBy('b.scheduledDate', 'DESC')
            ->addOrderBy('b.scheduledTime', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $bookings,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['booking:read', 'booking:list']]);
    }

    /**
     * Affiche le détail d'une réservation
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::VIEW_BOOKING, $booking);

        return $this->json([
            'success' => true,
            'data' => $booking,
        ], Response::HTTP_OK, [], ['groups' => ['booking:read', 'booking:detail']]);
    }

    /**
     * Accepte une réservation
     */
    #[Route('/{id}/accept', name: 'accept', methods: ['POST'])]
    public function accept(Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::ACCEPT_BOOKING, $booking);

        if ($booking->getStatus() !== BookingStatus::PENDING->value) {
            return $this->json([
                'success' => false,
                'message' => 'Cette réservation ne peut pas être acceptée',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->bookingService->acceptBooking($booking);

            $this->logger->info('Booking accepted', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $this->getUser()->getId(),
            ]);

            // Notifier le client
            $this->notificationService->notifyBookingAccepted($booking);

            return $this->json([
                'success' => true,
                'message' => 'Réservation acceptée avec succès',
                'data' => $booking,
            ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to accept booking', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'acceptation de la réservation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Refuse une réservation
     */
    #[Route('/{id}/reject', name: 'reject', methods: ['POST'])]
    public function reject(Request $request, Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::CANCEL_BOOKING, $booking);

        if ($booking->getStatus() !== BookingStatus::PENDING->value) {
            return $this->json([
                'success' => false,
                'message' => 'Cette réservation ne peut pas être refusée',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        try {
            $this->bookingService->rejectBooking($booking, $reason);

            $this->logger->info('Booking rejected', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $this->getUser()->getId(),
                'reason' => $reason,
            ]);

            // Notifier le client
            $this->notificationService->notifyBookingRejected($booking, $reason);

            return $this->json([
                'success' => true,
                'message' => 'Réservation refusée',
                'data' => $booking,
            ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to reject booking', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du refus de la réservation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Démarre une réservation (check-in)
     */
    #[Route('/{id}/start', name: 'start', methods: ['POST'])]
    public function start(Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::START_BOOKING, $booking);

        if ($booking->getStatus() !== BookingStatus::CONFIRMED->value) {
            return $this->json([
                'success' => false,
                'message' => 'Cette réservation ne peut pas être démarrée',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->bookingService->startBooking($booking);

            $this->logger->info('Booking started', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $this->getUser()->getId(),
            ]);

            // Notifier le client
            $this->notificationService->notifyBookingStarted($booking);

            return $this->json([
                'success' => true,
                'message' => 'Service démarré',
                'data' => $booking,
            ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to start booking', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du démarrage du service',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Termine une réservation (check-out)
     */
    #[Route('/{id}/complete', name: 'complete', methods: ['POST'])]
    public function complete(Request $request, Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::COMPLETE_BOOKING, $booking);

        if ($booking->getStatus() !== BookingStatus::IN_PROGRESS->value) {
            return $this->json([
                'success' => false,
                'message' => 'Cette réservation ne peut pas être terminée',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $completionNotes = $data['completion_notes'] ?? null;
        $actualDuration = $data['actual_duration'] ?? null;

        try {
            $this->bookingService->completeBooking($booking, $completionNotes, $actualDuration);

            $this->logger->info('Booking completed', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $this->getUser()->getId(),
            ]);

            // Notifier le client
            $this->notificationService->notifyBookingCompleted($booking);

            return $this->json([
                'success' => true,
                'message' => 'Service terminé avec succès',
                'data' => $booking,
            ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to complete booking', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la finalisation du service',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Annule une réservation
     */
    #[Route('/{id}/cancel', name: 'cancel', methods: ['POST'])]
    public function cancel(Request $request, Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::CANCEL_BOOKING, $booking);

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        if (!$reason) {
            return $this->json([
                'success' => false,
                'message' => 'Une raison d\'annulation est requise',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->bookingService->cancelBooking($booking, $reason, 'prestataire');

            $this->logger->info('Booking cancelled by prestataire', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $this->getUser()->getId(),
                'reason' => $reason,
            ]);

            // Notifier le client
            $this->notificationService->notifyBookingCancelled($booking, $reason);

            return $this->json([
                'success' => true,
                'message' => 'Réservation annulée',
                'data' => $booking,
            ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel booking', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Propose une nouvelle date/heure
     */
    #[Route('/{id}/reschedule', name: 'reschedule', methods: ['POST'])]
    public function reschedule(Request $request, Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::VIEW_BOOKING, $booking);

        if (!in_array($booking->getStatus(), [
            BookingStatus::PENDING->value,
            BookingStatus::CONFIRMED->value,
        ])) {
            return $this->json([
                'success' => false,
                'message' => 'Cette réservation ne peut pas être reprogrammée',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['proposed_date']) || !isset($data['proposed_time'])) {
            return $this->json([
                'success' => false,
                'message' => 'Date et heure proposées requises',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $proposedDate = new \DateTime($data['proposed_date']);
            $proposedTime = new \DateTime($data['proposed_time']);
            $reason = $data['reason'] ?? null;

            $this->bookingService->proposeReschedule(
                $booking,
                $proposedDate,
                $proposedTime,
                $reason
            );

            $this->logger->info('Reschedule proposed', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $this->getUser()->getId(),
                'proposed_date' => $proposedDate->format('Y-m-d'),
                'proposed_time' => $proposedTime->format('H:i'),
            ]);

            // Notifier le client
            $this->notificationService->notifyRescheduleProposed($booking, $proposedDate, $proposedTime);

            return $this->json([
                'success' => true,
                'message' => 'Proposition de reprogrammation envoyée au client',
                'data' => $booking,
            ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to propose reschedule', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la proposition de reprogrammation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Ajoute des notes à une réservation
     */
    #[Route('/{id}/notes', name: 'add_notes', methods: ['POST', 'PUT'])]
    public function addNotes(Request $request, Booking $booking): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::VIEW_BOOKING, $booking);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['notes'])) {
            return $this->json([
                'success' => false,
                'message' => 'Les notes sont requises',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $booking->setPrestataireNotes($data['notes']);
            $this->entityManager->flush();

            $this->logger->info('Booking notes updated', [
                'booking_id' => $booking->getId(),
                'prestataire_id' => $this->getUser()->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Notes enregistrées',
                'data' => $booking,
            ], Response::HTTP_OK, [], ['groups' => ['booking:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update booking notes', [
                'booking_id' => $booking->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement des notes',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Statistiques des réservations du prestataire
     */
    #[Route('/stats/overview', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $stats = $this->bookingService->getPrestataireStats(
                $prestataire,
                $startDate ? new \DateTime($startDate) : null,
                $endDate ? new \DateTime($endDate) : null
            );

            return $this->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get prestataire stats', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des statistiques',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revenus du prestataire
     */
    #[Route('/stats/earnings', name: 'earnings', methods: ['GET'])]
    public function earnings(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $period = $request->query->get('period', 'month'); // day, week, month, year
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $earnings = $this->bookingService->getPrestataireEarnings(
                $prestataire,
                $period,
                $startDate ? new \DateTime($startDate) : null,
                $endDate ? new \DateTime($endDate) : null
            );

            return $this->json([
                'success' => true,
                'data' => $earnings,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get prestataire earnings', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des revenus',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calendrier des réservations (vue mensuelle)
     */
    #[Route('/calendar', name: 'calendar', methods: ['GET'])]
    public function calendar(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $year = (int) $request->query->get('year', date('Y'));
        $month = (int) $request->query->get('month', date('m'));

        try {
            $firstDay = new \DateTime("$year-$month-01");
            $lastDay = (clone $firstDay)->modify('last day of this month');

            $bookings = $this->bookingRepository->createQueryBuilder('b')
                ->leftJoin('b.client', 'c')
                ->leftJoin('b.serviceRequest', 'sr')
                ->leftJoin('sr.category', 'cat')
                ->addSelect('c', 'sr', 'cat')
                ->where('b.prestataire = :prestataire')
                ->andWhere('b.scheduledDate >= :firstDay')
                ->andWhere('b.scheduledDate <= :lastDay')
                ->andWhere('b.status NOT IN (:excludedStatuses)')
                ->setParameter('prestataire', $prestataire)
                ->setParameter('firstDay', $firstDay)
                ->setParameter('lastDay', $lastDay)
                ->setParameter('excludedStatuses', [
                    BookingStatus::CANCELLED->value,
                ])
                ->orderBy('b.scheduledDate', 'ASC')
                ->addOrderBy('b.scheduledTime', 'ASC')
                ->getQuery()
                ->getResult();

            // Organiser par jour
            $calendar = [];
            foreach ($bookings as $booking) {
                $day = $booking->getScheduledDate()->format('Y-m-d');
                if (!isset($calendar[$day])) {
                    $calendar[$day] = [];
                }
                $calendar[$day][] = [
                    'id' => $booking->getId(),
                    'time' => $booking->getScheduledTime()->format('H:i'),
                    'duration' => $booking->getDuration(),
                    'client' => $booking->getClient()->getFullName(),
                    'service' => $booking->getServiceRequest()?->getCategory()?->getName(),
                    'status' => $booking->getStatus(),
                    'address' => $booking->getAddress(),
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'year' => $year,
                    'month' => $month,
                    'calendar' => $calendar,
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get calendar', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du calendrier',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}