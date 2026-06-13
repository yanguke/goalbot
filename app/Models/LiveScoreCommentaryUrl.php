<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LiveScoreCommentaryUrl extends Model
{
    use HasFactory;

    protected $table = 'livescore_commentary_urls';

    protected $fillable = [
        'fixture_id',
        'home_team',
        'away_team',
        'livescore_slug',
        'verified',
        'verified_at',
    ];

    protected $casts = [
        'verified' => 'boolean',
        'verified_at' => 'datetime',
    ];
}
