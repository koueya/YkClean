<?php

namespace App\Service\User;

use App\Entity\User\Client;
use App\Entity\Service\ServiceRequest;
use App\Entity\Booking\Booking;
use App\Entity\Rating\Review;
use App\Repository\User\ClientRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Repository\Booking\BookingRepository;
use App\Repository\Rating\ReviewRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Psr\Log\LoggerInterface;

class ClientService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ClientRepository $clientRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private BookingRepository $bookingRepository,
        private ReviewRepository $reviewRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée un nouveau client
     */
    public function createClient(array $data): Client
    {
        $this->logger->info('Creating new client', ['email' => $data['email']]);

        // Vérifier si l'email existe déjà
        if ($this->clientRepository->findOneBy(['email' => $data['email']])) {
            throw new BadRequestHttpException('Un compte avec cet email existe déjà.');
        }

        $client = new Client();
        $client->setEmail($data['email']);
        $client->setFirstName($data['firstName']);
        $client->setLastName($data['lastName']);
        $client->setPhone($data['phone'] ?? null);
        $client->setAddress($data['address'] ?? null);
        $client->setRoles(['ROLE_CLIENT']);
        
        // Hash du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword(
            $client,
            $data['password']
        );
        $client->setPassword($hashedPassword);

        // Paramètres optionnels
        if (isset($data['preferredPaymentMethod'])) {
            $client->setPreferredPaymentMethod($data['preferredPaymentMethod']);
        }

        if (isset($data['defaultAddress'])) {
            $client->setDefaultAddress($data['defaultAddress']);
        }

        $client->setIsVerified(false);
        $client->setIsActive(true);
        $client->setCreatedAt(new \DateTimeImmutable());
        $client->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($client);
        $this->entityManager->flush();

        $this->logger->info('Client created successfully', ['clientId' => $client->getId()]);

        return $client;
    }

    /**
     * Met à jour le profil d'un client
     */
    public function updateClientProfile(int $clientId, array $data): Client
    {
        $client = $this->getClientById($clientId);

        $this->logger->info('Updating client profile', ['clientId' => $clientId]);

        // Mise à jour des champs autorisés
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

        if (isset($data['defaultAddress'])) {
            $client->setDefaultAddress($data['defaultAddress']);
        }

        if (isset($data['preferredPaymentMethod'])) {
            $client->setPreferredPaymentMethod($data['preferredPaymentMethod']);
        }

        $client->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Client profile updated successfully', ['clientId' => $clientId]);

        return $client;
    }

    /**
     * Change le mot de passe d'un client
     */
    public function changePassword(int $clientId, string $currentPassword, string $newPassword): void
    {
        $client = $this->getClientById($clientId);

        // Vérifier le mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($client, $currentPassword)) {
            throw new BadRequestHttpException('Le mot de passe actuel est incorrect.');
        }

        // Hash du nouveau mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($client, $newPassword);
        $client->setPassword($hashedPassword);
        $client->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Client password changed', ['clientId' => $clientId]);
    }

    /**
     * Récupère un client par son ID
     */
    public function getClientById(int $clientId): Client
    {
        $client = $this->clientRepository->find($clientId);

        if (!$client) {
            throw new NotFoundHttpException('Client non trouvé.');
        }

        return $client;
    }

    /**
     * Récupère un client par son email
     */
    public function getClientByEmail(string $email): ?Client
    {
        return $this->clientRepository->findOneBy(['email' => $email]);
    }

    /**
     * Récupère toutes les demandes de service d'un client
     */
    public function getClientServiceRequests(int $clientId, array $filters = []): array
    {
        $client = $this->getClientById($clientId);

        $queryBuilder = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->where('sr.client = :client')
            ->setParameter('client', $client)
            ->orderBy('sr.createdAt', 'DESC');

        // Filtres optionnels
        if (isset($filters['status'])) {
            $queryBuilder->andWhere('sr.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['category'])) {
            $queryBuilder->andWhere('sr.category = :category')
                ->setParameter('category', $filters['category']);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère toutes les réservations d'un client
     */
    public function getClientBookings(int $clientId, array $filters = []): array
    {
        $client = $this->getClientById($clientId);

        $queryBuilder = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->orderBy('b.scheduledDate', 'DESC');

        // Filtres optionnels
        if (isset($filters['status'])) {
            $queryBuilder->andWhere('b.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (isset($filters['from_date'])) {
            $queryBuilder->andWhere('b.scheduledDate >= :fromDate')
                ->setParameter('fromDate', new \DateTime($filters['from_date']));
        }

        if (isset($filters['to_date'])) {
            $queryBuilder->andWhere('b.scheduledDate <= :toDate')
                ->setParameter('toDate', new \DateTime($filters['to_date']));
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère les réservations à venir d'un client
     */
    public function getUpcomingBookings(int $clientId, int $limit = 10): array
    {
        $client = $this->getClientById($clientId);

        return $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->orderBy('b.scheduledDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère l'historique des réservations d'un client
     */
    public function getBookingHistory(int $clientId, int $page = 1, int $limit = 20): array
    {
        $client = $this->getClientById($clientId);

        $offset = ($page - 1) * $limit;

        $bookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['completed', 'cancelled'])
            ->orderBy('b.scheduledDate', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.client = :client')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['completed', 'cancelled'])
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'bookings' => $bookings,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }

    /**
     * Récupère les avis laissés par un client
     */
    public function getClientReviews(int $clientId): array
    {
        $client = $this->getClientById($clientId);

        return $this->reviewRepository->createQueryBuilder('r')
            ->where('r.client = :client')
            ->setParameter('client', $client)
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les statistiques d'un client
     */
    public function getClientStatistics(int $clientId): array
    {
        $client = $this->getClientById($clientId);

        // Nombre total de réservations
        $totalBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.client = :client')
            ->setParameter('client', $client)
            ->getQuery()
            ->getSingleScalarResult();

        // Réservations complétées
        $completedBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.client = :client')
            ->andWhere('b.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Réservations annulées
        $cancelledBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.client = :client')
            ->andWhere('b.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        // Montant total dépensé
        $totalSpent = $this->bookingRepository->createQueryBuilder('b')
            ->select('SUM(b.amount)')
            ->where('b.client = :client')
            ->andWhere('b.status = :status')
            ->setParameter('client', $client)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Demandes de service actives
        $activeRequests = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.client = :client')
            ->andWhere('sr.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['open', 'quoted'])
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalBookings' => $totalBookings,
            'completedBookings' => $completedBookings,
            'cancelledBookings' => $cancelledBookings,
            'totalSpent' => (float) $totalSpent,
            'activeRequests' => $activeRequests,
            'memberSince' => $client->getCreatedAt()->format('Y-m-d'),
        ];
    }

    /**
     * Vérifie si un client peut créer une nouvelle demande de service
     */
    public function canCreateServiceRequest(int $clientId): bool
    {
        $client = $this->getClientById($clientId);

        // Vérifier si le client est actif et vérifié
        if (!$client->getIsActive()) {
            return false;
        }

        // Vérifier le nombre de demandes actives (limite à 5)
        $activeRequests = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.client = :client')
            ->andWhere('sr.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['open', 'quoted'])
            ->getQuery()
            ->getSingleScalarResult();

        return $activeRequests < 5;
    }

    /**
     * Désactive un compte client
     */
    public function deactivateClient(int $clientId, string $reason = null): void
    {
        $client = $this->getClientById($clientId);

        $this->logger->info('Deactivating client account', [
            'clientId' => $clientId,
            'reason' => $reason
        ]);

        $client->setIsActive(false);
        $client->setUpdatedAt(new \DateTimeImmutable());

        // Annuler toutes les réservations futures
        $futureBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.client = :client')
            ->andWhere('b.scheduledDate > :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->getQuery()
            ->getResult();

        foreach ($futureBookings as $booking) {
            $booking->setStatus('cancelled');
        }

        $this->entityManager->flush();

        $this->logger->info('Client account deactivated', ['clientId' => $clientId]);
    }

    /**
     * Réactive un compte client
     */
    public function reactivateClient(int $clientId): void
    {
        $client = $this->getClientById($clientId);

        $this->logger->info('Reactivating client account', ['clientId' => $clientId]);

        $client->setIsActive(true);
        $client->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Client account reactivated', ['clientId' => $clientId]);
    }

    /**
     * Supprime définitivement un client (RGPD)
     */
    public function deleteClient(int $clientId): void
    {
        $client = $this->getClientById($clientId);

        $this->logger->warning('Deleting client account permanently', ['clientId' => $clientId]);

        // Vérifier qu'il n'y a pas de réservations actives
        $activeBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.client = :client')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('client', $client)
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->getQuery()
            ->getSingleScalarResult();

        if ($activeBookings > 0) {
            throw new BadRequestHttpException(
                'Impossible de supprimer le compte : des réservations actives existent.'
            );
        }

        // Anonymiser les données au lieu de supprimer (RGPD)
        $client->setEmail('deleted_' . $clientId . '@deleted.com');
        $client->setFirstName('Utilisateur');
        $client->setLastName('Supprimé');
        $client->setPhone(null);
        $client->setAddress(null);
        $client->setDefaultAddress(null);
        $client->setIsActive(false);

        $this->entityManager->flush();

        $this->logger->warning('Client account deleted/anonymized', ['clientId' => $clientId]);
    }

    /**
     * Recherche des clients
     */
    public function searchClients(array $criteria, int $page = 1, int $limit = 20): array
    {
        $queryBuilder = $this->clientRepository->createQueryBuilder('c');
        $offset = ($page - 1) * $limit;

        // Recherche par nom ou email
        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $queryBuilder->andWhere(
                'c.firstName LIKE :search OR c.lastName LIKE :search OR c.email LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        // Filtrer par statut
        if (isset($criteria['isActive'])) {
            $queryBuilder->andWhere('c.isActive = :isActive')
                ->setParameter('isActive', $criteria['isActive']);
        }

        if (isset($criteria['isVerified'])) {
            $queryBuilder->andWhere('c.isVerified = :isVerified')
                ->setParameter('isVerified', $criteria['isVerified']);
        }

        $clients = $queryBuilder
            ->orderBy('c.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = $this->clientRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'clients' => $clients,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }
}