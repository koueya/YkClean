<?php

namespace App\Controller\Api\Prestataire;

use App\Entity\User\Prestataire;
use App\Repository\User\PrestataireRepository;
use App\Repository\Service\ServiceCategoryRepository;
use App\Repository\Booking\QuoteRepository;
use App\Service\Payment\StripeService;
use App\Service\Notification\NotificationService;
use App\Service\Email\EmailVerificationService;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;

#[Route('/api/prestataire', name: 'api_prestataire_registration_')]
class RegistrationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrestataireRepository $prestataireRepository,
        private ServiceCategoryRepository $serviceCategoryRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private StripeService $stripeService,
        private NotificationService $notificationService,
        private EmailVerificationService $emailVerificationService,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Inscription - Étape 1: Informations personnelles
     */
    #[Route('/register/step1', name: 'step1', methods: ['POST'])]
    public function registerStep1(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data) {
            return $this->json([
                'success' => false,
                'message' => 'Données invalides',
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation des champs requis
        $requiredFields = ['email', 'password', 'first_name', 'last_name', 'phone'];
        foreach ($requiredFields as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Le champ {$field} est requis",
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérifier si l'email existe déjà
        $existingUser = $this->prestataireRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser) {
            return $this->json([
                'success' => false,
                'message' => 'Un compte avec cet email existe déjà',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $prestataire = new Prestataire();
            
            // Informations de base
            $prestataire->setEmail($data['email']);
            $prestataire->setFirstName($data['first_name']);
            $prestataire->setLastName($data['last_name']);
            $prestataire->setPhone($data['phone']);

            // Hash du mot de passe
            $hashedPassword = $this->passwordHasher->hashPassword($prestataire, $data['password']);
            $prestataire->setPassword($hashedPassword);

            // Adresse (optionnel à cette étape)
            if (isset($data['address'])) {
                $prestataire->setAddress($data['address']);
            }
            if (isset($data['city'])) {
                $prestataire->setCity($data['city']);
            }
            if (isset($data['postal_code'])) {
                $prestataire->setPostalCode($data['postal_code']);
            }

            // Date de naissance (optionnel)
            if (isset($data['birth_date'])) {
                $prestataire->setBirthDate(new \DateTime($data['birth_date']));
            }

            // Genre (optionnel)
            if (isset($data['gender'])) {
                $prestataire->setGender($data['gender']);
            }

            // Rôle prestataire
            $prestataire->setRoles(['ROLE_PRESTATAIRE']);

            // Statut initial
            $prestataire->setIsApproved(false);
            $prestataire->setIsActive(true);
            $prestataire->setIsVerified(false);

            // Acceptation des CGU
            if (isset($data['terms_accepted']) && $data['terms_accepted']) {
                $prestataire->setTermsAccepted(true);
            }

            // Validation
            $errors = $this->validator->validate($prestataire, null, ['registration_step1']);

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

            // Générer un token de vérification email
            $prestataire->generateEmailVerificationToken();

            $this->entityManager->persist($prestataire);
            $this->entityManager->flush();

            $this->logger->info('Prestataire registration step 1 completed', [
                'prestataire_id' => $prestataire->getId(),
                'email' => $prestataire->getEmail(),
            ]);

            // Envoyer l'email de vérification
            $this->emailVerificationService->sendVerificationEmail($prestataire);

            return $this->json([
                'success' => true,
                'message' => 'Étape 1 complétée avec succès',
                'data' => [
                    'prestataire_id' => $prestataire->getId(),
                    'email' => $prestataire->getEmail(),
                    'next_step' => 'step2',
                ],
            ], Response::HTTP_CREATED);

        } catch (\Exception $e) {
            $this->logger->error('Failed to complete registration step 1', [
                'email' => $data['email'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'inscription',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Inscription - Étape 2: Informations professionnelles
     */
    #[Route('/register/step2', name: 'step2', methods: ['POST'])]
    public function registerStep2(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['prestataire_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'ID prestataire requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $prestataire = $this->prestataireRepository->find($data['prestataire_id']);

        if (!$prestataire) {
            return $this->json([
                'success' => false,
                'message' => 'Prestataire introuvable',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            // SIRET (obligatoire)
            if (isset($data['siret'])) {
                $prestataire->setSiret($data['siret']);
            }

            // Nom de l'entreprise (optionnel)
            if (isset($data['company_name'])) {
                $prestataire->setCompanyName($data['company_name']);
            }

            // Taux horaire
            if (isset($data['hourly_rate'])) {
                $prestataire->setHourlyRate($data['hourly_rate']);
            }

            // Rayon d'intervention (en km)
            if (isset($data['service_radius'])) {
                $prestataire->setServiceRadius((int) $data['service_radius']);
            }

            // Catégories de services
            if (isset($data['service_categories']) && is_array($data['service_categories'])) {
                foreach ($data['service_categories'] as $categoryId) {
                    $category = $this->serviceCategoryRepository->find($categoryId);
                    if ($category) {
                        $prestataire->addServiceCategory($category);
                    }
                }
            }

            // Présentation
            if (isset($data['bio'])) {
                $prestataire->setBio($data['bio']);
            }

            // Années d'expérience
            if (isset($data['years_experience'])) {
                $prestataire->setYearsExperience((int) $data['years_experience']);
            }

            // Langues parlées
            if (isset($data['languages'])) {
                $prestataire->setLanguages($data['languages']);
            }

            // Validation
            $errors = $this->validator->validate($prestataire, null, ['registration_step2']);

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

            $this->logger->info('Prestataire registration step 2 completed', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Étape 2 complétée avec succès',
                'data' => [
                    'prestataire_id' => $prestataire->getId(),
                    'next_step' => 'step3',
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to complete registration step 2', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'enregistrement',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Inscription - Étape 3: Configuration Stripe Connect
     */
    #[Route('/register/step3', name: 'step3', methods: ['POST'])]
    public function registerStep3(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['prestataire_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'ID prestataire requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $prestataire = $this->prestataireRepository->find($data['prestataire_id']);

        if (!$prestataire) {
            return $this->json([
                'success' => false,
                'message' => 'Prestataire introuvable',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            // Créer le compte Stripe Connect
            $stripeAccountId = $this->stripeService->createConnectedAccount($prestataire);

            if (!$stripeAccountId) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur lors de la création du compte Stripe',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            // Générer le lien d'onboarding Stripe
            $returnUrl = $data['return_url'] ?? $this->getParameter('app_url') . '/prestataire/onboarding/complete';
            $refreshUrl = $data['refresh_url'] ?? $this->getParameter('app_url') . '/prestataire/onboarding/stripe';

            $onboardingUrl = $this->stripeService->createAccountOnboardingLink(
                $prestataire,
                $returnUrl,
                $refreshUrl
            );

            if (!$onboardingUrl) {
                return $this->json([
                    'success' => false,
                    'message' => 'Erreur lors de la génération du lien d\'onboarding',
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }

            $this->logger->info('Prestataire registration step 3 initiated', [
                'prestataire_id' => $prestataire->getId(),
                'stripe_account_id' => $stripeAccountId,
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Compte Stripe créé avec succès',
                'data' => [
                    'prestataire_id' => $prestataire->getId(),
                    'stripe_account_id' => $stripeAccountId,
                    'onboarding_url' => $onboardingUrl,
                    'next_step' => 'stripe_onboarding',
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to complete registration step 3', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la configuration Stripe',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérification de l'email
     */
    #[Route('/verify-email/{token}', name: 'verify_email', methods: ['GET'])]
    public function verifyEmail(string $token): JsonResponse
    {
        $prestataire = $this->prestataireRepository->findOneBy([
            'emailVerificationToken' => $token,
        ]);

        if (!$prestataire) {
            return $this->json([
                'success' => false,
                'message' => 'Token de vérification invalide',
            ], Response::HTTP_NOT_FOUND);
        }

        if (!$prestataire->isEmailVerificationTokenValid($token)) {
            return $this->json([
                'success' => false,
                'message' => 'Le token de vérification a expiré',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $prestataire->verifyEmail();
            $this->entityManager->flush();

            $this->logger->info('Prestataire email verified', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Email vérifié avec succès',
                'data' => [
                    'email' => $prestataire->getEmail(),
                    'verified' => true,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to verify email', [
                'token' => $token,
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la vérification',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Renvoyer l'email de vérification
     */
    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['email'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $prestataire = $this->prestataireRepository->findOneBy(['email' => $data['email']]);

        if (!$prestataire) {
            // Ne pas révéler si l'email existe ou non (sécurité)
            return $this->json([
                'success' => true,
                'message' => 'Si cet email existe, un email de vérification a été envoyé',
            ]);
        }

        if ($prestataire->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Cet email est déjà vérifié',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // Générer un nouveau token
            $prestataire->generateEmailVerificationToken();
            $this->entityManager->flush();

            // Envoyer l'email
            $this->emailVerificationService->sendVerificationEmail($prestataire);

            $this->logger->info('Verification email resent', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Email de vérification envoyé',
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to resend verification email', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'envoi',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Vérifier le statut d'onboarding
     */
    #[Route('/onboarding/status', name: 'onboarding_status', methods: ['GET'])]
    public function getOnboardingStatus(Request $request): JsonResponse
    {
        $email = $request->query->get('email');
        $prestataireId = $request->query->get('prestataire_id');

        if (!$email && !$prestataireId) {
            return $this->json([
                'success' => false,
                'message' => 'Email ou ID prestataire requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $prestataire = $email 
            ? $this->prestataireRepository->findOneBy(['email' => $email])
            : $this->prestataireRepository->find($prestataireId);

        if (!$prestataire) {
            return $this->json([
                'success' => false,
                'message' => 'Prestataire introuvable',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            $status = [
                'prestataire_id' => $prestataire->getId(),
                'email' => $prestataire->getEmail(),
                'steps' => [
                    'step1_personal_info' => [
                        'completed' => $prestataire->getId() !== null,
                        'data' => [
                            'name' => $prestataire->getFullName(),
                            'email' => $prestataire->getEmail(),
                            'phone' => $prestataire->getPhone(),
                        ],
                    ],
                    'email_verification' => [
                        'completed' => $prestataire->isVerified(),
                    ],
                    'step2_professional_info' => [
                        'completed' => $prestataire->getSiret() !== null && 
                                     !$prestataire->getServiceCategories()->isEmpty(),
                        'data' => [
                            'siret' => $prestataire->getSiret(),
                            'categories_count' => $prestataire->getServiceCategories()->count(),
                            'hourly_rate' => $prestataire->getHourlyRate(),
                        ],
                    ],
                    'step3_stripe' => [
                        'completed' => $prestataire->getStripeConnectedAccountId() !== null &&
                                     $prestataire->getStripeAccountStatus() === 'active',
                        'data' => [
                            'stripe_account_id' => $prestataire->getStripeConnectedAccountId(),
                            'stripe_status' => $prestataire->getStripeAccountStatus(),
                        ],
                    ],
                    'documents' => [
                        'completed' => false, // À implémenter avec le système de documents
                        'required' => ['identity_card', 'kbis', 'insurance'],
                    ],
                ],
                'overall_completion' => $this->calculateCompletionPercentage($prestataire),
                'is_approved' => $prestataire->isApproved(),
                'can_work' => $prestataire->isApproved() && 
                             $prestataire->getStripeAccountStatus() === 'active',
            ];

            return $this->json([
                'success' => true,
                'data' => $status,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to get onboarding status', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du statut',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Compléter l'onboarding (après retour de Stripe)
     */
    #[Route('/onboarding/complete', name: 'onboarding_complete', methods: ['POST'])]
    public function completeOnboarding(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['prestataire_id'])) {
            return $this->json([
                'success' => false,
                'message' => 'ID prestataire requis',
            ], Response::HTTP_BAD_REQUEST);
        }

        $prestataire = $this->prestataireRepository->find($data['prestataire_id']);

        if (!$prestataire) {
            return $this->json([
                'success' => false,
                'message' => 'Prestataire introuvable',
            ], Response::HTTP_NOT_FOUND);
        }

        try {
            // Vérifier le statut du compte Stripe
            $stripeStatus = $this->stripeService->getAccountStatus($prestataire);

            if (!$stripeStatus || !$stripeStatus['charges_enabled']) {
                return $this->json([
                    'success' => false,
                    'message' => 'La configuration Stripe n\'est pas complète',
                    'stripe_status' => $stripeStatus,
                ], Response::HTTP_BAD_REQUEST);
            }

            // Notifier l'admin pour approbation
            $this->notificationService->notifyNewPrestataireRegistration($prestataire);

            $this->logger->info('Prestataire onboarding completed', [
                'prestataire_id' => $prestataire->getId(),
            ]);

            return $this->json([
                'success' => true,
                'message' => 'Inscription complétée avec succès. Votre compte est en attente d\'approbation.',
                'data' => [
                    'prestataire_id' => $prestataire->getId(),
                    'status' => 'pending_approval',
                    'stripe_status' => $stripeStatus,
                ],
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to complete onboarding', [
                'prestataire_id' => $prestataire->getId(),
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la finalisation',
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Catégories de services disponibles
     */
    #[Route('/register/categories', name: 'get_categories', methods: ['GET'])]
    public function getServiceCategories(): JsonResponse
    {
        $categories = $this->serviceCategoryRepository->findBy(
            ['isActive' => true],
            ['name' => 'ASC']
        );

        return $this->json([
            'success' => true,
            'data' => $categories,
        ], Response::HTTP_OK, [], ['groups' => ['category:read']]);
    }

    /**
     * Calcule le pourcentage de complétion de l'onboarding
     */
    private function calculateCompletionPercentage(Prestataire $prestataire): int
    {
        $steps = [
            $prestataire->getId() !== null, // Étape 1
            $prestataire->isVerified(), // Email vérifié
            $prestataire->getSiret() !== null, // Étape 2 - SIRET
            !$prestataire->getServiceCategories()->isEmpty(), // Étape 2 - Catégories
            $prestataire->getStripeConnectedAccountId() !== null, // Étape 3
            $prestataire->getStripeAccountStatus() === 'active', // Stripe actif
        ];

        $completed = count(array_filter($steps));
        $total = count($steps);

        return (int) round(($completed / $total) * 100);
    }
}