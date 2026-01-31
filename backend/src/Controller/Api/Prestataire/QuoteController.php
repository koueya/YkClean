<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\Quote\Quote;
use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceRequest;
use App\Enum\QuoteStatus;
use App\Repository\Quote\QuoteRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Security\Voter\PrestataireVoter;
use App\Service\Notification\NotificationService;
use App\Service\Quote\QuoteService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/quotes', name: 'api_prestataire_quote_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class QuoteController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuoteRepository $quoteRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private QuoteService $quoteService,
        private NotificationService $notificationService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Liste tous les devis du prestataire
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $status = $request->query->get('status');
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->quoteRepository->createQueryBuilder('q')
            ->leftJoin('q.serviceRequest', 'sr')
            ->leftJoin('sr.client', 'c')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('sr', 'c', 'cat')
            ->where('q.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire);

        if ($status) {
            $statuses = explode(',', $status);
            $qb->andWhere('q.status IN (:statuses)')
                ->setParameter('statuses', $statuses);
        }

        $total = count($qb->getQuery()->getResult());
        $quotes = $qb->orderBy('q.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $quotes,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['quote:read', 'quote:list']]);
    }

    /**
     * Devis en attente de réponse
     */
    #[Route('/pending', name: 'pending', methods: ['GET'])]
    public function pending(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $quotes = $this->quoteRepository->createQueryBuilder('q')
            ->leftJoin('q.serviceRequest', 'sr')
            ->leftJoin('sr.client', 'c')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('sr', 'c', 'cat')
            ->where('q.prestataire = :prestataire')
            ->andWhere('q.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', QuoteStatus::PENDING->value)
            ->orderBy('q.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $quotes,
            'count' => count($quotes),
        ], Response::HTTP_OK, [], ['groups' => ['quote:read']]);
    }

    /**
     * Devis acceptés
     */
    #[Route('/accepted', name: 'accepted', methods: ['GET'])]
    public function accepted(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));

        $qb = $this->quoteRepository->createQueryBuilder('q')
            ->leftJoin('q.serviceRequest', 'sr')
            ->leftJoin('sr.client', 'c')
            ->leftJoin('sr.category', 'cat')
            ->addSelect('sr', 'c', 'cat')
            ->where('q.prestataire = :prestataire')
            ->andWhere('q.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', QuoteStatus::ACCEPTED->value);

        $total = count($qb->getQuery()->getResult());
        $quotes = $qb->orderBy('q.acceptedAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $quotes,
            'meta' => [
                'total' => $total,
                'page' => $page,
                'limit' => $limit,
                'pages' => ceil($total / $limit),
            ],
        ], Response::HTTP_OK, [], ['groups' => ['quote:read']]);
    }

    /**
     * Affiche un devis spécifique
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Quote $quote): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::EDIT_QUOTE, $quote);

        return $this->json([
            'success' => true,
            'data' => $quote,
        ], Response::HTTP_OK, [], ['groups' => ['quote:read', 'quote:detail']]);
    }

    /**
     * Crée un nouveau devis
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['service_request_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'Demande de service requise',
            ], Response::HTTP_BAD_REQUEST);
        }

        $serviceRequest = $this->serviceRequestRepository->find($data['service_request_id']);

        if (!$serviceRequest) {
            return $this->json([
                'success' => false,
                'message' => 'Demande de service introuvable',
            ], Response::HTTP_NOT_FOUND);
        }

        // Vérifier que le prestataire peut créer un devis
        $this->denyAccessUnlessGranted(PrestataireVoter::CREATE_QUOTE, $serviceRequest);

        // Vérifier qu'il n'a pas déjà soumis un devis
        $existingQuote = $this->quoteRepository->findOneBy([
            'prestataire' => $prestataire,
            'serviceRequest' => $serviceRequest,
        ]);

        if ($existingQuote) {
            return $this->json([
                'success' => false,
                'message' => 'Vous avez déjà soumis un devis pour cette demande',
                'existing_quote_id' => $existingQuote->getId(),
            ], Response::HTTP_CONFLICT);
        }

        try {
            $quote = new Quote();
            $quote->setPrestataire($prestataire);
            $quote->setServiceRequest($serviceRequest);
            $quote->setStatus(QuoteStatus::PENDING->value);

            // Montant
            if (!isset($data['amount']) || $data['amount'] <= 0) {
                return $this->json([
                    'success' => false,
                    'message' => 'Montant invalide',
                ], Response::HTTP_BAD_REQUEST);
            }
            $quote->setAmount($data['amount']);

            // Description
            if (isset($data['description'])) {
                $quote->setDescription($data['description']);
            }

            // Date proposée
            if (isset($data['proposed_date'])) {
                $quote->setProposedDate(new \DateTime($data['proposed_date']));
            }

            // Heure proposée
            if (isset($data['proposed_time'])) {
                $quote->setProposedTime(new \DateTime($data['proposed_time']));
            }

            // Durée estimée
            if (isset($data['estimated_duration'])) {
                $quote->setEstimatedDuration((int) $data['estimated_duration']);
            }

            // Validité du devis (par défaut 7 jours)
            $validityDays = $data['validity_days'] ?? 7;
            $validUntil = (new \DateTime())->modify("+{$validityDays} days");
            $quote->setValidUntil($validUntil);

            // Conditions
            if (isset($data['conditions'])) {
                $quote->setConditions($data['conditions']);
            }

            // Inclusions
            if (isset($data['inclusions'])) {
                $quote->setInclusions($data['inclusions']);
            }

            // Exclusions
            if (isset($data['exclusions'])) {
                $quote->setExclusions($data['exclusions']);
            }

            // Notes privées
            if (isset($data['private_notes'])) {
                $quote->setPrivateNotes($data['private_notes']);
            }

            // Validation
            $errors = $this->validator->validate($quote);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->persist($quote);
            $this->entityManager->flush();

            $this->logger->info('Quote created', [
                'quote_id' => $quote->getId(),
                'prestataire_id' => $prestataire->getId(),
                'service_request_id' => $serviceRequest->getId(),
                'amount' => $quote->getAmount(),
            ]);

            // Notifier le client
            $this->notificationService->notifyNewQuote($quote);

            // Mettre à jour le statut de la demande de service
            if ($serviceRequest->getStatus() === 'open') {
                $serviceRequest->setStatus('quoting');
                $this->entityManager->flush();
            }

            return $this->json([
                'success' => true,
                'message' => 'Devis créé avec succès',
                'data' => $quote,
            ], Response::HTTP_CREATED, [], ['groups' => ['quote:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create quote', [
                'prestataire_id' => $prestataire->getId(),
                'service_request_id' => $data['service_request_id'],
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création du devis',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour un devis
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, Quote $quote): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::EDIT_QUOTE, $quote);

        if ($quote->getStatus() !== QuoteStatus::PENDING->value) {
            return $this->json([
                'success' => false,
                'message' => 'Seuls les devis en attente peuvent être modifiés',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Montant
            if (isset($data['amount'])) {
                if ($data['amount'] <= 0) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Montant invalide',
                    ], Response::HTTP_BAD_REQUEST);
                }
                $quote->setAmount($data['amount']);
            }

            // Description
            if (isset($data['description'])) {
                $quote->setDescription($data['description']);
            }

            // Date proposée
            if (isset($data['proposed_date'])) {
                $quote->setProposedDate(new \DateTime($data['proposed_date']));
            }

            // Heure proposée
            if (isset($data['proposed_time'])) {
                $quote->setProposedTime(new \DateTime($data['proposed_time']));
            }

            // Durée estimée
            if (isset($data['estimated_duration'])) {
                $quote->setEstimatedDuration((int) $data['estimated_duration']);
            }

            // Validité
            if (isset($data['validity_days'])) {
                $validUntil = (new \DateTime())->modify("+{$data['validity_days']} days");
                $quote->setValidUntil($validUntil);
            }

            // Conditions
            if (isset($data['conditions'])) {
                $quote->setConditions($data['conditions']);
            }

            // Inclusions
            if (isset($data['inclusions'])) {
                $quote->setInclusions($data['inclusions']);
            }

            // Exclusions
            if (isset($data['exclusions'])) {
                $quote->setExclusions($data['exclusions']);
            }

            // Notes privées
            if (isset($data['private_notes'])) {
                $quote->setPrivateNotes($data['private_notes']);
            }

            // Validation
            $errors = $this->validator->validate($quote);

            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[$error->getPropertyPath()] = $error->getMessage();
                }

                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errorMessages,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();

            $this->logger->info('Quote updated', [
                'quote_id' => $quote->getId(),
                'prestataire_id' => $quote->getPrestataire()->getId(),
            ]);

            // Notifier le client de la modification
            $this->notificationService->notifyQuoteUpdated($quote);

            return $this->json([
                'success' => true,
                'message' => 'Devis mis à jour avec succès',
                'data' => $quote,
            ], Response::HTTP_OK, [], ['groups' => ['quote:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update quote', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour du devis',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Retire un devis
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Quote $quote): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::DELETE_QUOTE, $quote);

        try {
            $this->entityManager->remove($quote);
            $this->entityManager->flush();

            $this->logger->info('Quote deleted', [
                'quote_id' => $quote->getId(),
                'prestataire_id' => $quote->getPrestataire()->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Devis supprimé avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete quote', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression du devis',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Annule un devis
     */
    #[Route('/{id}/withdraw', name: 'withdraw', methods: ['POST'])]
    public function withdraw(Request $request, Quote $quote): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::EDIT_QUOTE, $quote);

        if ($quote->getStatus() !== QuoteStatus::PENDING->value) {
            return $this->json([
                'success' => false,
                'message' => 'Seuls les devis en attente peuvent être retirés',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        try {
            $quote->setStatus(QuoteStatus::WITHDRAWN->value);
            $quote->setWithdrawnAt(new \DateTime());
            
            if ($reason) {
                $quote->setWithdrawalReason($reason);
            }

            $this->entityManager->flush();

            $this->logger->info('Quote withdrawn', [
                'quote_id' => $quote->getId(),
                'prestataire_id' => $quote->getPrestataire()->getId(),
                'reason' => $reason,
            ]);

            // Notifier le client
            $this->notificationService->notifyQuoteWithdrawn($quote, $reason);

            return $this->json([
                'success' => true,
                'message' => 'Devis retiré avec succès',
                'data' => $quote,
            ], Response::HTTP_OK, [], ['groups' => ['quote:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to withdraw quote', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du retrait du devis',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Prolonge la validité d'un devis
     */
    #[Route('/{id}/extend', name: 'extend', methods: ['POST'])]
    public function extend(Request $request, Quote $quote): JsonResponse
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::EDIT_QUOTE, $quote);

        if ($quote->getStatus() !== QuoteStatus::PENDING->value) {
            return $this->json([
                'success' => false,
                'message' => 'Seuls les devis en attente peuvent être prolongés',
            ], Response::HTTP_BAD_REQUEST);
        }

        $data = json_decode($request->getContent(), true);
        $days = $data['days'] ?? 7;

        if ($days < 1 || $days > 30) {
            return $this->json([
                'success' => false,
                'message' => 'La prolongation doit être entre 1 et 30 jours',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $newValidUntil = (new \DateTime())->modify("+{$days} days");
            $quote->setValidUntil($newValidUntil);

            $this->entityManager->flush();

            $this->logger->info('Quote validity extended', [
                'quote_id' => $quote->getId(),
                'days' => $days,
                'new_valid_until' => $newValidUntil->format('Y-m-d'),
            ]);

            // Notifier le client
            $this->notificationService->notifyQuoteExtended($quote);

            return $this->json([
                'success' => true,
                'message' => 'Validité du devis prolongée avec succès',
                'data' => $quote,
            ], Response::HTTP_OK, [], ['groups' => ['quote:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to extend quote', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la prolongation du devis',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Statistiques des devis
     */
    #[Route('/stats/overview', name: 'stats', methods: ['GET'])]
    public function stats(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        try {
            $start = $startDate ? new \DateTime($startDate) : new \DateTime('-30 days');
            $end = $endDate ? new \DateTime($endDate) : new \DateTime();

            $stats = $this->quoteService->getPrestataireQuoteStats($prestataire, $start, $end);

            return $this->json([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get quote stats', [
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
     * Taux de conversion des devis
     */
    #[Route('/stats/conversion', name: 'conversion', methods: ['GET'])]
    public function conversionRate(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $period = $request->query->get('period', 'month'); // week, month, quarter, year

        try {
            $conversionData = $this->quoteService->getConversionRate($prestataire, $period);

            return $this->json([
                'success' => true,
                'data' => $conversionData,
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to get conversion rate', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du calcul du taux de conversion',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Exporte les devis en CSV
     */
    #[Route('/export', name: 'export', methods: ['GET'])]
    public function export(Request $request): Response
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');
        $status = $request->query->get('status');

        try {
            $start = $startDate ? new \DateTime($startDate) : null;
            $end = $endDate ? new \DateTime($endDate) : null;

            return $this->quoteService->exportQuotesToCsv($prestataire, $start, $end, $status);

        } catch (\Exception $e) {
            $this->logger->error('Failed to export quotes', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'export',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifie les devis expirés
     */
    #[Route('/check-expired', name: 'check_expired', methods: ['GET'])]
    public function checkExpired(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $expiredQuotes = $this->quoteRepository->createQueryBuilder('q')
            ->leftJoin('q.serviceRequest', 'sr')
            ->leftJoin('sr.client', 'c')
            ->addSelect('sr', 'c')
            ->where('q.prestataire = :prestataire')
            ->andWhere('q.status = :status')
            ->andWhere('q.validUntil < :now')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', QuoteStatus::PENDING->value)
            ->setParameter('now', new \DateTime())
            ->orderBy('q.validUntil', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $expiredQuotes,
            'count' => count($expiredQuotes),
        ], Response::HTTP_OK, [], ['groups' => ['quote:read']]);
    }

    /**
     * Génère un PDF du devis
     */
    #[Route('/{id}/pdf', name: 'generate_pdf', methods: ['GET'])]
    public function generatePdf(Quote $quote): Response
    {
        $this->denyAccessUnlessGranted(PrestataireVoter::EDIT_QUOTE, $quote);

        try {
            return $this->quoteService->generateQuotePdf($quote);
        } catch (\Exception $e) {
            $this->logger->error('Failed to generate quote PDF', [
                'quote_id' => $quote->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du PDF',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}