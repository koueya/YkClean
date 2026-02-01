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
use App\Entity\Service\ServiceSubcategory;
use App\Repository\PrestataireRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PrestataireRepository::class)]
#[ORM\Table(name: 'prestataires')]
class Prestataire extends User
{
    // ===== INFORMATIONS PROFESSIONNELLES =====
    
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

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $skills = null;

    // ===== SPÉCIALISATIONS (REMPLACE serviceCategories DEPRECATED) =====
    
    /**
     * Spécialisations du prestataire
     * @var Collection<int, ServiceSubcategory>
     */
    #[ORM\ManyToMany(targetEntity: ServiceSubcategory::class, inversedBy: 'prestataires')]
    #[ORM\JoinTable(name: 'prestataire_specializations')]
    private Collection $specializations;

    // ===== DOCUMENTS ET VÉRIFICATIONS =====
    
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $idDocument = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $kbisDocument = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $insuranceDocument = null;

    #[ORM\Column(type: 'boolean')]
    private bool $hasInsurance = false;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $insuranceExpiryDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isApproved = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $approvedAt = null;

    #[ORM\ManyToOne(targetEntity: Admin::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Admin $approvedBy = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $approvalNotes = null;

    // ===== STATISTIQUES ET NOTATION =====
    
    #[ORM\Column(type: 'decimal', precision: 3, scale: 2)]
    private string $averageRating = '0.00';

    #[ORM\Column(type: 'integer')]
    private int $totalReviews = 0;

    #[ORM\Column(type: 'integer')]
    private int $completedBookings = 0;

    #[ORM\Column(type: 'integer')]
    private int $cancelledBookings = 0;

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $cancellationRate = '0.00';

    #[ORM\Column(type: 'decimal', precision: 5, scale: 2)]
    private string $responseRate = '100.00';

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $averageResponseTime = null; // en minutes

    // ===== FINANCES =====
    
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalEarnings = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $pendingEarnings = '0.00';

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $availableBalance = '0.00';

    #[ORM\Column(type: 'string', length: 3)]
    private string $preferredCurrency = 'EUR';

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    private ?string $vatNumber = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isVatRegistered = false;

    // ===== PARAMÈTRES DE DISPONIBILITÉ =====
    
    #[ORM\Column(type: 'boolean')]
    private bool $acceptsNewClients = true;

    #[ORM\Column(type: 'boolean')]
    private bool $acceptsRecurringServices = true;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $minBookingDuration = null; // en minutes

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $maxBookingDuration = null; // en minutes

    #[ORM\Column(type: 'integer')]
    private int $advanceNoticeDays = 1; // Préavis minimum en jours

    // ===== PRÉFÉRENCES DE NOTIFICATION =====
    
    #[ORM\Column(type: 'json')]
    private array $notificationPreferences = [
        'email' => [
            'newRequest' => true,
            'bookingConfirmed' => true,
            'bookingCancelled' => true,
            'paymentReceived' => true,
            'newReview' => true,
        ],
        'sms' => [
            'upcomingBooking' => true,
            'bookingCancelled' => true,
        ],
        'push' => [
            'newRequest' => true,
            'bookingConfirmed' => true,
            'upcomingBooking' => true,
        ]
    ];

    // ===== RELATIONS =====
    
    /**
     * @var Collection<int, Quote>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Quote::class, orphanRemoval: true)]
    private Collection $quotes;

    /**
     * @var Collection<int, Booking>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Booking::class)]
    private Collection $bookings;

    /**
     * @var Collection<int, Availability>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Availability::class, orphanRemoval: true)]
    private Collection $availabilities;

    /**
     * @var Collection<int, AvailableSlot>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: AvailableSlot::class, orphanRemoval: true)]
    private Collection $availableSlots;

    /**
     * @var Collection<int, Absence>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Absence::class, orphanRemoval: true)]
    private Collection $absences;

    /**
     * @var Collection<int, Replacement>
     */
    #[ORM\OneToMany(mappedBy: 'originalPrestataire', targetEntity: Replacement::class)]
    private Collection $originalReplacements;

    /**
     * @var Collection<int, Replacement>
     */
    #[ORM\OneToMany(mappedBy: 'replacementPrestataire', targetEntity: Replacement::class)]
    private Collection $replacementAssignments;

