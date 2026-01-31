<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceRequest;
use App\Repository\Service\ServiceRequestRepository;
use App\Repository\Quote\QuoteRepository;
use App\Security\Voter\PrestataireVoter;
use App\Service\Matching\MatchingService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/service-requests', name: 'api_prestataire_service_request_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class ServiceRequestController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ServiceRequestRepository $serviceRequestRepository,
        private QuoteRepository $quoteRepository,
        private MatchingService $matchingService,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Liste des demandes de service disponibles (matching)
     */
    #[Route('/available', name: 'available', methods: ['GET'])]
    public function available(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->isApproved()) {
            return $this->json([
                'success' => false,
                'message' => 'Votre compte doit être approuvé pour voir les demandes de service',
            ], Response::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $categoryId = $request->query->get('category_id');
        $maxDistance = $request->query->get('max_distance', $prestataire->getServiceRadius());
        $sortBy = $request->query->get('sort_by', 'created_at'); // created_at, budget, distance

        try {
            // Récupérer les demandes correspondant au profil du prestataire
            $result = $this->matchingService->findMatchingRequests(
                $prestataire,
                [
                    'page' => $page,
                    'limit' => $limit,
                    'category_id' => $categoryId,
                    'max_distance' => $maxDistance,
                    'sort_by' => $sortBy,
                ]
            );

            return $this->json([
                'success' => true,
                'data' => $result['requests'],
                'meta' => [
                    'total' => $result['total'],
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => ceil($result['total'] / $limit),
                    'filters' => [
                        'categories' => $prestataire->getServiceCategories()->count(),
                        'max_distance' => $maxDistance,
                    ],
                ],
            ], Response::HTTP_OK, [], ['groups' => ['service_request:read', 'service_request:list']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get available service requests', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des demandes',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Recommandations personnalisées basées sur le profil
     */
    #[Route('/recommended', name: 'recommended', methods: ['GET'])]
    public function recommended(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->isApproved()) {
            return $this->json([
                'success' => false,
                'message' => 'Votre compte doit être approuvé pour voir les recommandations',
            ], Response::HTTP_FORBIDDEN);
        }

        $limit = min(20, max(1, (int) $request->query->get('limit', 10)));

        try {
            $recommendations = $this->matchingService->getRecommendations($prestataire, $limit);

            return $this->json([
                'success' => true,
                'data' => $recommendations,
                'count' => count($recommendations),
            ], Response::HTTP_OK, [], ['groups' => ['service_request:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get recommendations', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des recommandations',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Demandes urgentes (démarrage dans moins de 48h)
     */
    #[Route('/urgent', name: 'urgent', methods: ['GET'])]
    public function urgent(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->isApproved()) {
            return $this->json([
                'success' => false,
                'message' => 'Votre compte doit être approuvé',
            ], Response::HTTP_FORBIDDEN);
        }

        $urgentDate = (new \DateTime())->modify('+48 hours');

        $requests = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->leftJoin('sr.category', 'cat')
            ->leftJoin('sr.client', 'c')
            ->addSelect('cat', 'c')
            ->where('sr.status IN (:statuses)')
            ->andWhere('sr.preferredDate <= :urgentDate')
            ->andWhere('cat IN (:categories)')
            ->setParameter('statuses', ['open', 'quoting'])
            ->setParameter('urgentDate', $urgentDate)
            ->setParameter('categories', $prestataire->getServiceCategories())
            ->orderBy('sr.preferredDate', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        // Filtrer les demandes déjà cotées
        $urgentRequests = [];
        foreach ($requests as $request) {
            $existingQuote = $this->quoteRepository->findOneBy([
                'prestataire' => $prestataire,
                'serviceRequest' => $request,
            ]);

            if (!$existingQuote) {
                $urgentRequests[] = $request;
            }
        }

        return $this->json([
            'success' => true,
            'data' => $urgentRequests,
            'count' => count($urgentRequests),
        ], Response::HTTP_OK, [], ['groups' => ['service_request:read']]);
    }

    /**
     * Affiche une demande de service spécifique
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(ServiceRequest $serviceRequest): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PrestataireVoter::VIEW_SERVICE_REQUEST,
            $serviceRequest
        );

        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        // Vérifier si le prestataire a déjà soumis un devis
        $existingQuote = $this->quoteRepository->findOneBy([
            'prestataire' => $prestataire,
            'serviceRequest' => $serviceRequest,
        ]);

        // Calculer la distance
        $distance = null;
        if ($prestataire->getLatitude() && $prestataire->getLongitude() &&
            $serviceRequest->getLatitude() && $serviceRequest->getLongitude()) {
            $distance = $this->matchingService->calculateDistance(
                $prestataire->getLatitude(),
                $prestataire->getLongitude(),
                $serviceRequest->getLatitude(),
                $serviceRequest->getLongitude()
            );
        }

        return $this->json([
            'success' => true,
            'data' => $serviceRequest,
            'meta' => [
                'has_quoted' => $existingQuote !== null,
                'existing_quote_id' => $existingQuote?->getId(),
                'distance_km' => $distance,
                'quotes_count' => $serviceRequest->getQuotes()->count(),
                'can_quote' => $this->canQuote($prestataire, $serviceRequest),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['service_request:read', 'service_request:detail']]);
    }

    /**
     * Recherche de demandes avec filtres avancés
     */
    #[Route('/search', name: 'search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->isApproved()) {
            return $this->json([
                'success' => false,
                'message' => 'Votre compte doit être approuvé',
            ], Response::HTTP_FORBIDDEN);
        }

        $categoryId = $request->query->get('category_id');
        $minBudget = $request->query->get('min_budget');
        $maxBudget = $request->query->get('max_budget');
        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $city = $request->query->get('city');
        $postalCode = $request->query->get('postal_code');
        $frequency = $request->query->get('frequency'); // ponctuel, hebdomadaire, etc.
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->leftJoin('sr.category', 'cat')
            ->leftJoin('sr.client', 'c')
            ->addSelect('cat', 'c')
            ->where('sr.status IN (:statuses)')
            ->andWhere('cat IN (:categories)')
            ->setParameter('statuses', ['open', 'quoting'])
            ->setParameter('categories', $prestataire->getServiceCategories());

        // Filtres
        if ($categoryId) {
            $qb->andWhere('cat.id = :categoryId')
                ->setParameter('categoryId', $categoryId);
        }

        if ($minBudget) {
            $qb->andWhere('sr.budget >= :minBudget')
                ->setParameter('minBudget', $minBudget);
        }

        if ($maxBudget) {
            $qb->andWhere('sr.budget <= :maxBudget')
                ->setParameter('maxBudget', $maxBudget);
        }

        if ($startDate) {
            $qb->andWhere('sr.preferredDate >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $qb->andWhere('sr.preferredDate <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        if ($city) {
            $qb->andWhere('sr.city LIKE :city')
                ->setParameter('city', '%' . $city . '%');
        }

        if ($postalCode) {
            $qb->andWhere('sr.postalCode = :postalCode')
                ->setParameter('postalCode', $postalCode);
        }

        if ($frequency) {
            $qb->andWhere('sr.frequency = :frequency')
                ->setParameter('frequency', $frequency);
        }

        $total = count($qb->getQuery()->getResult());
        $requests = $qb->orderBy('sr.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Filtrer celles déjà cotées
        $availableRequests = [];
        foreach ($requests as $serviceRequest) {
            $existingQuote = $this->quoteRepository->findOneBy([
                'prestataire' => $prestataire,
                'serviceRequest' => $serviceRequest,
            ]);

            if (!$existingQuote) {
                $availableRequests[] = $serviceRequest;
            }
        }

        return $this->json([
            'success' => true,
            'data' => $availableRequests,
            'meta' => [
                'total' => count($availableRequests),
                'total_before_filter' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil(count($availableRequests) / $limit),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['service_request:read', 'service_request:list']]);
    }

    /**
     * Demandes par catégorie
     */
    #[Route('/by-category/{categoryId}', name: 'by_category', methods: ['GET'])]
    public function byCategory(int $categoryId, Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->isApproved()) {
            return $this->json([
                'success' => false,
                'message' => 'Votre compte doit être approuvé',
            ], Response::HTTP_FORBIDDEN);
        }

        // Vérifier que le prestataire propose cette catégorie
        $hasCategory = false;
        foreach ($prestataire->getServiceCategories() as $category) {
            if ($category->getId() === $categoryId) {
                $hasCategory = true;
                break;
            }
        }

        if (!$hasCategory) {
            return $this->json([
                'success' => false,
                'message' => 'Cette catégorie ne fait pas partie de vos services',
            ], Response::HTTP_FORBIDDEN);
        }

        $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

        $requests = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->leftJoin('sr.category', 'cat')
            ->leftJoin('sr.client', 'c')
            ->addSelect('cat', 'c')
            ->where('sr.status IN (:statuses)')
            ->andWhere('cat.id = :categoryId')
            ->setParameter('statuses', ['open', 'quoting'])
            ->setParameter('categoryId', $categoryId)
            ->orderBy('sr.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Filtrer celles déjà cotées
        $availableRequests = [];
        foreach ($requests as $request) {
            $existingQuote = $this->quoteRepository->findOneBy([
                'prestataire' => $prestataire,
                'serviceRequest' => $request,
            ]);

            if (!$existingQuote) {
                $availableRequests[] = $request;
            }
        }

        return $this->json([
            'success' => true,
            'data' => $availableRequests,
            'count' => count($availableRequests),
        ], Response::HTTP_OK, [], ['groups' => ['service_request:read']]);
    }

    /**
     * Statistiques des demandes disponibles
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->isApproved()) {
            return $this->json([
                'success' => false,
                'message' => 'Votre compte doit être approuvé',
            ], Response::HTTP_FORBIDDEN);
        }

        try {
            // Total disponibles
            $totalAvailable = $this->serviceRequestRepository->createQueryBuilder('sr')
                ->select('COUNT(sr.id)')
                ->leftJoin('sr.category', 'cat')
                ->where('sr.status IN (:statuses)')
                ->andWhere('cat IN (:categories)')
                ->setParameter('statuses', ['open', 'quoting'])
                ->setParameter('categories', $prestataire->getServiceCategories())
                ->getQuery()
                ->getSingleScalarResult();

            // Par catégorie
            $byCategory = [];
            foreach ($prestataire->getServiceCategories() as $category) {
                $count = $this->serviceRequestRepository->createQueryBuilder('sr')
                    ->select('COUNT(sr.id)')
                    ->where('sr.status IN (:statuses)')
                    ->andWhere('sr.category = :category')
                    ->setParameter('statuses', ['open', 'quoting'])
                    ->setParameter('category', $category)
                    ->getQuery()
                    ->getSingleScalarResult();

                if ($count > 0) {
                    $byCategory[] = [
                        'category_id' => $category->getId(),
                        'category_name' => $category->getName(),
                        'count' => (int) $count,
                    ];
                }
            }

            // Budget moyen
            $avgBudget = $this->serviceRequestRepository->createQueryBuilder('sr')
                ->select('AVG(sr.budget)')
                ->leftJoin('sr.category', 'cat')
                ->where('sr.status IN (:statuses)')
                ->andWhere('cat IN (:categories)')
                ->andWhere('sr.budget IS NOT NULL')
                ->setParameter('statuses', ['open', 'quoting'])
                ->setParameter('categories', $prestataire->getServiceCategories())
                ->getQuery()
                ->getSingleScalarResult();

            // Urgentes (48h)
            $urgentDate = (new \DateTime())->modify('+48 hours');
            $urgentCount = $this->serviceRequestRepository->createQueryBuilder('sr')
                ->select('COUNT(sr.id)')
                ->leftJoin('sr.category', 'cat')
                ->where('sr.status IN (:statuses)')
                ->andWhere('sr.preferredDate <= :urgentDate')
                ->andWhere('cat IN (:categories)')
                ->setParameter('statuses', ['open', 'quoting'])
                ->setParameter('urgentDate', $urgentDate)
                ->setParameter('categories', $prestataire->getServiceCategories())
                ->getQuery()
                ->getSingleScalarResult();

            // Nouvelles aujourd'hui
            $today = new \DateTime('today');
            $newToday = $this->serviceRequestRepository->createQueryBuilder('sr')
                ->select('COUNT(sr.id)')
                ->leftJoin('sr.category', 'cat')
                ->where('sr.status IN (:statuses)')
                ->andWhere('sr.createdAt >= :today')
                ->andWhere('cat IN (:categories)')
                ->setParameter('statuses', ['open', 'quoting'])
                ->setParameter('today', $today)
                ->setParameter('categories', $prestataire->getServiceCategories())
                ->getQuery()
                ->getSingleScalarResult();

            return $this->json([
                'success' => true,
                'data' => [
                    'total_available' => (int) $totalAvailable,
                    'by_category' => $byCategory,
                    'average_budget' => round((float) $avgBudget, 2),
                    'urgent_count' => (int) $urgentCount,
                    'new_today' => (int) $newToday,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get service request stats', [
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
     * Marquer une demande comme vue
     */
    #[Route('/{id}/view', name: 'mark_viewed', methods: ['POST'])]
    public function markAsViewed(ServiceRequest $serviceRequest): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PrestataireVoter::VIEW_SERVICE_REQUEST,
            $serviceRequest
        );

        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            // Enregistrer la vue (à implémenter selon votre système de tracking)
            $this->logger->info('Service request viewed', [
                'service_request_id' => $serviceRequest->getId(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Vue enregistrée',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to mark as viewed', [
                'service_request_id' => $serviceRequest->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifier si le prestataire peut soumettre un devis
     */
    private function canQuote(Prestataire $prestataire, ServiceRequest $serviceRequest): bool
    {
        // Vérifier le statut
        if (!in_array($serviceRequest->getStatus(), ['open', 'quoting'])) {
            return false;
        }

        // Vérifier si déjà coté
        $existingQuote = $this->quoteRepository->findOneBy([
            'prestataire' => $prestataire,
            'serviceRequest' => $serviceRequest,
        ]);

        if ($existingQuote) {
            return false;
        }

        // Vérifier si expiré
        if ($serviceRequest->getExpiresAt() && 
            $serviceRequest->getExpiresAt() < new \DateTime()) {
            return false;
        }

        return true;
    }
}