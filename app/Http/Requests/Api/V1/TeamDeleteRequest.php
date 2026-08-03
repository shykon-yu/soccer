<?php

namespace App\Http\Requests\Api\V1;

/**
 * 战队删除请求校验
 *
 * 单条删除，id 必须存在。
 */
class TeamDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'exists:teams,id']];
    }
}
