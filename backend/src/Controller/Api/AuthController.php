<?php

namespace App\Controller\Api;

use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Repository\User\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserRepository $userRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private JWTTokenManagerInterface $jwtManager,
        private ValidatorInterface $validator
    ) {
    }

    /**
     * Inscription d'un nouveau client
     * 
     * @Route("/register/client", name="api_auth_register_client", methods={"POST"})
     */
    #[Route('/register/client', name: 'api_auth_register_client', methods: ['POST'])]
    public function registerClient(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation des données requises
        $requiredFields = ['email', 'password', 'firstName', 'lastName'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Le champ $field est obligatoire"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérifier si l'email existe déjà
        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return $this->json([
                'success' => false,
                'message' => 'Cet email est déjà utilisé'
            ], Response::HTTP_CONFLICT);
        }

        // Créer le client
        $client = new Client();
        $client->setEmail($data['email']);
        $client->setFirstName($data['firstName']);
        $client->setLastName($data['lastName']);
        $client->setPassword($this->passwordHasher->hashPassword($client, $data['password']));
        $client->setRoles(['ROLE_CLIENT']);
        
        // Champs optionnels
        if (!empty($data['phone'])) {
            $client->setPhone($data['phone']);
        }
        if (!empty($data['address'])) {
            $client->setAddress($data['address']);
        }
        if (!empty($data['city'])) {
            $client->setCity($data['city']);
        }
        if (!empty($data['postalCode'])) {
            $client->setPostalCode($data['postalCode']);
        }

        // Acceptation des CGU
        if (!empty($data['termsAccepted'])) {
            $client->setTermsAccepted(true);
        }

        // Validation
        $errors = $this->validator->validate($client);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Générer le token de vérification email
        $client->generateEmailVerificationToken();

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        // TODO: Envoyer l'email de vérification

        // Générer le JWT
        $token = $this->jwtManager->create($client);

        return $this->json([
            'success' => true,
            'message' => 'Inscription réussie',
            'user' => [
                'id' => $client->getId(),
                'email' => $client->getEmail(),
                'firstName' => $client->getFirstName(),
                'lastName' => $client->getLastName(),
                'fullName' => $client->getFullName(),
                'roles' => $client->getRoles(),
                'isVerified' => $client->isVerified(),
                'userType' => 'client',
            ],
            'token' => $token
        ], Response::HTTP_CREATED);
    }

    /**
     * Inscription d'un nouveau prestataire
     * 
     * @Route("/register/prestataire", name="api_auth_register_prestataire", methods={"POST"})
     */
    #[Route('/register/prestataire', name: 'api_auth_register_prestataire', methods: ['POST'])]
    public function registerPrestataire(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Validation des données requises
        $requiredFields = ['email', 'password', 'firstName', 'lastName', 'siret', 'companyName'];
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                return $this->json([
                    'success' => false,
                    'message' => "Le champ $field est obligatoire"
                ], Response::HTTP_BAD_REQUEST);
            }
        }

        // Vérifier si l'email existe déjà
        if ($this->userRepository->findOneBy(['email' => $data['email']])) {
            return $this->json([
                'success' => false,
                'message' => 'Cet email est déjà utilisé'
            ], Response::HTTP_CONFLICT);
        }

        // Créer le prestataire
        $prestataire = new Prestataire();
        $prestataire->setEmail($data['email']);
        $prestataire->setFirstName($data['firstName']);
        $prestataire->setLastName($data['lastName']);
        $prestataire->setSiret($data['siret']);
        $prestataire->setCompanyName($data['companyName']);
        $prestataire->setPassword($this->passwordHasher->hashPassword($prestataire, $data['password']));
        $prestataire->setRoles(['ROLE_PRESTATAIRE']);
        
        // Champs optionnels
        if (!empty($data['phone'])) {
            $prestataire->setPhone($data['phone']);
        }
        if (!empty($data['address'])) {
            $prestataire->setAddress($data['address']);
        }
        if (!empty($data['city'])) {
            $prestataire->setCity($data['city']);
        }
        if (!empty($data['postalCode'])) {
            $prestataire->setPostalCode($data['postalCode']);
        }
        if (!empty($data['hourlyRate'])) {
            $prestataire->setHourlyRate($data['hourlyRate']);
        }
        if (!empty($data['description'])) {
            $prestataire->setDescription($data['description']);
        }

        // Acceptation des CGU
        if (!empty($data['termsAccepted'])) {
            $prestataire->setTermsAccepted(true);
        }

        // Validation
        $errors = $this->validator->validate($prestataire);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return $this->json([
                'success' => false,
                'message' => 'Erreurs de validation',
                'errors' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        // Générer le token de vérification email
        $prestataire->generateEmailVerificationToken();

        $this->entityManager->persist($prestataire);
        $this->entityManager->flush();

        // TODO: Envoyer l'email de vérification

        // Générer le JWT
        $token = $this->jwtManager->create($prestataire);

        return $this->json([
            'success' => true,
            'message' => 'Inscription réussie. Votre compte sera vérifié par un administrateur.',
            'user' => [
                'id' => $prestataire->getId(),
                'email' => $prestataire->getEmail(),
                'firstName' => $prestataire->getFirstName(),
                'lastName' => $prestataire->getLastName(),
                'fullName' => $prestataire->getFullName(),
                'companyName' => $prestataire->getCompanyName(),
                'siret' => $prestataire->getSiret(),
                'roles' => $prestataire->getRoles(),
                'isVerified' => $prestataire->isVerified(),
                'isApproved' => $prestataire->isApproved(),
                'userType' => 'prestataire',
            ],
            'token' => $token
        ], Response::HTTP_CREATED);
    }

    /**
     * Obtenir l'utilisateur connecté
     * 
     * @Route("/me", name="api_auth_me", methods={"GET"})
     */
    #[Route('/me', name: 'api_auth_me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $userData = [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'fullName' => $user->getFullName(),
            'phone' => $user->getPhone(),
            'address' => $user->getAddress(),
            'city' => $user->getCity(),
            'postalCode' => $user->getPostalCode(),
            'avatar' => $user->getAvatar(),
            'roles' => $user->getRoles(),
            'isVerified' => $user->isVerified(),
            'isActive' => $user->isActive(),
            'createdAt' => $user->getCreatedAt()?->format('c'),
            'lastLoginAt' => $user->getLastLoginAt()?->format('c'),
            'loginCount' => $user->getLoginCount(),
        ];

        // Ajouter des infos spécifiques selon le type
        if ($user instanceof Client) {
            $userData['userType'] = 'client';
            $userData['stripeCustomerId'] = $user->getStripeCustomerId();
            $userData['defaultPaymentMethodId'] = $user->getDefaultPaymentMethodId();
        } elseif ($user instanceof Prestataire) {
            $userData['userType'] = 'prestataire';
            $userData['companyName'] = $user->getCompanyName();
            $userData['siret'] = $user->getSiret();
            $userData['isApproved'] = $user->isApproved();
            $userData['isAvailable'] = $user->isAvailable();
            $userData['averageRating'] = $user->getAverageRating();
            $userData['totalReviews'] = $user->getTotalReviews();
            $userData['hourlyRate'] = $user->getHourlyRate();
            $userData['description'] = $user->getDescription();
            $userData['stripeConnectedAccountId'] = $user->getStripeConnectedAccountId();
            $userData['stripeAccountStatus'] = $user->getStripeAccountStatus();
        }

        return $this->json([
            'success' => true,
            'user' => $userData
        ]);
    }

    /**
     * Rafraîchir le token JWT
     * 
     * @Route("/refresh", name="api_auth_refresh", methods={"POST"})
     */
    #[Route('/refresh', name: 'api_auth_refresh', methods: ['POST'])]
    public function refresh(): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Générer un nouveau token
        $token = $this->jwtManager->create($user);

        return $this->json([
            'success' => true,
            'token' => $token
        ]);
    }

    /**
     * Vérification de l'email
     * 
     * @Route("/verify-email", name="api_auth_verify_email", methods={"POST"})
     */
    #[Route('/verify-email', name: 'api_auth_verify_email', methods: ['POST'])]
    public function verifyEmail(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['token'])) {
            return $this->json([
                'success' => false,
                'message' => 'Token requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Chercher l'utilisateur avec ce token
        $user = $this->userRepository->findOneBy(['emailVerificationToken' => $data['token']]);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Token invalide'
            ], Response::HTTP_NOT_FOUND);
        }

        // Vérifier si le token n'a pas expiré
        if (!$user->isEmailVerificationTokenValid($data['token'])) {
            return $this->json([
                'success' => false,
                'message' => 'Token expiré'
            ], Response::HTTP_GONE);
        }

        // Marquer l'email comme vérifié
        $user->verifyEmail();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Email vérifié avec succès'
        ]);
    }

    /**
     * Renvoyer l'email de vérification
     * 
     * @Route("/resend-verification", name="api_auth_resend_verification", methods={"POST"})
     */
    #[Route('/resend-verification', name: 'api_auth_resend_verification', methods: ['POST'])]
    public function resendVerification(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if (!$user) {
            // Pour la sécurité, on retourne un succès même si l'email n'existe pas
            return $this->json([
                'success' => true,
                'message' => 'Si cet email existe, un email de vérification a été envoyé'
            ]);
        }

        if ($user->isVerified()) {
            return $this->json([
                'success' => false,
                'message' => 'Cet email est déjà vérifié'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Générer un nouveau token
        $user->generateEmailVerificationToken();
        $this->entityManager->flush();

        // TODO: Envoyer l'email

        return $this->json([
            'success' => true,
            'message' => 'Email de vérification envoyé'
        ]);
    }

    /**
     * Demander une réinitialisation de mot de passe
     * 
     * @Route("/forgot-password", name="api_auth_forgot_password", methods={"POST"})
     */
    #[Route('/forgot-password', name: 'api_auth_forgot_password', methods: ['POST'])]
    public function forgotPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['email'])) {
            return $this->json([
                'success' => false,
                'message' => 'Email requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        $user = $this->userRepository->findOneBy(['email' => $data['email']]);

        if (!$user) {
            // Pour la sécurité, on retourne un succès même si l'email n'existe pas
            return $this->json([
                'success' => true,
                'message' => 'Si cet email existe, un email de réinitialisation a été envoyé'
            ]);
        }

        // Générer le token de réinitialisation
        $user->generatePasswordResetToken();
        $this->entityManager->flush();

        // TODO: Envoyer l'email avec le lien de réinitialisation

        return $this->json([
            'success' => true,
            'message' => 'Email de réinitialisation envoyé'
        ]);
    }

    /**
     * Réinitialiser le mot de passe
     * 
     * @Route("/reset-password", name="api_auth_reset_password", methods={"POST"})
     */
    #[Route('/reset-password', name: 'api_auth_reset_password', methods: ['POST'])]
    public function resetPassword(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['token']) || empty($data['password'])) {
            return $this->json([
                'success' => false,
                'message' => 'Token et mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du mot de passe
        if (strlen($data['password']) < 8) {
            return $this->json([
                'success' => false,
                'message' => 'Le mot de passe doit contenir au moins 8 caractères'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Chercher l'utilisateur avec ce token
        $user = $this->userRepository->findOneBy(['passwordResetToken' => $data['token']]);

        if (!$user) {
            return $this->json([
                'success' => false,
                'message' => 'Token invalide'
            ], Response::HTTP_NOT_FOUND);
        }

        // Vérifier si le token n'a pas expiré
        if (!$user->isPasswordResetTokenValid($data['token'])) {
            return $this->json([
                'success' => false,
                'message' => 'Token expiré'
            ], Response::HTTP_GONE);
        }

        // Changer le mot de passe
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['password']));
        $user->clearPasswordResetToken();
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Mot de passe réinitialisé avec succès'
        ]);
    }

    /**
     * Changer le mot de passe (utilisateur connecté)
     * 
     * @Route("/change-password", name="api_auth_change_password", methods={"POST"})
     */
    #[Route('/change-password', name: 'api_auth_change_password', methods: ['POST'])]
    public function changePassword(Request $request): JsonResponse
    {
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json([
                'success' => false,
                'message' => 'Non authentifié'
            ], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (empty($data['currentPassword']) || empty($data['newPassword'])) {
            return $this->json([
                'success' => false,
                'message' => 'Ancien et nouveau mot de passe requis'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validation du nouveau mot de passe
        if (strlen($data['newPassword']) < 8) {
            return $this->json([
                'success' => false,
                'message' => 'Le nouveau mot de passe doit contenir au moins 8 caractères'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Vérifier l'ancien mot de passe
        if (!$this->passwordHasher->isPasswordValid($user, $data['currentPassword'])) {
            return $this->json([
                'success' => false,
                'message' => 'Mot de passe actuel incorrect'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Changer le mot de passe
        $user->setPassword($this->passwordHasher->hashPassword($user, $data['newPassword']));
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Mot de passe changé avec succès'
        ]);
    }

    /**
     * Déconnexion (côté client seulement - suppression du token)
     * 
     * @Route("/logout", name="api_auth_logout", methods={"POST"})
     */
    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(): JsonResponse
    {
        // Avec JWT, la déconnexion se fait côté client en supprimant le token
        // Cette route est optionnelle et peut servir pour des logs
        
        return $this->json([
            'success' => true,
            'message' => 'Déconnexion réussie'
        ]);
    }
}