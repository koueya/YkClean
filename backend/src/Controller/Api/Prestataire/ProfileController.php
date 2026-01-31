<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\User\Prestataire;
use App\Repository\User\PrestataireRepository;
use App\Repository\Service\ServiceCategoryRepository;
use App\Service\FileUploadService;
use App\Service\Payment\StripeService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire/profile', name: 'api_prestataire_profile_')]
#[IsGranted('ROLE_PRESTATAIRE')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrestataireRepository $prestataireRepository,
        private ServiceCategoryRepository $serviceCategoryRepository,
        private FileUploadService $fileUploadService,
        private StripeService $stripeService,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Récupère le profil complet
     */
    #[Route('', name: 'show', methods: ['GET'])]
    public function show(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        return $this->json([
            'success' => true,
            'data' => $prestataire,
        ], Response::HTTP_OK, [], ['groups' => ['prestataire:read', 'prestataire:profile']]);
    }

    /**
     * Met à jour les informations personnelles
     */
    #[Route('/personal', name: 'update_personal', methods: ['PUT', 'PATCH'])]
    public function updatePersonal(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Nom, prénom
            if (isset($data['first_name'])) {
                $prestataire->setFirstName($data['first_name']);
            }
            if (isset($data['last_name'])) {
                $prestataire->setLastName($data['last_name']);
            }

            // Téléphone
            if (isset($data['phone'])) {
                $prestataire->setPhone($data['phone']);
            }

            // Adresse
            if (isset($data['address'])) {
                $prestataire->setAddress($data['address']);
            }
            if (isset($data['city'])) {
                $prestataire->setCity($data['city']);
            }
            if (isset($data['postal_code'])) {
                $prestataire->setPostalCode($data['postal_code']);
            }

            // Date de naissance
            if (isset($data['birth_date'])) {
                $prestataire->setBirthDate(new \DateTime($data['birth_date']));
            }

            // Genre
            if (isset($data['gender'])) {
                $prestataire->setGender($data['gender']);
            }

            // Validation
            $errors = $this->validator->validate($prestataire, null, ['profile_update']);

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

            $this->logger->info('Prestataire personal info updated', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Informations personnelles mises à jour',
                'data' => $prestataire,
            ], Response::HTTP_OK, [], ['groups' => ['prestataire:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update personal info', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour les informations professionnelles
     */
    #[Route('/professional', name: 'update_professional', methods: ['PUT', 'PATCH'])]
    public function updateProfessional(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // SIRET
            if (isset($data['siret'])) {
                $prestataire->setSiret($data['siret']);
            }

            // Nom de l'entreprise
            if (isset($data['company_name'])) {
                $prestataire->setCompanyName($data['company_name']);
            }

            // Taux horaire
            if (isset($data['hourly_rate'])) {
                $prestataire->setHourlyRate($data['hourly_rate']);
            }

            // Rayon d'intervention
            if (isset($data['service_radius'])) {
                $prestataire->setServiceRadius((int) $data['service_radius']);
            }

            // Présentation
            if (isset($data['bio'])) {
                $prestataire->setBio($data['bio']);
            }

            // Années d'expérience
            if (isset($data['years_experience'])) {
                $prestataire->setYearsExperience((int) $data['years_experience']);
            }

            // Langues
            if (isset($data['languages'])) {
                $prestataire->setLanguages($data['languages']);
            }

            // Validation
            $errors = $this->validator->validate($prestataire, null, ['profile_update']);

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

            $this->logger->info('Prestataire professional info updated', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Informations professionnelles mises à jour',
                'data' => $prestataire,
            ], Response::HTTP_OK, [], ['groups' => ['prestataire:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update professional info', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour les catégories de services
     */
    #[Route('/categories', name: 'update_categories', methods: ['PUT'])]
    public function updateCategories(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['category_ids']) || !is_array($data['category_ids'])) {
            return $this->json([
                'success' => false,
                'message' => 'Liste des catégories requise',
            ], Response::HTTP_BAD_REQUEST);
        }

        if (empty($data['category_ids'])) {
            return $this->json([
                'success' => false,
                'message' => 'Au moins une catégorie est requise',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Supprimer toutes les catégories actuelles
            foreach ($prestataire->getServiceCategories() as $category) {
                $prestataire->removeServiceCategory($category);
            }

            // Ajouter les nouvelles catégories
            foreach ($data['category_ids'] as $categoryId) {
                $category = $this->serviceCategoryRepository->find($categoryId);
                
                if (!$category) {
                    return $this->json([
                        'success' => false,
                        'message' => "Catégorie {$categoryId} introuvable",
                    ], Response::HTTP_BAD_REQUEST);
                }

                if (!$category->isActive()) {
                    return $this->json([
                        'success' => false,
                        'message' => "La catégorie {$category->getName()} n'est pas active",
                    ], Response::HTTP_BAD_REQUEST);
                }

                $prestataire->addServiceCategory($category);
            }

            $this->entityManager->flush();

            $this->logger->info('Prestataire categories updated', [
                'prestataire_id' => $prestataire->getId(),
                'categories_count' => count($data['category_ids']),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Catégories mises à jour',
                'data' => [
                    'categories' => $prestataire->getServiceCategories(),
                ],
            ], Response::HTTP_OK, [], ['groups' => ['category:read']]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update categories', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Upload/mise à jour de la photo de profil
     */
    #[Route('/avatar', name: 'update_avatar', methods: ['POST'])]
    public function updateAvatar(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        /** @var UploadedFile $file */
        $file = $request->files->get('avatar');

        if (!$file) {
            return $this->json([
                'success' => false,
                'message' => 'Aucun fichier fourni',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du fichier
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($file->getMimeType(), $allowedMimeTypes)) {
            return $this->json([
                'success' => false,
                'message' => 'Format de fichier non supporté. Utilisez JPG, PNG ou WebP',
            ], Response::HTTP_BAD_REQUEST);
        }

        $maxSize = 5 * 1024 * 1024; // 5MB
        if ($file->getSize() > $maxSize) {
            return $this->json([
                'success' => false,
                'message' => 'Le fichier ne doit pas dépasser 5MB',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Supprimer l'ancien avatar
            if ($prestataire->getAvatar()) {
                $this->fileUploadService->deleteFile($prestataire->getAvatar());
            }

            // Upload le nouveau fichier
            $uploadResult = $this->fileUploadService->uploadAvatar($file, $prestataire->getId());

            if (!$uploadResult['success']) {
                return $this->json([
                    'success' => false,
                    'message' => $uploadResult['message'],
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $prestataire->setAvatar($uploadResult['path']);
            $this->entityManager->flush();

            $this->logger->info('Prestataire avatar updated', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Photo de profil mise à jour',
                'data' => [
                    'avatar' => $uploadResult['url'],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update avatar', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'upload',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Supprime la photo de profil
     */
    #[Route('/avatar', name: 'delete_avatar', methods: ['DELETE'])]
    public function deleteAvatar(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->getAvatar()) {
            return $this->json([
                'success' => false,
                'message' => 'Aucune photo de profil',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $this->fileUploadService->deleteFile($prestataire->getAvatar());
            $prestataire->setAvatar(null);
            $this->entityManager->flush();

            $this->logger->info('Prestataire avatar deleted', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Photo de profil supprimée',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to delete avatar', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Change le mot de passe
     */
    #[Route('/password', name: 'change_password', methods: ['PUT'])]
    public function changePassword(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!isset($data['current_password']) || !isset($data['new_password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe actuel et nouveau mot de passe requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier le mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($prestataire, $data['current_password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du nouveau mot de passe
        if (strlen($data['new_password']) < 8) {
            return $this->json([
                'success' => false,
                'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $hashedPassword = $this->passwordHasher->hashPassword($prestataire, $data['new_password']);
            $prestataire->setPassword($hashedPassword);
            $this->entityManager->flush();

            $this->logger->info('Prestataire password changed', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Mot de passe modifié avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to change password', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du changement de mot de passe',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Met à jour les préférences de notification
     */
    #[Route('/notifications', name: 'update_notifications', methods: ['PUT'])]
    public function updateNotifications(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            if (isset($data['notifications_enabled'])) {
                $prestataire->setNotificationsEnabled((bool) $data['notifications_enabled']);
            }

            if (isset($data['marketing_emails_enabled'])) {
                $prestataire->setMarketingEmailsEnabled((bool) $data['marketing_emails_enabled']);
            }

            if (isset($data['email_new_request'])) {
                $prestataire->setEmailNewRequest((bool) $data['email_new_request']);
            }

            if (isset($data['email_booking_confirmed'])) {
                $prestataire->setEmailBookingConfirmed((bool) $data['email_booking_confirmed']);
            }

            if (isset($data['email_payment_received'])) {
                $prestataire->setEmailPaymentReceived((bool) $data['email_payment_received']);
            }

            if (isset($data['sms_notifications'])) {
                $prestataire->setSmsNotifications((bool) $data['sms_notifications']);
            }

            $this->entityManager->flush();

            $this->logger->info('Prestataire notification preferences updated', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Préférences de notification mises à jour',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to update notification preferences', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Récupère les statistiques du profil
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        try {
            $stats = [
                'profile_completion' => $this->calculateProfileCompletion($prestataire),
                'total_bookings' => $prestataire->getBookings()->count(),
                'completed_bookings' => $prestataire->getBookings()->filter(
                    fn($b) => $b->getStatus() === 'completed'
                )->count(),
                'average_rating' => $prestataire->getAverageRating(),
                'total_reviews' => $prestataire->getReviews()->count(),
                'total_earnings' => 0, // À calculer depuis les paiements
                'member_since' => $prestataire->getCreatedAt()?->format('Y-m-d'),
                'is_approved' => $prestataire->isApproved(),
                'verification_status' => $prestataire->getStripeAccountStatus(),
            ];

            return $this->json([
                'success' => true,
                'data' => $stats,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get profile stats', [
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
     * Récupère les informations Stripe
     */
    #[Route('/stripe', name: 'stripe_info', methods: ['GET'])]
    public function stripeInfo(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->getStripeConnectedAccountId()) {
            return $this->json([
                'success' => false,
                'message' => 'Compte Stripe non configuré',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $accountStatus = $this->stripeService->getAccountStatus($prestataire);

            if (!$accountStatus) {
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible de récupérer les informations Stripe',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'account_id' => $prestataire->getStripeConnectedAccountId(),
                    'status' => $prestataire->getStripeAccountStatus(),
                    'charges_enabled' => $accountStatus['charges_enabled'],
                    'payouts_enabled' => $accountStatus['payouts_enabled'],
                    'details_submitted' => $accountStatus['details_submitted'],
                    'requirements' => $accountStatus['requirements'],
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get Stripe info', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération des informations Stripe',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Génère un lien vers le dashboard Stripe
     */
    #[Route('/stripe/dashboard', name: 'stripe_dashboard', methods: ['GET'])]
    public function stripeDashboard(): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        if (!$prestataire->getStripeConnectedAccountId()) {
            return $this->json([
                'success' => false,
                'message' => 'Compte Stripe non configuré',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $loginUrl = $this->stripeService->createLoginLink($prestataire);

            if (!$loginUrl) {
                return $this->json([
                    'success' => false,
                    'message' => 'Impossible de générer le lien',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'url' => $loginUrl,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to create Stripe dashboard link', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la génération du lien',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Désactive le compte
     */
    #[Route('/deactivate', name: 'deactivate', methods: ['POST'])]
    public function deactivate(Request $request): JsonResponse
    {
        /** @var Prestataire $prestataire */
        $prestataire = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $reason = $data['reason'] ?? null;

        try {
            $prestataire->deactivate();
            $this->entityManager->flush();

            $this->logger->info('Prestataire account deactivated', [
                'prestataire_id' => $prestataire->getId(),
                'reason' => $reason,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Compte désactivé avec succès',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to deactivate account', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la désactivation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Calcule le pourcentage de complétion du profil
     */
    private function calculateProfileCompletion(Prestataire $prestataire): int
    {
        $fields = [
            $prestataire->getFirstName() !== null,
            $prestataire->getLastName() !== null,
            $prestataire->getEmail() !== null,
            $prestataire->getPhone() !== null,
            $prestataire->getAddress() !== null,
            $prestataire->getCity() !== null,
            $prestataire->getPostalCode() !== null,
            $prestataire->getSiret() !== null,
            $prestataire->getHourlyRate() !== null,
            !$prestataire->getServiceCategories()->isEmpty(),
            $prestataire->getBio() !== null,
            $prestataire->getAvatar() !== null,
            $prestataire->getStripeConnectedAccountId() !== null,
        ];

        $completed = count(array_filter($fields));
        $total = count($fields);

        return (int) round(($completed / $total) * 100);
    }
}