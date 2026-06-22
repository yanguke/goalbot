<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lead extends Model
{
    protected $fillable = [
        'phone_number',
        'country',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'fbclid',
        'referrer',
        'user_agent',
        'ip_address',
        'converted',
        'converted_at',
    ];

    protected $casts = [
        'converted' => 'boolean',
        'converted_at' => 'datetime',
    ];

    /**
     * Mark this lead as converted (became a subscriber)
     */
    public function markAsConverted(): void
    {
        $this->update([
            'converted' => true,
            'converted_at' => now(),
        ]);
    }

    /**
     * Scope to get unconverted leads for follow-up
     */
    public function scopeUnconverted($query)
    {
        return $query->where('converted', false);
    }
}
