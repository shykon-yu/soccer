<?php

namespace App\Http\Requests\Api\V1;

/**
 * 用户角色分配请求校验
 *
 * 单独给某个用户重新分配后台角色，不走新增/编辑流程。
 */
class UserAssignRolesRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
            'role_ids' => ['array'],
            'role_ids.*' => ['integer', 'exists:roles,id'],
        ];
    }
}
