<?php
// src/Service/Booking/BookingStatusService.php

namespace App\Service\Booking;

use App\Entity\Booking;
use App\Entity\BookingStatus;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

class BookingStatusService
{
    public function __construct(
        private EntityManagerInterface $em,
        private RequestStack $requestStack
    ) {}

    /**
     * Change le statut d'une réservation et enregistre l'historique
     */
    public function changeStatus(
        Booking $booking,
        string $newStatus,
        ?User $changedBy = null,
        ?string $reason = null,
        ?string $comment = null,
        array $metadata = []
    ): BookingStatus {
        $oldStatus = $booking->getStatus();
        
        // Créer l'enregistrement d'historique
        $statusHistory = new BookingStatus();
        $statusHistory->setBooking($booking);
        $statusHistory->setOldStatus($oldStatus);
        $statusHistory->setNewStatus($newStatus);
        $statusHistory->setChangedBy($changedBy);
        $statusHistory->setReason($reason);
        $statusHistory->setComment($comment);
        $statusHistory->setMetadata($metadata);
        
        // Capturer les informations de la requête
        $request = $this->requestStack->getCurrentRequest();
        if ($request) {
            $statusHistory->setIpAddress($request->getClientIp());
            $statusHistory->setUserAgent($request->headers->get('User-Agent'));
        }
        
        // Changer le statut de la réservation
        $booking->setStatus($newStatus);
        
        // Persister
        $this->em->persist($statusHistory);
        $this->em->persist($booking);
        $this->em->flush();
        
        return $statusHistory;
    }

    /**
     * Obtient l'historique complet d'une réservation
     */
    public function getHistory(Booking $booking): array
    {
        return $this->em->getRepository(BookingStatus::class)
            ->findByBooking($booking);
    }
}