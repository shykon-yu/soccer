<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 比赛模板阶段
 *
 * 一个模板可包含多个阶段（如：小组赛 → 淘汰赛 → 决赛），
 * 各阶段按 sort 字段从小到大依次执行。
 *
 * 阶段类型(type)说明：
 * - area_group:    分区小组赛（先分区，各区内部小组循环）
 * - area_knockout: 分区淘汰赛（先分区，各区内部淘汰）
 * - group:         总赛区小组赛（所有人在一个赛区打小组赛）
 * - knockout:      总赛区淘汰赛（直接淘汰，无分区）
 * - league:        联赛（仅个人联赛模板可用，所有人一个大组循环）
 *
 * rules 字段为 JSON，不同 type 有不同的必填规则，参见 CompetitionTemplateSaveRequest
 */
class CompetitionTemplateStage extends Model
{
    public const TYPE_AREA_GROUP = 'area_group';

    public const TYPE_AREA_KNOCKOUT = 'area_knockout';

    public const TYPE_GROUP = 'group';

    public const TYPE_KNOCKOUT = 'knockout';

    public const TYPE_LEAGUE = 'league';

    protected $fillable = ['template_id', 'type', 'name', 'sort', 'rules'];

    protected $casts = ['rules' => 'array'];

    public function template(): BelongsTo
    {
        return $this->belongsTo(CompetitionTemplate::class, 'template_id');
    }
}
