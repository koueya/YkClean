<?php

namespace App\Service\Replacement;

use App\Entity\Booking\Booking;
use App\Entity\Planning\Replacement;
use App\Entity\User\Prestataire;
use App\Repository\Planning\ReplacementRepository;
use App\Repository\User\PrestataireRepository;
use App\Service\Planning\AvailabilityService;
use App\Service\Notification\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service de gestion des remplacements de prestataires
 * Gère les demandes, recherche et assignation de remplaçants
 */
class ReplacementService
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ReplacementRepository $replacementRepository,
        private PrestataireRepository $prestataireRepository,
        private AvailabilityService $availabilityService,
        private NotificationService $notificationService,
        private LoggerInterface $logger
    ) {}

    /**
     * Crée une demande de remplacement pour une réservation
     */
    public function requestReplacement(
        Booking $booking,
        Prestataire $originalPrestataire,
        string $reason
    ): Replacement {
        // Vérifier qu'il n'y a pas déjà une demande de remplacement active
        $existingReplacement = $this->replacementRepository->findOneBy([
            'booking' => $booking,
            'status' => ['pending', 'accepted'],
        ]);

        if ($existingReplacement) {
            throw new \RuntimeException('Une demande de remplacement existe déjà pour cette réservation');
        }

        $replacement = new Replacement();
        $replacement->setBooking($booking);
        $replacement->setOriginalPrestataire($originalPrestataire);
        $replacement->setReason($reason);
        $replacement->setStatus('pending');
        $replacement->setRequestedAt(new \DateTimeImmutable());

        $this->entityManager->persist($replacement);
        $this->entityManager->flush();

        $this->logger->info('Replacement requested', [
            'replacement_id' => $replacement->getId(),
            'booking_id' => $booking->getId(),
            'original_prestataire_id' => $originalPrestataire->getId(),
            'reason' => $reason,
        ]);

        // Notifier le client
        $this->notificationService->notifyReplacementRequested($replacement);

        return $replacement;
    }

    /**
     * Recherche des prestataires disponibles pour un remplacement
     */
    public function findAvailableReplacements(
        Booking $booking,
        ?int $maxDistance = null
    ): array {
        $scheduledDateTime = $booking->getScheduledDateTime();
        $endDateTime = $booking->getEndDateTime();
        $duration = $booking->getDuration();
        $serviceCategory = $booking->getServiceRequest()->getCategory();

        // Récupérer tous les prestataires de la même catégorie
        $prestataires = $this->prestataireRepository->findByServiceCategory($serviceCategory);

        $availablePrestataires = [];

        foreach ($prestataires as $prestataire) {
            // Ne pas inclure le prestataire original
            if ($prestataire->getId() === $booking->getPrestataire()->getId()) {
                continue;
            }

            // Vérifier que le prestataire est approuvé et actif
            if (!$prestataire->isApproved() || !$prestataire->isActive()) {
                continue;
            }

            // Vérifier la distance si un rayon max est spécifié
            if ($maxDistance !== null) {
                $distance = $this->calculateDistance(
                    $prestataire,
                    $booking->getLatitude(),
                    $booking->getLongitude()
                );

                if ($distance > $maxDistance) {
                    continue;
                }
            }

            // Vérifier la disponibilité
            $isAvailable = $this->availabilityService->isAvailable(
                $prestataire,
                $scheduledDateTime,
                $duration
            );

            if ($isAvailable) {
                $availablePrestataires[] = [
                    'prestataire' => $prestataire,
                    'distance' => $this->calculateDistance(
                        $prestataire,
                        $booking->getLatitude(),
                        $booking->getLongitude()
                    ),
                    'rating' => $prestataire->getAverageRating(),
                    'completed_bookings' => $prestataire->getCompletedBookingsCount(),
                ];
            }
        }

        // Trier par pertinence (distance, note, expérience)
        usort($availablePrestataires, function ($a, $b) {
            // Score composite : distance (40%), note (40%), expérience (20%)
            $scoreA = (1 / ($a['distance'] + 1)) * 0.4 
                    + $a['rating'] * 0.4 
                    + min($a['completed_bookings'] / 100, 1) * 0.2;
            
            $scoreB = (1 / ($b['distance'] + 1)) * 0.4 
                    + $b['rating'] * 0.4 
                    + min($b['completed_bookings'] / 100, 1) * 0.2;

            return $scoreB <=> $scoreA;
        });

        return $availablePrestataires;
    }

    /**
     * Propose un remplacement à un prestataire
     */
    public function proposeReplacement(
        Replacement $replacement,
        Prestataire $replacementPrestataire
    ): void {
        if ($replacement->getStatus() !== 'pending') {
            throw new \RuntimeException('Ce remplacement n\'est pas en attente');
        }

        $replacement->setReplacementPrestataire($replacementPrestataire);
        $replacement->setProposedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Replacement proposed', [
            'replacement_id' => $replacement->getId(),
            'replacement_prestataire_id' => $replacementPrestataire->getId(),
        ]);

        // Notifier le prestataire remplaçant
        $this->notificationService->notifyReplacementProposed($replacement);
    }

    /**
     * Accepte un remplacement (par le prestataire remplaçant)
     */
    public function acceptReplacement(Replacement $replacement): void
    {
        if ($replacement->getStatus() !== 'pending') {
            throw new \RuntimeException('Ce remplacement ne peut pas être accepté');
        }

        if (!$replacement->getReplacementPrestataire()) {
            throw new \RuntimeException('Aucun prestataire remplaçant n\'a été proposé');
        }

        $replacement->setStatus('accepted');
        $replacement->setAcceptedAt(new \DateTimeImmutable());

        // Mettre à jour la réservation avec le nouveau prestataire
        $booking = $replacement->getBooking();
        $booking->setPrestataire($replacement->getReplacementPrestataire());
        $booking->setIsReplacement(true);
        $booking->setReplacementReason($replacement->getReason());

        $this->entityManager->flush();

        $this->logger->info('Replacement accepted', [
            'replacement_id' => $replacement->getId(),
            'booking_id' => $booking->getId(),
        ]);

        // Notifier le client et le prestataire original
        $this->notificationService->notifyReplacementAccepted($replacement);
    }

    /**
     * Refuse un remplacement (par le prestataire remplaçant)
     */
    public function declineReplacement(
        Replacement $replacement,
        ?string $declineReason = null
    ): void {
        if ($replacement->getStatus() !== 'pending') {
            throw new \RuntimeException('Ce remplacement ne peut pas être refusé');
        }

        $replacement->setStatus('declined');
        $replacement->setDeclinedAt(new \DateTimeImmutable());
        $replacement->setDeclineReason($declineReason);

        $this->entityManager->flush();

        $this->logger->info('Replacement declined', [
            'replacement_id' => $replacement->getId(),
            'reason' => $declineReason,
        ]);

        // Notifier qu'il faut chercher un autre remplaçant
        $this->notificationService->notifyReplacementDeclined($replacement);
    }

    /**
     * Annule une demande de remplacement
     */
    public function cancelReplacement(Replacement $replacement): void
    {
        if (!in_array($replacement->getStatus(), ['pending', 'accepted'])) {
            throw new \RuntimeException('Ce remplacement ne peut pas être annulé');
        }

        $replacement->setStatus('cancelled');
        $replacement->setCancelledAt(new \DateTimeImmutable());

        // Si le remplacement était accepté, restaurer le prestataire original
        if ($replacement->getStatus() === 'accepted') {
            $booking = $replacement->getBooking();
            $booking->setPrestataire($replacement->getOriginalPrestataire());
            $booking->setIsReplacement(false);
            $booking->setReplacementReason(null);
        }

        $this->entityManager->flush();

        $this->logger->info('Replacement cancelled', [
            'replacement_id' => $replacement->getId(),
        ]);

        $this->notificationService->notifyReplacementCancelled($replacement);
    }

    /**
     * Recherche automatique et proposition du meilleur remplaçant
     */
    public function findAndProposeReplacement(
        Replacement $replacement,
        int $maxResults = 5
    ): array {
        $booking = $replacement->getBooking();
        
        $availableReplacements = $this->findAvailableReplacements(
            $booking,
            $booking->getPrestataire()->getServiceRadius()
        );

        if (empty($availableReplacements)) {
            $this->logger->warning('No available replacements found', [
                'replacement_id' => $replacement->getId(),
                'booking_id' => $booking->getId(),
            ]);

            return [];
        }

        // Prendre les meilleurs candidats
        $topCandidates = array_slice($availableReplacements, 0, $maxResults);

        // Proposer automatiquement au meilleur candidat
        $bestCandidate = $topCandidates[0];
        $this->proposeReplacement($replacement, $bestCandidate['prestataire']);

        return $topCandidates;
    }

    /**
     * Récupère les remplacements en attente pour un prestataire
     */
    public function getPendingReplacementsForPrestataire(Prestataire $prestataire): array
    {
        return $this->replacementRepository->findBy([
            'replacementPrestataire' => $prestataire,
            'status' => 'pending',
        ], ['proposedAt' => 'DESC']);
    }

    /**
     * Récupère l'historique des remplacements d'un prestataire
     */
    public function getReplacementHistory(
        Prestataire $prestataire,
        string $role = 'all' // 'original', 'replacement', 'all'
    ): array {
        $qb = $this->replacementRepository->createQueryBuilder('r');

        switch ($role) {
            case 'original':
                $qb->where('r.originalPrestataire = :prestataire');
                break;
            case 'replacement':
                $qb->where('r.replacementPrestataire = :prestataire');
                break;
            case 'all':
            default:
                $qb->where('r.originalPrestataire = :prestataire')
                   ->orWhere('r.replacementPrestataire = :prestataire');
                break;
        }

        $qb->setParameter('prestataire', $prestataire)
           ->orderBy('r.requestedAt', 'DESC');

        return $qb->getQuery()->getResult();
    }

    /**
     * Statistiques des remplacements pour un prestataire
     */
    public function getReplacementStats(Prestataire $prestataire): array
    {
        // Remplacements demandés (en tant que prestataire original)
        $requested = $this->replacementRepository->count([
            'originalPrestataire' => $prestataire,
        ]);

        // Remplacements effectués (en tant que remplaçant)
        $performed = $this->replacementRepository->count([
            'replacementPrestataire' => $prestataire,
            'status' => 'accepted',
        ]);

        // Remplacements refusés (en tant que remplaçant)
        $declined = $this->replacementRepository->count([
            'replacementPrestataire' => $prestataire,
            'status' => 'declined',
        ]);

        // Taux d'acceptation
        $acceptanceRate = ($performed + $declined) > 0 
            ? ($performed / ($performed + $declined)) * 100 
            : 0;

        return [
            'requested' => $requested,
            'performed' => $performed,
            'declined' => $declined,
            'acceptance_rate' => round($acceptanceRate, 2),
        ];
    }

    /**
     * Vérifie si un prestataire peut être remplaçant pour une réservation
     */
    public function canReplace(Prestataire $prestataire, Booking $booking): array
    {
        $reasons = [];
        $canReplace = true;

        // Vérifier que ce n'est pas le prestataire original
        if ($prestataire->getId() === $booking->getPrestataire()->getId()) {
            $canReplace = false;
            $reasons[] = 'Vous êtes le prestataire original de cette réservation';
        }

        // Vérifier que le prestataire est approuvé
        if (!$prestataire->isApproved()) {
            $canReplace = false;
            $reasons[] = 'Votre compte doit être approuvé';
        }

        // Vérifier que le prestataire est actif
        if (!$prestataire->isActive()) {
            $canReplace = false;
            $reasons[] = 'Votre compte est désactivé';
        }

        // Vérifier la catégorie de service
        $hasCategory = false;
        foreach ($prestataire->getServiceCategories() as $category) {
            if ($category->getId() === $booking->getServiceRequest()->getCategory()->getId()) {
                $hasCategory = true;
                break;
            }
        }

        if (!$hasCategory) {
            $canReplace = false;
            $reasons[] = 'Cette catégorie de service ne fait pas partie de vos compétences';
        }

        // Vérifier la disponibilité
        $isAvailable = $this->availabilityService->isAvailable(
            $prestataire,
            $booking->getScheduledDateTime(),
            $booking->getDuration()
        );

        if (!$isAvailable) {
            $canReplace = false;
            $reasons[] = 'Vous n\'êtes pas disponible à cette date/heure';
        }

        // Vérifier la zone géographique
        $distance = $this->calculateDistance(
            $prestataire,
            $booking->getLatitude(),
            $booking->getLongitude()
        );

        if ($distance > $prestataire->getServiceRadius()) {
            $canReplace = false;
            $reasons[] = sprintf(
                'Cette réservation est en dehors de votre zone d\'intervention (%.1f km)',
                $distance
            );
        }

        return [
            'can_replace' => $canReplace,
            'reasons' => $reasons,
            'distance' => round($distance, 2),
        ];
    }

    /**
     * Calcule la distance entre un prestataire et des coordonnées
     */
    private function calculateDistance(
        Prestataire $prestataire,
        ?float $latitude,
        ?float $longitude
    ): float {
        if (!$latitude || !$longitude || 
            !$prestataire->getLatitude() || !$prestataire->getLongitude()) {
            return 0;
        }

        // Formule de Haversine pour calculer la distance entre deux points GPS
        $earthRadius = 6371; // km

        $latFrom = deg2rad($prestataire->getLatitude());
        $lonFrom = deg2rad($prestataire->getLongitude());
        $latTo = deg2rad($latitude);
        $lonTo = deg2rad($longitude);

        $latDelta = $latTo - $latFrom;
        $lonDelta = $lonTo - $lonFrom;

        $a = sin($latDelta / 2) * sin($latDelta / 2) +
             cos($latFrom) * cos($latTo) *
             sin($lonDelta / 2) * sin($lonDelta / 2);
        
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadius * $c;
    }

    /**
     * Notifie les prestataires disponibles d'une opportunité de remplacement
     */
    public function notifyAvailablePrestataires(
        Replacement $replacement,
        int $maxNotifications = 10
    ): int {
        $booking = $replacement->getBooking();
        
        $availableReplacements = $this->findAvailableReplacements(
            $booking,
            $booking->getPrestataire()->getServiceRadius()
        );

        $notifiedCount = 0;
        $candidates = array_slice($availableReplacements, 0, $maxNotifications);

        foreach ($candidates as $candidate) {
            $this->notificationService->notifyReplacementOpportunity(
                $candidate['prestataire'],
                $replacement
            );
            $notifiedCount++;
        }

        $this->logger->info('Prestataires notified for replacement', [
            'replacement_id' => $replacement->getId(),
            'notified_count' => $notifiedCount,
        ]);

        return $notifiedCount;
    }
}