<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Headers;
use Illuminate\Queue\SerializesModels;

class DynamicTemplateMail extends Mailable
{
    use Queueable, SerializesModels;

    public string $htmlContent;
    public string $emailSubject;
    public string $fromEmail;
    public string $fromName;

    /** @var string[] */
    public array $ccEmails;

    /** @var string[] */
    public array $bccEmails;

    public ?string $replyToEmail;
    public ?string $messageId;

    /**
     * @param string[] $ccEmails
     * @param string[] $bccEmails
     */
    public function __construct(
        string $htmlContent,
        string $subject,
        string $fromEmail,
        string $fromName,
        array $ccEmails = [],
        array $bccEmails = [],
        ?string $replyToEmail = null,
        ?string $messageId = null
    ) {
        $this->htmlContent = $htmlContent;
        $this->emailSubject = $subject;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->ccEmails = $ccEmails;
        $this->bccEmails = $bccEmails;
        $this->replyToEmail = $replyToEmail;
        $this->messageId = $messageId;
    }

    public function envelope(): Envelope
    {
        $envelope = new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            subject: $this->emailSubject,
        );

        if ($this->replyToEmail) {
            $envelope->replyTo = [new Address($this->replyToEmail)];
        }

        if (!empty($this->ccEmails)) {
            $envelope->cc = array_map(fn (string $email) => new Address($email), $this->ccEmails);
        }

        if (!empty($this->bccEmails)) {
            $envelope->bcc = array_map(fn (string $email) => new Address($email), $this->bccEmails);
        }

        return $envelope;
    }

    public function headers(): Headers
    {
        if (!$this->messageId) {
            return new Headers();
        }

        return new Headers(
            messageId: $this->messageId,
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

