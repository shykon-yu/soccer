<?php

namespace App\Http\Requests\Api\V1;

use App\Models\CompetitionTemplateStage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * 比赛模板新增/编辑请求校验
 *
 * 共用同一个 Request，通过 id 是否存在区分新增和编辑：
 * - 新增时 id 为 prohibited（禁止传入）
 * - 编辑时 id 为 required（必须传入）
 *
 * 额外业务校验（withValidator）：
 * - 联盟级可用 team/cup/league，战队级只能用 cup/league
 * - 个人联赛模板只能包含联赛阶段
 * - 杯赛/团体赛不能包含联赛阶段
 * - 固定人数必须填 registration_limit
 * - 分区/小组/淘汰阶段各自有必填的 rules 字段
 */
class CompetitionTemplateSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            // 模板主表字段
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:competition_templates,id'],
            'name' => ['required', 'string', 'max:160'],                     // 模板名称
            'organizer_type' => ['required', 'in:league,team'],              // 举办方级别: league(联盟级) / team(战队级)
            'type' => ['required', 'in:team,cup,league'],                    // 比赛类型: team(团体赛) / cup(杯赛) / league(个人联赛)
            'registration_limit' => ['nullable', 'integer', 'min:2', 'max:4096'],  // 报名人数上限
            'is_fixed_participants' => ['required', 'boolean'],              // 是否固定报名人数
            'status' => ['required', 'boolean'],                             // 是否启用
            'notes' => ['nullable', 'string', 'max:5000'],                   // 模板说明
            // 阶段数组（至少1个，最多8个）
            'stages' => ['required', 'array', 'min:1', 'max:8'],
            'stages.*.id' => ['nullable', 'numeric'],                        // 阶段ID，编辑时带 id 表示更新，不带表示新增
            'stages.*.type' => ['required', Rule::in([
                CompetitionTemplateStage::TYPE_AREA_GROUP,
                CompetitionTemplateStage::TYPE_AREA_KNOCKOUT,
                CompetitionTemplateStage::TYPE_GROUP,
                CompetitionTemplateStage::TYPE_KNOCKOUT,
                CompetitionTemplateStage::TYPE_LEAGUE,
            ])],                                                              // 阶段类型
            'stages.*.name' => ['required', 'string', 'max:80'],             // 阶段名称，如"总赛区小组赛"
            // 阶段规则 rules（JSON 对象，不同 type 有不同的必填项）
            'stages.*.rules' => ['nullable', 'array'],
            'stages.*.rules.area_count' => ['nullable', 'integer', 'min:2', 'max:32'],          // 分区数量
            'stages.*.rules.group_count' => ['nullable', 'integer', 'min:1', 'max:64'],         // 小组数量
            'stages.*.rules.qualify_count' => ['nullable', 'integer', 'min:2', 'max:256'],      // 晋级名额
            'stages.*.rules.knockout_size' => ['nullable', 'integer', Rule::in([2, 4, 8, 16, 32, 64, 128, 256])],  // 淘汰赛签位
            'stages.*.rules.pairing_mode' => ['nullable', Rule::in(['cross', 'random', 'ranking'])],  // 对阵方式: cross(交叉) / random(随机) / ranking(按排名)
            'stages.*.rules.scoring_mode' => ['nullable', Rule::in(['single', 'home_away_combined', 'home_away_points'])],  // 计分方式: single(单场) / home_away_combined(主客场总分) / home_away_points(主客场积分)
            'stages.*.rules.team_assignment' => ['nullable', Rule::in(['none', 'random', 'preassigned'])],  // 球队分配方式
            'stages.*.rules.avoid_same_source' => ['nullable', 'boolean'],    // 是否首轮回避同组
        ];
    }

    /**
     * 自定义业务校验逻辑
     * 在基础字段校验通过后执行
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $organizerType = $this->input('organizer_type');
            $type = $this->input('type');

            // 举办方级别与比赛类型的兼容性校验
            $allowed = $organizerType === 'league' ? ['team', 'cup', 'league'] : ['cup', 'league'];
            if (! in_array($type, $allowed, true)) {
                $validator->errors()->add('type', '比赛类型与模板适用范围不匹配');
            }

            $stages = collect($this->input('stages', []));

            // 固定人数必须填报名上限
            if ($this->boolean('is_fixed_participants') && ! $this->filled('registration_limit')) {
                $validator->errors()->add('registration_limit', '固定人数模板必须设置报名人数');
            }

            // 个人联赛只能有联赛阶段
            if ($type === 'league' && $stages->contains(fn ($stage) => ($stage['type'] ?? null) !== 'league')) {
                $validator->errors()->add('stages', '个人联赛模板只能设置联赛阶段');
            }

            // 杯赛/团体赛不能有联赛阶段
            if ($type !== 'league' && $stages->contains(fn ($stage) => ($stage['type'] ?? null) === 'league')) {
                $validator->errors()->add('stages', '杯赛或团体赛模板不能设置联赛阶段');
            }

            // 各阶段类型对应的必填规则字段校验
            foreach ($stages as $index => $stage) {
                $stageType = $stage['type'] ?? null;
                $rules = $stage['rules'] ?? [];
                if (in_array($stageType, ['area_group', 'area_knockout'], true) && empty($rules['area_count'])) {
                    $validator->errors()->add("stages.$index.rules.area_count", '分区阶段必须设置分区数量');
                }
                if (in_array($stageType, ['group', 'area_group'], true) && empty($rules['group_count'])) {
                    $validator->errors()->add("stages.$index.rules.group_count", '小组赛阶段必须设置小组数量');
                }
                if (in_array($stageType, ['knockout', 'area_knockout'], true) && empty($rules['knockout_size'])) {
                    $validator->errors()->add("stages.$index.rules.knockout_size", '淘汰赛阶段必须设置签表人数');
                }
            }
        });
    }
}
