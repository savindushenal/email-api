<?php

namespace App\Services;

use App\Models\StaffMailbox;
use App\Models\StaffMailboxProcessedMessage;
use Exception;

class InboundMailService
{
    /**
     * Poll one staff mailbox and POST new messages to the CRM webhook.
     *
     * @return array{processed:int, skipped:int, errors:string[]}
     */
    public function pollMailbox(StaffMailbox $mailbox): array
    {
        if (!function_exists('imap_open')) {
            throw new Exception('PHP IMAP extension is not installed');
        }

        $config = $mailbox->imapConfig();
        $mailboxPath = $this->buildMailboxPath($config);
        $password = $config['password'];

        if (!$password) {
            throw new Exception("No IMAP password configured for {$mailbox->email}");
        }

        $connection = @imap_open($mailboxPath, $config['username'], $password, 0, 1, [
            'DISABLE_AUTHENTICATOR' => 'GSSAPI',
        ]);

        if ($connection === false) {
            throw new Exception('IMAP connection failed: ' . imap_last_error());
        }

        $processed = 0;
        $skipped = 0;
        $errors = [];

        try {
            $uids = imap_search($connection, 'UNSEEN', SE_UID) ?: [];

            foreach ($uids as $uid) {
                $uidKey = (string) $uid;

                if (StaffMailboxProcessedMessage::query()
                    ->where('staff_mailbox_id', $mailbox->id)
                    ->where('message_uid', $uidKey)
                    ->exists()) {
                    $skipped++;
                    continue;
                }

                $overview = imap_fetch_overview($connection, $uidKey, FT_UID);
                $msgNo = imap_msgno($connection, (int) $uid);
                $header = $msgNo ? imap_headerinfo($connection, $msgNo) : null;
                $body = $this->extractBody($connection, $uidKey);

                $from = $this->formatAddress($header->fromaddress ?? ($overview[0]->from ?? ''));
                $subject = isset($overview[0]->subject)
                    ? $this->decodeMime($overview[0]->subject)
                    : null;
                $messageId = $overview[0]->message_id ?? null;
                $inReplyTo = $overview[0]->in_reply_to ?? null;
                $to = $header?->toaddress ?? null;
                $cc = $header?->ccaddress ?? null;
                $deliveredTo = $this->extractHeaderValue($connection, $uidKey, 'Delivered-To');

                $payload = [
                    'from' => $from,
                    'subject' => $subject,
                    'body' => $body,
                    'messageId' => $messageId,
                    'inReplyTo' => $inReplyTo,
                    'to' => $to,
                    'cc' => $cc,
                    'deliveredTo' => $deliveredTo,
                    'receivedAt' => now()->toIso8601String(),
                ];

                $webhookResult = $this->postToCrmWebhook($payload);

                if ($webhookResult['ok']) {
                    StaffMailboxProcessedMessage::create([
                        'staff_mailbox_id' => $mailbox->id,
                        'message_uid' => $uidKey,
                        'message_id' => $messageId,
                        'processed_at' => now(),
                    ]);
                    imap_setflag_full($connection, $uidKey, '\\Seen', ST_UID);
                    $processed++;
                } else {
                    $errors[] = "UID {$uidKey}: {$webhookResult['error']}";
                }
            }
        } finally {
            imap_close($connection);
            $mailbox->update(['last_polled_at' => now()]);
        }

        return compact('processed', 'skipped', 'errors');
    }

    /**
     * @param array{from:string,subject:?string,body:string,messageId:?string,inReplyTo:?string,to:?string,cc:?string,deliveredTo:?string,receivedAt:string} $payload
     * @return array{ok:bool,error?:string}
     */
    protected function postToCrmWebhook(array $payload): array
    {
        $url = env('INBOUND_CRM_WEBHOOK_URL');
        $secret = env('INBOUND_CRM_WEBHOOK_SECRET');

        if (!$url || !$secret) {
            return ['ok' => false, 'error' => 'CRM webhook not configured'];
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Email-Api-Secret: ' . $secret,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            return ['ok' => false, 'error' => $curlError ?: 'Webhook request failed'];
        }

        if ($status >= 200 && $status < 300) {
            return ['ok' => true];
        }

        if ($status === 404) {
            // No matching deal thread — mark processed to avoid infinite retries
            return ['ok' => true];
        }

        return ['ok' => false, 'error' => "CRM returned HTTP {$status}: {$response}"];
    }

    protected function buildMailboxPath(array $config): string
    {
        $folder = $config['folder'] ?? 'INBOX';
        $encryption = $config['encryption'] ?? 'ssl';
        $flags = '/imap';

        if ($encryption === 'ssl') {
            $flags .= '/ssl';
        } elseif ($encryption === 'tls') {
            $flags .= '/tls';
        }

        $flags .= '/novalidate-cert';

        return sprintf('{%s:%d%s}%s', $config['host'], $config['port'], $flags, $folder);
    }

    protected function extractBody($connection, string $uid): string
    {
        $structure = imap_fetchstructure($connection, $uid, FT_UID);
        $body = $this->getPartBody($connection, $uid, $structure);

        return trim($body) !== '' ? trim($body) : '(empty message body)';
    }

    protected function getPartBody($connection, string $uid, $structure, string $partNumber = ''): string
    {
        if (!$structure) {
            $raw = imap_body($connection, $uid, FT_UID);
            return $this->decodeBody($raw, 0);
        }

        if (isset($structure->parts) && count($structure->parts)) {
            foreach ($structure->parts as $index => $sub) {
                $prefix = $partNumber === '' ? (string) ($index + 1) : $partNumber . '.' . ($index + 1);
                $type = $sub->type ?? 0;
                $subtype = strtolower($sub->subtype ?? '');

                if ($type === 0 && in_array($subtype, ['plain', 'html'], true)) {
                    $raw = imap_fetchbody($connection, $uid, $prefix, FT_UID);
                    $decoded = $this->decodeBody($raw, $sub->encoding ?? 0);
                    if ($subtype === 'plain' || stripos($decoded, '<html') === false) {
                        return strip_tags($decoded);
                    }
                    return trim(strip_tags($decoded));
                }

                if (isset($sub->parts)) {
                    $nested = $this->getPartBody($connection, $uid, $sub, $prefix);
                    if ($nested !== '') {
                        return $nested;
                    }
                }
            }
        }

        $raw = imap_fetchbody($connection, $uid, $partNumber ?: '1', FT_UID);
        return $this->decodeBody($raw, $structure->encoding ?? 0);
    }

    protected function decodeBody(string $body, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode($body) ?: $body,
            ENCQUOTEDPRINTABLE => quoted_printable_decode($body),
            default => $body,
        };
    }

    protected function decodeMime(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $decoded = imap_utf8($value);
        return $decoded !== false ? $decoded : $value;
    }

    protected function formatAddress(string $raw): string
    {
        $raw = trim($raw);
        if (preg_match('/<([^>]+)>/', $raw, $matches)) {
            return strtolower(trim($matches[1]));
        }

        return strtolower($raw);
    }

    protected function extractHeaderValue($connection, string $uid, string $headerName): ?string
    {
        $raw = imap_fetchheader($connection, $uid, FT_UID);
        if (!preg_match('/^' . preg_quote($headerName, '/') . ':\s*(.+)$/im', $raw, $matches)) {
            return null;
        }

        return trim($matches[1]);
    }
}
