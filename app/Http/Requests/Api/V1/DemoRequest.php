<?php

namespace App\Http\Requests\Api\V1;

class DemoRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => '名称不能为空',
            'email.required' => '邮箱不能为空',
            'email.email' => '邮箱格式不正确',
        ];
    }
}
