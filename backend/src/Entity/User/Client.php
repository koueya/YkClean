<?php
// src/Entity/User/Client.php

namespace App\Entity\User;

use App\Entity\Booking\Booking;
use App\Entity\Payment\Payment;
use App\Entity\Payment\PaymentMethod;
use App\Entity\Rating\Review;
use App\Entity\Service\ServiceRequest;
use App\Repository\User\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Client - Client de la plateforme
 * 
 * Hérite de User avec des propriétés spécifiques au client
 */
#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
class Client extends User
{
    // ============================================
    // PRÉFÉRENCES CLIENT
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    #[Assert\Choice(
        choices: ['card', 'sepa', 'wallet', 'cash'],
        message: 'La méthode de paiement doit être card, sepa, wallet ou cash'
    )]
    #[Groups(['client:read', 'client:write'])]
    private ?string $preferredPaymentMethod = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 500)]
    #[Groups(['client:read', 'client:write'])]
    private ?string $defaultAddress = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    #[Groups(['client:read', 'client:write'])]
    private ?array $preferences = null;

    // ============================================
    // STATISTIQUES CLIENT
    // ============================================

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['client:read', 'client:stats'])]
    private int $totalBookings = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['client:read', 'client:stats'])]
    private int $completedBookings = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    #[Groups(['client:read', 'client:stats'])]
    private int $cancelledBookings = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero]
    #[Groups(['client:read', 'client:stats'])]
    private string $totalSpent = '0.00';

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    #[Assert\Range(min: 0, max: 5)]
    #[Groups(['client:read', 'client:stats'])]
    private ?string $averageRatingGiven = null;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $reviewsCount = 0;

    // ============================================
    // INTÉGRATION STRIPE
    // ============================================

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $stripeCustomerId = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $defaultPaymentMethodId = null;

    // ============================================
    // COMPORTEMENT ET RÉPUTATION
    // ============================================

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $latePaymentsCount = 0;

    #[ORM\Column(type: Types::INTEGER)]
    #[Assert\PositiveOrZero]
    private int $disputesCount = 0;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isReliable = true;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $lastBookingAt = null;

    // ============================================
    // RELATIONS
    // ============================================

    /**
     * Demandes de service créées par le client
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ServiceRequest::class, cascade: ['persist'])]
    private Collection $serviceRequests;

    /**
     * Réservations du client
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Booking::class)]
    private Collection $bookings;

    /**
     * Avis laissés par le client
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Review::class)]
    private Collection $reviews;

    /**
     * Paiements effectués par le client
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Payment::class)]
    private Collection $payments;

    /**
     * Méthodes de paiement enregistrées
     */
    #[ORM\OneToMany(mappedBy: 'client', targetEntity: PaymentMethod::class, cascade: ['persist', 'remove'])]
    private Collection $paymentMethods;

    // ============================================
    // CONSTRUCTEUR
    // ============================================

    public function __construct()
    {
        parent::__construct();
        
        $this->serviceRequests = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->paymentMethods = new ArrayCollection();
        
        $this->preferences = [];
        $this->totalSpent = '0.00';
        
        // Ajouter le rôle CLIENT
        $this->addRole('ROLE_CLIENT');
    }

    // ============================================
    // GETTERS & SETTERS - PRÉFÉRENCES
    // ============================================

    public function getPreferredPaymentMethod(): ?string
    {
        return $this->preferredPaymentMethod;
    }

    public function setPreferredPaymentMethod(?string $preferredPaymentMethod): self
    {
        $this->preferredPaymentMethod = $preferredPaymentMethod;
        return $this;
    }

    public function getDefaultAddress(): ?string
    {
        return $this->defaultAddress;
    }

    public function setDefaultAddress(?string $defaultAddress): self
    {
        $this->defaultAddress = $defaultAddress;
        return $this;
    }

    public function getPreferences(): array
    {
        return $this->preferences ?? [];
    }

    public function setPreferences(?array $preferences): self
    {
        $this->preferences = $preferences;
        return $this;
    }

    public function addPreference(string $key, mixed $value): self
    {
        $preferences = $this->getPreferences();
        $preferences[$key] = $value;
        $this->preferences = $preferences;
        return $this;
    }

    public function getPreference(string $key): mixed
    {
        return $this->getPreferences()[$key] ?? null;
    }

    public function removePreference(string $key): self
    {
        $preferences = $this->getPreferences();
        unset($preferences[$key]);
        $this->preferences = $preferences;
        return $this;
    }

    // ============================================
    // GETTERS & SETTERS - STATISTIQUES
    // ============================================

    public function getTotalBookings(): int
    {
        return $this->totalBookings;
    }

    public function setTotalBookings(int $totalBookings): self
    {
        $this->totalBookings = $totalBookings;
        return $this;
    }

    public function incrementTotalBookings(): self
    {
        $this->totalBookings++;
        return $this;
    }

    public function getCompletedBookings(): int
    {
        return $this->completedBookings;
    }

    public function setCompletedBookings(int $completedBookings): self
    {
        $this->completedBookings = $completedBookings;
        return $this;
    }

    public function incrementCompletedBookings(): self
    {
        $this->completedBookings++;
        return $this;
    }

    public function getCancelledBookings(): int
    {
        return $this->cancelledBookings;
    }

    public function setCancelledBookings(int $cancelledBookings): self
    {
        $this->cancelledBookings = $cancelledBookings;
        return $this;
    }

    public function incrementCancelledBookings(): self
    {
        $this->cancelledBookings++;
        return $this;
    }

    public function getTotalSpent(): string
    {
        return $this->totalSpent;
    }

    public function getTotalSpentFloat(): float
    {
        return (float) $this->totalSpent;
    }

    public function setTotalSpent(string $totalSpent): self
    {
        $this->totalSpent = $totalSpent;
        return $this;
    }

    public function addToTotalSpent(string $amount): self
    {
        $this->totalSpent = bcadd($this->totalSpent, $amount, 2);
        return $this;
    }

    public function getAverageRatingGiven(): ?string
    {
        return $this->averageRatingGiven;
    }

    public function setAverageRatingGiven(?string $averageRatingGiven): self
    {
        $this->averageRatingGiven = $averageRatingGiven;
        return $this;
    }

    public function getReviewsCount(): int
    {
        return $this->reviewsCount;
    }

    public function setReviewsCount(int $reviewsCount): self
    {
        $this->reviewsCount = $reviewsCount;
        return $this;
    }

    public function incrementReviewsCount(): self
    {
        $this->reviewsCount++;
        return $this;
    }

    // ============================================
    // STRIPE
    // ============================================

    public function getStripeCustomerId(): ?string
    {
        return $this->stripeCustomerId;
    }

    public function setStripeCustomerId(?string $stripeCustomerId): self
    {
        $this->stripeCustomerId = $stripeCustomerId;
        return $this;
    }

    public function getDefaultPaymentMethodId(): ?string
    {
        return $this->defaultPaymentMethodId;
    }

    public function setDefaultPaymentMethodId(?string $defaultPaymentMethodId): self
    {
        $this->defaultPaymentMethodId = $defaultPaymentMethodId;
        return $this;
    }

    public function hasStripeAccount(): bool
    {
        return $this->stripeCustomerId !== null;
    }

    public function hasDefaultPaymentMethod(): bool
    {
        return $this->defaultPaymentMethodId !== null;
    }

    // ============================================
    // COMPORTEMENT
    // ============================================

    public function getLatePaymentsCount(): int
    {
        return $this->latePaymentsCount;
    }

    public function setLatePaymentsCount(int $latePaymentsCount): self
    {
        $this->latePaymentsCount = $latePaymentsCount;
        return $this;
    }

    public function incrementLatePaymentsCount(): self
    {
        $this->latePaymentsCount++;
        
        // Si trop de retards, marquer comme non fiable
        if ($this->latePaymentsCount >= 3) {
            $this->isReliable = false;
        }
        
        return $this;
    }

    public function getDisputesCount(): int
    {
        return $this->disputesCount;
    }

    public function setDisputesCount(int $disputesCount): self
    {
        $this->disputesCount = $disputesCount;
        return $this;
    }

    public function incrementDisputesCount(): self
    {
        $this->disputesCount++;
        
        // Si trop de litiges, marquer comme non fiable
        if ($this->disputesCount >= 2) {
            $this->isReliable = false;
        }
        
        return $this;
    }

    public function isReliable(): bool
    {
        return $this->isReliable;
    }

    public function setIsReliable(bool $isReliable): self
    {
        $this->isReliable = $isReliable;
        return $this;
    }

    public function getLastBookingAt(): ?\DateTimeImmutable
    {
        return $this->lastBookingAt;
    }

    public function setLastBookingAt(?\DateTimeImmutable $lastBookingAt): self
    {
        $this->lastBookingAt = $lastBookingAt;
        return $this;
    }

    // ============================================
    // RELATIONS - SERVICE REQUESTS
    // ============================================

    /**
     * @return Collection<int, ServiceRequest>
     */
    public function getServiceRequests(): Collection
    {
        return $this->serviceRequests;
    }

    public function addServiceRequest(ServiceRequest $serviceRequest): self
    {
        if (!$this->serviceRequests->contains($serviceRequest)) {
            $this->serviceRequests->add($serviceRequest);
            $serviceRequest->setClient($this);
        }

        return $this;
    }

    public function removeServiceRequest(ServiceRequest $serviceRequest): self
    {
        if ($this->serviceRequests->removeElement($serviceRequest)) {
            if ($serviceRequest->getClient() === $this) {
                $serviceRequest->setClient(null);
            }
        }

        return $this;
    }

    public function getActiveServiceRequests(): Collection
    {
        return $this->serviceRequests->filter(
            fn(ServiceRequest $sr) => in_array($sr->getStatus(), ['open', 'quoting'])
        );
    }

    // ============================================
    // RELATIONS - BOOKINGS
    // ============================================

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): self
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setClient($this);
            
            $this->incrementTotalBookings();
            $this->lastBookingAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getClient() === $this) {
                $booking->setClient(null);
            }
        }

        return $this;
    }

    public function getUpcomingBookings(): Collection
    {
        return $this->bookings->filter(
            fn(Booking $b) => $b->getScheduledDate() >= new \DateTimeImmutable() 
                && in_array($b->getStatus(), ['scheduled', 'confirmed'])
        );
    }

    public function getPastBookings(): Collection
    {
        return $this->bookings->filter(
            fn(Booking $b) => $b->getScheduledDate() < new \DateTimeImmutable() 
                || in_array($b->getStatus(), ['completed', 'cancelled'])
        );
    }

    // ============================================
    // RELATIONS - REVIEWS
    // ============================================

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setClient($this);
            
            $this->incrementReviewsCount();
        }

        return $this;
    }

    public function removeReview(Review $review): self
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getClient() === $this) {
                $review->setClient(null);
            }
        }

        return $this;
    }

    // ============================================
    // RELATIONS - PAYMENTS
    // ============================================

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
    }

    public function addPayment(Payment $payment): self
    {
        if (!$this->payments->contains($payment)) {
            $this->payments->add($payment);
            $payment->setClient($this);
        }

        return $this;
    }

    public function removePayment(Payment $payment): self
    {
        if ($this->payments->removeElement($payment)) {
            if ($payment->getClient() === $this) {
                $payment->setClient(null);
            }
        }

        return $this;
    }

    // ============================================
    // RELATIONS - PAYMENT METHODS
    // ============================================

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function getPaymentMethods(): Collection
    {
        return $this->paymentMethods;
    }

    public function addPaymentMethod(PaymentMethod $paymentMethod): self
    {
        if (!$this->paymentMethods->contains($paymentMethod)) {
            $this->paymentMethods->add($paymentMethod);
            $paymentMethod->setClient($this);
        }

        return $this;
    }

    public function removePaymentMethod(PaymentMethod $paymentMethod): self
    {
        if ($this->paymentMethods->removeElement($paymentMethod)) {
            if ($paymentMethod->getClient() === $this) {
                $paymentMethod->setClient(null);
            }
        }

        return $this;
    }

    // ============================================
    // MÉTHODES UTILITAIRES
    // ============================================

    /**
     * Calcule le taux de complétion des réservations
     */
    public function getCompletionRate(): float
    {
        if ($this->totalBookings === 0) {
            return 0;
        }

        return round(($this->completedBookings / $this->totalBookings) * 100, 2);
    }

    /**
     * Calcule le taux d'annulation
     */
    public function getCancellationRate(): float
    {
        if ($this->totalBookings === 0) {
            return 0;
        }

        return round(($this->cancelledBookings / $this->totalBookings) * 100, 2);
    }

    /**
     * Calcule la dépense moyenne par réservation
     */
    public function getAverageBookingAmount(): float
    {
        if ($this->completedBookings === 0) {
            return 0;
        }

        return round((float) $this->totalSpent / $this->completedBookings, 2);
    }

    /**
     * Vérifie si le client est nouveau (moins de 30 jours)
     */
    public function isNewClient(): bool
    {
        $thirtyDaysAgo = (new \DateTimeImmutable())->modify('-30 days');
        return $this->getCreatedAt() >= $thirtyDaysAgo;
    }

    /**
     * Vérifie si le client est actif (réservation dans les 90 derniers jours)
     */
    public function isActiveClient(): bool
    {
        if (!$this->lastBookingAt) {
            return false;
        }

        $ninetyDaysAgo = (new \DateTimeImmutable())->modify('-90 days');
        return $this->lastBookingAt >= $ninetyDaysAgo;
    }

    /**
     * Calcule la note moyenne donnée par le client
     */
    public function calculateAverageRatingGiven(): void
    {
        $count = $this->reviews->count();
        
        if ($count === 0) {
            $this->averageRatingGiven = null;
            return;
        }

        $total = 0;
        foreach ($this->reviews as $review) {
            $total += $review->getRating();
        }

        $this->averageRatingGiven = (string) round($total / $count, 2);
    }

    // ============================================
    // MÉTHODES SPÉCIALES
    // ============================================

    public function __toString(): string
    {
        return sprintf('Client: %s', $this->getFullName());
    }

    /**
     * Retourne une représentation JSON-friendly du client
     */
    public function toArray(): array
    {
        $baseArray = parent::toArray();

        return array_merge($baseArray, [
            'userType' => 'client',
            'preferredPaymentMethod' => $this->preferredPaymentMethod,
            'defaultAddress' => $this->defaultAddress,
            'totalBookings' => $this->totalBookings,
            'completedBookings' => $this->completedBookings,
            'cancelledBookings' => $this->cancelledBookings,
            'totalSpent' => $this->getTotalSpentFloat(),
            'averageRatingGiven' => $this->averageRatingGiven !== null ? (float) $this->averageRatingGiven : null,
            'completionRate' => $this->getCompletionRate(),
            'cancellationRate' => $this->getCancellationRate(),
            'isReliable' => $this->isReliable,
            'isActiveClient' => $this->isActiveClient(),
            'hasStripeAccount' => $this->hasStripeAccount(),
        ]);
    }
}