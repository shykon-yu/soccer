<?php

namespace App\Http\Requests\Api\V1;

/**
 * 用户列表请求校验
 *
 * 支持按用户名、昵称、联盟、战队和状态筛选，分页参数兼容 page/pageNum。
 */
class UserListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'username' => ['nullable', 'string', 'max:80'],
            'nickname' => ['nullable', 'string', 'max:80'],
            'league_id' => ['nullable', 'integer', 'exists:leagues,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'status' => ['nullable', 'integer', 'in:0,1'],
            'page' => ['nullable', 'integer', 'min:1'],
            'pageNum' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
