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
    ];

    protected $casts = [
        'notifications_enabled' => 'boolean',
        'notify_all_matches' => 'boolean',
        'last_notification_at' => 'datetime',
        'is_active' => 'boolean',
        'demo_mode' => 'boolean',
        'demo_started_at' => 'datetime',
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class);
    }

    public function scopeInterestedInMatch($query, string $homeTeam, string $awayTeam)
    {
        return $query->where(function ($q) use ($homeTeam, $awayTeam) {
            $q->where('favorite_team', $homeTeam)
              ->orWhere('favorite_team', $awayTeam)
              ->orWhere('notify_all_matches', true);
        })->where('notifications_enabled', true);
    }
}
