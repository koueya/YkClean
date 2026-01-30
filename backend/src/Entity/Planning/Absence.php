<?php
// src/Entity/Planning/Absence.php

namespace App\Planning\Entity;

use App\Repository\AbsenceRepository;
use App\Entity\Planning\Prestataire;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AbsenceRepository::class)]
#[ORM\Table(name: 'absences')]
class Absence
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'absences')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startDate = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $endDate = null;

    #[ORM\Column(type: 'string', length: 255)]
    #[Assert\NotBlank]
    private ?string $reason = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

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

    public function getStartDate(): ?\DateTimeInterface
    {
        return $this->startDate;
    }

    public function setStartDate(\DateTimeInterface $startDate): self
    {
        $this->startDate = $startDate;
        return $this;
    }

    public function getEndDate(): ?\DateTimeInterface
    {
        return $this->endDate;
    }

    public function setEndDate(\DateTimeInterface $endDate): self
    {
        $this->endDate = $endDate;
        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(string $reason): self
    {
        $this->reason = $reason;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function isActive(\DateTimeInterface $date = null): bool
    {
        $date = $date ?? new \DateTime();
        return $date >= $this->startDate && $date <= $this->endDate;
    }

    public function getDuration(): int
    {
        $diff = $this->startDate->diff($this->endDate);
        return $diff->days + 1;
    }
}