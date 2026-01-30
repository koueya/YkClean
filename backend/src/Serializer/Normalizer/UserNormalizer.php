<?php

namespace App\Serializer\Normalizer;

use App\Entity\User\User;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use App\Entity\User\Admin;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Serializer\Normalizer\ObjectNormalizer;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

class UserNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'USER_NORMALIZER_ALREADY_CALLED';

    public function __construct(
        private Security $security,
        private ObjectNormalizer $objectNormalizer
    ) {
    }

    public function supportsNormalization($data, ?string $format = null, array $context = []): bool
    {
        // Éviter une boucle infinie
        if (isset($context[self::ALREADY_CALLED])) {
            return false;
        }

        return $data instanceof User;
    }

    public function normalize($object, ?string $format = null, array $context = []): array
    {
        // Marquer pour éviter la boucle infinie
        $context[self::ALREADY_CALLED] = true;

        // Obtenir les données normalisées de base
        $data = $this->objectNormalizer->normalize($object, $format, $context);

        // Déterminer le contexte de normalisation
        $groups = $context['groups'] ?? [];

        // Données communes à tous les utilisateurs
        $normalizedData = [
            'id' => $object->getId(),
            'email' => $object->getEmail(),
            'firstName' => $object->getFirstName(),
            'lastName' => $object->getLastName(),
            'fullName' => $object->getFullName(),
            'phone' => $object->getPhone(),
            'roles' => $object->getRoles(),
            'userType' => $this->getUserType($object),
            'isVerified' => $object->isVerified(),
            'isActive' => $object->isActive(),
            'createdAt' => $object->getCreatedAt()?->format('c'),
            'updatedAt' => $object->getUpdatedAt()?->format('c'),
        ];

        // Ajouter l'adresse si présente dans les groupes ou si c'est une vue détaillée
        if (in_array('user:read', $groups) || in_array('user:detail', $groups)) {
            $normalizedData['address'] = $object->getAddress();
            $normalizedData['city'] = $object->getCity();
            $normalizedData['postalCode'] = $object->getPostalCode();
        }

        // Normalisation spécifique selon le type d'utilisateur
        if ($object instanceof Client) {
            $normalizedData = array_merge($normalizedData, $this->normalizeClient($object, $groups));
        } elseif ($object instanceof Prestataire) {
            $normalizedData = array_merge($normalizedData, $this->normalizePrestataire($object, $groups));
        } elseif ($object instanceof Admin) {
            $normalizedData = array_merge($normalizedData, $this->normalizeAdmin($object, $groups));
        }

        // Filtrer les données sensibles selon l'utilisateur connecté
        $normalizedData = $this->filterSensitiveData($normalizedData, $object, $groups);

        return $normalizedData;
    }

    private function normalizeClient(Client $client, array $groups): array
    {
        $data = [];

        // Données de base du client
        if (in_array('client:read', $groups) || in_array('user:detail', $groups)) {
            $data['preferredPaymentMethod'] = $client->getPreferredPaymentMethod();
            $data['defaultAddress'] = $client->getDefaultAddress();
            
            // Informations Stripe (seulement pour le client lui-même)
            $currentUser = $this->security->getUser();
            if ($currentUser instanceof Client && $currentUser->getId() === $client->getId()) {
                $data['stripeCustomerId'] = $client->getStripeCustomerId();
                $data['hasDefaultPaymentMethod'] = $client->getDefaultPaymentMethodId() !== null;
            }
        }

        // Statistiques du client
        if (in_array('client:stats', $groups)) {
            $data['statistics'] = [
                'totalBookings' => $client->getBookings()->count(),
                'completedBookings' => $this->countBookingsByStatus($client, 'completed'),
                'cancelledBookings' => $this->countBookingsByStatus($client, 'cancelled'),
                'totalSpent' => $this->calculateTotalSpent($client),
            ];
        }

        // Réservations du client
        if (in_array('client:bookings', $groups)) {
            $bookings = [];
            foreach ($client->getBookings() as $booking) {
                $bookings[] = [
                    'id' => $booking->getId(),
                    'status' => $booking->getStatus(),
                    'scheduledDate' => $booking->getScheduledDate()?->format('Y-m-d'),
                    'scheduledTime' => $booking->getScheduledTime()?->format('H:i'),
                    'amount' => $booking->getAmount(),
                    'prestataire' => [
                        'id' => $booking->getPrestataire()->getId(),
                        'fullName' => $booking->getPrestataire()->getFullName(),
                    ],
                ];
            }
            $data['bookings'] = $bookings;
        }

        // Demandes de service
        if (in_array('client:service_requests', $groups)) {
            $serviceRequests = [];
            foreach ($client->getServiceRequests() as $request) {
                $serviceRequests[] = [
                    'id' => $request->getId(),
                    'status' => $request->getStatus(),
                    'category' => $request->getCategory()?->getName(),
                    'preferredDate' => $request->getPreferredDate()?->format('Y-m-d'),
                    'budget' => $request->getBudget(),
                    'quotesCount' => $request->getQuotes()->count(),
                ];
            }
            $data['serviceRequests'] = $serviceRequests;
        }

        // Avis laissés
        if (in_array('client:reviews', $groups)) {
            $reviews = [];
            foreach ($client->getReviews() as $review) {
                $reviews[] = [
                    'id' => $review->getId(),
                    'rating' => $review->getRating(),
                    'comment' => $review->getComment(),
                    'createdAt' => $review->getCreatedAt()?->format('c'),
                    'prestataire' => [
                        'id' => $review->getPrestataire()->getId(),
                        'fullName' => $review->getPrestataire()->getFullName(),
                    ],
                ];
            }
            $data['reviews'] = $reviews;
        }

        return $data;
    }

    private function normalizePrestataire(Prestataire $prestataire, array $groups): array
    {
        $data = [];
        $currentUser = $this->security->getUser();

        // Données de base du prestataire
        if (in_array('prestataire:read', $groups) || in_array('user:detail', $groups)) {
            $data['siret'] = $prestataire->getSiret();
            $data['hourlyRate'] = $prestataire->getHourlyRate();
            $data['radius'] = $prestataire->getRadius();
            $data['averageRating'] = $prestataire->getAverageRating();
            $data['isApproved'] = $prestataire->isApproved();
            $data['approvedAt'] = $prestataire->getApprovedAt()?->format('c');
            
            // Catégories de service
            $categories = [];
            foreach ($prestataire->getServiceCategories() as $category) {
                $categories[] = [
                    'id' => $category->getId(),
                    'name' => $category->getName(),
                ];
            }
            $data['serviceCategories'] = $categories;

            // Informations publiques
            $data['description'] = $prestataire->getDescription();
            $data['experience'] = $prestataire->getExperience();
            $data['languages'] = $prestataire->getLanguages();
        }

        // Données sensibles (seulement pour le prestataire lui-même ou les admins)
        if ($this->canAccessSensitiveData($prestataire, $currentUser)) {
            if (in_array('prestataire:private', $groups)) {
                $data['stripeConnectedAccountId'] = $prestataire->getStripeConnectedAccountId();
                $data['stripeAccountStatus'] = $prestataire->getStripeAccountStatus();
                $data['kbisDocument'] = $prestataire->getKbis();
                $data['insuranceDocument'] = $prestataire->getInsurance();
                $data['bankAccountDetails'] = $prestataire->getBankAccountDetails();
            }
        }

        // Statistiques du prestataire
        if (in_array('prestataire:stats', $groups)) {
            $data['statistics'] = [
                'totalBookings' => $prestataire->getBookings()->count(),
                'completedBookings' => $this->countBookingsByStatus($prestataire, 'completed'),
                'cancelledBookings' => $this->countBookingsByStatus($prestataire, 'cancelled'),
                'totalEarnings' => $this->calculateTotalEarnings($prestataire),
                'reviewsCount' => $prestataire->getReviews()->count(),
                'averageRating' => $prestataire->getAverageRating(),
            ];
        }

        // Réservations du prestataire
        if (in_array('prestataire:bookings', $groups)) {
            $bookings = [];
            foreach ($prestataire->getBookings() as $booking) {
                $bookings[] = [
                    'id' => $booking->getId(),
                    'status' => $booking->getStatus(),
                    'scheduledDate' => $booking->getScheduledDate()?->format('Y-m-d'),
                    'scheduledTime' => $booking->getScheduledTime()?->format('H:i'),
                    'duration' => $booking->getDuration(),
                    'amount' => $booking->getAmount(),
                    'client' => [
                        'id' => $booking->getClient()->getId(),
                        'fullName' => $booking->getClient()->getFullName(),
                        'phone' => $booking->getClient()->getPhone(),
                        'address' => $booking->getAddress(),
                    ],
                ];
            }
            $data['bookings'] = $bookings;
        }

        // Devis soumis
        if (in_array('prestataire:quotes', $groups)) {
            $quotes = [];
            foreach ($prestataire->getQuotes() as $quote) {
                $quotes[] = [
                    'id' => $quote->getId(),
                    'status' => $quote->getStatus(),
                    'amount' => $quote->getAmount(),
                    'proposedDate' => $quote->getProposedDate()?->format('Y-m-d'),
                    'validUntil' => $quote->getValidUntil()?->format('Y-m-d'),
                    'serviceRequest' => [
                        'id' => $quote->getServiceRequest()->getId(),
                        'category' => $quote->getServiceRequest()->getCategory()?->getName(),
                    ],
                ];
            }
            $data['quotes'] = $quotes;
        }

        // Disponibilités
        if (in_array('prestataire:availability', $groups)) {
            $availabilities = [];
            foreach ($prestataire->getAvailabilities() as $availability) {
                $availabilities[] = [
                    'id' => $availability->getId(),
                    'dayOfWeek' => $availability->getDayOfWeek(),
                    'startTime' => $availability->getStartTime()?->format('H:i'),
                    'endTime' => $availability->getEndTime()?->format('H:i'),
                    'isRecurring' => $availability->isRecurring(),
                    'specificDate' => $availability->getSpecificDate()?->format('Y-m-d'),
                ];
            }
            $data['availabilities'] = $availabilities;
        }

        // Avis reçus
        if (in_array('prestataire:reviews', $groups)) {
            $reviews = [];
            foreach ($prestataire->getReviews() as $review) {
                $reviews[] = [
                    'id' => $review->getId(),
                    'rating' => $review->getRating(),
                    'comment' => $review->getComment(),
                    'createdAt' => $review->getCreatedAt()?->format('c'),
                    'client' => [
                        'id' => $review->getClient()->getId(),
                        'firstName' => $review->getClient()->getFirstName(),
                        // Ne pas exposer le nom complet pour la confidentialité
                    ],
                    'booking' => [
                        'id' => $review->getBooking()->getId(),
                        'serviceCategory' => $review->getBooking()->getServiceRequest()?->getCategory()?->getName(),
                    ],
                ];
            }
            $data['reviews'] = $reviews;
        }

        return $data;
    }

    private function normalizeAdmin(Admin $admin, array $groups): array
    {
        $data = [];

        // Les admins ont des données minimales exposées
        if (in_array('admin:read', $groups)) {
            $data['department'] = $admin->getDepartment();
            $data['permissions'] = $admin->getPermissions();
        }

        return $data;
    }

    private function filterSensitiveData(array $data, User $user, array $groups): array
    {
        $currentUser = $this->security->getUser();

        // Si l'utilisateur n'est pas connecté ou n'est pas le propriétaire des données
        if (!$currentUser || $currentUser->getId() !== $user->getId()) {
            // Supprimer les données sensibles pour les autres utilisateurs
            unset(
                $data['stripeCustomerId'],
                $data['stripeConnectedAccountId'],
                $data['stripeAccountStatus'],
                $data['defaultPaymentMethodId'],
                $data['kbisDocument'],
                $data['insuranceDocument'],
                $data['bankAccountDetails']
            );

            // Ne pas exposer le numéro de téléphone complet aux non-propriétaires
            if (isset($data['phone']) && !in_array('admin:read', $groups)) {
                $data['phone'] = $this->maskPhoneNumber($data['phone']);
            }

            // Limiter les informations d'adresse
            if (!in_array('booking:detail', $groups)) {
                unset($data['address']);
            }
        }

        return $data;
    }

    private function getUserType(User $user): string
    {
        if ($user instanceof Client) {
            return 'client';
        } elseif ($user instanceof Prestataire) {
            return 'prestataire';
        } elseif ($user instanceof Admin) {
            return 'admin';
        }

        return 'user';
    }

    private function canAccessSensitiveData(Prestataire $prestataire, ?UserInterface $currentUser): bool
    {
        if (!$currentUser) {
            return false;
        }

        // Le prestataire peut accéder à ses propres données sensibles
        if ($currentUser instanceof Prestataire && 
            $currentUser->getId() === $prestataire->getId()) {
            return true;
        }

        // Les admins peuvent accéder aux données sensibles
        if ($currentUser instanceof Admin) {
            return true;
        }

        return false;
    }

    private function countBookingsByStatus(User $user, string $status): int
    {
        $count = 0;
        $bookings = [];

        if ($user instanceof Client) {
            $bookings = $user->getBookings();
        } elseif ($user instanceof Prestataire) {
            $bookings = $user->getBookings();
        }

        foreach ($bookings as $booking) {
            if ($booking->getStatus() === $status) {
                $count++;
            }
        }

        return $count;
    }

    private function calculateTotalSpent(Client $client): float
    {
        $total = 0.0;

        foreach ($client->getBookings() as $booking) {
            if ($booking->getStatus() === 'completed') {
                $total += $booking->getAmount();
            }
        }

        return round($total, 2);
    }

    private function calculateTotalEarnings(Prestataire $prestataire): float
    {
        $total = 0.0;

        foreach ($prestataire->getBookings() as $booking) {
            if ($booking->getStatus() === 'completed') {
                // Soustraire la commission de la plateforme
                $commission = $booking->getAmount() * 0.15; // 15% de commission
                $total += ($booking->getAmount() - $commission);
            }
        }

        return round($total, 2);
    }

    private function maskPhoneNumber(?string $phone): ?string
    {
        if (!$phone || strlen($phone) < 4) {
            return $phone;
        }

        // Masquer tous les chiffres sauf les 2 derniers
        $visibleDigits = 2;
        $masked = str_repeat('*', strlen($phone) - $visibleDigits);
        $visible = substr($phone, -$visibleDigits);

        return $masked . $visible;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            User::class => true,
            Client::class => true,
            Prestataire::class => true,
            Admin::class => true,
        ];
    }
}