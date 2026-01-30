<?php
// src/Entity/User/Client.php

namespace App\Entity\User;

use App\Repository\ClientRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use App\Entity\ServiceRequest\ServiceRequest;
use App\Entity\Booking\Booking;
use App\Entity\Review\Review;
use App\Entity\Financial\ClientExpense;
use Symfony\Component\Validator\Constraints as Assert;
#[ORM\Entity(repositoryClass: ClientRepository::class)]
#[ORM\Table(name: 'clients')]
class Client extends User
{
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $preferredPaymentMethod = null; // card, sepa, wallet

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $defaultAddress = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $preferences = [];

    #[ORM\Column(type: 'integer')]
    private int $totalBookings = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalSpent = '0.00';

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ServiceRequest::class, cascade: ['persist'])]
    private Collection $serviceRequests;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'client', targetEntity: ClientExpense::class)]
    private Collection $expenses;

    public function __construct()
    {
        parent::__construct();
        $this->serviceRequests = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->expenses = new ArrayCollection();
        $this->addRole('ROLE_CLIENT');
    }

    // Getters and Setters

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

    public function getPreferences(): ?array
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

    public function getTotalSpent(): string
    {
        return $this->totalSpent;
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

    /**
     * @return Collection<int, ClientExpense>
     */
    public function getExpenses(): Collection
    {
        return $this->expenses;
    }

    public function addExpense(ClientExpense $expense): self
    {
        if (!$this->expenses->contains($expense)) {
            $this->expenses->add($expense);
            $expense->setClient($this);
        }

        return $this;
    }

    public function removeExpense(ClientExpense $expense): self
    {
        if ($this->expenses->removeElement($expense)) {
            if ($expense->getClient() === $this) {
                $expense->setClient(null);
            }
        }

        return $this;
    }
}