    /**
     * @var Collection<int, Review>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Review::class)]
    private Collection $reviews;

    /**
     * @var Collection<int, BankAccount>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: BankAccount::class, orphanRemoval: true)]
    private Collection $bankAccounts;

    /**
     * @var Collection<int, Payout>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Payout::class)]
    private Collection $payouts;

    /**
     * @var Collection<int, PrestataireEarning>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: PrestataireEarning::class)]
    private Collection $earnings;

    /**
     * @var Collection<int, Document>
     */
    #[ORM\OneToMany(mappedBy: 'prestataire', targetEntity: Document::class, orphanRemoval: true)]
    private Collection $documents;

    // ===== CONSTRUCTEUR =====
    
    public function __construct()
    {
        parent::__construct();
        
        $this->specializations = new ArrayCollection();
        $this->quotes = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->availableSlots = new ArrayCollection();
        $this->absences = new ArrayCollection();
        $this->originalReplacements = new ArrayCollection();
        $this->replacementAssignments = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->bankAccounts = new ArrayCollection();
        $this->payouts = new ArrayCollection();
        $this->earnings = new ArrayCollection();
        $this->documents = new ArrayCollection();
        
        $this->setRoles(['ROLE_PRESTATAIRE']);
        $this->averageRating = '0.00';
        $this->totalReviews = 0;
        $this->completedBookings = 0;
        $this->cancelledBookings = 0;
        $this->cancellationRate = '0.00';
        $this->responseRate = '100.00';
        $this->totalEarnings = '0.00';
        $this->pendingEarnings = '0.00';
        $this->availableBalance = '0.00';
        $this->preferredCurrency = 'EUR';
        $this->acceptsNewClients = true;
        $this->acceptsRecurringServices = true;
        $this->advanceNoticeDays = 1;
        $this->isApproved = false;
        $this->hasInsurance = false;
        $this->isVatRegistered = false;
    }

    // ===== GETTERS & SETTERS =====

    public function getSiret(): ?string
    {
        return $this->siret;
    }

    public function setSiret(?string $siret): self
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

    // ===== SPÉCIALISATIONS =====

    /**
     * @return Collection<int, ServiceSubcategory>
     */
    public function getSpecializations(): Collection
    {
        return $this->specializations;
    }

    public function addSpecialization(ServiceSubcategory $specialization): self
    {
        if (!$this->specializations->contains($specialization)) {
            $this->specializations->add($specialization);
        }
        return $this;
    }

    public function removeSpecialization(ServiceSubcategory $specialization): self
    {
        $this->specializations->removeElement($specialization);
        return $this;
    }

    public function hasSpecialization(ServiceSubcategory $specialization): bool
    {
        return $this->specializations->contains($specialization);
    }

    // ===== DOCUMENTS =====

    public function getIdDocument(): ?string
    {
        return $this->idDocument;
    }

    public function setIdDocument(?string $idDocument): self
    {
        $this->idDocument = $idDocument;
        return $this;
    }

    public function getKbisDocument(): ?string
    {
        return $this->kbisDocument;
    }

    public function setKbisDocument(?string $kbisDocument): self
    {
        $this->kbisDocument = $kbisDocument;
        return $this;
    }

    public function getInsuranceDocument(): ?string
    {
        return $this->insuranceDocument;
    }

    public function setInsuranceDocument(?string $insuranceDocument): self
    {
        $this->insuranceDocument = $insuranceDocument;
        return $this;
    }

    public function hasInsurance(): bool
    {
        return $this->hasInsurance;
    }

    public function setHasInsurance(bool $hasInsurance): self
    {
        $this->hasInsurance = $hasInsurance;
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
        if (!$this->hasInsurance || !$this->insuranceExpiryDate) {
            return false;
        }
        return $this->insuranceExpiryDate > new \DateTime();
    }

    // ===== APPROBATION =====

    public function isApproved(): bool
    {
        return $this->isApproved;
    }

    public function setIsApproved(bool $isApproved): self
    {
        $this->isApproved = $isApproved;
        
        if ($isApproved && !$this->approvedAt) {
            $this->approvedAt = new \DateTime();
        }
        
        return $this;
    }

    public function getApprovedAt(): ?\DateTimeInterface
    {
        return $this->approvedAt;
    }

    public function setApprovedAt(?\DateTimeInterface $approvedAt): self
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

    public function getApprovalNotes(): ?string
    {
        return $this->approvalNotes;
    }

    public function setApprovalNotes(?string $approvalNotes): self
    {
        $this->approvalNotes = $approvalNotes;
        return $this;
    }

    // ===== STATISTIQUES =====

