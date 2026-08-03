<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionStage extends Model
{
    protected $fillable = ['competition_id', 'template_stage_id', 'type', 'name', 'sort', 'status', 'rules'];

    protected $casts = ['rules' => 'array'];

    /** 获取阶段所属赛事。 */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** 获取小组赛阶段下的分组。 */
    public function groups(): HasMany
    {
        return $this->hasMany(CompetitionGroup::class, 'stage_id')->orderBy('sort');
    }

    /** 获取当前阶段按轮次排列的比分记录。 */
    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class, 'stage_id')->orderBy('round_number')->orderBy('sequence');
    }

    public function teamFixtures(): HasMany
    {
        return $this->hasMany(CompetitionTeamFixture::class, 'stage_id')->orderBy('round_number')->orderBy('sequence');
    }
}
