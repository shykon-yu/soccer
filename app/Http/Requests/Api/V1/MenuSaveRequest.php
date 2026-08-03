<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * 菜单新增/编辑请求校验
 *
 * 共用同一个 Request，通过 id 区分新增/编辑。
 * permission 全局唯一，用于 Spatie 权限控制。
 * button 类型节点不需要路由相关字段（name/path/component/redirect）。
 */
class MenuSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:menus,id'],         // 新增时禁止传入，编辑时必填
            'parent_id' => ['nullable', 'integer', 'exists:menus,id'],                         // 父节点ID，顶级菜单为null
            'type' => ['required', 'in:menu,button'],                                           // 节点类型: menu(页面菜单) / button(按钮权限)
            'title' => ['required', 'string', 'max:80'],                                        // 菜单标题/按钮名称
            'name' => ['nullable', 'string', 'max:120'],                                        // 路由名称（仅menu需要）
            'path' => ['nullable', 'string', 'max:255'],                                        // 路由路径（仅menu需要）
            'component' => ['nullable', 'string', 'max:255'],                                   // 前端组件路径（仅menu需要）
            'redirect' => ['nullable', 'string', 'max:255'],                                    // 重定向路径
            'icon' => ['nullable', 'string', 'max:80'],                                         // 菜单图标
            'permission' => ['required', 'string', 'max:160', Rule::unique('menus', 'permission')->ignore($id)],  // 权限标识（全局唯一）
            'button_code' => ['nullable', 'string', 'max:80'],                                  // 按钮操作编码（仅button需要）
            'sort' => ['nullable', 'integer', 'min:0'],                                         // 排序值
            'status' => ['nullable', 'integer', 'in:0,1'],                                      // 0=隐藏 1=显示
            'is_link' => ['nullable', 'string', 'max:255'],                                     // 外链URL
            'is_hide' => ['nullable', 'boolean'],                                               // 是否在侧边栏隐藏
            'is_full' => ['nullable', 'boolean'],                                               // 是否全屏显示
            'is_affix' => ['nullable', 'boolean'],                                              // 是否固定标签页
            'is_keep_alive' => ['nullable', 'boolean'],                                         // 是否缓存页面
        ];
    }
}
