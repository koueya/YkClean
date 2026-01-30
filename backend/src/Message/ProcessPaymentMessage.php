<?php

namespace App\Message;

/**
 * Message pour traiter les paiements de manière asynchrone
 */
class ProcessPaymentMessage
{
    private int $bookingId;
    private string $paymentIntentId;
    private float $amount;
    private string $action; // charge, refund, transfer

    public function __construct(
        int $bookingId,
        string $paymentIntentId,
        float $amount,
        string $action = 'charge'
    ) {
        $this->bookingId = $bookingId;
        $this->paymentIntentId = $paymentIntentId;
        $this->amount = $amount;
        $this->action = $action;
    }

    public function getBookingId(): int
    {
        return $this->bookingId;
    }

    public function getPaymentIntentId(): string
    {
        return $this->paymentIntentId;
    }

    public function getAmount(): float
    {
        return $this->amount;
    }

    public function getAction(): string
    {
        return $this->action;
    }
}