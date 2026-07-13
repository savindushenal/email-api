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
    public array $ccRecipients;

    /** @var string[] */
    public array $bccRecipients;

    public ?string $replyToAddress;
    public ?string $messageIdHeader;
    public ?string $inReplyToHeader;
    public ?string $referencesHeader;

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
        ?string $replyToAddress = null,
        ?string $messageIdHeader = null,
        ?string $inReplyToHeader = null,
        ?string $referencesHeader = null
    ) {
        $this->htmlContent = $htmlContent;
        $this->emailSubject = $subject;
        $this->fromEmail = $fromEmail;
        $this->fromName = $fromName;
        $this->ccRecipients = $ccRecipients;
        $this->bccRecipients = $bccRecipients;
        $this->replyToAddress = $replyToAddress;
        $this->messageIdHeader = $messageIdHeader;
        $this->inReplyToHeader = $inReplyToHeader;
        $this->referencesHeader = $referencesHeader;
    }

    public function envelope(): Envelope
    {
        $cc = array_map(fn (string $email) => new Address($email), $this->ccRecipients);
        $bcc = array_map(fn (string $email) => new Address($email), $this->bccRecipients);
        $replyTo = $this->replyToAddress ? [new Address($this->replyToAddress)] : [];

        return new Envelope(
            from: new Address($this->fromEmail, $this->fromName),
            replyTo: $replyTo,
            cc: $cc,
            bcc: $bcc,
            subject: $this->emailSubject,
        );
    }

    public function headers(): Headers
    {
        $text = [];
        $inReplyTo = $this->formatMessageIdHeader($this->inReplyToHeader);
        if ($inReplyTo !== null) {
            $text['In-Reply-To'] = $inReplyTo;
        }
        if ($this->referencesHeader !== null && trim($this->referencesHeader) !== '') {
            $text['References'] = trim($this->referencesHeader);
        }

        if ($this->messageIdHeader === null) {
            return $text === [] ? new Headers() : new Headers(text: $text);
        }

        return new Headers(
            messageId: trim($this->messageIdHeader, '<>'),
            text: $text === [] ? [] : $text,
        );
    }

    protected function formatMessageIdHeader(?string $messageId): ?string
    {
        if ($messageId === null) {
            return null;
        }
        $id = trim($messageId, " \t\n\r\0\x0B<>");
        return $id === '' ? null : sprintf('<%s>', $id);
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
