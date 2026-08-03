<?php

namespace App\Http\Requests\Api\V1;

/**
 * 用户状态变更请求校验
 *
 * 单条操作，id 和 status 均必填。
 */
class UserStatusRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
            'status' => ['required', 'integer', 'in:0,1'],
        ];
    }
}
