<?php

namespace App\Services;

use App\Models\StaffMailbox;
use App\Models\StaffMailboxProcessedMessage;
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
     * @param bool $includeSeen When true, also process read messages from the last $days days (CRM dedupes by messageId).
     * @param int $days Lookback window when $includeSeen is true.
     * @return array{processed: int, forwarded: int, skipped: int, errors: int, mailboxes: int, details: list<array{mailbox: string, domain: ?string, candidates: int}>}
     */
    public function poll(bool $includeSeen = false, int $days = 7): array
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

        $stats = [
            'processed' => 0,
            'forwarded' => 0,
            'skipped' => 0,
            'errors' => 0,
            'unmatched' => 0,
            'already_forwarded' => 0,
            'mailboxes' => count($mailboxes),
            'details' => [],
        ];

        foreach ($mailboxes as $config) {
            $label = $config['username'];
            try {
                $mailboxStats = $this->pollMailbox($config, $includeSeen, $days);
                foreach (['processed', 'forwarded', 'skipped', 'errors', 'unmatched', 'already_forwarded'] as $key) {
                    $stats[$key] += $mailboxStats[$key] ?? 0;
                }
                $stats['details'][] = [
                    'mailbox' => $label,
                    'domain' => $config['domain'] ?? null,
                    'candidates' => $mailboxStats['candidates'],
                    'inbox_total' => $mailboxStats['inbox_total'],
                    'unseen_total' => $mailboxStats['unseen_total'],
                    'folder' => $config['folder'] ?? 'INBOX',
                    'host' => $config['host'] ?? null,
                ];
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
        $mailboxes = $this->mailConfigService->resolveInboundMailboxesFromDatabase();

        foreach (StaffMailbox::query()->active()->with('domain')->get() as $staffMailbox) {
            $imap = $staffMailbox->imapConfig();
            $password = $imap['password'] ?? null;
            $username = trim((string) ($imap['username'] ?? $staffMailbox->email));
            if ($username === '' || $password === null || $password === '') {
                continue;
            }

            $mailboxes[] = [
                'domain' => $staffMailbox->domain?->domain,
                'domain_id' => (int) $staffMailbox->email_domain_id,
                'staff_mailbox_id' => (int) $staffMailbox->id,
                'username' => $username,
                'password' => $password,
                'host' => (string) ($imap['host'] ?? 'localhost'),
                'port' => (int) ($imap['port'] ?? 993),
                'encryption' => strtolower((string) ($imap['encryption'] ?? 'ssl')),
                'folder' => (string) ($imap['folder'] ?? 'INBOX'),
            ];
        }

        if ($mailboxes !== []) {
            return $mailboxes;
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
     * @return array{processed: int, forwarded: int, skipped: int, errors: int, unmatched: int, candidates: int, inbox_total: int, unseen_total: int}
     */
    protected function pollMailbox(array $config, bool $includeSeen = false, int $days = 7): array
    {
        $mailbox = $this->buildMailboxString($config);
        $connection = @imap_open($mailbox, $config['username'], $config['password']);
        if ($connection === false) {
            throw new \RuntimeException(
                sprintf('IMAP connect failed for %s: %s', $config['username'], imap_last_error())
            );
        }

        $stats = [
            'processed' => 0,
            'forwarded' => 0,
            'skipped' => 0,
            'errors' => 0,
            'unmatched' => 0,
            'already_forwarded' => 0,
            'candidates' => 0,
            'inbox_total' => 0,
            'unseen_total' => 0,
        ];

        $staffMailboxId = isset($config['staff_mailbox_id']) ? (int) $config['staff_mailbox_id'] : null;

        try {
            $stats['inbox_total'] = imap_num_msg($connection) ?: 0;
            $status = imap_status($connection, $mailbox, SA_UNSEEN);
            $stats['unseen_total'] = $status ? (int) ($status->unseen ?? 0) : 0;

            $uids = $this->searchMessageUids($connection, $includeSeen, $days, $stats['inbox_total']);
            $stats['candidates'] = count($uids);
            foreach ($uids as $uid) {
                $uid = (int) $uid;
                $stats['processed']++;
                try {
                    $payload = $this->parseMessage($connection, $uid, $config['username'] ?? null);
                    if ($payload === null) {
                        $stats['skipped']++;
                        imap_setflag_full($connection, (string) $uid, '\\Seen');
                        continue;
                    }

                    $messageId = isset($payload['messageId']) ? trim((string) $payload['messageId']) : null;
                    $mailboxEmail = strtolower(trim((string) ($config['username'] ?? '')));
                    if ($mailboxEmail !== '' && strtolower($payload['from']) === $mailboxEmail) {
                        $stats['skipped']++;
                        continue;
                    }

                    if ($this->isAlreadyForwarded($staffMailboxId, $messageId, $uid)) {
                        $stats['already_forwarded']++;
                        continue;
                    }

                    if (!empty($config['domain'])) {
                        $payload['sourceDomain'] = $config['domain'];
                    }
                    if (!empty($config['username'])) {
                        $payload['mailboxEmail'] = strtolower(trim((string) $config['username']));
                    }

                    $result = $this->forwardToCrm($payload);
                    if ($result === 'forwarded') {
                        $stats['forwarded']++;
                        $this->markForwarded($staffMailboxId, $uid, $messageId);
                        imap_setflag_full($connection, (string) $uid, '\\Seen');
                    } elseif ($result === 'unmatched') {
                        $stats['unmatched']++;
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
     * @return list<int>
     */
    protected function searchMessageUids($connection, bool $includeSeen, int $days, int $inboxTotal = 0): array
    {
        if (!$includeSeen) {
            return imap_search($connection, 'UNSEEN') ?: [];
        }

        $since = date('d-M-Y', strtotime(sprintf('-%d days', max(1, $days))));
        $criteria = sprintf('SINCE "%s"', $since);
        $uids = imap_search($connection, $criteria) ?: [];

        if ($uids === [] && $inboxTotal > 0) {
            Log::warning('[imap] SINCE search returned no UIDs but INBOX has messages — falling back to ALL', [
                'since' => $since,
                'inbox_total' => $inboxTotal,
            ]);
            $uids = imap_search($connection, 'ALL') ?: [];
        }

        $unseen = imap_search($connection, 'UNSEEN') ?: [];
        $merged = array_values(array_unique(array_map('intval', array_merge($uids, $unseen))));

        return $merged;
    }

    protected function isAlreadyForwarded(?int $staffMailboxId, ?string $messageId, int $uid): bool
    {
        if (!$staffMailboxId) {
            return false;
        }

        if ($messageId !== null && $messageId !== '') {
            $normalized = trim($messageId, '<>');
            if (
                StaffMailboxProcessedMessage::query()
                    ->where('staff_mailbox_id', $staffMailboxId)
                    ->where(function ($query) use ($normalized) {
                        $query->where('message_id', $normalized)
                            ->orWhere('message_id', '<' . $normalized . '>');
                    })
                    ->exists()
            ) {
                return true;
            }
        }

        return StaffMailboxProcessedMessage::query()
            ->where('staff_mailbox_id', $staffMailboxId)
            ->where('message_uid', (string) $uid)
            ->exists();
    }

    protected function markForwarded(?int $staffMailboxId, int $uid, ?string $messageId): void
    {
        if (!$staffMailboxId) {
            return;
        }

        StaffMailboxProcessedMessage::query()->updateOrCreate(
            [
                'staff_mailbox_id' => $staffMailboxId,
                'message_uid' => (string) $uid,
            ],
            [
                'message_id' => $messageId !== null && $messageId !== '' ? trim($messageId, '<>') : null,
                'processed_at' => now(),
            ]
        );

        StaffMailbox::query()->whereKey($staffMailboxId)->update(['last_polled_at' => now()]);
    }

    /**
     * Connect and return mailbox diagnostics (folder counts, recent subjects).
     *
     * @return list<array<string, mixed>>
     */
    public function diagnose(): array
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

        $reports = [];
        foreach ($mailboxes as $config) {
            $reports[] = $this->diagnoseMailbox($config);
        }

        return $reports;
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, mixed>
     */
    protected function diagnoseMailbox(array $config): array
    {
        $mailbox = $this->buildMailboxString($config);
        $connection = @imap_open($mailbox, $config['username'], $config['password']);
        if ($connection === false) {
            return [
                'mailbox' => $config['username'],
                'domain' => $config['domain'] ?? null,
                'connected' => false,
                'error' => imap_last_error() ?: 'IMAP connect failed',
            ];
        }

        try {
            $status = imap_status($connection, $mailbox, SA_ALL);
            $total = imap_num_msg($connection) ?: 0;
            $recent = [];
            if ($total > 0) {
                $start = max(1, $total - 4);
                for ($msgNo = $start; $msgNo <= $total; $msgNo++) {
                    $header = imap_headerinfo($connection, $msgNo);
                    if (!$header) {
                        continue;
                    }
                    $from = '';
                    if (!empty($header->from[0])) {
                        $from = ($header->from[0]->mailbox ?? '') . '@' . ($header->from[0]->host ?? '');
                    }
                    $recent[] = [
                        'from' => $from,
                        'subject' => isset($header->subject) ? imap_utf8((string) $header->subject) : null,
                        'date' => $header->date ?? null,
                    ];
                }
            }

            $folders = [];
            $rootMailbox = sprintf(
                '{%s:%d%s}',
                $config['host'],
                $config['port'],
                $config['encryption'] === 'ssl' ? '/ssl' : ($config['encryption'] === 'tls' ? '/tls' : '')
            );
            $folderList = @imap_list($connection, $rootMailbox, '*') ?: [];
            foreach ($folderList as $folderPath) {
                $name = str_replace($rootMailbox, '', $folderPath);
                $count = 0;
                $folderConn = @imap_open($folderPath, $config['username'], $config['password']);
                if ($folderConn !== false) {
                    $count = imap_num_msg($folderConn) ?: 0;
                    imap_close($folderConn);
                }
                if ($count > 0) {
                    $folders[] = ['name' => $name, 'messages' => $count];
                }
            }

            return [
                'mailbox' => $config['username'],
                'domain' => $config['domain'] ?? null,
                'connected' => true,
                'host' => $config['host'],
                'folder' => $config['folder'] ?? 'INBOX',
                'inbox_total' => $total,
                'unseen_total' => $status ? (int) ($status->unseen ?? 0) : 0,
                'recent_messages' => $recent,
                'non_empty_folders' => $folders,
            ];
        } finally {
            imap_close($connection);
        }
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
    protected function parseMessage($connection, int $uid, ?string $mailboxLabel = null): ?array
    {
        $header = imap_headerinfo($connection, $uid);
        if (!$header) {
            Log::warning('[imap] Skipped message — no header', [
                'mailbox' => $mailboxLabel,
                'uid' => $uid,
            ]);
            return null;
        }

        $rawHeader = imap_fetchheader($connection, $uid) ?: '';
        $from = $this->extractEmailAddress($header->from ?? null)
            ?: $this->extractEmailAddress($header->reply_to ?? null)
            ?: $this->extractEmailAddress($header->sender ?? null)
            ?: $this->extractEmailFromRawHeader($rawHeader, 'From')
            ?: $this->extractEmailFromRawHeader($rawHeader, 'Reply-To');

        if ($from === '' || !filter_var($from, FILTER_VALIDATE_EMAIL)) {
            Log::warning('[imap] Skipped message — missing or invalid From', [
                'mailbox' => $mailboxLabel,
                'uid' => $uid,
                'subject' => isset($header->subject) ? imap_utf8((string) $header->subject) : null,
            ]);
            return null;
        }

        $structure = imap_fetchstructure($connection, $uid);
        $body = $this->extractBody($connection, $uid, $structure);
        if (trim($body) === '') {
            Log::warning('[imap] Skipped message — empty body after MIME parse', [
                'mailbox' => $mailboxLabel,
                'uid' => $uid,
                'from' => $from,
                'subject' => isset($header->subject) ? imap_utf8((string) $header->subject) : null,
            ]);
            return null;
        }

        $messageId = isset($header->message_id) ? trim($header->message_id, '<>') : null;
        $inReplyTo = $this->extractHeaderValue($rawHeader, 'In-Reply-To');
        $references = $this->extractHeaderValue($rawHeader, 'References');
        $deliveredTo = $this->extractHeaderValue($rawHeader, 'Delivered-To');
        $to = $this->formatAddressList($header->to ?? []);
        $cc = $this->formatAddressList($header->cc ?? []);

        $replyToken = null;
        if (preg_match('/reply\+([A-Za-z0-9_-]+)@/i', $rawHeader, $m)) {
            $replyToken = $m[1];
        } elseif (preg_match('/\+reply\.([A-Za-z0-9_-]+)@/i', $rawHeader, $m)) {
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
            'inReplyTo' => $inReplyTo,
            'references' => $references,
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
        if (!preg_match('/^' . preg_quote($name, '/') . ':\s*((?:.+(?:\r?\n[ \t].+)*)+)/im', $rawHeader, $m)) {
            return null;
        }

        $value = preg_replace('/\r?\n[ \t]+/', ' ', trim($m[1])) ?? trim($m[1]);
        return $value !== '' ? $value : null;
    }

    /**
     * @param object|array|null $addresses
     */
    protected function extractEmailAddress($addresses): string
    {
        if (empty($addresses)) {
            return '';
        }

        $list = is_array($addresses) ? $addresses : [$addresses];
        foreach ($list as $addr) {
            $mailbox = $addr->mailbox ?? '';
            $host = $addr->host ?? '';
            if ($mailbox === '' || $host === '') {
                continue;
            }
            $email = strtolower(trim($mailbox . '@' . $host));
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $email;
            }
        }

        return '';
    }

    protected function extractEmailFromRawHeader(string $rawHeader, string $headerName): string
    {
        if (!preg_match('/^' . preg_quote($headerName, '/') . ':\s*(.+)$/im', $rawHeader, $m)) {
            return '';
        }

        $value = imap_utf8(trim($m[1]));
        if (preg_match('/<([^>]+@[^>]+)>/', $value, $angle)) {
            $email = strtolower(trim($angle[1]));
            return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
        }

        $email = strtolower(trim($value));
        return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
    }

    protected function extractBody($connection, int $uid, $structure): string
    {
        if (!$structure) {
            $raw = imap_body($connection, $uid) ?: '';
            return $this->normalizeBodyText($this->decodePart($raw, 0));
        }

        if (!isset($structure->parts)) {
            $raw = imap_body($connection, $uid) ?: '';
            return $this->normalizeBodyText(
                $this->convertCharset($this->decodePart($raw, $structure->encoding ?? 0), $structure)
            );
        }

        [$plain, $html] = $this->collectTextParts($connection, $uid, $structure, '');
        $text = trim($plain) !== '' ? $plain : strip_tags($html);
        if (trim($text) !== '') {
            return $this->normalizeBodyText($text);
        }

        $raw = imap_body($connection, $uid) ?: '';
        return $this->normalizeBodyText(
            $this->convertCharset($this->decodePart($raw, $structure->encoding ?? 0), $structure)
        );
    }

    /**
     * Walk nested multipart MIME (e.g. iPhone multipart/alternative inside multipart/mixed).
     *
     * @return array{0: string, 1: string} plain text, html
     */
    protected function collectTextParts($connection, int $uid, $structure, string $prefix): array
    {
        $plain = '';
        $html = '';

        if (!isset($structure->parts) || $structure->parts === []) {
            $type = $structure->type ?? 0;
            $subtype = strtolower($structure->subtype ?? '');

            if ($type === 1) {
                return ['', ''];
            }

            $raw = $prefix === ''
                ? (imap_body($connection, $uid) ?: '')
                : (imap_fetchbody($connection, $uid, $prefix) ?: '');
            $decoded = $this->convertCharset(
                $this->decodePart($raw, $structure->encoding ?? 0),
                $structure
            );

            if ($type === 0 && $subtype === 'plain') {
                return [$decoded, ''];
            }
            if ($type === 0 && ($subtype === 'html' || $subtype === 'xml')) {
                return ['', $decoded];
            }

            return ['', ''];
        }

        foreach ($structure->parts as $index => $part) {
            $partNum = $prefix === '' ? (string) ($index + 1) : $prefix . '.' . ($index + 1);
            [$partPlain, $partHtml] = $this->collectTextParts($connection, $uid, $part, $partNum);
            if ($plain === '' && trim($partPlain) !== '') {
                $plain = $partPlain;
            }
            if ($html === '' && trim($partHtml) !== '') {
                $html = $partHtml;
            }
        }

        return [$plain, $html];
    }

    protected function normalizeBodyText(string $text): string
    {
        $text = str_replace("\xEF\xBB\xBF", '', $text);
        $text = preg_replace("/\r\n?|\n/", "\n", $text) ?? $text;
        return trim($text);
    }

    protected function convertCharset(string $text, $structure): string
    {
        if ($text === '') {
            return '';
        }

        $charset = $this->structureCharset($structure);
        if ($charset !== '' && strtoupper($charset) !== 'UTF-8' && function_exists('iconv')) {
            $converted = @iconv($charset, 'UTF-8//IGNORE', $text);
            if ($converted !== false) {
                return $converted;
            }
        }

        return imap_utf8($text);
    }

    protected function structureCharset($structure): string
    {
        foreach ([$structure->parameters ?? null, $structure->dparameters ?? null] as $params) {
            if (empty($params)) {
                continue;
            }
            foreach ($params as $param) {
                if (strtolower($param->attribute ?? '') === 'charset' && !empty($param->value)) {
                    return (string) $param->value;
                }
            }
        }

        return '';
    }

    protected function decodePart(string $text, int $encoding): string
    {
        return match ($encoding) {
            ENCBASE64 => base64_decode(str_replace(["\r", "\n"], '', $text)) ?: '',
            ENCQUOTEDPRINTABLE => quoted_printable_decode($text),
            default => $text,
        };
    }

    /**
     * @param array<string, mixed> $payload
     * @return 'forwarded'|'unmatched'|'error'
     */
    protected function forwardToCrm(array $payload): string
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
            return 'error';
        }

        $status = $response->getStatusCode();
        if ($status === 404) {
            Log::info('[imap] CRM could not match reply to a deal thread', [
                'from' => $payload['from'] ?? null,
                'subject' => $payload['subject'] ?? null,
                'inReplyTo' => $payload['inReplyTo'] ?? null,
                'references' => $payload['references'] ?? null,
                'replyToken' => $payload['replyToken'] ?? null,
                'mailboxEmail' => $payload['mailboxEmail'] ?? null,
            ]);
            return 'unmatched';
        }

        if ($status < 200 || $status >= 300) {
            Log::warning('[imap] CRM webhook rejected payload', [
                'status' => $status,
                'body' => (string) $response->getBody(),
            ]);
            return 'error';
        }

        return 'forwarded';
    }
}
