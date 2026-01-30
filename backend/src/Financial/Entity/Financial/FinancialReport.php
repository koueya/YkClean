<?php
// src/Entity/Financial/FinancialReport.php

namespace App\Entity\Financial;

use App\Entity\User;
use App\Entity\Prestataire;
use App\Entity\Client;
use App\Repository\Financial\FinancialReportRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: FinancialReportRepository::class)]
#[ORM\Table(name: 'financial_reports')]
class FinancialReport
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $reportType = null; // monthly, quarterly, yearly, custom, tax

    #[ORM\Column(type: 'string', length: 50)]
    private ?string $entityType = null; // prestataire, client, platform, admin

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null; // Prestataire ou Client concerné

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $periodStart = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $periodEnd = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $year = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $month = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $quarter = null;

    // Données financières générales
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $totalRevenue = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $totalExpenses = '0.00';

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2)]
    private ?string $netAmount = '0.00';

    // Pour les prestataires
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $grossEarnings = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $commissionsDeducted = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $netEarnings = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $completedBookings = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?string $averageBookingValue = null;

    // Pour les clients
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $totalSpent = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalBookings = null;

    // Pour la plateforme
    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $platformCommissions = null;

    #[ORM\Column(type: 'decimal', precision: 15, scale: 2, nullable: true)]
    private ?string $platformRevenue = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $totalTransactions = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $newClients = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $newPrestataires = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $activePrestataires = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $activeClients = null;

    // Données détaillées (JSON)
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $detailedData = [];

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $breakdown = []; // Répartition par catégorie, mois, etc.

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $statistics = []; // Statistiques diverses

    // Métadonnées du rapport
    #[ORM\Column(type: 'string', length: 50)]
    private string $status = 'generated'; // generated, sent, archived

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $filePath = null; // Chemin du PDF généré

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $generatedBy = null; // Admin qui a généré le rapport

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $generatedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $sentAt = null;

    public function __construct()
    {
        $this->generatedAt = new \DateTimeImmutable();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getReportType(): ?string
    {
        return $this->reportType;
    }

    public function setReportType(string $reportType): self
    {
        $this->reportType = $reportType;
        return $this;
    }

    public function getEntityType(): ?string
    {
        return $this->entityType;
    }

    public function setEntityType(string $entityType): self
    {
        $this->entityType = $entityType;
        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        return $this;
    }

    public function getPeriodStart(): ?\DateTimeInterface
    {
        return $this->periodStart;
    }

    public function setPeriodStart(\DateTimeInterface $periodStart): self
    {
        $this->periodStart = $periodStart;
        return $this;
    }

    public function getPeriodEnd(): ?\DateTimeInterface
    {
        return $this->periodEnd;
    }

    public function setPeriodEnd(\DateTimeInterface $periodEnd): self
    {
        $this->periodEnd = $periodEnd;
        return $this;
    }

    public function getPeriodLabel(): string
    {
        if ($this->reportType === 'monthly' && $this->year && $this->month) {
            $months = [
                1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
                5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
                9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre'
            ];
            return $months[$this->month] . ' ' . $this->year;
        }

        if ($this->reportType === 'quarterly' && $this->year && $this->quarter) {
            return 'T' . $this->quarter . ' ' . $this->year;
        }

        if ($this->reportType === 'yearly' && $this->year) {
            return 'Année ' . $this->year;
        }

        return $this->periodStart->format('d/m/Y') . ' - ' . $this->periodEnd->format('d/m/Y');
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): self
    {
        $this->year = $year;
        return $this;
    }

    public function getMonth(): ?int
    {
        return $this->month;
    }

    public function setMonth(?int $month): self
    {
        $this->month = $month;
        return $this;
    }

    public function getQuarter(): ?int
    {
        return $this->quarter;
    }

    public function setQuarter(?int $quarter): self
    {
        $this->quarter = $quarter;
        return $this;
    }

    public function getTotalRevenue(): ?string
    {
        return $this->totalRevenue;
    }

    public function setTotalRevenue(string $totalRevenue): self
    {
        $this->totalRevenue = $totalRevenue;
        return $this;
    }

    public function getTotalExpenses(): ?string
    {
        return $this->totalExpenses;
    }

    public function setTotalExpenses(string $totalExpenses): self
    {
        $this->totalExpenses = $totalExpenses;
        return $this;
    }

    public function getNetAmount(): ?string
    {
        return $this->netAmount;
    }

    public function setNetAmount(string $netAmount): self
    {
        $this->netAmount = $netAmount;
        return $this;
    }

    public function calculateNetAmount(): void
    {
        $this->netAmount = bcsub($this->totalRevenue, $this->totalExpenses, 2);
    }

    public function getGrossEarnings(): ?string
    {
        return $this->grossEarnings;
    }

    public function setGrossEarnings(?string $grossEarnings): self
    {
        $this->grossEarnings = $grossEarnings;
        return $this;
    }

    public function getCommissionsDeducted(): ?string
    {
        return $this->commissionsDeducted;
    }

    public function setCommissionsDeducted(?string $commissionsDeducted): self
    {
        $this->commissionsDeducted = $commissionsDeducted;
        return $this;
    }

    public function getNetEarnings(): ?string
    {
        return $this->netEarnings;
    }

    public function setNetEarnings(?string $netEarnings): self
    {
        $this->netEarnings = $netEarnings;
        return $this;
    }

    public function calculateNetEarnings(): void
    {
        if ($this->grossEarnings && $this->commissionsDeducted) {
            $this->netEarnings = bcsub($this->grossEarnings, $this->commissionsDeducted, 2);
        }
    }

    public function getCompletedBookings(): ?int
    {
        return $this->completedBookings;
    }

    public function setCompletedBookings(?int $completedBookings): self
    {
        $this->completedBookings = $completedBookings;
        return $this;
    }

    public function getAverageBookingValue(): ?string
    {
        return $this->averageBookingValue;
    }

    public function setAverageBookingValue(?string $averageBookingValue): self
    {
        $this->averageBookingValue = $averageBookingValue;
        return $this;
    }

    public function getTotalSpent(): ?string
    {
        return $this->totalSpent;
    }

    public function setTotalSpent(?string $totalSpent): self
    {
        $this->totalSpent = $totalSpent;
        return $this;
    }

    public function getTotalBookings(): ?int
    {
        return $this->totalBookings;
    }

    public function setTotalBookings(?int $totalBookings): self
    {
        $this->totalBookings = $totalBookings;
        return $this;
    }

    public function getPlatformCommissions(): ?string
    {
        return $this->platformCommissions;
    }

    public function setPlatformCommissions(?string $platformCommissions): self
    {
        $this->platformCommissions = $platformCommissions;
        return $this;
    }

    public function getPlatformRevenue(): ?string
    {
        return $this->platformRevenue;
    }

    public function setPlatformRevenue(?string $platformRevenue): self
    {
        $this->platformRevenue = $platformRevenue;
        return $this;
    }

    public function getTotalTransactions(): ?int
    {
        return $this->totalTransactions;
    }

    public function setTotalTransactions(?int $totalTransactions): self
    {
        $this->totalTransactions = $totalTransactions;
        return $this;
    }

    public function getNewClients(): ?int
    {
        return $this->newClients;
    }

    public function setNewClients(?int $newClients): self
    {
        $this->newClients = $newClients;
        return $this;
    }

    public function getNewPrestataires(): ?int
    {
        return $this->newPrestataires;
    }

    public function setNewPrestataires(?int $newPrestataires): self
    {
        $this->newPrestataires = $newPrestataires;
        return $this;
    }

    public function getActivePrestataires(): ?int
    {
        return $this->activePrestataires;
    }

    public function setActivePrestataires(?int $activePrestataires): self
    {
        $this->activePrestataires = $activePrestataires;
        return $this;
    }

    public function getActiveClients(): ?int
    {
        return $this->activeClients;
    }

    public function setActiveClients(?int $activeClients): self
    {
        $this->activeClients = $activeClients;
        return $this;
    }

    public function getDetailedData(): ?array
    {
        return $this->detailedData ?? [];
    }

    public function setDetailedData(?array $detailedData): self
    {
        $this->detailedData = $detailedData;
        return $this;
    }

    public function addDetailedData(string $key, mixed $value): self
    {
        $data = $this->getDetailedData();
        $data[$key] = $value;
        $this->detailedData = $data;
        return $this;
    }

    public function getBreakdown(): ?array
    {
        return $this->breakdown ?? [];
    }

    public function setBreakdown(?array $breakdown): self
    {
        $this->breakdown = $breakdown;
        return $this;
    }

    public function addBreakdown(string $category, array $data): self
    {
        $breakdown = $this->getBreakdown();
        $breakdown[$category] = $data;
        $this->breakdown = $breakdown;
        return $this;
    }

    public function getStatistics(): ?array
    {
        return $this->statistics ?? [];
    }

    public function setStatistics(?array $statistics): self
    {
        $this->statistics = $statistics;
        return $this;
    }

    public function addStatistic(string $key, mixed $value): self
    {
        $stats = $this->getStatistics();
        $stats[$key] = $value;
        $this->statistics = $stats;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        
        if ($status === 'sent' && !$this->sentAt) {
            $this->sentAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(?string $filePath): self
    {
        $this->filePath = $filePath;
        return $this;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    public function getGeneratedBy(): ?User
    {
        return $this->generatedBy;
    }

    public function setGeneratedBy(?User $generatedBy): self
    {
        $this->generatedBy = $generatedBy;
        return $this;
    }

    public function getGeneratedAt(): ?\DateTimeImmutable
    {
        return $this->generatedAt;
    }

    public function setGeneratedAt(\DateTimeImmutable $generatedAt): self
    {
        $this->generatedAt = $generatedAt;
        return $this;
    }

    public function getSentAt(): ?\DateTimeImmutable
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeImmutable $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }
}