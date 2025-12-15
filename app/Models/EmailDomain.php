<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class EmailDomain extends Model
{
    use HasFactory;

    protected $fillable = [
        'domain',
        'from_email',
        'from_name',
        'mailer',
        'status',
        'api_key',
        'ses_key',
        'ses_secret',
        'ses_region',
        'daily_limit',
        'hourly_limit',
    ];

    protected $hidden = [
        'api_key',
        'ses_key',
        'ses_secret',
    ];

    protected $casts = [
        'daily_limit' => 'integer',
        'hourly_limit' => 'integer',
    ];

    /**
     * Boot the model
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($domain) {
            if (empty($domain->api_key)) {
                $domain->api_key = 'eak_' . Str::random(40);
            }
        });
    }

    /**
     * Get the templates for this domain
     */
    public function templates(): HasMany
    {
        return $this->hasMany(EmailTemplate::class, 'domain_id');
    }

    /**
     * Get the email logs for this domain
     */
    public function logs(): HasMany
    {
        return $this->hasMany(EmailLog::class, 'domain_id');
    }

    /**
     * Check if domain is active
     */
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /**
     * Check if domain uses SES
     */
    public function usesSes(): bool
    {
        return $this->mailer === 'ses';
    }

    /**
     * Check rate limit for the domain
     */
    public function checkRateLimit(): array
    {
        $now = now();
        
        // Check hourly limit
        $hourlyCount = $this->logs()
            ->where('created_at', '>=', $now->copy()->subHour())
            ->count();
        
        if ($hourlyCount >= $this->hourly_limit) {
            return [
                'allowed' => false,
                'message' => 'Hourly rate limit exceeded',
                'limit' => $this->hourly_limit,
                'current' => $hourlyCount,
            ];
        }
        
        // Check daily limit
        $dailyCount = $this->logs()
            ->where('created_at', '>=', $now->copy()->startOfDay())
            ->count();
        
        if ($dailyCount >= $this->daily_limit) {
            return [
                'allowed' => false,
                'message' => 'Daily rate limit exceeded',
                'limit' => $this->daily_limit,
                'current' => $dailyCount,
            ];
        }
        
        return [
            'allowed' => true,
            'hourly_remaining' => $this->hourly_limit - $hourlyCount,
            'daily_remaining' => $this->daily_limit - $dailyCount,
        ];
    }

    /**
     * Scope for finding by API key
     */
    public function scopeByApiKey($query, string $apiKey)
    {
        // Hash the API key to compare with stored hash
        return $query->where('api_key', hash('sha256', $apiKey));
    }

    /**
     * Scope for active domains
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }
}
