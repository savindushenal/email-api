<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StaffMailbox extends Model
{
    protected $fillable = [
        'email_domain_id',
        'email',
        'display_name',
        'staff_user_id',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'imap_folder',
        'active',
        'last_polled_at',
    ];

    protected $hidden = [
        'smtp_password',
        'imap_password',
    ];

    protected $casts = [
        'smtp_port' => 'integer',
        'imap_port' => 'integer',
        'active' => 'boolean',
        'last_polled_at' => 'datetime',
        'smtp_password' => 'encrypted',
        'imap_password' => 'encrypted',
    ];

    public function domain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class, 'email_domain_id');
    }

    public function processedMessages(): HasMany
    {
        return $this->hasMany(StaffMailboxProcessedMessage::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function smtpConfig(): array
    {
        $domainConfig = $this->domain?->mail_config ?? [];

        return [
            'host' => $this->smtp_host ?: ($domainConfig['host'] ?? 'localhost'),
            'port' => $this->smtp_port ?: ($domainConfig['port'] ?? 465),
            'encryption' => $this->smtp_encryption ?: ($domainConfig['encryption'] ?? 'ssl'),
            'username' => $this->smtp_username ?: $this->email,
            'password' => $this->smtp_password ?: ($domainConfig['password'] ?? null),
        ];
    }

    public function imapConfig(): array
    {
        $domainConfig = $this->domain?->mail_config ?? [];
        $inbound = $domainConfig['inbound'] ?? [];

        return [
            'host' => $this->imap_host ?: ($inbound['host'] ?? $domainConfig['host'] ?? 'localhost'),
            'port' => $this->imap_port ?: ($inbound['port'] ?? 993),
            'encryption' => $this->imap_encryption ?: ($inbound['encryption'] ?? 'ssl'),
            'username' => $this->imap_username ?: $this->email,
            'password' => $this->imap_password ?: $this->smtp_password ?: ($domainConfig['password'] ?? null),
            'folder' => $this->imap_folder ?: ($inbound['folder'] ?? 'INBOX'),
        ];
    }
}
