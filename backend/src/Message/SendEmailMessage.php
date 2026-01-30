<?php

namespace App\Message;

/**
 * Message pour envoyer des emails de manière asynchrone
 */
class SendEmailMessage
{
    private string $to;
    private string $subject;
    private string $template;
    private array $context;
    private ?string $fromEmail;
    private ?string $fromName;
    private array $attachments;
    private ?int $priority;

    public function __construct(
        string $to,
        string $subject,
        string $template,
        array $context = [],
        ?string $fromEmail = null,
        ?string $fromName = null,
        array $attachments = [],
        ?int $priority = 0
    ) {
        $this->to = $to;
        $this->subject = $subject;
        $this->template = $template;
        $this->context = $context;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->attachments = $attachments;
        $this->priority = $priority;
    }

    public function getTo(): string
    {
        return $this->to;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getTemplate(): string
    {
        return $this->template;
    }

    public function getContext(): array
    {
        return $this->context;
    }

    public function getFromEmail(): ?string
    {
        return $this->fromEmail;
    }

    public function getFromName(): ?string
    {
        return $this->fromName;
    }

    public function getAttachments(): array
    {
        return $this->attachments;
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }
}