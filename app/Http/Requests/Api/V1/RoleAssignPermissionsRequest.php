<?php

namespace App\Http\Requests\Api\V1;

/**
 * 角色权限分配请求校验
 *
 * 单独给某个角色重新分配权限，不走新增/编辑流程。
 */
class RoleAssignPermissionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:roles,id'],
            'permissions' => ['array'],
            'permissions.*' => ['string'],
        ];
    }
}
