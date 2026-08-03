<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetitionGroup extends Model
{
    protected $fillable = ['stage_id', 'name', 'sort', 'capacity', 'reserved_count'];

    protected $casts = ['capacity' => 'integer', 'reserved_count' => 'integer'];

    /** 获取小组所属比赛阶段。 */
    public function stage(): BelongsTo
    {
        return $this->belongsTo(CompetitionStage::class, 'stage_id');
    }

    /** 获取该小组内的比赛记录。 */
    public function matches(): HasMany
    {
        return $this->hasMany(CompetitionMatch::class, 'group_id');
    }

    /** 获取抽签进入该小组的报名对象。 */
    public function entries(): BelongsToMany
    {
        return $this->belongsToMany(CompetitionEntry::class, 'competition_group_entries', 'group_id', 'entry_id');
    }
}
