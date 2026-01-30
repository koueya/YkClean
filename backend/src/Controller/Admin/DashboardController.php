<?php

namespace App\Controller\Admin;

use App\Repository\User\UserRepository;
use App\Repository\User\ClientRepository;
use App\Repository\User\PrestataireRepository;
use App\Repository\Booking\BookingRepository;
use App\Repository\Payment\PaymentRepository;
use App\Repository\Payment\InvoiceRepository;
use App\Repository\Service\ServiceCategoryRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Enum\BookingStatus;
use App\Enum\PaymentStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private ClientRepository $clientRepository,
        private PrestataireRepository $prestataireRepository,
        private BookingRepository $bookingRepository,
        private PaymentRepository $paymentRepository,
        private InvoiceRepository $invoiceRepository,
        private ServiceCategoryRepository $categoryRepository,
        private ServiceRequestRepository $serviceRequestRepository
    ) {
    }

    #[Route('/dashboard', name: 'admin_dashboard')]
    public function index(): Response
    {
        // Statistiques générales
        $stats = $this->getGlobalStats();

        // Réservations récentes
        $recentBookings = $this->getRecentBookings();

        // Activités récentes
        $recentActivities = $this->getRecentActivities();

        // Données pour les graphiques
        $bookingsChartData = $this->getBookingsChartData();
        $categoriesChartData = $this->getCategoriesChartData();

        // Nouveaux utilisateurs cette semaine
        $newUsersThisWeek = $this->getNewUsersThisWeek();

        // Prestataires en attente d'approbation
        $pendingPrestataires = $this->getPendingPrestataires();

        // Paiements en attente
        $pendingPayments = $this->getPendingPayments();

        return $this->render('admin/dashboard.html.twig', [
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'recentActivities' => $recentActivities,
            'bookingsChartData' => $bookingsChartData,
            'categoriesChartData' => $categoriesChartData,
            'newUsersThisWeek' => $newUsersThisWeek,
            'pendingPrestataires' => $pendingPrestataires,
            'pendingPayments' => $pendingPayments,
        ]);
    }

    /**
     * Récupère les statistiques globales
     */
    private function getGlobalStats(): array
    {
        $now = new \DateTime();
        $lastMonth = (clone $now)->modify('-1 month');

        // Total utilisateurs
        $totalUsers = $this->userRepository->count([]);
        $usersLastMonth = $this->userRepository->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.createdAt >= :lastMonth')
            ->setParameter('lastMonth', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult();
        $usersGrowth = $this->calculateGrowthPercentage($totalUsers, $usersLastMonth);

        // Total clients
        $totalClients = $this->clientRepository->count([]);

        // Total prestataires
        $totalPrestataires = $this->prestataireRepository->count([]);
        $prestatairesPending = $this->prestataireRepository->count(['isApproved' => false]);
        $prestataireLastMonth = $this->prestataireRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.createdAt >= :lastMonth')
            ->setParameter('lastMonth', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult();
        $prestatairesGrowth = $this->calculateGrowthPercentage($totalPrestataires, $prestataireLastMonth);

        // Total réservations
        $totalBookings = $this->bookingRepository->count([]);
        $bookingsLastMonth = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.createdAt >= :lastMonth')
            ->setParameter('lastMonth', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult();
        $bookingsGrowth = $this->calculateGrowthPercentage($totalBookings, $bookingsLastMonth);

        // Réservations par statut
        $bookingsConfirmed = $this->bookingRepository->count(['status' => BookingStatus::CONFIRMED->value]);
        $bookingsInProgress = $this->bookingRepository->count(['status' => BookingStatus::IN_PROGRESS->value]);
        $bookingsCompleted = $this->bookingRepository->count(['status' => BookingStatus::COMPLETED->value]);
        $bookingsCancelled = $this->bookingRepository->count(['status' => BookingStatus::CANCELLED->value]);

        // Revenus totaux
        $totalRevenue = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', PaymentStatus::PAID->value)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $revenueLastMonth = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :lastMonth')
            ->setParameter('status', PaymentStatus::PAID->value)
            ->setParameter('lastMonth', $lastMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        $revenueGrowth = $this->calculateGrowthPercentage($totalRevenue, $revenueLastMonth);

        // Revenus ce mois
        $firstDayOfMonth = new \DateTime('first day of this month');
        $revenueThisMonth = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->andWhere('p.createdAt >= :firstDay')
            ->setParameter('status', PaymentStatus::PAID->value)
            ->setParameter('firstDay', $firstDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Commission moyenne
        $averageCommission = $this->calculateAverageCommission();

        // Factures impayées
        $unpaidInvoices = $this->invoiceRepository->count(['status' => 'sent']);
        $overdueInvoices = $this->invoiceRepository->count(['status' => 'overdue']);

        // Taux de conversion (demandes -> réservations)
        $totalServiceRequests = $this->serviceRequestRepository->count([]);
        $conversionRate = $totalServiceRequests > 0 
            ? round(($totalBookings / $totalServiceRequests) * 100, 1) 
            : 0;

        // Note moyenne des prestataires
        $averageRating = $this->prestataireRepository->createQueryBuilder('p')
            ->select('AVG(p.averageRating)')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'totalUsers' => $totalUsers,
            'usersGrowth' => $usersGrowth,
            'totalClients' => $totalClients,
            'totalPrestataires' => $totalPrestataires,
            'prestatairesPending' => $prestatairesPending,
            'prestatairesGrowth' => $prestatairesGrowth,
            'totalBookings' => $totalBookings,
            'bookingsGrowth' => $bookingsGrowth,
            'bookingsConfirmed' => $bookingsConfirmed,
            'bookingsInProgress' => $bookingsInProgress,
            'bookingsCompleted' => $bookingsCompleted,
            'bookingsCancelled' => $bookingsCancelled,
            'totalRevenue' => number_format($totalRevenue, 2, '.', ''),
            'revenueGrowth' => $revenueGrowth,
            'revenueThisMonth' => number_format($revenueThisMonth, 2, '.', ''),
            'averageCommission' => $averageCommission,
            'unpaidInvoices' => $unpaidInvoices,
            'overdueInvoices' => $overdueInvoices,
            'conversionRate' => $conversionRate,
            'averageRating' => round($averageRating, 1),
        ];
    }

    /**
     * Récupère les réservations récentes
     */
    private function getRecentBookings(int $limit = 10): array
    {
        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.prestataire', 'p')
            ->leftJoin('b.serviceRequest', 'sr')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('c', 'p', 'sr', 'cat')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(function ($booking) {
            return [
                'id' => $booking->getId(),
                'client' => [
                    'id' => $booking->getClient()->getId(),
                    'fullName' => $booking->getClient()->getFullName(),
                ],
                'prestataire' => [
                    'id' => $booking->getPrestataire()->getId(),
                    'fullName' => $booking->getPrestataire()->getFullName(),
                ],
                'service' => $booking->getServiceRequest()?->getCategory()?->getName() ?? 'Service',
                'date' => $booking->getScheduledDate(),
                'amount' => $booking->getAmount(),
                'status' => BookingStatus::from($booking->getStatus())->label(),
                'statusColor' => $this->getStatusColor($booking->getStatus()),
                'createdAt' => $booking->getCreatedAt(),
            ];
        }, $bookings);
    }

    /**
     * Récupère les activités récentes
     */
    private function getRecentActivities(int $limit = 10): array
    {
        $activities = [];

        // Derniers utilisateurs inscrits
        $recentUsers = $this->userRepository->createQueryBuilder('u')
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        foreach ($recentUsers as $user) {
            $activities[] = [
                'title' => 'Nouvel utilisateur inscrit',
                'description' => $user->getFullName() . ' s\'est inscrit',
                'time' => $this->getRelativeTime($user->getCreatedAt()),
                'icon' => 'user-plus',
                'color' => 'primary',
            ];
        }

        // Dernières réservations
        $recentBookings = $this->bookingRepository->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults(3)
            ->getQuery()
            ->getResult();

        foreach ($recentBookings as $booking) {
            $activities[] = [
                'title' => 'Nouvelle réservation',
                'description' => 'Réservation #' . $booking->getId() . ' créée',
                'time' => $this->getRelativeTime($booking->getCreatedAt()),
                'icon' => 'calendar-check',
                'color' => 'success',
            ];
        }

        // Derniers paiements
        $recentPayments = $this->paymentRepository->createQueryBuilder('p')
            ->where('p.status = :status')
            ->setParameter('status', PaymentStatus::PAID->value)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(2)
            ->getQuery()
            ->getResult();

        foreach ($recentPayments as $payment) {
            $activities[] = [
                'title' => 'Paiement reçu',
                'description' => 'Paiement de ' . $payment->getAmount() . '€',
                'time' => $this->getRelativeTime($payment->getCreatedAt()),
                'icon' => 'credit-card',
                'color' => 'info',
            ];
        }

        // Trier par date
        usort($activities, function ($a, $b) {
            return $b['time'] <=> $a['time'];
        });

        return array_slice($activities, 0, $limit);
    }

    /**
     * Données pour le graphique des réservations
     */
    private function getBookingsChartData(): array
    {
        $currentYear = date('Y');
        $data = [];

        for ($month = 1; $month <= 12; $month++) {
            $startDate = new \DateTime("$currentYear-$month-01");
            $endDate = (clone $startDate)->modify('last day of this month');

            $count = $this->bookingRepository->createQueryBuilder('b')
                ->select('COUNT(b.id)')
                ->where('b.createdAt >= :startDate')
                ->andWhere('b.createdAt <= :endDate')
                ->setParameter('startDate', $startDate)
                ->setParameter('endDate', $endDate)
                ->getQuery()
                ->getSingleScalarResult();

            $data[] = (int) $count;
        }

        return [
            'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Jun', 'Jul', 'Aoû', 'Sep', 'Oct', 'Nov', 'Déc'],
            'data' => $data,
        ];
    }

    /**
     * Données pour le graphique des catégories
     */
    private function getCategoriesChartData(): array
    {
        $categories = $this->categoryRepository->findMostRequested(5);

        $labels = [];
        $data = [];

        foreach ($categories as $category) {
            $labels[] = $category->getName();
            $data[] = $category->getRequestCount();
        }

        return [
            'labels' => $labels,
            'data' => $data,
        ];
    }

    /**
     * Nouveaux utilisateurs cette semaine
     */
    private function getNewUsersThisWeek(): array
    {
        $startOfWeek = new \DateTime('monday this week');

        return $this->userRepository->createQueryBuilder('u')
            ->where('u.createdAt >= :startOfWeek')
            ->setParameter('startOfWeek', $startOfWeek)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Prestataires en attente d'approbation
     */
    private function getPendingPrestataires(): array
    {
        return $this->prestataireRepository->findBy(
            ['isApproved' => false],
            ['createdAt' => 'DESC'],
            5
        );
    }

    /**
     * Paiements en attente
     */
    private function getPendingPayments(): array
    {
        return $this->paymentRepository->findBy(
            ['status' => PaymentStatus::PENDING->value],
            ['createdAt' => 'DESC'],
            5
        );
    }

    /**
     * Calcule le pourcentage de croissance
     */
    private function calculateGrowthPercentage(float $total, float $lastPeriod): float
    {
        if ($total == 0) {
            return 0;
        }

        $previousPeriod = $total - $lastPeriod;
        
        if ($previousPeriod == 0) {
            return $lastPeriod > 0 ? 100 : 0;
        }

        return round((($lastPeriod - $previousPeriod) / $previousPeriod) * 100, 1);
    }

    /**
     * Calcule la commission moyenne
     */
    private function calculateAverageCommission(): float
    {
        // Supposons une commission de 15% sur chaque transaction
        $totalRevenue = $this->paymentRepository->createQueryBuilder('p')
            ->select('SUM(p.amount)')
            ->where('p.status = :status')
            ->setParameter('status', PaymentStatus::PAID->value)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return round($totalRevenue * 0.15, 2);
    }

    /**
     * Obtient la couleur du statut
     */
    private function getStatusColor(string $status): string
    {
        return match ($status) {
            BookingStatus::PENDING->value => 'warning',
            BookingStatus::CONFIRMED->value => 'info',
            BookingStatus::IN_PROGRESS->value => 'primary',
            BookingStatus::COMPLETED->value => 'success',
            BookingStatus::CANCELLED->value => 'danger',
            BookingStatus::NO_SHOW->value => 'secondary',
            default => 'secondary',
        };
    }

    /**
     * Obtient le temps relatif
     */
    private function getRelativeTime(\DateTimeInterface $date): string
    {
        $now = new \DateTime();
        $diff = $now->diff($date);

        if ($diff->y > 0) {
            return $diff->y . ' an' . ($diff->y > 1 ? 's' : '');
        }
        if ($diff->m > 0) {
            return $diff->m . ' mois';
        }
        if ($diff->d > 0) {
            return 'Il y a ' . $diff->d . ' jour' . ($diff->d > 1 ? 's' : '');
        }
        if ($diff->h > 0) {
            return 'Il y a ' . $diff->h . ' heure' . ($diff->h > 1 ? 's' : '');
        }
        if ($diff->i > 0) {
            return 'Il y a ' . $diff->i . ' min';
        }

        return 'À l\'instant';
    }

    /**
     * Export des statistiques en JSON
     */
    #[Route('/dashboard/stats/export', name: 'admin_dashboard_stats_export', methods: ['GET'])]
    public function exportStats(): Response
    {
        $stats = $this->getGlobalStats();

        return $this->json($stats);
    }

    /**
     * Données en temps réel pour AJAX
     */
    #[Route('/dashboard/realtime', name: 'admin_dashboard_realtime', methods: ['GET'])]
    public function realtimeData(): Response
    {
        return $this->json([
            'pendingBookings' => $this->bookingRepository->count(['status' => BookingStatus::PENDING->value]),
            'pendingPayments' => $this->paymentRepository->count(['status' => PaymentStatus::PENDING->value]),
            'pendingPrestataires' => $this->prestataireRepository->count(['isApproved' => false]),
            'recentActivities' => array_slice($this->getRecentActivities(), 0, 5),
            'timestamp' => time(),
        ]);
    }
}