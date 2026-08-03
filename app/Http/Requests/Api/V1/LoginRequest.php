<?php

namespace App\Http\Requests\Api\V1;

/**
 * 登录请求校验
 *
 * 支持用户名+密码登录，校验失败返回中文错误提示。
 */
class LoginRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'between:1,50'],
            'password' => ['required', 'string', 'min:6'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => '用户名不能为空',
            'username.string'   => '用户名格式不正确',
            'username.max'      => '用户名不能超过 50 个字符',
            'password.required' => '密码不能为空',
            'password.min'      => '密码至少 6 位',
        ];
    }
}
