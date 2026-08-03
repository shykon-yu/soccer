<?php

namespace App\Http\Requests\Api\V1;

/**
 * 用户删除请求校验
 *
 * 支持批量删除（id 为数组）。
 */
class UserDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'id.*' => ['integer', 'exists:users,id'],
        ];
    }
}
