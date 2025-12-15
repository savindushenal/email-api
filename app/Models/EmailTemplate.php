<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain_id',
        'template_key',
        'subject',
        'blade_html',
        'status',
    ];

    protected $casts = [
        'domain_id' => 'integer',
    ];

    /**
     * Get the domain that owns the template
     */
    public function domain(): BelongsTo
    {
        return $this->belongsTo(EmailDomain::class, 'domain_id');
    }

    /**
     * Get the email logs for this template
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'template_id');
    }

    /**
     * Check if template is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Scope for active templates
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope for finding by domain and template key
     */
    public function scopeForDomain($query, int $domainId, string $templateKey)
    {
        return $query->where('domain_id', $domainId)
                     ->where('template_key', $templateKey);
    }
}
