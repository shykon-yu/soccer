<?php

namespace App\Http\Requests\Api\V1;

class FrontCompetitionListRequest extends BaseRequest
{
    /** 验证前台用户赛事列表的类型、状态分组和分页参数。 */
    public function rules(): array
    {
        return [
            'pageNum' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:50'],
            'type' => ['required', 'in:cup,league,kof'],
            'scope' => ['required', 'in:ongoing,completed'],
        ];
    }
}
