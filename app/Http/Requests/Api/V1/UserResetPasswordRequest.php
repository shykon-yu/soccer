<?php

namespace App\Http\Requests\Api\V1;

/**
 * 用户密码重置请求校验
 *
 * 密码可选，不传则使用系统默认密码。
 */
class UserResetPasswordRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
            'password' => ['nullable', 'string', 'min:6', 'max:64'],
        ];
    }
}
