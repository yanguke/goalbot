<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subscriber extends Model
{
    use HasFactory;

    protected $fillable = [
        'phone_number',
        'favorite_team',
        'notifications_enabled',
        'notify_all_matches',
        'timezone',
        'last_notification_at',
        'is_active',
        'demo_mode',
        'demo_started_at',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'attribution_ip',
        'country',
        'subscription_type',
        'subscription_expires_at',
        'paid_at',
        'commentary_mode',
        'last_message_in_at',
        'window_failed',
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'notify_all_matches' => 'boolean',
        'last_notification_at' => 'datetime',
        'is_active' => 'boolean',
        'demo_mode' => 'boolean',
        'demo_started_at' => 'datetime',
        'subscription_expires_at' => 'datetime',
        'paid_at' => 'datetime',
        'last_message_in_at' => 'datetime',
        'window_failed' => 'boolean',
    ];

    /**
     * True if the subscriber has an active paid subscription.
     * full_tournament: never expires (covers whole WC).
     * per_match: expires_at is set per-match window.
     */
    public function isPaid(): bool
    {
        if ($this->subscription_type === 'full_tournament') {
            return true;
        }

        if ($this->subscription_type === 'per_match') {
            return $this->subscription_expires_at && $this->subscription_expires_at->isFuture();
        }

        return false;
    }

    public function isFree(): bool
    {
        return ! $this->isPaid();
    }

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    /**
     * per_match subscribers whose pass has expired — should receive a renewal nudge.
     */
    public function scopeExpiredPerMatch($query, string $homeTeam, string $awayTeam)
    {
        return $query->where(function ($q) use ($homeTeam, $awayTeam) {
            $q->where('favorite_team', $homeTeam)
              ->orWhere('favorite_team', $awayTeam)
              ->orWhere('notify_all_matches', true);
        })
        ->where('notifications_enabled', true)
        ->where('subscription_type', 'per_match')
        ->where(function ($q) {
            $q->whereNull('subscription_expires_at')
              ->orWhere('subscription_expires_at', '<=', now());
        });
    }

    /**
     * Subscribers who want alerts for this match AND have a valid paid subscription.
     */
    public function scopeInterestedInMatch($query, string $homeTeam, string $awayTeam)
    {
        return $query->where(function ($q) use ($homeTeam, $awayTeam) {
            $q->where('favorite_team', $homeTeam)
              ->orWhere('favorite_team', $awayTeam)
              ->orWhere('notify_all_matches', true);
        })
        ->where('notifications_enabled', true)
        ->where(function ($q) {
            $q->where('subscription_type', 'full_tournament')
              ->orWhere(function ($q2) {
                  $q2->where('subscription_type', 'per_match')
                     ->where('subscription_expires_at', '>', now());
              });
        });
    }
}
