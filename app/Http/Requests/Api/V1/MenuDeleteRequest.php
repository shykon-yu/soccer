<?php

namespace App\Http\Requests\Api\V1;

/**
 * 菜单删除请求校验
 *
 * 支持批量删除（id 为数组），删除时自动级联删除所有后代节点及关联权限。
 */
class MenuDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required'],
            'id.*' => ['integer', 'exists:menus,id'],
        ];
    }
}
