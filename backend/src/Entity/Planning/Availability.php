<?php
// src/Entity/Planning/Availability.php

namespace App\Planning\Entity;

use App\Repository\AvailabilityRepository;
use App\Entity\Planning\AvailableSlot;
use App\Entity\Planning\Prestataire;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
#[ORM\Table(name: 'availabilities')]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Prestataire::class, inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Prestataire $prestataire = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $dayOfWeek = null; // 0 = Dimanche, 6 = Samedi

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: 'time')]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isRecurring = true;

    #[ORM\Column(type: 'date', nullable: true)]
    private ?\DateTimeInterface $specificDate = null;

    #[ORM\Column(type: 'boolean')]
    private bool $isAvailable = true;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(mappedBy: 'sourceAvailability', targetEntity: AvailableSlot::class)]
    private Collection $generatedSlots;

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

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): self
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getDayOfWeekName(): ?string
    {
        $days = [
            0 => 'Dimanche',
            1 => 'Lundi',
            2 => 'Mardi',
            3 => 'Mercredi',
            4 => 'Jeudi',
            5 => 'Vendredi',
            6 => 'Samedi'
        ];

        return $days[$this->dayOfWeek] ?? null;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): self
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): self
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): self
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function getSpecificDate(): ?\DateTimeInterface
    {
        return $this->specificDate;
    }

    public function setSpecificDate(?\DateTimeInterface $specificDate): self
    {
        $this->specificDate = $specificDate;
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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}