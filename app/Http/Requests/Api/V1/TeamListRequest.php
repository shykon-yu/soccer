<?php

namespace App\Http\Requests\Api\V1;

/**
 * 战队列表请求校验
 *
 * 支持按联盟、名称模糊搜索和状态筛选。
 */
class TeamListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'pageNum' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
            'league_id' => ['nullable', 'integer', 'exists:leagues,id'],
            'name' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ];
    }
}
