<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HonorEvent extends Model
{
    public const SOURCE_COMPETITION = 'competition';

    public const SOURCE_MANUAL = 'manual';

    protected $fillable = [
        'competition_id', 'source', 'organizer_type', 'league_id', 'team_id', 'competition_type',
        'competition_name', 'season', 'ended_at', 'notes',
    ];

    protected $casts = [
        'competition_id' => 'integer',
        'league_id' => 'integer',
        'team_id' => 'integer',
        'ended_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function awards(): HasMany
    {
        return $this->hasMany(CompetitionHonor::class)->orderBy('rank');
    }
}
