<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * 比赛模板
 *
 * 定义了比赛的结构骨架：赛制类型、阶段流程、报名规则等。
 * 创建具体比赛时，以模板为蓝图复制生成比赛实例。
 *
 * 关键字段：
 * - organizer_type: 举办方级别，league(联盟级) / team(战队级)，决定了可选的比赛类型范围
 * - type: 比赛类型，team(团体赛) / cup(杯赛) / league(个人联赛)，拳皇赛(kof)不走模板
 * - is_fixed_participants: 是否固定报名人数，固定时必须填 registration_limit
 * - status: 是否启用，停用的模板不会出现在创建比赛的选项列表中
 */
class CompetitionTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'organizer_type', 'type', 'registration_limit', 'is_fixed_participants', 'status', 'notes',
    ];

    protected $casts = [
        'registration_limit' => 'integer',
        'is_fixed_participants' => 'boolean',
        'status' => 'boolean',
    ];

    /**
     * 模板的阶段流程，按 sort 排序
     * 例如：小组赛(sort=10) → 淘汰赛(sort=20) → 决赛(sort=30)
     */
    public function stages(): HasMany
    {
        return $this->hasMany(CompetitionTemplateStage::class, 'template_id')->orderBy('sort')->orderBy('id');
    }

    /**
     * 使用此模板创建的比赛列表
     * 删除模板前会检查是否有比赛在使用，有则禁止删除
     */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class, 'template_id');
    }
}
