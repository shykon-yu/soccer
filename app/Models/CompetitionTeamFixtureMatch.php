<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionTeamFixtureMatch extends Model
{
    protected $fillable = [
        'fixture_id', 'sequence', 'home_user_id', 'away_user_id', 'home_player_name', 'away_player_name',
        'home_score', 'away_score',
    ];

    protected $casts = [
        'fixture_id' => 'integer',
        'sequence' => 'integer',
        'home_user_id' => 'integer',
        'away_user_id' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
    ];

    public function fixture(): BelongsTo
    {
        return $this->belongsTo(CompetitionTeamFixture::class, 'fixture_id');
    }

    public function homeUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'home_user_id');
    }

    public function awayUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'away_user_id');
    }
}
