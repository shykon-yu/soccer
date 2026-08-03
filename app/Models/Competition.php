<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Competition extends Model
{
    use HasFactory;
    use SoftDeletes;

    public const ORGANIZER_LEAGUE = 'league';

    public const ORGANIZER_TEAM = 'team';

    public const TYPE_TEAM = 'team';

    public const TYPE_CUP = 'cup';

    public const TYPE_LEAGUE = 'league';

    public const TYPE_KOF = 'kof';

    public const FORMAT_GROUP_KNOCKOUT = 'group_knockout';

    public const FORMAT_KNOCKOUT = 'knockout';

    public const FORMAT_ROUND_ROBIN = 'round_robin';

    public const STATUS_REGISTRATION = 'registration';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_KNOCKOUT = 'knockout';

    public const STATUS_AWAITING_AWARDS = 'awaiting_awards';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'template_id', 'template_name', 'organizer_type', 'league_id', 'team_id', 'type', 'name', 'season', 'format', 'status',
        'registration_deadline', 'registration_limit', 'is_fixed_participants', 'reserved_count', 'group_count', 'knockout_size', 'starts_at', 'ended_at',
        'awarded_at', 'notes',
    ];

    protected $casts = [
        'league_id' => 'integer',
        'team_id' => 'integer',
        'template_id' => 'integer',
        'registration_deadline' => 'datetime',
        'registration_limit' => 'integer',
        'is_fixed_participants' => 'boolean',
        'reserved_count' => 'integer',
        'group_count' => 'integer',
        'knockout_size' => 'integer',
        'starts_at' => 'datetime',
        'ended_at' => 'datetime',
        'awarded_at' => 'datetime',
    ];

    /** 获取联盟级赛事所属联盟。 */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** 获取战队级赛事所属战队。 */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CompetitionTemplate::class, 'template_id');
    }

    /** 获取赛事报名对象。 */
    public function entries(): HasMany
    {
        return $this->hasMany(CompetitionEntry::class);
    }

    /** 获取按执行顺序排列的赛事阶段。 */
    public function stages(): HasMany
    {
        return $this->hasMany(CompetitionStage::class)->orderBy('sort')->orderBy('id');
    }

    /** 获取赛事全部比分记录。 */
    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class);
    }

    public function teamFixtures(): HasMany
    {
        return $this->hasMany(CompetitionTeamFixture::class);
    }

    /** 获取赛事最终颁发的四个名次荣誉。 */
    public function honors(): HasMany
    {
        return $this->hasMany(CompetitionHonor::class)->orderBy('rank');
    }

    public function honorEvent(): HasOne
    {
        return $this->hasOne(HonorEvent::class);
    }
}
