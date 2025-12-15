<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'template_id',
        'from_email',
        'to_email',
        'subject',
        'template_key',
        'status',
        'error_message',
        'mailer_used',
        'message_id',
        'variables',
        'sent_at',
    ];

    protected $casts = [
        'domain_id' => 'integer',
        'template_id' => 'integer',
        'variables' => 'array',
        'sent_at' => 'datetime',
    ];

    /**
     * Get the domain that owns the log
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class, 'domain_id');
    }

    /**
     * Get the template that was used
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_id');
    }

    /**
     * Mark as sent
     */
    public function markAsSent(string $messageId = null): void
    {
        $this->update([
            'status' => 'sent',
            'sent_at' => now(),
            'message_id' => $messageId,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => 'failed',
            'error_message' => $errorMessage,
        ]);
    }

    /**
     * Scope for sent emails
     */
    public function scopeSent($query)
    {
        return $query->where('status', 'sent');
    }

    /**
     * Scope for failed emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }
}
