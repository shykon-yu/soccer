<?php

namespace App\Http\Requests\Api\V1;

use App\Models\Team;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

/**
 * 用户新增/编辑请求校验
 *
 * 共用同一个 Request，通过 id 区分新增/编辑。
 * 额外校验：memberships 中的 team_id 必须属于对应的 league_id（防止前端传入错误组合）。
 */
class UserSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:users,id'],         // 新增时禁止传入，编辑时必填
            'username' => ['required', 'string', 'max:80', Rule::unique('users', 'username')->ignore($id)],  // 登录账号
            'nickname' => ['nullable', 'string', 'max:80'],                                   // 昵称
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')->ignore($id)],       // 邮箱
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($id)],       // 手机号
            'avatar' => ['nullable', 'string', 'max:255'],                                    // 头像URL
            'status' => ['nullable', 'integer', 'in:0,1'],                                    // 0=禁用 1=启用
            'password' => [$id ? 'nullable' : 'sometimes', 'string', 'min:6', 'max:64'],      // 新增时必填，编辑时选填（不填则不修改）
            'role_ids' => ['array'],                                                           // 分配的后台角色ID列表
            'role_ids.*' => ['integer', 'exists:roles,id'],
            'memberships' => ['array'],                                                        // 联盟-战队归属关系
            'memberships.*.league_id' => ['required', 'integer', 'distinct', 'exists:leagues,id'],  // 联盟ID，同一数组内不可重复
            'memberships.*.team_id' => ['required', 'integer', 'exists:teams,id'],                  // 战队ID
        ];
    }

    /**
     * 验证队伍 ID 是否在该联盟 ID 下，防止前端传入错误组合
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $memberships = $this->input('memberships', []);
            $teamLeagueIds = Team::query()
                ->whereIn('id', collect($memberships)->pluck('team_id')->filter())
                ->pluck('league_id', 'id');

            foreach ($memberships as $index => $membership) {
                $teamId = (int) ($membership['team_id'] ?? 0);
                $leagueId = (int) ($membership['league_id'] ?? 0);
                if ($teamId > 0 && $leagueId > 0 && (int) ($teamLeagueIds[$teamId] ?? 0) !== $leagueId) {
                    $validator->errors()->add(
                        "memberships.$index.team_id",
                        '所选战队不属于该联盟'
                    );
                }
            }
        });
    }
}
