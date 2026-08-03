<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionMatch extends Model
{
    protected $fillable = [
        'competition_id', 'stage_id', 'group_id', 'home_entry_id', 'away_entry_id', 'winner_entry_id', 'round_label',
        'round_number', 'sequence', 'home_score', 'away_score', 'tie_break_type', 'status', 'reported_by_user_id',
        'reported_at', 'reviewed_by_user_id', 'reviewed_at', 'review_note', 'scheduled_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'reported_at' => 'datetime',
        'reviewed_at' => 'datetime',
    ];

    /** 获取比分记录所属赛事。 */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** 获取比分记录所属阶段。 */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CompetitionStage::class, 'stage_id');
    }

    /** 获取比分记录所属小组，淘汰赛可为空。 */
    public function group(): BelongsTo
    {
        return $this->belongsTo(CompetitionGroup::class, 'group_id');
    }

    /** 获取主队或主选手报名对象。 */
    public function homeEntry(): BelongsTo
    {
        return $this->belongsTo(CompetitionEntry::class, 'home_entry_id');
    }

    /** 获取客队或客选手报名对象。 */
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

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by_user_id');
    }
}
