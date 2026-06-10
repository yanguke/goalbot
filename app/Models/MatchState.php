<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MatchState extends Model
{
    use HasFactory;

    protected $fillable = [
        'external_match_id',
        'state_snapshot',
        'last_checked',
    ];

    protected $casts = [
        'state_snapshot' => 'array',
        'last_checked' => 'datetime',
    ];
}
