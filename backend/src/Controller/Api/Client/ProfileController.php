<?php

namespace App\Controller\Api\Client;

use App\Entity\User\Client;
use App\Repository\User\ClientRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;

#[Route('/api/client/profile')]
#[IsGranted('ROLE_CLIENT')]
class ProfileController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientRepository $clientRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private ValidatorInterface $validator,
        private SerializerInterface $serializer
    ) {
    }

    /**
     * Get current client profile
     */
    #[Route('', name: 'api_client_profile_get', methods: ['GET'])]
    public function getProfile(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        return $this->json([
            'success' => true,
            'data' => [
                'id' => $client->getId(),
                'email' => $client->getEmail(),
                'firstName' => $client->getFirstName(),
                'lastName' => $client->getLastName(),
                'phone' => $client->getPhone(),
                'address' => $client->getAddress(),
                'preferredPaymentMethod' => $client->getPreferredPaymentMethod(),
                'defaultAddress' => $client->getDefaultAddress(),
                'isVerified' => $client->isVerified(),
                'isActive' => $client->isActive(),
                'createdAt' => $client->getCreatedAt()?->format('c'),
                'updatedAt' => $client->getUpdatedAt()?->format('c'),
            ]
        ]);
    }

    /**
     * Update client profile
     */
    #[Route('', name: 'api_client_profile_update', methods: ['PUT', 'PATCH'])]
    public function updateProfile(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            return $this->json([
                'error' => 'Invalid JSON format'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Update allowed fields
        if (isset($data['firstName'])) {
            $client->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $client->setLastName($data['lastName']);
        }

        if (isset($data['phone'])) {
            $client->setPhone($data['phone']);
        }

        if (isset($data['address'])) {
            $client->setAddress($data['address']);
        }

        if (isset($data['preferredPaymentMethod'])) {
            $client->setPreferredPaymentMethod($data['preferredPaymentMethod']);
        }

        if (isset($data['defaultAddress'])) {
            $client->setDefaultAddress($data['defaultAddress']);
        }

        // Validate entity
        $errors = $this->validator->validate($client);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Validation failed',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $client->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'data' => [
                'id' => $client->getId(),
                'email' => $client->getEmail(),
                'firstName' => $client->getFirstName(),
                'lastName' => $client->getLastName(),
                'phone' => $client->getPhone(),
                'address' => $client->getAddress(),
                'preferredPaymentMethod' => $client->getPreferredPaymentMethod(),
                'defaultAddress' => $client->getDefaultAddress(),
                'updatedAt' => $client->getUpdatedAt()?->format('c'),
            ]
        ]);
    }

    /**
     * Update email
     */
    #[Route('/email', name: 'api_client_profile_update_email', methods: ['PUT'])]
    public function updateEmail(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['email']) || !isset($data['currentPassword'])) {
            return $this->json([
                'error' => 'Email and current password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($client, $data['currentPassword'])) {
            return $this->json([
                'error' => 'Current password is incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Check if email is already taken
        $existingUser = $this->clientRepository->findOneBy(['email' => $data['email']]);
        if ($existingUser && $existingUser->getId() !== $client->getId()) {
            return $this->json([
                'error' => 'This email is already in use'
            ], Response::HTTP_CONFLICT);
        }

        $client->setEmail($data['email']);
        $client->setIsVerified(false); // Require email verification again
        $client->setUpdatedAt(new \DateTimeImmutable());

        // Validate
        $errors = $this->validator->validate($client);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }

            return $this->json([
                'error' => 'Validation failed',
                'details' => $errorMessages
            ], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->flush();

        // TODO: Send verification email

        return $this->json([
            'success' => true,
            'message' => 'Email updated successfully. Please verify your new email address.',
            'data' => [
                'email' => $client->getEmail(),
                'isVerified' => $client->isVerified()
            ]
        ]);
    }

    /**
     * Update password
     */
    #[Route('/password', name: 'api_client_profile_update_password', methods: ['PUT'])]
    public function updatePassword(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['currentPassword']) || !isset($data['newPassword'])) {
            return $this->json([
                'error' => 'Current password and new password are required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify current password
        if (!$this->passwordHasher->isPasswordValid($client, $data['currentPassword'])) {
            return $this->json([
                'error' => 'Current password is incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Validate new password strength
        if (strlen($data['newPassword']) < 8) {
            return $this->json([
                'error' => 'New password must be at least 8 characters long'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Hash and set new password
        $hashedPassword = $this->passwordHasher->hashPassword($client, $data['newPassword']);
        $client->setPassword($hashedPassword);
        $client->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    }

    /**
     * Delete account (soft delete)
     */
    #[Route('', name: 'api_client_profile_delete', methods: ['DELETE'])]
    public function deleteAccount(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['password'])) {
            return $this->json([
                'error' => 'Password confirmation is required'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($client, $data['password'])) {
            return $this->json([
                'error' => 'Password is incorrect'
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Soft delete - deactivate account
        $client->setIsActive(false);
        $client->setUpdatedAt(new \DateTimeImmutable());

        // TODO: Check for active bookings before deletion
        // TODO: Cancel all pending service requests
        // TODO: Send account deletion confirmation email

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'Account has been deactivated successfully'
        ]);
    }

    /**
     * Get account statistics
     */
    #[Route('/stats', name: 'api_client_profile_stats', methods: ['GET'])]
    public function getStats(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        // TODO: Implement real statistics queries
        // For now, returning mock structure
        $stats = [
            'totalServiceRequests' => 0,
            'activeBookings' => 0,
            'completedBookings' => 0,
            'cancelledBookings' => 0,
            'totalSpent' => 0,
            'averageRating' => 0,
            'reviewsGiven' => 0,
        ];

        return $this->json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Upload profile picture
     */
    #[Route('/picture', name: 'api_client_profile_picture', methods: ['POST'])]
    public function uploadProfilePicture(Request $request): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        $uploadedFile = $request->files->get('picture');

        if (!$uploadedFile) {
            return $this->json([
                'error' => 'No file uploaded'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate file type
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/jpg', 'image/webp'];
        if (!in_array($uploadedFile->getMimeType(), $allowedMimeTypes)) {
            return $this->json([
                'error' => 'Invalid file type. Only JPEG, PNG, and WebP images are allowed.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // Validate file size (max 5MB)
        $maxSize = 5 * 1024 * 1024;
        if ($uploadedFile->getSize() > $maxSize) {
            return $this->json([
                'error' => 'File too large. Maximum size is 5MB.'
            ], Response::HTTP_BAD_REQUEST);
        }

        // TODO: Implement file upload logic
        // - Generate unique filename
        // - Move file to storage
        // - Resize/optimize image
        // - Update client entity with picture URL
        // - Delete old picture if exists

        return $this->json([
            'success' => true,
            'message' => 'Profile picture uploaded successfully',
            'data' => [
                'pictureUrl' => '/uploads/profiles/placeholder.jpg' // TODO: Replace with actual URL
            ]
        ]);
    }

    /**
     * Delete profile picture
     */
    #[Route('/picture', name: 'api_client_profile_picture_delete', methods: ['DELETE'])]
    public function deleteProfilePicture(): JsonResponse
    {
        /** @var Client $client */
        $client = $this->getUser();

        if (!$client instanceof Client) {
            return $this->json([
                'error' => 'User is not a client'
            ], Response::HTTP_FORBIDDEN);
        }

        // TODO: Implement picture deletion logic
        // - Delete file from storage
        // - Update client entity

        return $this->json([
            'success' => true,
            'message' => 'Profile picture deleted successfully'
        ]);
    }
}