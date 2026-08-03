<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CompetitionEntry extends Model
{
    public const TYPE_USER = 'user';

    public const TYPE_TEAM = 'team';

    public const TYPE_SQUAD = 'squad';

    public const STATUS_REGISTERED = 'registered';

    protected $fillable = ['competition_id', 'entry_type', 'user_id', 'team_id', 'squad_id', 'seed', 'status'];

    /** 获取报名记录所属赛事。 */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** 获取个人赛参赛用户。 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 获取团体赛参赛战队。 */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** 获取拳皇赛临时组团。 */
    public function squad(): BelongsTo
    {
        return $this->belongsTo(CompetitionSquad::class);
    }

    public function groups(): BelongsToMany
    {
        return $this->belongsToMany(CompetitionGroup::class, 'competition_group_entries', 'entry_id', 'group_id');
    }

    /** 统一返回用户、战队或组团的比赛显示名称。 */
    public function displayName(): string
    {
        return $this->user?->nickname ?: $this->team?->name ?: $this->squad?->name ?: '待定';
    }
}
