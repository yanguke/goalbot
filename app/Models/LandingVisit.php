<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingVisit extends Model
{
    protected $fillable = [
        'ip', 'country', 'user_agent', 'referrer', 'version',
        'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
        'event',
    ];
}
