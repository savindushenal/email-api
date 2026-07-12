<?php

namespace App\Services;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;

class InboundImapService
{
    public function __construct(
        protected DomainMailConfigService $mailConfigService
    ) {
    }

    /**
     * Poll IMAP inboxes (per-domain mail_config.inbound) and forward deal replies to CRM.
     *
     * @return array{processed: int, forwarded: int, skipped: int, errors: int, mailboxes: int}
     */
    public function poll(): array
    {
        if (!extension_loaded('imap')) {
            throw new \RuntimeException('PHP IMAP extension is not installed');
        }

        $mailboxes = $this->resolveMailboxes();
        if ($mailboxes === []) {
            throw new \RuntimeException(
                'No inbound IMAP mailboxes configured — enable mail_config.inbound on an active email_domains row'
            );
        }

        $stats = ['processed' => 0, 'forwarded' => 0, 'skipped' => 0, 'errors' => 0, 'mailboxes' => count($mailboxes)];

        foreach ($mailboxes as $config) {
            $label = $config['username'];
            try {
                $mailboxStats = $this->pollMailbox($config);
                foreach (['processed', 'forwarded', 'skipped', 'errors'] as $key) {
                    $stats[$key] += $mailboxStats[$key];
                }
            } catch (\Throwable $e) {
                $stats['errors']++;
                Log::error('[imap] Mailbox poll failed', [
                    'domain' => $config['domain'] ?? null,
                    'mailbox' => $label,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $stats;
    }

    /**
     * @return list<array{domain?: string, domain_id?: int, username: string, password: string, host: string, port: int, encryption: string, folder: string}>
     */
    protected function resolveMailboxes(): array
    {
        $fromDb = $this->mailConfigService->resolveInboundMailboxesFromDatabase();
        if ($fromDb !== []) {
            return $fromDb;
        }

        return $this->resolveMailboxesFromLegacyEnv();
    }

    /**
     * @deprecated Use email_domains.mail_config.inbound instead
     * @return list<array{username: string, password: string, host: string, port: int, encryption: string, folder: string}>
     */
    protected function resolveMailboxesFromLegacyEnv(): array
    {
        $raw = env('INBOUND_IMAP_MAILBOXES');
        if (is_string($raw) && trim($raw) !== '') {
            Log::warning('[imap] INBOUND_IMAP_MAILBOXES env is deprecated — configure mail_config.inbound on email_domains');

            $decoded = json_decode($raw, true);
            if (!is_array($decoded)) {
                throw new \RuntimeException('INBOUND_IMAP_MAILBOXES must be a JSON array');
            }

            $mailboxes = [];
            foreach ($decoded as $entry) {
                if (!is_array($entry)) {
                    continue;
                }
                $username = trim((string) ($entry['username'] ?? ''));
                $password = (string) ($entry['password'] ?? '');
                if ($username === '' || $password === '') {
                    continue;
                }
                $mailboxes[] = $this->normalizeLegacyMailboxConfig($entry, $username, $password);
            }

            if ($mailboxes !== []) {
                return $mailboxes;
            }
        }

        $username = trim((string) env('INBOUND_IMAP_USERNAME', ''));
        $password = (string) env('INBOUND_IMAP_PASSWORD', '');
        if ($username === '' || $password === '') {
            return [];
        }

        Log::warning('[imap] INBOUND_IMAP_USERNAME/PASSWORD env is deprecated — configure mail_config.inbound on email_domains');

        return [$this->normalizeLegacyMailboxConfig([], $username, $password)];
    }

    /**
     * @param array<string, mixed> $entry
     * @return array{username: string, password: string, host: string, port: int, encryption: string, folder: string}
     */
    protected function normalizeLegacyMailboxConfig(array $entry, string $username, string $password): array
    {
        return [
            'username' => $username,
            'password' => $password,
            'host' => (string) ($entry['host'] ?? env('INBOUND_IMAP_HOST', 'localhost')),
            'port' => (int) ($entry['port'] ?? env('INBOUND_IMAP_PORT', 993)),
            'encryption' => strtolower((string) ($entry['encryption'] ?? env('INBOUND_IMAP_ENCRYPTION', 'ssl'))),
            'folder' => (string) ($entry['folder'] ?? env('INBOUND_IMAP_FOLDER', 'INBOX')),
        ];
    }

    /**
     * @param array{username: string, password: string, host: string, port: int, encryption: string, folder: string} $config
     * @return array{processed: int, forwarded: int, skipped: int, errors: int}
     */
    protected function pollMailbox(array $config): array
    {
        $mailbox = $this->buildMailboxString($config);
        $connection = @imap_open($mailbox, $config['username'], $config['password']);
        if ($connection === false) {
            throw new \RuntimeException(
                sprintf('IMAP connect failed for %s: %s', $config['username'], imap_last_error())
            );
        }

        $stats = ['processed' => 0, 'forwarded' => 0, 'skipped' => 0, 'errors' => 0];

        try {
            $uids = imap_search($connection, 'UNSEEN') ?: [];
            foreach ($uids as $uid) {
                $stats['processed']++;
                try {
                    $payload = $this->parseMessage($connection, (int) $uid);
                    if ($payload === null) {
                        $stats['skipped']++;
                        imap_setflag_full($connection, (string) $uid, '\\Seen');
                        continue;
                    }

                    if (!empty($config['domain'])) {
                        $payload['sourceDomain'] = $config['domain'];
                    }

                    if ($this->forwardToCrm($payload)) {
                        $stats['forwarded']++;
                        imap_setflag_full($connection, (string) $uid, '\\Seen');
                    } else {
                        $stats['errors']++;
                    }
                } catch (\Throwable $e) {
                    $stats['errors']++;
                    Log::error('[imap] Message parse/forward failed', [
                        'domain' => $config['domain'] ?? null,
                        'mailbox' => $config['username'],
                        'uid' => $uid,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        } finally {
            imap_close($connection);
        }

        return $stats;
    }

    /**
     * @param array{host: string, port: int, encryption: string, folder: string} $config
     */
    protected function buildMailboxString(array $config): string
    {
        $flags = '/imap';
        if ($config['encryption'] === 'ssl') {
            $flags .= '/ssl';
        } elseif ($config['encryption'] === 'tls') {
            $flags .= '/tls';
        }

        return sprintf('{%s:%d%s}%s', $config['host'], $config['port'], $flags, $config['folder']);
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function parseMessage($connection, int $uid): ?array
    {
        $header = imap_headerinfo($connection, $uid);
        if (!$header) {
            return null;
        }

        $from = '';
        if (!empty($header->from[0])) {
            $mailbox = $header->from[0]->mailbox ?? '';
            $host = $header->from[0]->host ?? '';
            $from = strtolower(trim($mailbox . '@' . $host));
        }
        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $structure = imap_fetchstructure($connection, $uid);
        $body = $this->extractBody($connection, $uid, $structure);
        if (trim($body) === '') {
            return null;
        }

        $rawHeader = imap_fetchheader($connection, $uid) ?: '';
        $messageId = isset($header->message_id) ? trim($header->message_id, '<>') : null;
        $inReplyTo = $this->extractHeaderValue($rawHeader, 'In-Reply-To');
        $deliveredTo = $this->extractHeaderValue($rawHeader, 'Delivered-To');
        $to = $this->formatAddressList($header->to ?? []);
        $cc = $this->formatAddressList($header->cc ?? []);

        $replyToken = null;
        if (preg_match('/reply\+([A-Za-z0-9_-]+)@/i', $rawHeader, $m)) {
            $replyToken = $m[1];
        }

        $receivedAt = null;
        if (!empty($header->date)) {
            $ts = strtotime($header->date);
            if ($ts !== false) {
                $receivedAt = gmdate('c', $ts);
            }
        }

        return [
            'replyToken' => $replyToken,
            'from' => $from,
            'subject' => isset($header->subject) ? imap_utf8((string) $header->subject) : null,
            'body' => $body,
            'messageId' => $messageId,
            'inReplyTo' => $inReplyTo ? trim($inReplyTo, '<>') : null,
            'receivedAt' => $receivedAt,
            'deliveredTo' => $deliveredTo,
            'to' => $to,
            'cc' => $cc,
        ];
    }

    /**
     * @param object|array|null $addresses
     */
    protected function formatAddressList($addresses): ?string
    {
        if (empty($addresses)) {
            return null;
        }
        $parts = [];
        foreach ($addresses as $addr) {
            $mailbox = $addr->mailbox ?? '';
            $host = $addr->host ?? '';
            if ($mailbox && $host) {
                $parts[] = $mailbox . '@' . $host;
            }
        }
        return $parts ? implode(', ', $parts) : null;
    }

    protected function extractHeaderValue(string $rawHeader, string $name): ?string
    {
        if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.+)$/im', $rawHeader, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    protected function extractBody($connection, int $uid, $structure): string
    {
        if (!$structure) {
            $raw = imap_body($connection, $uid) ?: '';
            return $this->decodePart($raw, 0);
        }

        if (!isset($structure->parts)) {
            $raw = imap_body($connection, $uid) ?: '';
            return trim($this->decodePart($raw, $structure->encoding ?? 0));
        }

        $plain = '';
        $html = '';
        foreach ($structure->parts as $index => $part) {
            $partNum = (string) ($index + 1);
            $raw = imap_fetchbody($connection, $uid, $partNum) ?: '';
            $decoded = $this->decodePart($raw, $part->encoding ?? 0);
            $type = $part->type ?? 0;
            $subtype = strtolower($part->subtype ?? '');
            if ($type === 0 && $subtype === 'plain' && $plain === '') {
                $plain = $decoded;
            }
            if ($type === 0 && $subtype === 'html' && $html === '') {
                $html = $decoded;
            }
        }

        $text = trim($plain) !== '' ? $plain : strip_tags($html);
        return trim(preg_replace("/\r\n?|\n/", "\n", $text));
    }

    protected function decodePart(string $text, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode($text) ?: '',
            ENCQUOTEDPRINTABLE => quoted_printable_decode($text),
            default => $text,
        };
    }

    /**
     * @param array<string, mixed> $payload
     */
    protected function forwardToCrm(array $payload): bool
    {
        $url = env('INBOUND_CRM_WEBHOOK_URL');
        $secret = env('INBOUND_CRM_WEBHOOK_SECRET');

        if (!$url || !$secret) {
            throw new \RuntimeException('INBOUND_CRM_WEBHOOK_URL and INBOUND_CRM_WEBHOOK_SECRET are required');
        }

        $client = new Client(['timeout' => 20]);

        try {
            $response = $client->post($url, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-Email-Api-Secret' => $secret,
                ],
                'json' => $payload,
                'http_errors' => false,
            ]);
        } catch (GuzzleException $e) {
            Log::error('[imap] CRM webhook request failed', ['error' => $e->getMessage()]);
            return false;
        }

        $status = $response->getStatusCode();
        if ($status === 404) {
            return true;
        }

        if ($status < 200 || $status >= 300) {
            Log::warning('[imap] CRM webhook rejected payload', [
                'status' => $status,
                'body' => (string) $response->getBody(),
            ]);
            return false;
        }

        return true;
    }
}
