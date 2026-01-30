<?php
// src/Entity/Financial/BankAccount.php

namespace App\Entity\Financial;

use App\Entity\Prestataire;
use App\Repository\Financial\BankAccountRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: BankAccountRepository::class)]
#[ORM\Table(name: 'bank_accounts')]
class BankAccount
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'bankAccounts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $holderName = null;

    #[ORM\Column(type: 'string', length: 34)]
    #[Assert\NotBlank]
    #[Assert\Iban]
    private ?string $iban = null;

    #[ORM\Column(type: 'string', length: 11)]
    #[Assert\NotBlank]
    #[Assert\Bic]
    private ?string $bic = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isDefault = false;

    #[ORM\Column(type: 'boolean')]
    private bool $isVerified = false;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters and Setters

    public function getId(): ?int
    {
        return $this->id;
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

    public function getHolderName(): ?string
    {
        return $this->holderName;
    }

    public function setHolderName(string $holderName): self
    {
        $this->holderName = $holderName;
        return $this;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function setIban(string $iban): self
    {
        $this->iban = $iban;
        return $this;
    }

    public function getMaskedIban(): string
    {
        if (!$this->iban) {
            return '';
        }
        
        $length = strlen($this->iban);
        return substr($this->iban, 0, 4) . str_repeat('*', $length - 8) . substr($this->iban, -4);
    }

    public function getBic(): ?string
    {
        return $this->bic;
    }

    public function setBic(string $bic): self
    {
        $this->bic = $bic;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;
        
        if ($isVerified && !$this->verifiedAt) {
            $this->verifiedAt = new \DateTimeImmutable();
        }
        
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): self
    {
        $this->verifiedAt = $verifiedAt;
        return $this;
    }
}
