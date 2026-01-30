<?php

namespace App\MessageHandler;

use App\Message\UpdateStatisticsMessage;
use App\Repository\User\PrestataireRepository;
use App\Repository\User\ClientRepository;
use App\Repository\Booking\BookingRepository;
use App\Repository\Payment\PaymentRepository;
use App\Repository\Payment\CommissionRepository;
use App\Repository\Rating\ReviewRepository;
use App\Repository\Service\ServiceCategoryRepository;
use App\Repository\Quote\QuoteRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class UpdateStatisticsHandler
{
    private EntityManagerInterface $entityManager;
    private PrestataireRepository $prestataireRepository;
    private ClientRepository $clientRepository;
    private BookingRepository $bookingRepository;
    private PaymentRepository $paymentRepository;
    private CommissionRepository $commissionRepository;
    private ReviewRepository $reviewRepository;
    private ServiceCategoryRepository $categoryRepository;
    private QuoteRepository $quoteRepository;
    private LoggerInterface $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        PrestataireRepository $prestataireRepository,
        ClientRepository $clientRepository,
        BookingRepository $bookingRepository,
        PaymentRepository $paymentRepository,
        CommissionRepository $commissionRepository,
        ReviewRepository $reviewRepository,
        ServiceCategoryRepository $categoryRepository,
        QuoteRepository $quoteRepository,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->prestataireRepository = $prestataireRepository;
        $this->clientRepository = $clientRepository;
        $this->bookingRepository = $bookingRepository;
        $this->paymentRepository = $paymentRepository;
        $this->commissionRepository = $commissionRepository;
        $this->reviewRepository = $reviewRepository;
        $this->categoryRepository = $categoryRepository;
        $this->quoteRepository = $quoteRepository;
        $this->logger = $logger;
    }

    /**
     * Traiter le message de mise à jour des statistiques
     */
    public function __invoke(UpdateStatisticsMessage $message): void
    {
        $statisticType = $message->getStatisticType();
        $entityId = $message->getEntityId();
        $metadata = $message->getMetadata();

        $this->logger->info('Processing statistics update', [
            'type' => $statisticType,
            'entity_id' => $entityId,
        ]);

        try {
            match ($statisticType) {
                UpdateStatisticsMessage::TYPE_PLATFORM_GLOBAL => $this->updatePlatformStats($metadata),
                UpdateStatisticsMessage::TYPE_PRESTATAIRE_STATS => $this->updatePrestataireStats($entityId, $metadata),
                UpdateStatisticsMessage::TYPE_CLIENT_STATS => $this->updateClientStats($entityId, $metadata),
                UpdateStatisticsMessage::TYPE_CATEGORY_STATS => $this->updateCategoryStats($entityId, $metadata),
                UpdateStatisticsMessage::TYPE_REVENUE_STATS => $this->updateRevenueStats($metadata),
                UpdateStatisticsMessage::TYPE_BOOKING_STATS => $this->updateBookingStats($metadata),
                UpdateStatisticsMessage::TYPE_RATING_STATS => $this->updateRatingStats($entityId, $metadata),
                UpdateStatisticsMessage::TYPE_RESPONSE_RATE => $this->updateResponseRate($entityId, $metadata),
                default => throw new \InvalidArgumentException('Unknown statistic type: ' . $statisticType),
            };

            $this->logger->info('Statistics update completed', [
                'type' => $statisticType,
                'entity_id' => $entityId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update statistics', [
                'type' => $statisticType,
                'entity_id' => $entityId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Mettre à jour les statistiques globales de la plateforme
     */
    private function updatePlatformStats(array $metadata): void
    {
        $this->logger->info('Updating platform statistics');

        $stats = [
            'total_prestataires' => $this->prestataireRepository->count(['isActive' => true]),
            'total_clients' => $this->clientRepository->count([]),
            'total_bookings' => $this->bookingRepository->count([]),
            'completed_bookings' => $this->bookingRepository->count(['status' => 'completed']),
            'cancelled_bookings' => $this->bookingRepository->count(['status' => 'cancelled']),
            'total_revenue' => $this->calculateTotalRevenue(),
            'total_commissions' => $this->calculateTotalCommissions(),
            'average_rating' => $this->calculateAverageRating(),
            'active_bookings' => $this->bookingRepository->count(['status' => 'confirmed']),
        ];

        // Calculer le taux de complétion
        if ($stats['total_bookings'] > 0) {
            $stats['completion_rate'] = ($stats['completed_bookings'] / $stats['total_bookings']) * 100;
        } else {
            $stats['completion_rate'] = 0;
        }

        // Calculer le taux d'annulation
        if ($stats['total_bookings'] > 0) {
            $stats['cancellation_rate'] = ($stats['cancelled_bookings'] / $stats['total_bookings']) * 100;
        } else {
            $stats['cancellation_rate'] = 0;
        }

        $this->logger->info('Platform statistics calculated', $stats);

        // Enregistrer dans une table de statistiques ou cache
        // $this->saveStatistics('platform', null, $stats);
    }

    /**
     * Mettre à jour les statistiques d'un prestataire
     */
    private function updatePrestataireStats(int $prestataireId, array $metadata): void
    {
        $prestataire = $this->prestataireRepository->find($prestataireId);

        if (!$prestataire) {
            throw new \RuntimeException('Prestataire not found: ' . $prestataireId);
        }

        $this->logger->info('Updating prestataire statistics', [
            'prestataire_id' => $prestataireId,
        ]);

        // Mettre à jour le nombre de réservations complétées
        $completedBookings = $this->bookingRepository->count([
            'prestataire' => $prestataire,
            'status' => 'completed',
        ]);
        $prestataire->setCompletedBookingsCount($completedBookings);

        // Mettre à jour la note moyenne
        $this->updatePrestataireRating($prestataire);

        // Mettre à jour le taux de réponse
        $this->updatePrestataireResponseRate($prestataire);

        // Calculer les revenus
        $earnings = $this->calculatePrestataireEarnings($prestataire);
        $prestataire->setTotalEarnings($earnings['total']);

        // Mettre à jour le taux d'annulation
        $totalBookings = $this->bookingRepository->count(['prestataire' => $prestataire]);
        $cancelledBookings = $this->bookingRepository->count([
            'prestataire' => $prestataire,
            'status' => 'cancelled',
        ]);

        if ($totalBookings > 0) {
            $cancellationRate = ($cancelledBookings / $totalBookings) * 100;
            $prestataire->setCancellationRate($cancellationRate);
        }

        $this->entityManager->flush();

        $this->logger->info('Prestataire statistics updated', [
            'prestataire_id' => $prestataireId,
            'completed_bookings' => $completedBookings,
            'average_rating' => $prestataire->getAverageRating(),
        ]);
    }

    /**
     * Mettre à jour les statistiques d'un client
     */
    private function updateClientStats(int $clientId, array $metadata): void
    {
        $client = $this->clientRepository->find($clientId);

        if (!$client) {
            throw new \RuntimeException('Client not found: ' . $clientId);
        }

        $this->logger->info('Updating client statistics', [
            'client_id' => $clientId,
        ]);

        // Nombre de réservations
        $totalBookings = $this->bookingRepository->count(['client' => $client]);
        $completedBookings = $this->bookingRepository->count([
            'client' => $client,
            'status' => 'completed',
        ]);

        $client->setTotalBookings($totalBookings);
        $client->setCompletedBookings($completedBookings);

        // Montant total dépensé
        $totalSpent = $this->calculateClientTotalSpent($client);
        $client->setTotalSpent($totalSpent);

        // Nombre d'avis laissés
        $reviewsCount = $this->reviewRepository->count(['client' => $client]);
        $client->setReviewsCount($reviewsCount);

        $this->entityManager->flush();

        $this->logger->info('Client statistics updated', [
            'client_id' => $clientId,
            'total_bookings' => $totalBookings,
            'total_spent' => $totalSpent,
        ]);
    }

    /**
     * Mettre à jour les statistiques d'une catégorie
     */
    private function updateCategoryStats(int $categoryId, array $metadata): void
    {
        $category = $this->categoryRepository->find($categoryId);

        if (!$category) {
            throw new \RuntimeException('Category not found: ' . $categoryId);
        }

        $this->logger->info('Updating category statistics', [
            'category_id' => $categoryId,
        ]);

        // Compter les réservations par catégorie
        $bookingsCount = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->join('b.serviceRequest', 'sr')
            ->where('sr.category = :category')
            ->setParameter('category', $category)
            ->getQuery()
            ->getSingleScalarResult();

        // Revenus par catégorie
        $revenue = $this->bookingRepository->createQueryBuilder('b')
            ->select('SUM(b.amount)')
            ->join('b.serviceRequest', 'sr')
            ->where('sr.category = :category')
            ->andWhere('b.paymentStatus = :status')
            ->setParameter('category', $category)
            ->setParameter('status', 'paid')
            ->getQuery()
            ->getSingleScalarResult();

        $category->setTotalBookings((int)$bookingsCount);
        $category->setTotalRevenue((float)$revenue ?? 0);

        // Nombre de prestataires actifs dans cette catégorie
        $activePrestataireCount = $this->prestataireRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->join('p.serviceCategories', 'c')
            ->where('c.id = :categoryId')
            ->andWhere('p.isActive = :active')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('active', true)
            ->getQuery()
            ->getSingleScalarResult();

        $category->setActivePrestataireCount((int)$activePrestataireCount);

        $this->entityManager->flush();

        $this->logger->info('Category statistics updated', [
            'category_id' => $categoryId,
            'bookings_count' => $bookingsCount,
            'revenue' => $revenue,
        ]);
    }

    /**
     * Mettre à jour les statistiques de revenus
     */
    private function updateRevenueStats(array $metadata): void
    {
        $this->logger->info('Updating revenue statistics');

        $period = $metadata['period'] ?? 'all_time';
        $dateFilter = $this->getDateFilterForPeriod($period);

        $qb = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount) as total_revenue, COUNT(p.id) as payment_count')
            ->where('p.status = :status')
            ->setParameter('status', 'completed');

        if ($dateFilter) {
            $qb->andWhere('p.paidAt >= :startDate')
                ->setParameter('startDate', $dateFilter);
        }

        $result = $qb->getQuery()->getSingleResult();

        $stats = [
            'total_revenue' => (float)$result['total_revenue'] ?? 0,
            'payment_count' => (int)$result['payment_count'] ?? 0,
            'period' => $period,
        ];

        // Calculer les commissions
        $commissionQb = $this->commissionRepository->createQueryBuilder('c')
            ->select('SUM(c.commissionAmount) as total_commission')
            ->where('c.status = :status')
            ->setParameter('status', 'transferred');

        if ($dateFilter) {
            $commissionQb->andWhere('c.transferredAt >= :startDate')
                ->setParameter('startDate', $dateFilter);
        }

        $commissionResult = $commissionQb->getQuery()->getSingleResult();
        $stats['total_commission'] = (float)$commissionResult['total_commission'] ?? 0;

        $this->logger->info('Revenue statistics calculated', $stats);
    }

    /**
     * Mettre à jour les statistiques de réservations
     */
    private function updateBookingStats(array $metadata): void
    {
        $this->logger->info('Updating booking statistics');

        $period = $metadata['period'] ?? 'month';
        $dateFilter = $this->getDateFilterForPeriod($period);

        $qb = $this->bookingRepository->createQueryBuilder('b')
            ->select('b.status, COUNT(b.id) as count')
            ->groupBy('b.status');

        if ($dateFilter) {
            $qb->where('b.createdAt >= :startDate')
                ->setParameter('startDate', $dateFilter);
        }

        $results = $qb->getQuery()->getResult();

        $stats = [
            'period' => $period,
            'by_status' => [],
        ];

        foreach ($results as $result) {
            $stats['by_status'][$result['status']] = $result['count'];
        }

        $this->logger->info('Booking statistics calculated', $stats);
    }

    /**
     * Mettre à jour les statistiques de notation
     */
    private function updateRatingStats(?int $prestataireId, array $metadata): void
    {
        if ($prestataireId) {
            $prestataire = $this->prestataireRepository->find($prestataireId);
            if ($prestataire) {
                $this->updatePrestataireRating($prestataire);
            }
        } else {
            // Mettre à jour toutes les notes
            $this->logger->info('Updating all rating statistics');
            
            $avgRating = $this->reviewRepository->createQueryBuilder('r')
                ->select('AVG(r.rating)')
                ->getQuery()
                ->getSingleScalarResult();

            $this->logger->info('Global average rating calculated', [
                'average_rating' => $avgRating,
            ]);
        }
    }

    /**
     * Mettre à jour le taux de réponse d'un prestataire
     */
    private function updateResponseRate(int $prestataireId, array $metadata): void
    {
        $prestataire = $this->prestataireRepository->find($prestataireId);

        if (!$prestataire) {
            throw new \RuntimeException('Prestataire not found: ' . $prestataireId);
        }

        $this->updatePrestataireResponseRate($prestataire);
    }

    /**
     * Mettre à jour la note moyenne d'un prestataire
     */
    private function updatePrestataireRating($prestataire): void
    {
        $avgRating = $this->reviewRepository->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        $reviewCount = $this->reviewRepository->count(['prestataire' => $prestataire]);

        $prestataire->setAverageRating((float)$avgRating ?? 0);
        $prestataire->setReviewCount($reviewCount);

        $this->entityManager->flush();
    }

    /**
     * Mettre à jour le taux de réponse d'un prestataire
     */
    private function updatePrestataireResponseRate($prestataire): void
    {
        // Nombre de demandes de devis reçues
        $quotesReceived = $this->quoteRepository->createQueryBuilder('q')
            ->select('COUNT(DISTINCT sr.id)')
            ->join('q.serviceRequest', 'sr')
            ->where('q.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        // Nombre de devis envoyés
        $quotesAnswered = $this->quoteRepository->count([
            'prestataire' => $prestataire,
        ]);

        if ($quotesReceived > 0) {
            $responseRate = ($quotesAnswered / $quotesReceived);
            $prestataire->setResponseRate($responseRate);
        } else {
            $prestataire->setResponseRate(0);
        }

        $this->entityManager->flush();
    }

    /**
     * Calculer les revenus d'un prestataire
     */
    private function calculatePrestataireEarnings($prestataire): array
    {
        $result = $this->commissionRepository->createQueryBuilder('c')
            ->select('SUM(c.prestataireAmount) as total, SUM(c.commissionAmount) as commission')
            ->where('c.prestataire = :prestataire')
            ->andWhere('c.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'transferred')
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (float)$result['total'] ?? 0,
            'commission_paid' => (float)$result['commission'] ?? 0,
        ];
    }

    /**
     * Calculer le montant total dépensé par un client
     */
    private function calculateClientTotalSpent($client): float
    {
        $result = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->join('p.booking', 'b')
            ->where('b.client = :client')
            ->andWhere('p.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result ?? 0;
    }

    /**
     * Calculer le revenu total de la plateforme
     */
    private function calculateTotalRevenue(): float
    {
        $result = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result ?? 0;
    }

    /**
     * Calculer le total des commissions de la plateforme
     */
    private function calculateTotalCommissions(): float
    {
        $result = $this->commissionRepository->createQueryBuilder('c')
            ->select('SUM(c.commissionAmount)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result ?? 0;
    }

    /**
     * Calculer la note moyenne globale
     */
    private function calculateAverageRating(): float
    {
        $result = $this->reviewRepository->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->getQuery()
            ->getSingleScalarResult();

        return (float)$result ?? 0;
    }

    /**
     * Obtenir le filtre de date pour une période
     */
    private function getDateFilterForPeriod(string $period): ?\DateTime
    {
        return match ($period) {
            'today' => new \DateTime('today'),
            'week' => new \DateTime('-7 days'),
            'month' => new \DateTime('-30 days'),
            'quarter' => new \DateTime('-90 days'),
            'year' => new \DateTime('-365 days'),
            default => null,
        };
    }
}