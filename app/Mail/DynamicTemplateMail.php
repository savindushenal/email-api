<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Queue\SerializesModels;

class DynamicTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlContent;
    public string $emailSubject;
    public string $fromEmail;
    public string $fromName;

    /** @var string[] */
    public array $ccRecipients;

    /** @var string[] */
    public array $bccRecipients;

    public ?string $replyToAddress;

    /**
     * @param string[] $ccRecipients
     * @param string[] $bccRecipients
     */
    public function __construct(
        string $htmlContent,
        string $subject,
        string $fromEmail,
        string $fromName,
        array $ccRecipients = [],
        array $bccRecipients = [],
        ?string $replyToAddress = null
    ) {
        $this->htmlContent = $htmlContent;
        $this->emailSubject = $subject;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->ccRecipients = $ccRecipients;
        $this->bccRecipients = $bccRecipients;
        $this->replyToAddress = $replyToAddress;
    }

    public function envelope(): Envelope
    {
        $cc = array_map(fn (string $email) => new Address($email), $this->ccRecipients);
        $bcc = array_map(fn (string $email) => new Address($email), $this->bccRecipients);
        $replyTo = $this->replyToAddress
            ? [new Address($this->replyToAddress)]
            : [];

        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            replyTo: $replyTo,
            cc: $cc,
            bcc: $bcc,
            subject: $this->emailSubject,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->htmlContent,
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
