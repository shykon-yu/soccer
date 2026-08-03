<?php

namespace App\Http\Requests\Api\V1;

/**
 * 角色列表请求校验
 *
 * 支持按角色名模糊搜索，分页参数兼容 page/pageNum。
 */
class RoleListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:125'],
            'page' => ['nullable', 'integer', 'min:1'],
            'pageNum' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
