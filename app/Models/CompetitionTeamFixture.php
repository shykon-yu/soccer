<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionTeamFixture extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'competition_id', 'stage_id', 'home_entry_id', 'away_entry_id', 'winner_entry_id', 'round_label',
        'round_number', 'sequence', 'leg_number', 'home_score', 'away_score', 'status', 'scheduled_at',
        'reported_by_user_id', 'reported_at',
    ];

    protected $casts = [
        'competition_id' => 'integer',
        'stage_id' => 'integer',
        'home_entry_id' => 'integer',
        'away_entry_id' => 'integer',
        'winner_entry_id' => 'integer',
        'round_number' => 'integer',
        'sequence' => 'integer',
        'leg_number' => 'integer',
        'home_score' => 'integer',
        'away_score' => 'integer',
        'scheduled_at' => 'datetime',
        'reported_at' => 'datetime',
    ];

    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(CompetitionStage::class, 'stage_id');
    }

    public function homeEntry(): BelongsTo
    {
        return $this->belongsTo(CompetitionEntry::class, 'home_entry_id');
    }

    public function awayEntry(): BelongsTo
    {
        return $this->belongsTo(CompetitionEntry::class, 'away_entry_id');
    }

    public function winnerEntry(): BelongsTo
    {
        return $this->belongsTo(CompetitionEntry::class, 'winner_entry_id');
    }

    public function reportedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reported_by_user_id');
    }

    public function playerMatches(): HasMany
    {
        return $this->hasMany(CompetitionTeamFixtureMatch::class, 'fixture_id')->orderBy('sequence');
    }
}
