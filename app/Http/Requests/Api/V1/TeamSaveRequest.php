<?php

namespace App\Http\Requests\Api\V1;

use App\Models\TeamStaff;
use Illuminate\Validation\Rule;

/**
 * 战队新增/编辑请求校验
 *
 * 共用同一个 Request，通过 id 区分新增/编辑。
 * 战队名称在同一联盟内唯一。
 * captain_user_id 和 manager_user_ids 用于同步队长和管理职务。
 */
class TeamSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:teams,id'],         // 新增时禁止传入，编辑时必填
            'league_id' => ['required', 'integer', 'exists:leagues,id'],                       // 所属联盟ID
            'name' => [
                'required', 'string', 'max:120',
                Rule::unique('teams', 'name')->where(fn ($query) => $query->where('league_id', $this->input('league_id')))->ignore($id),  // 同一联盟内唯一
            ],
            'status' => ['nullable', 'integer', 'in:0,1'],                                     // 0=禁用 1=启用
            'captain_user_id' => ['nullable', 'integer', 'exists:users,id'],                    // 队长用户ID，必须是战队成员
            'manager_user_ids' => ['nullable', 'array', 'max:'.TeamStaff::MAX_MANAGERS],        // 管理用户ID列表，最多5人
            'manager_user_ids.*' => ['integer', 'distinct', 'exists:users,id'],                 // 管理ID不可重复
        ];
    }
}
