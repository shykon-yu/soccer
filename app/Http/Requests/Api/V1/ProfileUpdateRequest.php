<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends BaseRequest
{
    /** 验证当前登录用户可修改的个人资料字段。 */
    public function rules(): array
    {
        $userId = $this->user()?->id;

        return [
            'username' => ['required', 'string', 'max:80', Rule::unique('users', 'username')->ignore($userId)],
            'nickname' => ['required', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:160', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'string', 'max:32', Rule::unique('users', 'phone')->ignore($userId)],
            'avatar' => ['nullable', 'string', 'max:255'],
            'current_password' => ['required_with:password', 'nullable', 'string'],
            'password' => ['nullable', 'string', 'min:6', 'max:64', 'confirmed'],
            'password_confirmation' => ['required_with:password', 'nullable', 'string'],
        ];
    }
}
