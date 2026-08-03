<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * 角色新增/编辑请求校验
 *
 * 共用同一个 Request，通过 id 区分新增/编辑。
 * permissions 为权限标识名称数组，与 Menu 表的 permission 字段对应。
 */
class RoleSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:roles,id'],
            'name' => ['required', 'string', 'max:125', Rule::unique('roles', 'name')->ignore($id)],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }
}
