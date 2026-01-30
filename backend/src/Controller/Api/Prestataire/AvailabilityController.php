<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\Planning\Availability;
use App\Entity\User\Prestataire;
use App\Repository\Planning\AvailabilityRepository;
use App\Security\Voter\PrestataireVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/availabilities', name: 'api_prestataire_availability_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class AvailabilityController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AvailabilityRepository $availabilityRepository,
        private SerializerInterface $serializer,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Liste toutes les disponibilités du prestataire connecté
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $availabilities = $this->availabilityRepository->findBy(
            ['prestataire' => $prestataire],
            ['dayOfWeek' => 'ASC', 'startTime' => 'ASC']
        );

        return $this->json([
            'success' => true,
            'data' => $availabilities,
        ], Response::HTTP_OK, [], ['groups' => ['availability:read']]);
    }

    /**
     * Récupère les disponibilités récurrentes (hebdomadaires)
     */
    #[Route('/recurring', name: 'recurring', methods: ['GET'])]
    public function getRecurringAvailabilities(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $availabilities = $this->availabilityRepository->findBy(
            [
                'prestataire' => $prestataire,
                'isRecurring' => true,
            ],
            ['dayOfWeek' => 'ASC', 'startTime' => 'ASC']
        );

        // Organiser par jour de la semaine
        $organized = [];
        foreach ($availabilities as $availability) {
            $day = $availability->getDayOfWeek();
            if (!isset($organized[$day])) {
                $organized[$day] = [];
            }
            $organized[$day][] = [
                'id' => $availability->getId(),
                'startTime' => $availability->getStartTime()->format('H:i'),
                'endTime' => $availability->getEndTime()->format('H:i'),
                'isAvailable' => $availability->isAvailable(),
            ];
        }

        return $this->json([
            'success' => true,
            'data' => $organized,
        ]);
    }

    /**
     * Récupère les disponibilités spécifiques (dates précises)
     */
    #[Route('/specific', name: 'specific', methods: ['GET'])]
    public function getSpecificAvailabilities(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        $qb = $this->availabilityRepository->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->andWhere('a.isRecurring = :isRecurring')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('isRecurring', false);

        if ($startDate) {
            $qb->andWhere('a.specificDate >= :startDate')
                ->setParameter('startDate', new \DateTime($startDate));
        }

        if ($endDate) {
            $qb->andWhere('a.specificDate <= :endDate')
                ->setParameter('endDate', new \DateTime($endDate));
        }

        $availabilities = $qb->orderBy('a.specificDate', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->json([
            'success' => true,
            'data' => $availabilities,
        ], Response::HTTP_OK, [], ['groups' => ['availability:read']]);
    }

    /**
     * Récupère les disponibilités pour une période donnée
     */
    #[Route('/period', name: 'period', methods: ['GET'])]
    public function getAvailabilitiesForPeriod(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $startDate = $request->query->get('start_date');
        $endDate = $request->query->get('end_date');

        if (!$startDate || !$endDate) {
            return $this->json([
                'success' => false,
                'message' => 'Les paramètres start_date et end_date sont requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $start = new \DateTime($startDate);
            $end = new \DateTime($endDate);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Format de date invalide',
            ], Response::HTTP_BAD_REQUEST);
        }

        $availabilities = $this->availabilityRepository->findAvailabilitiesForPeriod(
            $prestataire,
            $start,
            $end
        );

        return $this->json([
            'success' => true,
            'data' => $availabilities,
        ], Response::HTTP_OK, [], ['groups' => ['availability:read']]);
    }

    /**
     * Récupère une disponibilité spécifique
     */
    #[Route('/{id}', name: 'show', methods: ['GET'])]
    public function show(Availability $availability): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PrestataireVoter::MANAGE_AVAILABILITY,
            $availability
        );

        return $this->json([
            'success' => true,
            'data' => $availability,
        ], Response::HTTP_OK, [], ['groups' => ['availability:read']]);
    }

    /**
     * Crée une nouvelle disponibilité
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $this->denyAccessUnlessGranted(PrestataireVoter::MANAGE_AVAILABILITY, null);

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides',
            ], Response::HTTP_BAD_REQUEST);
        }

        $availability = new Availability();
        $availability->setPrestataire($prestataire);

        // Définir les propriétés
        if (isset($data['dayOfWeek'])) {
            $availability->setDayOfWeek((int) $data['dayOfWeek']);
        }

        if (isset($data['startTime'])) {
            $availability->setStartTime(new \DateTime($data['startTime']));
        }

        if (isset($data['endTime'])) {
            $availability->setEndTime(new \DateTime($data['endTime']));
        }

        if (isset($data['isRecurring'])) {
            $availability->setIsRecurring((bool) $data['isRecurring']);
        }

        if (isset($data['specificDate'])) {
            $availability->setSpecificDate(new \DateTime($data['specificDate']));
        }

        if (isset($data['isAvailable'])) {
            $availability->setIsAvailable((bool) $data['isAvailable']);
        }

        // Validation
        $errors = $this->validator->validate($availability);

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

        // Vérifier les chevauchements
        if ($this->hasOverlap($availability)) {
            return $this->json([
                'success' => false,
                'message' => 'Cette disponibilité chevauche une disponibilité existante',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $this->entityManager->persist($availability);
            $this->entityManager->flush();

            $this->logger->info('Availability created', [
                'prestataire_id' => $prestataire->getId(),
                'availability_id' => $availability->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Disponibilité créée avec succès',
                'data' => $availability,
            ], Response::HTTP_CREATED, [], ['groups' => ['availability:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create availability', [
                'error' => $e->getMessage(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création de la disponibilité',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour une disponibilité
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(Request $request, Availability $availability): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PrestataireVoter::MANAGE_AVAILABILITY,
            $availability
        );

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Mettre à jour les propriétés
        if (isset($data['dayOfWeek'])) {
            $availability->setDayOfWeek((int) $data['dayOfWeek']);
        }

        if (isset($data['startTime'])) {
            $availability->setStartTime(new \DateTime($data['startTime']));
        }

        if (isset($data['endTime'])) {
            $availability->setEndTime(new \DateTime($data['endTime']));
        }

        if (isset($data['isRecurring'])) {
            $availability->setIsRecurring((bool) $data['isRecurring']);
        }

        if (isset($data['specificDate'])) {
            $availability->setSpecificDate(new \DateTime($data['specificDate']));
        }

        if (isset($data['isAvailable'])) {
            $availability->setIsAvailable((bool) $data['isAvailable']);
        }

        // Validation
        $errors = $this->validator->validate($availability);

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

        // Vérifier les chevauchements (en excluant la disponibilité actuelle)
        if ($this->hasOverlap($availability, $availability->getId())) {
            return $this->json([
                'success' => false,
                'message' => 'Cette disponibilité chevauche une disponibilité existante',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $this->entityManager->flush();

            $this->logger->info('Availability updated', [
                'availability_id' => $availability->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Disponibilité mise à jour avec succès',
                'data' => $availability,
            ], Response::HTTP_OK, [], ['groups' => ['availability:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to update availability', [
                'error' => $e->getMessage(),
                'availability_id' => $availability->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour de la disponibilité',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime une disponibilité
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(Availability $availability): JsonResponse
    {
        $this->denyAccessUnlessGranted(
            PrestataireVoter::MANAGE_AVAILABILITY,
            $availability
        );

        try {
            $this->entityManager->remove($availability);
            $this->entityManager->flush();

            $this->logger->info('Availability deleted', [
                'availability_id' => $availability->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Disponibilité supprimée avec succès',
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to delete availability', [
                'error' => $e->getMessage(),
                'availability_id' => $availability->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression de la disponibilité',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Crée des disponibilités récurrentes pour une semaine type
     */
    #[Route('/recurring/batch', name: 'batch_create', methods: ['POST'])]
    public function createRecurringBatch(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $this->denyAccessUnlessGranted(PrestataireVoter::MANAGE_AVAILABILITY, null);

        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['schedule'])) {
            return $this->json([
                'success' => false,
                'message' => 'Format de données invalide',
            ], Response::HTTP_BAD_REQUEST);
        }

        $createdAvailabilities = [];
        $errors = [];

        $this->entityManager->beginTransaction();

        try {
            foreach ($data['schedule'] as $daySchedule) {
                if (!isset($daySchedule['dayOfWeek']) || !isset($daySchedule['slots'])) {
                    continue;
                }

                $dayOfWeek = (int) $daySchedule['dayOfWeek'];

                foreach ($daySchedule['slots'] as $slot) {
                    if (!isset($slot['startTime']) || !isset($slot['endTime'])) {
                        continue;
                    }

                    $availability = new Availability();
                    $availability->setPrestataire($prestataire);
                    $availability->setDayOfWeek($dayOfWeek);
                    $availability->setStartTime(new \DateTime($slot['startTime']));
                    $availability->setEndTime(new \DateTime($slot['endTime']));
                    $availability->setIsRecurring(true);
                    $availability->setIsAvailable(true);

                    $validationErrors = $this->validator->validate($availability);

                    if (count($validationErrors) > 0) {
                        foreach ($validationErrors as $error) {
                            $errors[] = sprintf(
                                'Jour %d, %s-%s: %s',
                                $dayOfWeek,
                                $slot['startTime'],
                                $slot['endTime'],
                                $error->getMessage()
                            );
                        }
                        continue;
                    }

                    $this->entityManager->persist($availability);
                    $createdAvailabilities[] = $availability;
                }
            }

            if (!empty($errors)) {
                $this->entityManager->rollback();
                return $this->json([
                    'success' => false,
                    'message' => 'Erreurs de validation',
                    'errors' => $errors,
                ], Response::HTTP_BAD_REQUEST);
            }

            $this->entityManager->flush();
            $this->entityManager->commit();

            $this->logger->info('Batch availabilities created', [
                'prestataire_id' => $prestataire->getId(),
                'count' => count($createdAvailabilities),
            ]);

            return $this->json([
                'success' => true,
                'message' => sprintf('%d disponibilités créées avec succès', count($createdAvailabilities)),
                'data' => $createdAvailabilities,
            ], Response::HTTP_CREATED, [], ['groups' => ['availability:read']]);
        } catch (\Exception $e) {
            $this->entityManager->rollback();

            $this->logger->error('Failed to create batch availabilities', [
                'error' => $e->getMessage(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la création des disponibilités',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime toutes les disponibilités récurrentes
     */
    #[Route('/recurring/clear', name: 'clear_recurring', methods: ['DELETE'])]
    public function clearRecurring(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $this->denyAccessUnlessGranted(PrestataireVoter::MANAGE_AVAILABILITY, null);

        try {
            $count = $this->availabilityRepository->createQueryBuilder('a')
                ->delete()
                ->where('a.prestataire = :prestataire')
                ->andWhere('a.isRecurring = :isRecurring')
                ->setParameter('prestataire', $prestataire)
                ->setParameter('isRecurring', true)
                ->getQuery()
                ->execute();

            $this->logger->info('Recurring availabilities cleared', [
                'prestataire_id' => $prestataire->getId(),
                'count' => $count,
            ]);

            return $this->json([
                'success' => true,
                'message' => sprintf('%d disponibilités supprimées', $count),
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to clear recurring availabilities', [
                'error' => $e->getMessage(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression des disponibilités',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Marque un créneau comme indisponible (congé, absence)
     */
    #[Route('/unavailable', name: 'mark_unavailable', methods: ['POST'])]
    public function markUnavailable(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $this->denyAccessUnlessGranted(PrestataireVoter::MANAGE_AVAILABILITY, null);

        $data = json_decode($request->getContent(), true);

        if (!isset($data['date']) || !isset($data['startTime']) || !isset($data['endTime'])) {
            return $this->json([
                'success' => false,
                'message' => 'Paramètres manquants (date, startTime, endTime)',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $date = new \DateTime($data['date']);
            $startTime = new \DateTime($data['startTime']);
            $endTime = new \DateTime($data['endTime']);

            $availability = new Availability();
            $availability->setPrestataire($prestataire);
            $availability->setSpecificDate($date);
            $availability->setStartTime($startTime);
            $availability->setEndTime($endTime);
            $availability->setIsRecurring(false);
            $availability->setIsAvailable(false);

            if (isset($data['reason'])) {
                $availability->setReason($data['reason']);
            }

            $errors = $this->validator->validate($availability);

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

            $this->entityManager->persist($availability);
            $this->entityManager->flush();

            $this->logger->info('Unavailability created', [
                'prestataire_id' => $prestataire->getId(),
                'date' => $date->format('Y-m-d'),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Indisponibilité enregistrée avec succès',
                'data' => $availability,
            ], Response::HTTP_CREATED, [], ['groups' => ['availability:read']]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to create unavailability', [
                'error' => $e->getMessage(),
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement de l\'indisponibilité',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifie si une disponibilité chevauche une autre
     */
    private function hasOverlap(Availability $availability, ?int $excludeId = null): bool
    {
        $qb = $this->availabilityRepository->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $availability->getPrestataire());

        if ($excludeId) {
            $qb->andWhere('a.id != :excludeId')
                ->setParameter('excludeId', $excludeId);
        }

        if ($availability->isRecurring()) {
            // Vérifier les chevauchements pour les disponibilités récurrentes
            $qb->andWhere('a.isRecurring = :isRecurring')
                ->andWhere('a.dayOfWeek = :dayOfWeek')
                ->setParameter('isRecurring', true)
                ->setParameter('dayOfWeek', $availability->getDayOfWeek());
        } else {
            // Vérifier les chevauchements pour les disponibilités spécifiques
            $qb->andWhere('a.isRecurring = :isRecurring')
                ->andWhere('a.specificDate = :specificDate')
                ->setParameter('isRecurring', false)
                ->setParameter('specificDate', $availability->getSpecificDate());
        }

        $existingAvailabilities = $qb->getQuery()->getResult();

        foreach ($existingAvailabilities as $existing) {
            // Vérifier si les heures se chevauchent
            if ($this->timesOverlap(
                $availability->getStartTime(),
                $availability->getEndTime(),
                $existing->getStartTime(),
                $existing->getEndTime()
            )) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si deux plages horaires se chevauchent
     */
    private function timesOverlap(
        \DateTimeInterface $start1,
        \DateTimeInterface $end1,
        \DateTimeInterface $start2,
        \DateTimeInterface $end2
    ): bool {
        $startTime1 = $start1->format('H:i');
        $endTime1 = $end1->format('H:i');
        $startTime2 = $start2->format('H:i');
        $endTime2 = $end2->format('H:i');

        return ($startTime1 < $endTime2) && ($endTime1 > $startTime2);
    }
}