<?php
namespace App\Entity\Financial;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TransactionRepository::class)]
#[ORM\Table(name: 'transactions')]
class Transaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private string $type; // payment, refund, payout, commission

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $amount;

    #[ORM\ManyToOne(targetEntity: Booking::class)]
    private ?Booking $booking = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $fromUser = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    private ?User $toUser = null;

    #[ORM\Column(length: 50)]
    private string $status; // pending, completed, failed, cancelled

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripePaymentId = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTime $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $completedAt = null;

    // Getters et Setters...
}