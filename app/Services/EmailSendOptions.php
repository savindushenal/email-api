<?php

namespace App\Services;

class EmailSendOptions
{
    public function __construct(
        public ?string $fromEmail = null,
        public ?string $fromName = null,
        public ?string $replyTo = null,
        public ?string $inReplyTo = null,
        public ?string $references = null,
        /** @var string[] */
        public array $cc = [],
        /** @var string[] */
        public array $bcc = [],
    ) {
    }

    public static function fromArray(array $data): self
    {
        $cc = $data['cc'] ?? [];
        $bcc = $data['bcc'] ?? [];

        if (is_string($cc)) {
            $cc = array_filter(array_map('trim', explode(',', $cc)));
        }
        if (is_string($bcc)) {
            $bcc = array_filter(array_map('trim', explode(',', $bcc)));
        }

        return new self(
            fromEmail: $data['from_email'] ?? null,
            fromName: $data['from_name'] ?? null,
            replyTo: $data['reply_to'] ?? null,
            inReplyTo: $data['in_reply_to'] ?? null,
            references: $data['references'] ?? null,
            cc: is_array($cc) ? array_values($cc) : [],
            bcc: is_array($bcc) ? array_values($bcc) : [],
        );
    }
}
