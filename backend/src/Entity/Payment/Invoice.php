<?php

namespace App\Entity\Payment;

use App\Entity\Booking\Booking;
use App\Entity\User\Client;
use App\Entity\User\Prestataire;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Entité Invoice - Représente une facture
 */
#[ORM\Entity(repositoryClass: 'App\Repository\Payment\InvoiceRepository')]
#[ORM\Table(name: 'invoices')]
#[ORM\Index(columns: ['invoice_number'], name: 'idx_invoice_number')]
#[ORM\Index(columns: ['client_id', 'status'], name: 'idx_client_status')]
#[ORM\Index(columns: ['prestataire_id', 'status'], name: 'idx_prestataire_status')]
#[ORM\Index(columns: ['status', 'due_date'], name: 'idx_status_due_date')]
#[ORM\Index(columns: ['created_at'], name: 'idx_created_at')]
#[ORM\HasLifecycleCallbacks]
class Invoice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private ?int $id = null;

    /**
     * Numéro de facture unique (ex: INV-2024-00001)
     */
    #[ORM\Column(type: 'string', length: 50, unique: true)]
    #[Assert\NotBlank(message: 'Le numéro de facture est obligatoire')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private ?string $invoiceNumber = null;

    /**
     * Type de facture
     */
    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le type de facture est obligatoire')]
    #[Assert\Choice(
        choices: ['standard', 'advance', 'credit_note', 'proforma'],
        message: 'Type de facture invalide'
    )]
    #[Groups(['invoice:read', 'invoice:list'])]
    private string $type = 'standard';

    /**
     * Client (payeur)
     */
    #[ORM\ManyToOne(targetEntity: Client::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le client est obligatoire')]
    #[Groups(['invoice:read'])]
    private ?Client $client = null;

    /**
     * Prestataire (bénéficiaire)
     */
    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le prestataire est obligatoire')]
    #[Groups(['invoice:read'])]
    private ?Prestataire $prestataire = null;

    /**
     * Réservation associée
     */
    #[ORM\ManyToOne(targetEntity: Booking::class, inversedBy: 'invoices')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    #[Groups(['invoice:read'])]
    private ?Booking $booking = null;

    /**
     * Statut de la facture
     */
    #[ORM\Column(type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(
        choices: ['draft', 'sent', 'paid', 'partially_paid', 'overdue', 'cancelled', 'refunded'],
        message: 'Statut invalide'
    )]
    #[Groups(['invoice:read', 'invoice:list'])]
    private string $status = 'draft';

    /**
     * Montant HT (hors taxes)
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Le montant HT est obligatoire')]
    #[Assert\Positive(message: 'Le montant doit être positif')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private ?string $amountHT = null;

    /**
     * Montant TVA
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero(message: 'Le montant TVA ne peut pas être négatif')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private string $amountTVA = '0.00';

    /**
     * Taux de TVA en pourcentage
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero(message: 'Le taux de TVA ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 100, message: 'Le taux de TVA ne peut pas dépasser 100%')]
    #[Groups(['invoice:read'])]
    private string $tvaRate = '0.00';

    /**
     * Montant TTC (toutes taxes comprises)
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotNull(message: 'Le montant TTC est obligatoire')]
    #[Assert\Positive(message: 'Le montant TTC doit être positif')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private ?string $amountTTC = null;

    /**
     * Montant déjà payé
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, options: ['default' => '0.00'])]
    #[Assert\PositiveOrZero(message: 'Le montant payé ne peut pas être négatif')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private string $amountPaid = '0.00';

    /**
     * Montant restant dû
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Groups(['invoice:read', 'invoice:list'])]
    private ?string $amountDue = null;

    /**
     * Remise en pourcentage
     */
    #[ORM\Column(type: 'decimal', precision: 5, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'La remise ne peut pas être négative')]
    #[Assert\LessThanOrEqual(value: 100, message: 'La remise ne peut pas dépasser 100%')]
    #[Groups(['invoice:read'])]
    private ?string $discountPercent = null;

    /**
     * Montant de la remise
     */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\PositiveOrZero(message: 'Le montant de remise ne peut pas être négatif')]
    #[Groups(['invoice:read'])]
    private ?string $discountAmount = null;

    /**
     * Lignes de facture (détails)
     */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: InvoiceItem::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[Groups(['invoice:read'])]
    private Collection $items;

    /**
     * Date d'émission de la facture
     */
    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'La date d\'émission est obligatoire')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private ?\DateTimeInterface $issuedAt = null;

    /**
     * Date d'échéance (date limite de paiement)
     */
    #[ORM\Column(type: 'date')]
    #[Assert\NotNull(message: 'La date d\'échéance est obligatoire')]
    #[Groups(['invoice:read', 'invoice:list'])]
    private ?\DateTimeInterface $dueDate = null;

    /**
     * Date de paiement effectif
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $paidAt = null;

    /**
     * Délai de paiement en jours
     */
    #[ORM\Column(type: 'integer', options: ['default' => 30])]
    #[Assert\Positive(message: 'Le délai de paiement doit être positif')]
    #[Groups(['invoice:read'])]
    private int $paymentTermDays = 30;

    /**
     * Méthode de paiement utilisée
     */
    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $paymentMethod = null;

    /**
     * Référence de paiement (transaction ID)
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $paymentReference = null;

    /**
     * Paiements associés à cette facture
     */
    #[ORM\OneToMany(mappedBy: 'invoice', targetEntity: Payment::class)]
    private Collection $payments;

    /**
     * Notes ou commentaires sur la facture
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $notes = null;

    /**
     * Conditions de paiement
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $paymentConditions = null;

    /**
     * Mentions légales
     */
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $legalMentions = null;

    /**
     * Chemin vers le PDF de la facture
     */
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    #[Groups(['invoice:read'])]
    private ?string $pdfPath = null;

    /**
     * Devise (EUR par défaut)
     */
    #[ORM\Column(type: 'string', length: 3, options: ['default' => 'EUR'])]
    #[Assert\Currency(message: 'Code devise invalide')]
    #[Groups(['invoice:read'])]
    private string $currency = 'EUR';

    /**
     * Adresse de facturation du client
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?array $billingAddress = null;

    /**
     * Informations du prestataire (snapshot au moment de la facture)
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?array $prestataireInfo = null;

    /**
     * Envoyée par email
     */
    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    #[Groups(['invoice:read'])]
    private bool $sentByEmail = false;

    /**
     * Date d'envoi par email
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $sentAt = null;

    /**
     * Nombre de relances envoyées
     */
    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    #[Groups(['invoice:read'])]
    private int $reminderCount = 0;

    /**
     * Date de dernière relance
     */
    #[ORM\Column(type: 'datetime', nullable: true)]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $lastReminderAt = null;

    /**
     * Date de création
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $createdAt = null;

    /**
     * Date de dernière modification
     */
    #[ORM\Column(type: 'datetime')]
    #[Groups(['invoice:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->items = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->issuedAt = new \DateTime();
        $this->calculateDueDate();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTime();
    }

    #[ORM\PrePersist]
    #[ORM\PreUpdate]
    public function calculateAmounts(): void
    {
        // Calculer le montant HT total des items
        if ($this->items->count() > 0) {
            $totalHT = '0.00';
            foreach ($this->items as $item) {
                $totalHT = bcadd($totalHT, $item->getTotalPrice(), 2);
            }
            $this->amountHT = $totalHT;
        }

        // Appliquer la remise
        $amountAfterDiscount = $this->amountHT;
        if ($this->discountPercent !== null) {
            $discount = bcmul($this->amountHT, bcdiv($this->discountPercent, '100', 4), 2);
            $this->discountAmount = $discount;
            $amountAfterDiscount = bcsub($this->amountHT, $discount, 2);
        } elseif ($this->discountAmount !== null) {
            $amountAfterDiscount = bcsub($this->amountHT, $this->discountAmount, 2);
        }

        // Calculer la TVA
        $this->amountTVA = bcmul($amountAfterDiscount, bcdiv($this->tvaRate, '100', 4), 2);

        // Calculer le TTC
        $this->amountTTC = bcadd($amountAfterDiscount, $this->amountTVA, 2);

        // Calculer le montant dû
        $this->amountDue = bcsub($this->amountTTC, $this->amountPaid, 2);
    }

    // Getters et Setters

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getInvoiceNumber(): ?string
    {
        return $this->invoiceNumber;
    }

    public function setInvoiceNumber(?string $invoiceNumber): self
    {
        $this->invoiceNumber = $invoiceNumber;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getClient(): ?Client
    {
        return $this->client;
    }

    public function setClient(?Client $client): self
    {
        $this->client = $client;
        return $this;
    }

    public function getPrestataire(): ?Prestataire
    {
        return $this->prestataire;
    }

    public function setPrestataire(?Prestataire $prestataire): self
    {
        $this->prestataire = $prestataire;
        return $this;
    }

    public function getBooking(): ?Booking
    {
        return $this->booking;
    }

    public function setBooking(?Booking $booking): self
    {
        $this->booking = $booking;
        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;
        
        // Si la facture est payée, mettre à jour la date de paiement
        if ($status === 'paid' && !$this->paidAt) {
            $this->paidAt = new \DateTime();
        }
        
        return $this;
    }

    public function getAmountHT(): ?string
    {
        return $this->amountHT;
    }

    public function setAmountHT(?string $amountHT): self
    {
        $this->amountHT = $amountHT;
        return $this;
    }

    public function getAmountTVA(): string
    {
        return $this->amountTVA;
    }

    public function setAmountTVA(string $amountTVA): self
    {
        $this->amountTVA = $amountTVA;
        return $this;
    }

    public function getTvaRate(): string
    {
        return $this->tvaRate;
    }

    public function setTvaRate(string $tvaRate): self
    {
        $this->tvaRate = $tvaRate;
        return $this;
    }

    public function getAmountTTC(): ?string
    {
        return $this->amountTTC;
    }

    public function setAmountTTC(?string $amountTTC): self
    {
        $this->amountTTC = $amountTTC;
        return $this;
    }

    public function getAmountPaid(): string
    {
        return $this->amountPaid;
    }

    public function setAmountPaid(string $amountPaid): self
    {
        $this->amountPaid = $amountPaid;
        return $this;
    }

    public function getAmountDue(): ?string
    {
        return $this->amountDue;
    }

    public function setAmountDue(?string $amountDue): self
    {
        $this->amountDue = $amountDue;
        return $this;
    }

    public function getDiscountPercent(): ?string
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(?string $discountPercent): self
    {
        $this->discountPercent = $discountPercent;
        return $this;
    }

    public function getDiscountAmount(): ?string
    {
        return $this->discountAmount;
    }

    public function setDiscountAmount(?string $discountAmount): self
    {
        $this->discountAmount = $discountAmount;
        return $this;
    }

    /**
     * @return Collection<int, InvoiceItem>
     */
    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(InvoiceItem $item): self
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setInvoice($this);
        }

        return $this;
    }

    public function removeItem(InvoiceItem $item): self
    {
        if ($this->items->removeElement($item)) {
            if ($item->getInvoice() === $this) {
                $item->setInvoice(null);
            }
        }

        return $this;
    }

    public function getIssuedAt(): ?\DateTimeInterface
    {
        return $this->issuedAt;
    }

    public function setIssuedAt(?\DateTimeInterface $issuedAt): self
    {
        $this->issuedAt = $issuedAt;
        $this->calculateDueDate();
        return $this;
    }

    public function getDueDate(): ?\DateTimeInterface
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeInterface $dueDate): self
    {
        $this->dueDate = $dueDate;
        return $this;
    }

    public function getPaidAt(): ?\DateTimeInterface
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeInterface $paidAt): self
    {
        $this->paidAt = $paidAt;
        return $this;
    }

    public function getPaymentTermDays(): int
    {
        return $this->paymentTermDays;
    }

    public function setPaymentTermDays(int $paymentTermDays): self
    {
        $this->paymentTermDays = $paymentTermDays;
        $this->calculateDueDate();
        return $this;
    }

    public function getPaymentMethod(): ?string
    {
        return $this->paymentMethod;
    }

    public function setPaymentMethod(?string $paymentMethod): self
    {
        $this->paymentMethod = $paymentMethod;
        return $this;
    }

    public function getPaymentReference(): ?string
    {
        return $this->paymentReference;
    }

    public function setPaymentReference(?string $paymentReference): self
    {
        $this->paymentReference = $paymentReference;
        return $this;
    }

    /**
     * @return Collection<int, Payment>
     */
    public function getPayments(): Collection
    {
        return $this->payments;
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

    public function getPaymentConditions(): ?string
    {
        return $this->paymentConditions;
    }

    public function setPaymentConditions(?string $paymentConditions): self
    {
        $this->paymentConditions = $paymentConditions;
        return $this;
    }

    public function getLegalMentions(): ?string
    {
        return $this->legalMentions;
    }

    public function setLegalMentions(?string $legalMentions): self
    {
        $this->legalMentions = $legalMentions;
        return $this;
    }

    public function getPdfPath(): ?string
    {
        return $this->pdfPath;
    }

    public function setPdfPath(?string $pdfPath): self
    {
        $this->pdfPath = $pdfPath;
        return $this;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): self
    {
        $this->currency = $currency;
        return $this;
    }

    public function getBillingAddress(): ?array
    {
        return $this->billingAddress;
    }

    public function setBillingAddress(?array $billingAddress): self
    {
        $this->billingAddress = $billingAddress;
        return $this;
    }

    public function getPrestataireInfo(): ?array
    {
        return $this->prestataireInfo;
    }

    public function setPrestataireInfo(?array $prestataireInfo): self
    {
        $this->prestataireInfo = $prestataireInfo;
        return $this;
    }

    public function isSentByEmail(): bool
    {
        return $this->sentByEmail;
    }

    public function setSentByEmail(bool $sentByEmail): self
    {
        $this->sentByEmail = $sentByEmail;
        
        if ($sentByEmail && !$this->sentAt) {
            $this->sentAt = new \DateTime();
        }
        
        return $this;
    }

    public function getSentAt(): ?\DateTimeInterface
    {
        return $this->sentAt;
    }

    public function setSentAt(?\DateTimeInterface $sentAt): self
    {
        $this->sentAt = $sentAt;
        return $this;
    }

    public function getReminderCount(): int
    {
        return $this->reminderCount;
    }

    public function setReminderCount(int $reminderCount): self
    {
        $this->reminderCount = $reminderCount;
        return $this;
    }

    public function incrementReminderCount(): self
    {
        $this->reminderCount++;
        $this->lastReminderAt = new \DateTime();
        return $this;
    }

    public function getLastReminderAt(): ?\DateTimeInterface
    {
        return $this->lastReminderAt;
    }

    public function setLastReminderAt(?\DateTimeInterface $lastReminderAt): self
    {
        $this->lastReminderAt = $lastReminderAt;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    // Méthodes utilitaires

    /**
     * Calcule la date d'échéance à partir de la date d'émission et du délai
     */
    private function calculateDueDate(): void
    {
        if ($this->issuedAt) {
            $this->dueDate = (clone $this->issuedAt)->modify("+{$this->paymentTermDays} days");
        }
    }

    /**
     * Vérifie si la facture est en retard
     */
    #[Groups(['invoice:read', 'invoice:list'])]
    public function isOverdue(): bool
    {
        if ($this->status === 'paid' || $this->status === 'cancelled') {
            return false;
        }

        if (!$this->dueDate) {
            return false;
        }

        return $this->dueDate < new \DateTime();
    }

    /**
     * Obtient le nombre de jours de retard
     */
    #[Groups(['invoice:read'])]
    public function getDaysOverdue(): int
    {
        if (!$this->isOverdue()) {
            return 0;
        }

        $now = new \DateTime();
        return $now->diff($this->dueDate)->days;
    }

    /**
     * Vérifie si la facture est complètement payée
     */
    public function isPaid(): bool
    {
        return $this->status === 'paid' || bccomp($this->amountDue, '0.00', 2) <= 0;
    }

    /**
     * Vérifie si la facture est partiellement payée
     */
    public function isPartiallyPaid(): bool
    {
        return bccomp($this->amountPaid, '0.00', 2) > 0 && 
               bccomp($this->amountDue, '0.00', 2) > 0;
    }

    /**
     * Enregistre un paiement
     */
    public function recordPayment(string $amount): self
    {
        $this->amountPaid = bcadd($this->amountPaid, $amount, 2);
        $this->amountDue = bcsub($this->amountTTC, $this->amountPaid, 2);

        // Mettre à jour le statut
        if (bccomp($this->amountDue, '0.00', 2) <= 0) {
            $this->status = 'paid';
            $this->paidAt = new \DateTime();
        } else {
            $this->status = 'partially_paid';
        }

        return $this;
    }

    /**
     * Marque la facture comme payée
     */
    public function markAsPaid(): self
    {
        $this->status = 'paid';
        $this->amountPaid = $this->amountTTC;
        $this->amountDue = '0.00';
        $this->paidAt = new \DateTime();

        return $this;
    }

    /**
     * Annule la facture
     */
    public function cancel(): self
    {
        $this->status = 'cancelled';
        return $this;
    }

    /**
     * Génère le numéro de facture
     */
    public function generateInvoiceNumber(int $year = null, int $sequence = null): self
    {
        $year = $year ?? (int) date('Y');
        $sequence = $sequence ?? 1;
        
        $this->invoiceNumber = sprintf('INV-%d-%05d', $year, $sequence);
        
        return $this;
    }

    /**
     * Snapshot des informations du prestataire
     */
    public function snapshotPrestataireInfo(): self
    {
        if ($this->prestataire) {
            $this->prestataireInfo = [
                'name' => $this->prestataire->getFullName(),
                'company' => $this->prestataire->getCompanyName(),
                'siret' => $this->prestataire->getSiret(),
                'email' => $this->prestataire->getEmail(),
                'phone' => $this->prestataire->getPhone(),
                'address' => $this->prestataire->getAddress(),
                'city' => $this->prestataire->getCity(),
                'postalCode' => $this->prestataire->getPostalCode(),
            ];
        }

        return $this;
    }

    /**
     * Snapshot de l'adresse de facturation du client
     */
    public function snapshotBillingAddress(): self
    {
        if ($this->client) {
            $this->billingAddress = [
                'name' => $this->client->getFullName(),
                'email' => $this->client->getEmail(),
                'phone' => $this->client->getPhone(),
                'address' => $this->client->getAddress(),
                'city' => $this->client->getCity(),
                'postalCode' => $this->client->getPostalCode(),
            ];
        }

        return $this;
    }

    /**
     * Retourne un tableau pour l'export
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'invoiceNumber' => $this->invoiceNumber,
            'type' => $this->type,
            'status' => $this->status,
            'amountHT' => (float) $this->amountHT,
            'amountTVA' => (float) $this->amountTVA,
            'amountTTC' => (float) $this->amountTTC,
            'amountPaid' => (float) $this->amountPaid,
            'amountDue' => (float) $this->amountDue,
            'issuedAt' => $this->issuedAt?->format('Y-m-d'),
            'dueDate' => $this->dueDate?->format('Y-m-d'),
            'paidAt' => $this->paidAt?->format('Y-m-d H:i:s'),
            'isOverdue' => $this->isOverdue(),
            'daysOverdue' => $this->getDaysOverdue(),
            'clientName' => $this->client?->getFullName(),
            'prestataireName' => $this->prestataire?->getFullName(),
        ];
    }
}