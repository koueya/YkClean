<?php

namespace App\Service\User;

use App\Entity\User\Prestataire;
use App\Entity\Service\ServiceRequest;
use App\Entity\Booking\Booking;
use App\Entity\Planning\Availability;
use App\Entity\Rating\Review;
use App\Repository\User\PrestataireRepository;
use App\Repository\Service\ServiceRequestRepository;
use App\Repository\Booking\BookingRepository;
use App\Repository\Planning\AvailabilityRepository;
use App\Repository\Rating\ReviewRepository;
use App\Repository\Payment\PaymentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Psr\Log\LoggerInterface;

class PrestataireService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PrestataireRepository $prestataireRepository,
        private ServiceRequestRepository $serviceRequestRepository,
        private BookingRepository $bookingRepository,
        private AvailabilityRepository $availabilityRepository,
        private ReviewRepository $reviewRepository,
        private PaymentRepository $paymentRepository,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée un nouveau prestataire
     */
    public function createPrestataire(array $data): Prestataire
    {
        $this->logger->info('Creating new prestataire', ['email' => $data['email']]);

        // Vérifier si l'email existe déjà
        if ($this->prestataireRepository->findOneBy(['email' => $data['email']])) {
            throw new BadRequestHttpException('Un compte avec cet email existe déjà.');
        }

        $prestataire = new Prestataire();
        $prestataire->setEmail($data['email']);
        $prestataire->setFirstName($data['firstName']);
        $prestataire->setLastName($data['lastName']);
        $prestataire->setPhone($data['phone'] ?? null);
        $prestataire->setAddress($data['address'] ?? null);
        $prestataire->setRoles(['ROLE_PRESTATAIRE']);
        
        // Hash du mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword(
            $prestataire,
            $data['password']
        );
        $prestataire->setPassword($hashedPassword);

        // Informations professionnelles
        if (isset($data['siret'])) {
            $prestataire->setSiret($data['siret']);
        }

        if (isset($data['serviceCategories'])) {
            $prestataire->setServiceCategories($data['serviceCategories']);
        }

        if (isset($data['hourlyRate'])) {
            $prestataire->setHourlyRate($data['hourlyRate']);
        }

        if (isset($data['radius'])) {
            $prestataire->setRadius($data['radius']);
        }

        $prestataire->setIsVerified(false);
        $prestataire->setIsActive(true);
        $prestataire->setIsApproved(false);
        $prestataire->setAverageRating(0);
        $prestataire->setCreatedAt(new \DateTimeImmutable());
        $prestataire->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->persist($prestataire);
        $this->entityManager->flush();

        $this->logger->info('Prestataire created successfully', ['prestataireId' => $prestataire->getId()]);

        return $prestataire;
    }

    /**
     * Met à jour le profil d'un prestataire
     */
    public function updatePrestataireProfile(int $prestataireId, array $data): Prestataire
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $this->logger->info('Updating prestataire profile', ['prestataireId' => $prestataireId]);

        // Mise à jour des champs autorisés
        if (isset($data['firstName'])) {
            $prestataire->setFirstName($data['firstName']);
        }

        if (isset($data['lastName'])) {
            $prestataire->setLastName($data['lastName']);
        }

        if (isset($data['phone'])) {
            $prestataire->setPhone($data['phone']);
        }

        if (isset($data['address'])) {
            $prestataire->setAddress($data['address']);
        }

        if (isset($data['serviceCategories'])) {
            $prestataire->setServiceCategories($data['serviceCategories']);
        }

        if (isset($data['hourlyRate'])) {
            $prestataire->setHourlyRate($data['hourlyRate']);
        }

        if (isset($data['radius'])) {
            $prestataire->setRadius($data['radius']);
        }

        if (isset($data['bio'])) {
            $prestataire->setBio($data['bio']);
        }

        $prestataire->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Prestataire profile updated successfully', ['prestataireId' => $prestataireId]);

        return $prestataire;
    }

    /**
     * Upload des documents professionnels (KBIS, assurance)
     */
    public function uploadDocument(int $prestataireId, string $documentType, string $filePath): Prestataire
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $this->logger->info('Uploading document for prestataire', [
            'prestataireId' => $prestataireId,
            'documentType' => $documentType
        ]);

        switch ($documentType) {
            case 'kbis':
                $prestataire->setKbis($filePath);
                break;
            case 'insurance':
                $prestataire->setInsurance($filePath);
                break;
            default:
                throw new BadRequestHttpException('Type de document invalide.');
        }

        $prestataire->setUpdatedAt(new \DateTimeImmutable());
        $this->entityManager->flush();

        return $prestataire;
    }

    /**
     * Approuve un prestataire (admin uniquement)
     */
    public function approvePrestataire(int $prestataireId): Prestataire
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $this->logger->info('Approving prestataire', ['prestataireId' => $prestataireId]);

        // Vérifier que tous les documents requis sont présents
        if (!$prestataire->getKbis() || !$prestataire->getInsurance()) {
            throw new BadRequestHttpException('Documents requis manquants (KBIS et assurance).');
        }

        $prestataire->setIsApproved(true);
        $prestataire->setApprovedAt(new \DateTimeImmutable());
        $prestataire->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Prestataire approved successfully', ['prestataireId' => $prestataireId]);

        return $prestataire;
    }

    /**
     * Refuse ou révoque l'approbation d'un prestataire
     */
    public function rejectPrestataire(int $prestataireId, string $reason = null): Prestataire
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $this->logger->info('Rejecting prestataire', [
            'prestataireId' => $prestataireId,
            'reason' => $reason
        ]);

        $prestataire->setIsApproved(false);
        $prestataire->setApprovedAt(null);
        $prestataire->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        return $prestataire;
    }

    /**
     * Change le mot de passe d'un prestataire
     */
    public function changePassword(int $prestataireId, string $currentPassword, string $newPassword): void
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        // Vérifier le mot de passe actuel
        if (!$this->passwordHasher->isPasswordValid($prestataire, $currentPassword)) {
            throw new BadRequestHttpException('Le mot de passe actuel est incorrect.');
        }

        // Hash du nouveau mot de passe
        $hashedPassword = $this->passwordHasher->hashPassword($prestataire, $newPassword);
        $prestataire->setPassword($hashedPassword);
        $prestataire->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Prestataire password changed', ['prestataireId' => $prestataireId]);
    }

    /**
     * Récupère un prestataire par son ID
     */
    public function getPrestataireById(int $prestataireId): Prestataire
    {
        $prestataire = $this->prestataireRepository->find($prestataireId);

        if (!$prestataire) {
            throw new NotFoundHttpException('Prestataire non trouvé.');
        }

        return $prestataire;
    }

    /**
     * Récupère un prestataire par son email
     */
    public function getPrestataireByEmail(string $email): ?Prestataire
    {
        return $this->prestataireRepository->findOneBy(['email' => $email]);
    }

    /**
     * Récupère les demandes de service disponibles pour un prestataire
     */
    public function getAvailableServiceRequests(int $prestataireId, array $filters = []): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        // Vérifier que le prestataire est approuvé
        if (!$prestataire->getIsApproved()) {
            throw new AccessDeniedHttpException('Votre compte doit être approuvé pour voir les demandes.');
        }

        $queryBuilder = $this->serviceRequestRepository->createQueryBuilder('sr')
            ->where('sr.status = :status')
            ->setParameter('status', 'open')
            ->orderBy('sr.createdAt', 'DESC');

        // Filtrer par catégories de service du prestataire
        $categories = $prestataire->getServiceCategories();
        if ($categories && count($categories) > 0) {
            $queryBuilder->andWhere('sr.category IN (:categories)')
                ->setParameter('categories', $categories);
        }

        // TODO: Ajouter le filtrage géographique basé sur le rayon d'intervention
        // Cela nécessiterait des coordonnées géographiques et un calcul de distance

        // Filtres optionnels
        if (isset($filters['category'])) {
            $queryBuilder->andWhere('sr.category = :filterCategory')
                ->setParameter('filterCategory', $filters['category']);
        }

        if (isset($filters['min_budget'])) {
            $queryBuilder->andWhere('sr.budget >= :minBudget')
                ->setParameter('minBudget', $filters['min_budget']);
        }

        if (isset($filters['max_budget'])) {
            $queryBuilder->andWhere('sr.budget <= :maxBudget')
                ->setParameter('maxBudget', $filters['max_budget']);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Récupère toutes les réservations d'un prestataire
     */
    public function getPrestataireBookings(int $prestataireId, array $filters = []): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $queryBuilder = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
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
     * Récupère les réservations à venir d'un prestataire
     */
    public function getUpcomingBookings(int $prestataireId, int $limit = 10): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        return $this->bookingRepository->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate >= :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->orderBy('b.scheduledDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les réservations du jour pour un prestataire
     */
    public function getTodayBookings(int $prestataireId): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);
        
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        return $this->bookingRepository->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate >= :today')
            ->andWhere('b.scheduledDate < :tomorrow')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('today', $today)
            ->setParameter('tomorrow', $tomorrow)
            ->setParameter('statuses', ['scheduled', 'confirmed', 'in_progress'])
            ->orderBy('b.scheduledTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les disponibilités d'un prestataire
     */
    public function getAvailabilities(int $prestataireId): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        return $this->availabilityRepository->createQueryBuilder('a')
            ->where('a.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('a.dayOfWeek', 'ASC')
            ->addOrderBy('a.startTime', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Récupère les avis reçus par un prestataire
     */
    public function getReviews(int $prestataireId, int $page = 1, int $limit = 20): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);
        $offset = ($page - 1) * $limit;

        $reviews = $this->reviewRepository->createQueryBuilder('r')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = $this->reviewRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'reviews' => $reviews,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }

    /**
     * Met à jour la note moyenne d'un prestataire
     */
    public function updateAverageRating(int $prestataireId): void
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $averageRating = $this->reviewRepository->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        $prestataire->setAverageRating($averageRating ?? 0);
        $this->entityManager->flush();

        $this->logger->info('Updated average rating for prestataire', [
            'prestataireId' => $prestataireId,
            'averageRating' => $averageRating
        ]);
    }

    /**
     * Récupère les statistiques d'un prestataire
     */
    public function getPrestataireStatistics(int $prestataireId): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        // Nombre total de réservations
        $totalBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        // Réservations complétées
        $completedBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult();

        // Réservations annulées
        $cancelledBookings = $this->bookingRepository->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'cancelled')
            ->getQuery()
            ->getSingleScalarResult();

        // Revenus totaux
        $totalEarnings = $this->bookingRepository->createQueryBuilder('b')
            ->select('SUM(b.amount)')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'completed')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Revenus du mois en cours
        $firstDayOfMonth = new \DateTime('first day of this month');
        $monthlyEarnings = $this->bookingRepository->createQueryBuilder('b')
            ->select('SUM(b.amount)')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->andWhere('b.scheduledDate >= :firstDay')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'completed')
            ->setParameter('firstDay', $firstDayOfMonth)
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        // Nombre d'avis
        $totalReviews = $this->reviewRepository->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.prestataire = :prestataire')
            ->setParameter('prestataire', $prestataire)
            ->getQuery()
            ->getSingleScalarResult();

        // Taux de complétion
        $completionRate = $totalBookings > 0 
            ? round(($completedBookings / $totalBookings) * 100, 2) 
            : 0;

        return [
            'totalBookings' => $totalBookings,
            'completedBookings' => $completedBookings,
            'cancelledBookings' => $cancelledBookings,
            'completionRate' => $completionRate,
            'totalEarnings' => (float) $totalEarnings,
            'monthlyEarnings' => (float) $monthlyEarnings,
            'averageRating' => $prestataire->getAverageRating(),
            'totalReviews' => $totalReviews,
            'isApproved' => $prestataire->getIsApproved(),
            'memberSince' => $prestataire->getCreatedAt()->format('Y-m-d'),
        ];
    }

    /**
     * Récupère les revenus détaillés par période
     */
    public function getEarningsByPeriod(int $prestataireId, string $period = 'month'): array
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        switch ($period) {
            case 'week':
                $startDate = new \DateTime('monday this week');
                $groupBy = 'DATE(b.scheduledDate)';
                break;
            case 'year':
                $startDate = new \DateTime('first day of january this year');
                $groupBy = 'MONTH(b.scheduledDate)';
                break;
            case 'month':
            default:
                $startDate = new \DateTime('first day of this month');
                $groupBy = 'DATE(b.scheduledDate)';
                break;
        }

        $earnings = $this->bookingRepository->createQueryBuilder('b')
            ->select('DATE(b.scheduledDate) as date, SUM(b.amount) as total')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.status = :status')
            ->andWhere('b.scheduledDate >= :startDate')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('status', 'completed')
            ->setParameter('startDate', $startDate)
            ->groupBy('date')
            ->orderBy('date', 'ASC')
            ->getQuery()
            ->getResult();

        return $earnings;
    }

    /**
     * Vérifie si un prestataire est disponible à une date/heure donnée
     */
    public function isAvailable(int $prestataireId, \DateTime $date, string $startTime, int $duration): bool
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        // Vérifier s'il y a des réservations existantes qui se chevauchent
        $endTime = (clone $date)->setTime(
            (int) explode(':', $startTime)[0],
            (int) explode(':', $startTime)[1]
        )->modify("+{$duration} minutes");

        $conflictingBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate = :date')
            ->andWhere('b.status IN (:statuses)')
            ->andWhere(
                '(b.scheduledTime < :endTime AND ' .
                'DATE_ADD(b.scheduledTime, b.duration, \'MINUTE\') > :startTime)'
            )
            ->setParameter('prestataire', $prestataire)
            ->setParameter('date', $date->format('Y-m-d'))
            ->setParameter('statuses', ['scheduled', 'confirmed', 'in_progress'])
            ->setParameter('startTime', $startTime)
            ->setParameter('endTime', $endTime->format('H:i:s'))
            ->getQuery()
            ->getResult();

        return count($conflictingBookings) === 0;
    }

    /**
     * Désactive un compte prestataire
     */
    public function deactivatePrestataire(int $prestataireId, string $reason = null): void
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $this->logger->info('Deactivating prestataire account', [
            'prestataireId' => $prestataireId,
            'reason' => $reason
        ]);

        $prestataire->setIsActive(false);
        $prestataire->setUpdatedAt(new \DateTimeImmutable());

        // Annuler toutes les réservations futures
        $futureBookings = $this->bookingRepository->createQueryBuilder('b')
            ->where('b.prestataire = :prestataire')
            ->andWhere('b.scheduledDate > :now')
            ->andWhere('b.status IN (:statuses)')
            ->setParameter('prestataire', $prestataire)
            ->setParameter('now', new \DateTime())
            ->setParameter('statuses', ['scheduled', 'confirmed'])
            ->getQuery()
            ->getResult();

        foreach ($futureBookings as $booking) {
            $booking->setStatus('cancelled');
        }

        $this->entityManager->flush();

        $this->logger->info('Prestataire account deactivated', ['prestataireId' => $prestataireId]);
    }

    /**
     * Réactive un compte prestataire
     */
    public function reactivatePrestataire(int $prestataireId): void
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        $this->logger->info('Reactivating prestataire account', ['prestataireId' => $prestataireId]);

        $prestataire->setIsActive(true);
        $prestataire->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Prestataire account reactivated', ['prestataireId' => $prestataireId]);
    }

    /**
     * Recherche de prestataires
     */
    public function searchPrestataires(array $criteria, int $page = 1, int $limit = 20): array
    {
        $queryBuilder = $this->prestataireRepository->createQueryBuilder('p');
        $offset = ($page - 1) * $limit;

        // Recherche par nom ou email
        if (isset($criteria['search'])) {
            $search = $criteria['search'];
            $queryBuilder->andWhere(
                'p.firstName LIKE :search OR p.lastName LIKE :search OR p.email LIKE :search'
            )->setParameter('search', '%' . $search . '%');
        }

        // Filtrer par statut
        if (isset($criteria['isActive'])) {
            $queryBuilder->andWhere('p.isActive = :isActive')
                ->setParameter('isActive', $criteria['isActive']);
        }

        if (isset($criteria['isApproved'])) {
            $queryBuilder->andWhere('p.isApproved = :isApproved')
                ->setParameter('isApproved', $criteria['isApproved']);
        }

        // Filtrer par catégorie de service
        if (isset($criteria['category'])) {
            $queryBuilder->andWhere('p.serviceCategories LIKE :category')
                ->setParameter('category', '%' . $criteria['category'] . '%');
        }

        // Filtrer par note minimale
        if (isset($criteria['minRating'])) {
            $queryBuilder->andWhere('p.averageRating >= :minRating')
                ->setParameter('minRating', $criteria['minRating']);
        }

        $prestataires = $queryBuilder
            ->orderBy('p.averageRating', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $total = $this->prestataireRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'prestataires' => $prestataires,
            'total' => $total,
            'page' => $page,
            'limit' => $limit,
            'totalPages' => ceil($total / $limit)
        ];
    }

    /**
     * Récupère les prestataires les mieux notés
     */
    public function getTopRatedPrestataires(int $limit = 10): array
    {
        return $this->prestataireRepository->createQueryBuilder('p')
            ->where('p.isApproved = :approved')
            ->andWhere('p.isActive = :active')
            ->andWhere('p.averageRating > 0')
            ->setParameter('approved', true)
            ->setParameter('active', true)
            ->orderBy('p.averageRating', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Vérifie si un prestataire peut accepter une nouvelle réservation
     */
    public function canAcceptBooking(int $prestataireId): bool
    {
        $prestataire = $this->getPrestataireById($prestataireId);

        // Vérifier si le prestataire est actif et approuvé
        if (!$prestataire->getIsActive() || !$prestataire->getIsApproved()) {
            return false;
        }

        return true;
    }
}