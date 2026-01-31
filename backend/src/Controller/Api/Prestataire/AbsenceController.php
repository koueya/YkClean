<?php

namespace App\Controller\Api\Prestataire;

use App\Service\Planning\PlanningService;
use App\Service\Notification\NotificationService;
use App\Service\Replacement\ReplacementService;
use App\DTO\CreateAbsenceDTO;
use App\DTO\UpdateAbsenceDTO;
use App\Entity\User\Prestataire;
use App\Entity\Planning\Absence;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/absences')]
#[IsGranted('ROLE_PRESTATAIRE')]
class AbsenceController extends AbstractController
{
    private PlanningService $planningService;
    private NotificationService $notificationService;
    private ReplacementService $replacementService;
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;
    private LoggerInterface $logger;

    public function __construct(
        PlanningService $planningService,
        NotificationService $notificationService,
        ReplacementService $replacementService,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger
    ) {
        $this->planningService = $planningService;
        $this->notificationService = $notificationService;
        $this->replacementService = $replacementService;
        $this->entityManager = $entityManager;
        $this->validator = $validator;
        $this->logger = $logger;
    }

    /**
     * Déclarer une absence
     */
    #[Route('', name: 'api_prestataire_create_absence', methods: ['POST'])]
    public function createAbsence(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'JSON invalide',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Créer le DTO
            $dto = CreateAbsenceDTO::fromArray($data);

            // Valider
            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = [
                        'property' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                    ];
                }

                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'Erreur de validation',
                        'violations' => $errorMessages,
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Vérifier les réservations existantes dans la période
            $affectedBookings = $this->planningService->getBookingsInPeriod(
                $prestataire,
                $dto->getStartDateAsDateTime(),
                $dto->getEndDateAsDateTime()
            );

            // Créer l'absence
            $absence = $this->planningService->createAbsence($prestataire, $dto);

            $this->logger->info('Absence created', [
                'absence_id' => $absence->getId(),
                'prestataire_id' => $prestataire->getId(),
                'start_date' => $dto->getStartDate(),
                'end_date' => $dto->getEndDate(),
                'affected_bookings' => count($affectedBookings),
            ]);

            // Si des réservations sont affectées, lancer le processus de remplacement
            $replacements = [];
            if (!empty($affectedBookings)) {
                foreach ($affectedBookings as $booking) {
                    // Créer une demande de remplacement
                    $replacement = $this->replacementService->requestReplacement(
                        $booking,
                        $prestataire,
                        $dto->getReason()
                    );
                    $replacements[] = $replacement->getId();
                }

                $this->logger->info('Replacements requested for absence', [
                    'absence_id' => $absence->getId(),
                    'replacement_count' => count($replacements),
                ]);
            }

