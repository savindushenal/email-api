<?php

namespace App\Services;

use App\Models\EmailDomain;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

/**
 * Normalizes per-domain mail_config (SMTP + optional inbound IMAP).
 * Secrets are encrypted at rest with Laravel Crypt (APP_KEY).
 */
class DomainMailConfigService
{
    private const ENCRYPTED_PREFIX = 'enc:';

    /**
     * Merge incoming mail_config with existing and encrypt secrets for storage.
     *
     * @param array<string, mixed>|null $existing
     * @param array<string, mixed> $incoming
     * @return array<string, mixed>
     */
    public function prepareForStorage(?array $existing, array $incoming): array
    {
        $merged = array_merge($existing ?? [], $incoming);

        if (array_key_exists('password', $incoming) && $incoming['password'] !== null && $incoming['password'] !== '') {
            $merged['password'] = $this->encryptSecret((string) $incoming['password']);
        } elseif ($existing !== null && isset($existing['password'])) {
            $merged['password'] = $existing['password'];
        }

        if (isset($incoming['inbound']) && is_array($incoming['inbound'])) {
            $existingInbound = is_array($existing['inbound'] ?? null) ? $existing['inbound'] : [];
            $inbound = array_merge($existingInbound, $incoming['inbound']);

            if (array_key_exists('password', $incoming['inbound'])) {
                $pw = $incoming['inbound']['password'];
                if ($pw !== null && $pw !== '') {
                    $inbound['password'] = $this->encryptSecret((string) $pw);
                } elseif (isset($existingInbound['password'])) {
                    $inbound['password'] = $existingInbound['password'];
                } else {
                    unset($inbound['password']);
                }
            }

            $merged['inbound'] = $inbound;
        }

        return $merged;
    }

    /**
     * Safe mail_config for API responses — never exposes passwords.
     *
     * @param array<string, mixed>|null $mailConfig
     * @return array<string, mixed>
     */
    public function publicView(?array $mailConfig): array
    {
        if ($mailConfig === null || $mailConfig === []) {
            return ['configured' => false];
        }

        $view = [
            'configured' => true,
            'transport' => $mailConfig['transport'] ?? 'smtp',
            'host' => $mailConfig['host'] ?? null,
            'port' => $mailConfig['port'] ?? null,
            'encryption' => $mailConfig['encryption'] ?? null,
            'username' => $mailConfig['username'] ?? null,
            'smtp_password_set' => !empty($mailConfig['password']),
        ];

        $inbound = $mailConfig['inbound'] ?? null;
        if (is_array($inbound)) {
            $view['inbound'] = [
                'enabled' => (bool) ($inbound['enabled'] ?? false),
                'host' => $inbound['host'] ?? null,
                'port' => $inbound['port'] ?? 993,
                'encryption' => $inbound['encryption'] ?? 'ssl',
                'folder' => $inbound['folder'] ?? 'INBOX',
                'username' => $inbound['username'] ?? null,
                'password_set' => !empty($inbound['password']) || !empty($mailConfig['password']),
            ];
        } else {
            $view['inbound'] = ['enabled' => false];
        }

        return $view;
    }

    /**
     * Resolve SMTP password for sending (supports legacy plaintext in DB).
     */
    public function smtpPassword(array $mailConfig): ?string
    {
        if (empty($mailConfig['password'])) {
            return null;
        }

        return $this->decryptSecret((string) $mailConfig['password']);
    }

    /**
     * All active domains with inbound polling enabled.
     *
     * @return list<array{domain: string, domain_id: int, username: string, password: string, host: string, port: int, encryption: string, folder: string}>
     */
    public function resolveInboundMailboxesFromDatabase(): array
    {
        $domains = EmailDomain::query()
            ->active()
            ->whereNotNull('mail_config')
            ->get();

        $mailboxes = [];
        foreach ($domains as $domain) {
            $resolved = $this->resolveInboundMailboxForDomain($domain);
            if ($resolved !== null) {
                $mailboxes[] = $resolved;
            }
        }

        return $mailboxes;
    }

    /**
     * @return array{domain: string, domain_id: int, username: string, password: string, host: string, port: int, encryption: string, folder: string}|null
     */
    public function resolveInboundMailboxForDomain(EmailDomain $domain): ?array
    {
        $mailConfig = $domain->mail_config;
        if (!is_array($mailConfig)) {
            return null;
        }

        $inbound = $mailConfig['inbound'] ?? null;
        if (!is_array($inbound) || empty($inbound['enabled'])) {
            return null;
        }

        $username = trim((string) ($inbound['username'] ?? $mailConfig['username'] ?? ''));
        $password = $this->inboundPassword($mailConfig);
        if ($username === '' || $password === null || $password === '') {
            Log::warning('[imap] Domain has inbound.enabled but missing credentials', [
                'domain' => $domain->domain,
            ]);
            return null;
        }

        $host = (string) ($inbound['host'] ?? $mailConfig['host'] ?? 'localhost');
        if ($host === '') {
            return null;
        }

        return [
            'domain' => $domain->domain,
            'domain_id' => (int) $domain->id,
            'username' => $username,
            'password' => $password,
            'host' => $host,
            'port' => (int) ($inbound['port'] ?? 993),
            'encryption' => strtolower((string) ($inbound['encryption'] ?? 'ssl')),
            'folder' => (string) ($inbound['folder'] ?? 'INBOX'),
        ];
    }

    public function inboundPassword(array $mailConfig): ?string
    {
        $inbound = $mailConfig['inbound'] ?? null;
        if (is_array($inbound) && !empty($inbound['password'])) {
            return $this->decryptSecret((string) $inbound['password']);
        }

        return $this->smtpPassword($mailConfig);
    }

    public function encryptSecret(string $plain): string
    {
        return self::ENCRYPTED_PREFIX . Crypt::encryptString($plain);
    }

    public function decryptSecret(string $stored): string
    {
        if (str_starts_with($stored, self::ENCRYPTED_PREFIX)) {
            try {
                return Crypt::decryptString(substr($stored, strlen(self::ENCRYPTED_PREFIX)));
            } catch (DecryptException $e) {
                Log::error('[mail_config] Failed to decrypt secret — check APP_KEY');
                throw $e;
            }
        }

        // Legacy plaintext (pre-encryption migration)
        return $stored;
    }
}
