<?php
// src/Entity/User/Prestataire.php

namespace App\Entity\User;

use App\Entity\Booking\Booking;
use App\Entity\Document\Document;
use App\Entity\Payment\BankAccount;
use App\Entity\Payment\Payout;
use App\Entity\Payment\PrestataireEarning;
use App\Entity\Planning\Absence;
use App\Entity\Planning\Availability;
use App\Entity\Planning\AvailableSlot;
use App\Entity\Planning\Replacement;
use App\Entity\Quote\Quote;
use App\Entity\Rating\Review;
use App\Repository\PrestataireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PrestataireRepository::class)]
#[ORM\Table(name: 'prestataires')]
class Prestataire extends User
{
    #[ORM\Column(type: 'string', length: 14, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro SIRET est obligatoire')]
    #[Assert\Regex(
        pattern: '/^\d{14}$/',
        message: 'Le SIRET doit contenir exactement 14 chiffres'
    )]
    private ?string $siret = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $companyName = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\Positive(message: 'Le tarif horaire doit être positif')]
    private ?string $hourlyRate = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'Le rayon d\'intervention doit être positif')]
    private ?int $radiusKm = null;

    #[ORM\Column(type: 'json')]
    private array $serviceCategories = [];

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $skills = [];

    #[ORM\Column(type: 'decimal', precision: 3, scale: 2, nullable: true)]
    private ?string $averageRating = null;

    #[ORM\Column(type: 'integer')]
    private int $totalReviews = 0;

    #[ORM\Column(type: 'integer')]
    private int $completedBookings = 0;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalEarnings = '0.00';

    #[ORM\Column(type: 'boolean')]
    private bool $isApproved = false;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $approvedAt = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?Admin $approvedBy = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $insurance = null; // Assurance professionnelle

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $insuranceExpiryDate = null;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Quote::class)]
    private Collection $quotes;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Availability::class, cascade: ['persist', 'remove'])]
    private Collection $availabilities;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: AvailableSlot::class)]
    private Collection $availableSlots;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Absence::class, cascade: ['persist', 'remove'])]
    private Collection $absences;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Review::class)]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'originalPrestataire', targetEntity: Replacement::class)]
    private Collection $replacementsAsOriginal;

    #[ORM\OneToMany(mappedBy: 'replacementPrestataire', targetEntity: Replacement::class)]
    private Collection $replacementsAsReplacement;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: PrestataireEarning::class)]
    private Collection $earnings;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Document::class, cascade: ['persist', 'remove'])]
    private Collection $documents;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: BankAccount::class, cascade: ['persist', 'remove'])]
    private Collection $bankAccounts;

    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Payout::class)]
    private Collection $payouts;

    public function __construct()
    {
        parent::__construct();
        $this->quotes = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->availableSlots = new ArrayCollection();
        $this->absences = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->replacementsAsOriginal = new ArrayCollection();
        $this->replacementsAsReplacement = new ArrayCollection();
        $this->earnings = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->bankAccounts = new ArrayCollection();
        $this->payouts = new ArrayCollection();
        $this->addRole('ROLE_PRESTATAIRE');
    }

    // Getters and Setters

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(string $siret): self
    {
        $this->siret = $siret;
        return $this;
    }

    public function getCompanyName(): ?string
    {
        return $this->companyName;
    }

    public function setCompanyName(?string $companyName): self
    {
        $this->companyName = $companyName;
        return $this;
    }

    public function getHourlyRate(): ?string
    {
        return $this->hourlyRate;
    }

    public function setHourlyRate(?string $hourlyRate): self
    {
        $this->hourlyRate = $hourlyRate;
        return $this;
    }

    public function getRadiusKm(): ?int
    {
        return $this->radiusKm;
    }

    public function setRadiusKm(?int $radiusKm): self
    {
        $this->radiusKm = $radiusKm;
        return $this;
    }

    public function getServiceCategories(): array
    {
        return $this->serviceCategories;
    }

    public function setServiceCategories(array $serviceCategories): self
    {
        $this->serviceCategories = $serviceCategories;
        return $this;
    }

    public function addServiceCategory(string $category): self
    {
        if (!in_array($category, $this->serviceCategories, true)) {
            $this->serviceCategories[] = $category;
        }
        return $this;
    }

    public function removeServiceCategory(string $category): self
    {
        $key = array_search($category, $this->serviceCategories, true);
        if ($key !== false) {
            unset($this->serviceCategories[$key]);
            $this->serviceCategories = array_values($this->serviceCategories);
        }
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    public function getSkills(): ?array
    {
        return $this->skills ?? [];
    }

    public function setSkills(?array $skills): self
    {
        $this->skills = $skills;
        return $this;
    }

    public function addSkill(string $skill): self
    {
        $skills = $this->getSkills();
        if (!in_array($skill, $skills, true)) {
            $skills[] = $skill;
            $this->skills = $skills;
        }
        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): self
    {
        $this->averageRating = $averageRating;
        return $this;
    }

    public function getTotalReviews(): int
    {
        return $this->totalReviews;
    }

    public function setTotalReviews(int $totalReviews): self
    {
        $this->totalReviews = $totalReviews;
        return $this;
    }

    public function incrementTotalReviews(): self
    {
        $this->totalReviews++;
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

    public function getTotalEarnings(): string
    {
        return $this->totalEarnings;
    }

    public function setTotalEarnings(string $totalEarnings): self
    {
        $this->totalEarnings = $totalEarnings;
        return $this;
    }

    public function addToTotalEarnings(string $amount): self
    {
        $this->totalEarnings = bcadd($this->totalEarnings, $amount, 2);
        return $this;
    }

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): self
    {
        $this->isApproved = $isApproved;
        
        if ($isApproved && !$this->approvedAt) {
            $this->approvedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeImmutable
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeImmutable $approvedAt): self
    {
        $this->approvedAt = $approvedAt;
        return $this;
    }

    public function getApprovedBy(): ?Admin
    {
        return $this->approvedBy;
    }

    public function setApprovedBy(?Admin $approvedBy): self
    {
        $this->approvedBy = $approvedBy;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): self
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getInsurance(): ?string
    {
        return $this->insurance;
    }

    public function setInsurance(?string $insurance): self
    {
        $this->insurance = $insurance;
        return $this;
    }

    public function getInsuranceExpiryDate(): ?\DateTimeInterface
    {
        return $this->insuranceExpiryDate;
    }

    public function setInsuranceExpiryDate(?\DateTimeInterface $insuranceExpiryDate): self
    {
        $this->insuranceExpiryDate = $insuranceExpiryDate;
        return $this;
    }

    public function isInsuranceValid(): bool
    {
        if (!$this->insuranceExpiryDate) {
            return false;
        }
        
        return $this->insuranceExpiryDate > new \DateTime();
    }

    /**
     * @return Collection<int, Quote>
     */
    public function getQuotes(): Collection
    {
        return $this->quotes;
    }

    public function addQuote(Quote $quote): self
    {
        if (!$this->quotes->contains($quote)) {
            $this->quotes->add($quote);
            $quote->setPrestataire($this);
        }

        return $this;
    }

    public function removeQuote(Quote $quote): self
    {
        if ($this->quotes->removeElement($quote)) {
            if ($quote->getPrestataire() === $this) {
                $quote->setPrestataire(null);
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
            $booking->setPrestataire($this);
        }

        return $this;
    }

    public function removeBooking(Booking $booking): self
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getPrestataire() === $this) {
                $booking->setPrestataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Availability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(Availability $availability): self
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setPrestataire($this);
        }

        return $this;
    }

    public function removeAvailability(Availability $availability): self
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getPrestataire() === $this) {
                $availability->setPrestataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, AvailableSlot>
     */
    public function getAvailableSlots(): Collection
    {
        return $this->availableSlots;
    }

    public function addAvailableSlot(AvailableSlot $availableSlot): self
    {
        if (!$this->availableSlots->contains($availableSlot)) {
            $this->availableSlots->add($availableSlot);
            $availableSlot->setPrestataire($this);
        }

        return $this;
    }

    public function removeAvailableSlot(AvailableSlot $availableSlot): self
    {
        if ($this->availableSlots->removeElement($availableSlot)) {
            if ($availableSlot->getPrestataire() === $this) {
                $availableSlot->setPrestataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Absence>
     */
    public function getAbsences(): Collection
    {
        return $this->absences;
    }

    public function addAbsence(Absence $absence): self
    {
        if (!$this->absences->contains($absence)) {
            $this->absences->add($absence);
            $absence->setPrestataire($this);
        }

        return $this;
    }

    public function removeAbsence(Absence $absence): self
    {
        if ($this->absences->removeElement($absence)) {
            if ($absence->getPrestataire() === $this) {
                $absence->setPrestataire(null);
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
            $review->setPrestataire($this);
        }

        return $this;
    }

    public function removeReview(Review $review): self
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getPrestataire() === $this) {
                $review->setPrestataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Replacement>
     */
    public function getReplacementsAsOriginal(): Collection
    {
        return $this->replacementsAsOriginal;
    }

    /**
     * @return Collection<int, Replacement>
     */
    public function getReplacementsAsReplacement(): Collection
    {
        return $this->replacementsAsReplacement;
    }

    /**
     * @return Collection<int, PrestataireEarning>
     */
    public function getEarnings(): Collection
    {
        return $this->earnings;
    }

    /**
     * @return Collection<int, Document>
     */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): self
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setPrestataire($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): self
    {
        if ($this->documents->removeElement($document)) {
            if ($document->getPrestataire() === $this) {
                $document->setPrestataire(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, BankAccount>
     */
    public function getBankAccounts(): Collection
    {
        return $this->bankAccounts;
    }

    public function addBankAccount(BankAccount $bankAccount): self
    {
        if (!$this->bankAccounts->contains($bankAccount)) {
            $this->bankAccounts->add($bankAccount);
            $bankAccount->setPrestataire($this);
        }

        return $this;
    }

    public function removeBankAccount(BankAccount $bankAccount): self
    {
        if ($this->bankAccounts->removeElement($bankAccount)) {
            if ($bankAccount->getPrestataire() === $this) {
                $bankAccount->setPrestataire(null);
            }
        }

        return $this;
    }

    public function getDefaultBankAccount(): ?BankAccount
    {
        foreach ($this->bankAccounts as $account) {
            if ($account->isDefault()) {
                return $account;
            }
        }
        
        return $this->bankAccounts->first() ?: null;
    }

    /**
     * @return Collection<int, Payout>
     */
    public function getPayouts(): Collection
    {
        return $this->payouts;
    }
}