            return new JsonResponse([
                'success' => true,
                'message' => 'Absence déclarée avec succès',
                'data' => [
                    'absence' => [
                        'id' => $absence->getId(),
                        'startDate' => $absence->getStartDate()->format('Y-m-d'),
                        'endDate' => $absence->getEndDate()->format('Y-m-d'),
                        'reason' => $absence->getReason(),
                        'description' => $absence->getDescription(),
                        'status' => $absence->getStatus(),
                        'createdAt' => $absence->getCreatedAt()->format('c'),
                    ],
                    'affectedBookingsCount' => count($affectedBookings),
                    'replacementIds' => $replacements,
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create absence', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la déclaration de l\'absence',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer les absences du prestataire
     */
    #[Route('', name: 'api_prestataire_get_absences', methods: ['GET'])]
    public function getAbsences(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $status = $request->query->get('status');
            $upcoming = $request->query->getBoolean('upcoming', false);
            $page = max(1, (int) $request->query->get('page', 1));
            $limit = min(50, max(1, (int) $request->query->get('limit', 20)));

            $queryBuilder = $this->entityManager
                ->getRepository(Absence::class)
                ->createQueryBuilder('a')
                ->where('a.prestataire = :prestataire')
                ->setParameter('prestataire', $prestataire)
                ->orderBy('a.startDate', 'DESC');

            // Filtrer par statut
            if ($status) {
                $queryBuilder
                    ->andWhere('a.status = :status')
                    ->setParameter('status', $status);
            }

            // Filtrer par absences à venir
            if ($upcoming) {
                $queryBuilder
                    ->andWhere('a.endDate >= :today')
                    ->setParameter('today', new \DateTime('today'))
                    ->orderBy('a.startDate', 'ASC');
            }

            // Compter le total
            $totalQuery = clone $queryBuilder;
            $total = $totalQuery->select('COUNT(a.id)')->getQuery()->getSingleScalarResult();

            // Appliquer la pagination
            $absences = $queryBuilder
                ->setFirstResult(($page - 1) * $limit)
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();

            return new JsonResponse([
                'success' => true,
                'data' => array_map(function (Absence $absence) {
                    return $this->formatAbsence($absence);
                }, $absences),
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limit,
                    'pages' => (int) ceil($total / $limit),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get absences', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la récupération des absences',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupérer une absence spécifique
     */
    #[Route('/{id}', name: 'api_prestataire_get_absence', methods: ['GET'])]
    public function getAbsence(int $id): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $absence = $this->entityManager
                ->getRepository(Absence::class)
                ->find($id);

            if (!$absence) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_NOT_FOUND,
                        'message' => 'Absence non trouvée',
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'absence appartient au prestataire
            if ($absence->getPrestataire()->getId() !== $prestataire->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_FORBIDDEN,
                        'message' => 'Accès refusé',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            // Récupérer les réservations affectées
            $affectedBookings = $this->planningService->getBookingsInPeriod(
                $prestataire,
                $absence->getStartDate(),
                $absence->getEndDate()
            );

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'absence' => $this->formatAbsence($absence),
                    'affectedBookings' => array_map(function ($booking) {
                        return [
                            'id' => $booking->getId(),
                            'scheduledDate' => $booking->getScheduledDate()->format('c'),
                            'duration' => $booking->getDuration(),
                            'client' => [
                                'firstName' => $booking->getClient()->getFirstName(),
                                'lastName' => $booking->getClient()->getLastName(),
                            ],
                            'address' => $booking->getAddress(),
                            'amount' => $booking->getAmount(),
                            'status' => $booking->getStatus(),
                            'hasReplacement' => $booking->getReplacement() !== null,
                        ];
                    }, $affectedBookings),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get absence', [
                'absence_id' => $id,
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la récupération de l\'absence',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Modifier une absence
     */
    #[Route('/{id}', name: 'api_prestataire_update_absence', methods: ['PUT'])]
    public function updateAbsence(int $id, Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $absence = $this->entityManager
                ->getRepository(Absence::class)
                ->find($id);

            if (!$absence) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_NOT_FOUND,
                        'message' => 'Absence non trouvée',
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'absence appartient au prestataire
            if ($absence->getPrestataire()->getId() !== $prestataire->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_FORBIDDEN,
                        'message' => 'Accès refusé',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            // Vérifier que l'absence n'est pas déjà annulée
            if ($absence->getStatus() === 'cancelled') {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'Impossible de modifier une absence annulée',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            $data = json_decode($request->getContent(), true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'JSON invalide',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Créer le DTO
            $dto = UpdateAbsenceDTO::fromArray($data);

            // Valider
            $errors = $this->validator->validate($dto);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = [
                        'property' => $error->getPropertyPath(),
                        'message' => $error->getMessage(),
                    ];
                }

                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'Erreur de validation',
                        'violations' => $errorMessages,
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Mettre à jour l'absence
            $this->planningService->updateAbsence($absence, $dto);

            $this->logger->info('Absence updated', [
                'absence_id' => $absence->getId(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Absence mise à jour avec succès',
                'data' => [
                    'absence' => $this->formatAbsence($absence),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update absence', [
                'absence_id' => $id,
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la mise à jour de l\'absence',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Annuler une absence
     */
    #[Route('/{id}/cancel', name: 'api_prestataire_cancel_absence', methods: ['POST'])]
    public function cancelAbsence(int $id): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $absence = $this->entityManager
                ->getRepository(Absence::class)
                ->find($id);

            if (!$absence) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_NOT_FOUND,
                        'message' => 'Absence non trouvée',
                    ],
                ], Response::HTTP_NOT_FOUND);
            }

            // Vérifier que l'absence appartient au prestataire
            if ($absence->getPrestataire()->getId() !== $prestataire->getId()) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_FORBIDDEN,
                        'message' => 'Accès refusé',
                    ],
                ], Response::HTTP_FORBIDDEN);
            }

            // Vérifier que l'absence n'est pas déjà annulée
            if ($absence->getStatus() === 'cancelled') {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'Cette absence est déjà annulée',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            // Annuler l'absence
            $this->planningService->cancelAbsence($absence);

            // Notifier les clients des réservations qui étaient en remplacement
            $this->notificationService->notifyAbsenceCancelled($absence);

            $this->logger->info('Absence cancelled', [
                'absence_id' => $absence->getId(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            return new JsonResponse([
                'success' => true,
                'message' => 'Absence annulée avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to cancel absence', [
                'absence_id' => $id,
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de l\'annulation de l\'absence',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifier les conflits avant de créer une absence
     */
    #[Route('/check-conflicts', name: 'api_prestataire_check_absence_conflicts', methods: ['POST'])]
    public function checkConflicts(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $data = json_decode($request->getContent(), true);

            if (!isset($data['start_date']) || !isset($data['end_date'])) {
                return new JsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => Response::HTTP_BAD_REQUEST,
                        'message' => 'Dates de début et de fin requises',
                    ],
                ], Response::HTTP_BAD_REQUEST);
            }

            $startDate = new \DateTime($data['start_date']);
            $endDate = new \DateTime($data['end_date']);

            // Vérifier les réservations dans la période
            $affectedBookings = $this->planningService->getBookingsInPeriod(
                $prestataire,
                $startDate,
                $endDate
            );

            // Vérifier les absences existantes
            $existingAbsences = $this->planningService->getAbsencesInPeriod(
                $prestataire,
                $startDate,
                $endDate
            );

            return new JsonResponse([
                'success' => true,
                'data' => [
                    'hasConflicts' => !empty($affectedBookings) || !empty($existingAbsences),
                    'affectedBookingsCount' => count($affectedBookings),
                    'existingAbsencesCount' => count($existingAbsences),
                    'affectedBookings' => array_map(function ($booking) {
                        return [
                            'id' => $booking->getId(),
                            'scheduledDate' => $booking->getScheduledDate()->format('c'),
                            'duration' => $booking->getDuration(),
                            'client' => $booking->getClient()->getFirstName(),
                            'amount' => $booking->getAmount(),
                        ];
                    }, $affectedBookings),
                    'existingAbsences' => array_map(function ($absence) {
                        return [
                            'id' => $absence->getId(),
                            'startDate' => $absence->getStartDate()->format('Y-m-d'),
                            'endDate' => $absence->getEndDate()->format('Y-m-d'),
                            'reason' => $absence->getReason(),
                        ];
                    }, $existingAbsences),
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to check absence conflicts', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return new JsonResponse([
                'success' => false,
                'error' => [
                    'code' => Response::HTTP_INTERNAL_SERVER_ERROR,
                    'message' => 'Erreur lors de la vérification des conflits',
                ],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Formater une absence pour la réponse JSON
     */
    private function formatAbsence(Absence $absence): array
    {
        return [
            'id' => $absence->getId(),
            'startDate' => $absence->getStartDate()->format('Y-m-d'),
            'endDate' => $absence->getEndDate()->format('Y-m-d'),
            'reason' => $absence->getReason(),
            'description' => $absence->getDescription(),
            'status' => $absence->getStatus(),
            'daysCount' => $absence->getStartDate()->diff($absence->getEndDate())->days + 1,
            'createdAt' => $absence->getCreatedAt()->format('c'),
            'updatedAt' => $absence->getUpdatedAt()?->format('c'),
            'cancelledAt' => $absence->getCancelledAt()?->format('c'),
        ];
    }
}