    public function getAverageRating(): string
    {
        return $this->averageRating;
    }

    public function setAverageRating(string $averageRating): self
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
        $this->updateCancellationRate();
        return $this;
    }

    public function getCancellationRate(): string
    {
        return $this->cancellationRate;
    }

    public function setCancellationRate(string $cancellationRate): self
    {
        $this->cancellationRate = $cancellationRate;
        return $this;
    }

    private function updateCancellationRate(): void
    {
        $total = $this->completedBookings + $this->cancelledBookings;
        if ($total > 0) {
            $rate = ($this->cancelledBookings / $total) * 100;
            $this->cancellationRate = number_format($rate, 2);
        }
    }

    public function getResponseRate(): string
    {
        return $this->responseRate;
    }

    public function setResponseRate(string $responseRate): self
    {
        $this->responseRate = $responseRate;
        return $this;
    }

    public function getAverageResponseTime(): ?int
    {
        return $this->averageResponseTime;
    }

    public function setAverageResponseTime(?int $averageResponseTime): self
    {
        $this->averageResponseTime = $averageResponseTime;
        return $this;
    }

    // ===== FINANCES =====

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

    public function getPendingEarnings(): string
    {
        return $this->pendingEarnings;
    }

    public function setPendingEarnings(string $pendingEarnings): self
    {
        $this->pendingEarnings = $pendingEarnings;
        return $this;
    }

    public function addToPendingEarnings(string $amount): self
    {
        $this->pendingEarnings = bcadd($this->pendingEarnings, $amount, 2);
        return $this;
    }

    public function getAvailableBalance(): string
    {
        return $this->availableBalance;
    }

    public function setAvailableBalance(string $availableBalance): self
    {
        $this->availableBalance = $availableBalance;
        return $this;
    }

    public function addToAvailableBalance(string $amount): self
    {
        $this->availableBalance = bcadd($this->availableBalance, $amount, 2);
        return $this;
    }

    public function subtractFromAvailableBalance(string $amount): self
    {
        $this->availableBalance = bcsub($this->availableBalance, $amount, 2);
        return $this;
    }

    public function getPreferredCurrency(): string
    {
        return $this->preferredCurrency;
    }

    public function setPreferredCurrency(string $preferredCurrency): self
    {
        $this->preferredCurrency = $preferredCurrency;
        return $this;
    }

    public function getVatNumber(): ?string
    {
        return $this->vatNumber;
    }

    public function setVatNumber(?string $vatNumber): self
    {
        $this->vatNumber = $vatNumber;
        return $this;
    }

    public function isVatRegistered(): bool
    {
        return $this->isVatRegistered;
    }

    public function setIsVatRegistered(bool $isVatRegistered): self
    {
        $this->isVatRegistered = $isVatRegistered;
        return $this;
    }

    // ===== PARAMÈTRES DE DISPONIBILITÉ =====

    public function acceptsNewClients(): bool
    {
        return $this->acceptsNewClients;
    }

    public function setAcceptsNewClients(bool $acceptsNewClients): self
    {
        $this->acceptsNewClients = $acceptsNewClients;
        return $this;
    }

    public function acceptsRecurringServices(): bool
    {
        return $this->acceptsRecurringServices;
    }

    public function setAcceptsRecurringServices(bool $acceptsRecurringServices): self
    {
        $this->acceptsRecurringServices = $acceptsRecurringServices;
        return $this;
    }

    public function getMinBookingDuration(): ?int
    {
        return $this->minBookingDuration;
    }

    public function setMinBookingDuration(?int $minBookingDuration): self
    {
        $this->minBookingDuration = $minBookingDuration;
        return $this;
    }

    public function getMaxBookingDuration(): ?int
    {
        return $this->maxBookingDuration;
    }

    public function setMaxBookingDuration(?int $maxBookingDuration): self
    {
        $this->maxBookingDuration = $maxBookingDuration;
        return $this;
    }

    public function getAdvanceNoticeDays(): int
    {
        return $this->advanceNoticeDays;
    }

    public function setAdvanceNoticeDays(int $advanceNoticeDays): self
    {
        $this->advanceNoticeDays = $advanceNoticeDays;
        return $this;
    }

    // ===== PRÉFÉRENCES DE NOTIFICATION =====

    public function getNotificationPreferences(): array
    {
        return $this->notificationPreferences;
    }

    public function setNotificationPreferences(array $notificationPreferences): self
    {
        $this->notificationPreferences = $notificationPreferences;
        return $this;
    }

    public function updateNotificationPreference(string $channel, string $type, bool $enabled): self
    {
        if (!isset($this->notificationPreferences[$channel])) {
            $this->notificationPreferences[$channel] = [];
        }
        
        $this->notificationPreferences[$channel][$type] = $enabled;
        return $this;
    }

    // ===== RELATIONS - QUOTES =====

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

    // ===== RELATIONS - BOOKINGS =====

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

    // ===== RELATIONS - AVAILABILITIES =====

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

    // ===== RELATIONS - AVAILABLE SLOTS =====

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

    // ===== RELATIONS - ABSENCES =====

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

    // ===== RELATIONS - REPLACEMENTS =====

    /**
     * @return Collection<int, Replacement>
     */
    public function getOriginalReplacements(): Collection
    {
        return $this->originalReplacements;
    }

    public function addOriginalReplacement(Replacement $replacement): self
    {
        if (!$this->originalReplacements->contains($replacement)) {
            $this->originalReplacements->add($replacement);
            $replacement->setOriginalPrestataire($this);
        }
        return $this;
    }

    public function removeOriginalReplacement(Replacement $replacement): self
    {
        if ($this->originalReplacements->removeElement($replacement)) {
            if ($replacement->getOriginalPrestataire() === $this) {
                $replacement->setOriginalPrestataire(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Replacement>
     */
    public function getReplacementAssignments(): Collection
    {
        return $this->replacementAssignments;
    }

    public function addReplacementAssignment(Replacement $replacement): self
    {
        if (!$this->replacementAssignments->contains($replacement)) {
            $this->replacementAssignments->add($replacement);
            $replacement->setReplacementPrestataire($this);
        }
        return $this;
    }

    public function removeReplacementAssignment(Replacement $replacement): self
    {
        if ($this->replacementAssignments->removeElement($replacement)) {
            if ($replacement->getReplacementPrestataire() === $this) {
                $replacement->setReplacementPrestataire(null);
            }
        }
        return $this;
    }

    // ===== RELATIONS - REVIEWS =====

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

    // ===== RELATIONS - BANK ACCOUNTS =====

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
        return null;
    }

    // ===== RELATIONS - PAYOUTS =====

    /**
     * @return Collection<int, Payout>
     */
    public function getPayouts(): Collection
    {
        return $this->payouts;
    }

    public function addPayout(Payout $payout): self
    {
        if (!$this->payouts->contains($payout)) {
            $this->payouts->add($payout);
            $payout->setPrestataire($this);
        }
        return $this;
    }

    public function removePayout(Payout $payout): self
    {
        if ($this->payouts->removeElement($payout)) {
            if ($payout->getPrestataire() === $this) {
                $payout->setPrestataire(null);
            }
        }
        return $this;
    }

    // ===== RELATIONS - EARNINGS =====

    /**
     * @return Collection<int, PrestataireEarning>
     */
    public function getEarnings(): Collection
    {
        return $this->earnings;
    }

    public function addEarning(PrestataireEarning $earning): self
    {
        if (!$this->earnings->contains($earning)) {
            $this->earnings->add($earning);
            $earning->setPrestataire($this);
        }
        return $this;
    }

    public function removeEarning(PrestataireEarning $earning): self
    {
        if ($this->earnings->removeElement($earning)) {
            if ($earning->getPrestataire() === $this) {
                $earning->setPrestataire(null);
            }
        }
        return $this;
    }

    // ===== RELATIONS - DOCUMENTS =====

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

    // ===== MÉTHODES UTILITAIRES =====

    public function canAcceptBooking(\DateTimeInterface $requestedDate): bool
    {
        if (!$this->isApproved || !$this->isActive() || !$this->acceptsNewClients) {
            return false;
        }

        $now = new \DateTime();
        $minDate = $now->modify("+{$this->advanceNoticeDays} days");

        return $requestedDate >= $minDate;
    }

    public function hasRequiredDocuments(): bool
    {
        return $this->idDocument !== null 
            && $this->kbisDocument !== null 
            && $this->insuranceDocument !== null;
    }

    public function isFullyVerified(): bool
    {
        return $this->isVerified() 
            && $this->isApproved 
            && $this->hasRequiredDocuments() 
            && $this->isInsuranceValid();
    }

    public function getDisplayName(): string
    {
        return $this->companyName ?? $this->getFullName();
    }

    public function __toString(): string
    {
        return $this->getDisplayName();
    }
}