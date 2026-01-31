<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\User\Prestataire;
use App\Enum\PaymentStatus;
use App\Repository\Payment\PaymentRepository;
use App\Repository\Booking\BookingRepository;
#use App\Repository\Payment\PayoutRepository;
#use App\Repository\Financial\PayoutRepository;
use App\Financial\Repository\PayoutRepository;
use App\Service\Payment\StripeService;
use App\Financial\Service\PrestataireEarningService as EarningsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/earnings', name: 'api_prestataire_earnings_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class EarningsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PaymentRepository $paymentRepository,
        private BookingRepository $bookingRepository,
        private PayoutRepository $payoutRepository,
        private StripeService $stripeService,
        private EarningsService $earningsService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Vue d'ensemble des revenus
     */
    #[Route('/overview', name: 'overview', methods: ['GET'])]
    public function overview(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $period = $request->query->get('period', 'month'); // day, week, month, year, all

        try {
            $overview = $this->earningsService->getEarningsOverview($prestataire, $period);

            return $this->json([
                'success' => true,
                'data' => $overview,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get earnings overview', [
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
     * Statistiques détaillées des revenus
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $start = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this month');
            $end = $endDate ? new \DateTime($endDate) : new \DateTime('last day of this month');

            $stats = $this->earningsService->getDetailedStats($prestataire, $start, $end);

            return $this->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get earnings stats', [
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
     * Historique des paiements reçus
     */
    #[Route('/payments', name: 'payments', methods: ['GET'])]
    public function payments(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $status = $request->query->get('status');

        $qb = $this->paymentRepository->createQueryBuilder('p')
            ->leftJoin('p.booking', 'b')
            ->leftJoin('b.client', 'c')
            ->leftJoin('b.serviceRequest', 'sr')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('b', 'c', 'sr', 'cat')
            ->where('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($status) {
            $statuses = explode(',', $status);
            $qb->andWhere('p.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        $total = count($qb->getQuery()->getResult());
        $payments = $qb->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $payments,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['payment:read']]);
    }

    /**
     * Revenus par période (graphique)
     */
    #[Route('/chart', name: 'chart', methods: ['GET'])]
    public function chart(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $period = $request->query->get('period', 'month'); // day, week, month, year
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $start = $startDate ? new \DateTime($startDate) : new \DateTime('-6 months');
            $end = $endDate ? new \DateTime($endDate) : new \DateTime();

            $chartData = $this->earningsService->getChartData($prestataire, $period, $start, $end);

            return $this->json([
                'success' => true,
                'data' => $chartData,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get chart data', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du graphique',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Solde Stripe disponible
     */
    #[Route('/balance', name: 'balance', methods: ['GET'])]
    public function balance(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->getStripeConnectedAccountId()) {
            return $this->json([
                'success' => false,
                'message' => 'Compte Stripe non configuré',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $balance = $this->stripeService->getAccountBalance($prestataire);

            if (!$balance) {
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible de récupérer le solde',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json([
                'success' => true,
                'data' => $balance,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get balance', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du solde',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Historique des virements (payouts)
     */
    #[Route('/payouts', name: 'payouts', methods: ['GET'])]
    public function payouts(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->payoutRepository->createQueryBuilder('p')
            ->where('p.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        $total = count($qb->getQuery()->getResult());
        $payouts = $qb->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $payouts,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['payout:read']]);
    }

    /**
     * Demande un virement (payout)
     */
    #[Route('/payouts/request', name: 'request_payout', methods: ['POST'])]
    public function requestPayout(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        // Vérifier que le compte Stripe est actif
        if ($prestataire->getStripeAccountStatus() !== 'active') {
            return $this->json([
                'success' => false,
                'message' => 'Votre compte Stripe doit être vérifié pour demander un virement',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $amount = $data['amount'] ?? null;

        if (!$amount || $amount <= 0) {
            return $this->json([
                'success' => false,
                'message' => 'Montant invalide',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Vérifier le solde disponible
            $balance = $this->stripeService->getAccountBalance($prestataire);

            if (!$balance || $balance['available'] < $amount) {
                return $this->json([
                    'success' => false,
                    'message' => 'Solde disponible insuffisant',
                    'available' => $balance['available'] ?? 0,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Créer le payout
            $payoutId = $this->stripeService->createPayout($prestataire, $amount);

            if (!$payoutId) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur lors de la création du virement',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Enregistrer le payout en base de données
            $payout = $this->earningsService->recordPayout($prestataire, $amount, $payoutId);

            $this->logger->info('Payout requested', [
                'prestataire_id' => $prestataire->getId(),
                'amount' => $amount,
                'payout_id' => $payoutId,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Demande de virement effectuée avec succès',
                'data' => $payout,
            ], Response::HTTP_CREATED, [], ['groups' => ['payout:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to request payout', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la demande de virement',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Transactions récentes
     */
    #[Route('/transactions', name: 'transactions', methods: ['GET'])]
    public function transactions(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $type = $request->query->get('type'); // payment, payout, commission

        try {
            $transactions = $this->earningsService->getRecentTransactions(
                $prestataire,
                $limit,
                $type
            );

            return $this->json([
                'success' => true,
                'data' => $transactions,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get transactions', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des transactions',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revenus par catégorie de service
     */
    #[Route('/by-category', name: 'by_category', methods: ['GET'])]
    public function byCategory(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $start = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this year');
            $end = $endDate ? new \DateTime($endDate) : new \DateTime();

            $earnings = $this->earningsService->getEarningsByCategory($prestataire, $start, $end);

            return $this->json([
                'success' => true,
                'data' => $earnings,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get earnings by category', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des revenus par catégorie',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Commissions payées à la plateforme
     */
    #[Route('/commissions', name: 'commissions', methods: ['GET'])]
    public function commissions(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $start = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this month');
            $end = $endDate ? new \DateTime($endDate) : new \DateTime('last day of this month');

            $commissions = $this->earningsService->getCommissions($prestataire, $start, $end);

            return $this->json([
                'success' => true,
                'data' => $commissions,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get commissions', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des commissions',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Revenus prévisionnels (basés sur les réservations confirmées)
     */
    #[Route('/forecast', name: 'forecast', methods: ['GET'])]
    public function forecast(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $period = $request->query->get('period', 'month'); // week, month, quarter

        try {
            $forecast = $this->earningsService->getForecast($prestataire, $period);

            return $this->json([
                'success' => true,
                'data' => $forecast,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get forecast', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des prévisions',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Comparaison des revenus (période actuelle vs période précédente)
     */
    #[Route('/comparison', name: 'comparison', methods: ['GET'])]
    public function comparison(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $period = $request->query->get('period', 'month'); // week, month, year

        try {
            $comparison = $this->earningsService->getComparison($prestataire, $period);

            return $this->json([
                'success' => true,
                'data' => $comparison,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get comparison', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la comparaison',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Export des revenus (CSV/PDF)
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $format = $request->query->get('format', 'csv'); // csv, pdf
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $start = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this month');
            $end = $endDate ? new \DateTime($endDate) : new \DateTime('last day of this month');

            if ($format === 'pdf') {
                return $this->earningsService->exportToPdf($prestataire, $start, $end);
            } else {
                return $this->earningsService->exportToCsv($prestataire, $start, $end);
            }

        } catch (\Exception $e) {
            $this->logger->error('Failed to export earnings', [
                'prestataire_id' => $prestataire->getId(),
                'format' => $format,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Taux horaire moyen effectif
     */
    #[Route('/hourly-rate', name: 'hourly_rate', methods: ['GET'])]
    public function hourlyRate(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $start = $startDate ? new \DateTime($startDate) : new \DateTime('first day of this month');
            $end = $endDate ? new \DateTime($endDate) : new \DateTime('last day of this month');

            $hourlyRate = $this->earningsService->getAverageHourlyRate($prestataire, $start, $end);

            return $this->json([
                'success' => true,
                'data' => [
                    'average' => $hourlyRate['average'],
                    'totalEarnings' => $hourlyRate['totalEarnings'],
                    'totalHours' => $hourlyRate['totalHours'],
                    'bookingsCount' => $hourlyRate['bookingsCount'],
                    'configuredRate' => $prestataire->getHourlyRate(),
                ],
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to calculate hourly rate', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du taux horaire',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifier les revenus en attente (non payés)
     */
    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $pending = $this->earningsService->getPendingEarnings($prestataire);

            return $this->json([
                'success' => true,
                'data' => $pending,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get pending earnings', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul des revenus en attente',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Dashboard financier complet
     */
    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $dashboard = [
                'overview' => $this->earningsService->getEarningsOverview($prestataire, 'month'),
                'balance' => $this->stripeService->getAccountBalance($prestataire),
                'pending' => $this->earningsService->getPendingEarnings($prestataire),
                'forecast' => $this->earningsService->getForecast($prestataire, 'month'),
                'comparison' => $this->earningsService->getComparison($prestataire, 'month'),
                'recentTransactions' => $this->earningsService->getRecentTransactions($prestataire, 5),
            ];

            return $this->json([
                'success' => true,
                'data' => $dashboard,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get dashboard', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du chargement du dashboard',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}