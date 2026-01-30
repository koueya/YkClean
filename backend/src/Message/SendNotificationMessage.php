<?php

namespace App\Message;

/**
 * Message pour envoyer des notifications de manière asynchrone
 */
class SendNotificationMessage
{
    private int $userId;
    private string $type;
    private string $title;
    private string $body;
    private array $data;
    private array $channels; // email, push, sms
    private ?int $priority;
    private ?\DateTimeInterface $scheduledAt;

    public function __construct(
        int $userId,
        string $type,
        string $title,
        string $body,
        array $data = [],
        array $channels = ['push'],
        ?int $priority = 0,
        ?\DateTimeInterface $scheduledAt = null
    ) {
        $this->userId = $userId;
        $this->type = $type;
        $this->title = $title;
        $this->body = $body;
        $this->data = $data;
        $this->channels = $channels;
        $this->priority = $priority;
        $this->scheduledAt = $scheduledAt;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function getChannels(): array
    {
        return $this->channels;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function getScheduledAt(): ?\DateTimeInterface
    {
        return $this->scheduledAt;
    }

    public function shouldSendEmail(): bool
    {
        return in_array('email', $this->channels);
    }

    public function shouldSendPush(): bool
    {
        return in_array('push', $this->channels);
    }

    public function shouldSendSms(): bool
    {
        return in_array('sms', $this->channels);
    }
}