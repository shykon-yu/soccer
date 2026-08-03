<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Menu;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * 菜单管理业务层
 *
 * 负责菜单树的 CRUD、权限同步、前端动态路由构建。
 * 菜单创建/更新/删除时自动维护 Spatie Permission 表。
 */
class MenuService
{
    /**
     * 根据用户权限构建后台动态路由菜单树。
     *
     * 步骤：
     * 1. 获取用户全部权限标识
     * 2. 查一级：用户有权访问的菜单节点
     * 3. 从一级菜单收集 parent_id，反向查找需要保留的上级父菜单
     * 4. 查二级：有权菜单 + 其父菜单，组成完整菜单树
     * 5. 递归构建前端路由树返回
     */
    public function authMenus(User $user): array
    {
        // 步骤1：获取用户全部权限标识
        $permissions = $user->getAllPermissions()->pluck('name')->all();

        // 步骤2：查询用户有权限直接访问的菜单节点
        $allowedMenus = Menu::query()
            ->enabled()
            ->menus()
            ->whereIn('permission', $permissions)
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        // 步骤3：收集有权菜单的父级 ID，确保父菜单也会被加载
        $parentIds = $allowedMenus
            ->pluck('parent_id')
            ->filter()
            ->values()
            ->all();

        // 步骤4：查询有权菜单 + 它们的父菜单，组成完整层级
        $menus = Menu::query()
            ->enabled()
            ->menus()
            ->where(function ($query) use ($permissions, $parentIds) {
                $query->whereIn('permission', $permissions)
                    ->orWhereIn('id', $parentIds);
            })
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        // 步骤5：递归构建前端路由树
        return $this->buildRouterTree($menus);
    }

    /**
     * 按页面路由名称汇总用户拥有的按钮编码。
     *
     * 步骤：
     * 1. 获取用户全部权限标识
     * 2. 查询用户有权使用的按钮节点，并预加载父菜单（页面路由）
     * 3. 按父菜单的路由名称分组，汇总每个页面下用户可用的按钮编码
     */
    public function authButtons(User $user): array
    {
        // 步骤1：获取用户全部权限标识
        $permissions = $user->getAllPermissions()->pluck('name')->all();

        // 步骤2：查询用户有权使用的按钮节点
        $buttons = Menu::query()
            ->enabled()
            ->buttons()
            ->whereIn('permission', $permissions)
            ->with('parent')
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        // 步骤3：按父菜单路由名称分组，汇总按钮编码
        $result = [];
        foreach ($buttons as $button) {
            $routeName = $button->parent?->name ?: 'authButton';
            $result[$routeName] ??= [];
            $result[$routeName][] = $button->button_code;
        }

        return $result;
    }

    /** 获取菜单管理页面使用的完整递归树。 */
    public function list(): array
    {
        $menus = Menu::query()
            ->orderBy('sort')
            ->orderBy('id')
            ->get();

        return $this->buildManageTree($menus);
    }

    /**
     * 创建菜单或按钮节点，并同步 Spatie 权限。
     *
     * 步骤：
     * 1. 补齐默认字段、清理无效字段
     * 2. 入库创建菜单记录
     * 3. 在 Spatie 权限表中同步创建对应权限标识
     */
    public function create(array $data): array
    {
        // 步骤1：补齐默认字段，清理不同节点类型的无效字段
        $data = $this->withDefaults($data);

        // 步骤2：入库创建菜单记录
        $menu = Menu::create($data);

        // 步骤3：同步创建 Spatie 权限标识
        Permission::findOrCreate($menu->permission, 'api');

        return $menu->toManageArray();
    }

    /**
     * 更新菜单节点，并在权限标识变化时清理旧权限。
     *
     * 步骤：
     * 1. 查找目标菜单，记录旧权限标识
     * 2. 补齐默认字段后更新菜单记录
     * 3. 确保新权限标识在 Spatie 表中存在
     * 4. 若权限标识变更，删除旧的 Spatie 权限记录
     */
    public function update(array $data): array
    {
        // 步骤1：查找菜单，记录旧权限标识
        $menu = $this->findMenu((int) $data['id']);
        $oldPermission = $menu->permission;

        // 步骤2：补齐默认字段后更新
        $menu->update($this->withDefaults($data));

        // 步骤3：确保新权限标识存在
        Permission::findOrCreate($menu->permission, 'api');

        // 步骤4：若权限标识发生变更，清理旧权限
        if ($oldPermission !== $menu->permission) {
            Permission::query()
                ->where('guard_name', 'api')
                ->where('name', $oldPermission)
                ->delete();
        }

        return $menu->fresh()->toManageArray();
    }

    /**
     * 删除菜单及所有后代节点，同时清理关联权限。
     *
     * 步骤：
     * 1. 标准化 ID 数组：支持单值或数组，统一为 int 数组
     * 2. 校验传入 ID 是否全部存在
     * 3. 递归收集所有后代节点 ID（子孙菜单一起删）
     * 4. 收集待删除菜单的权限标识
     * 5. 删除菜单记录
     * 6. 清理 Spatie 权限表中的对应记录
     */
    public function delete(array|int|string $ids): void
    {
        // 步骤1：标准化 ID 数组
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($ids === []) {
            throw BusinessException::fromCode(ApiCode::PARAM_ERROR);
        }

        // 步骤2：校验传入 ID 是否全部存在
        $existingCount = Menu::query()->whereIn('id', $ids)->count();
        if ($existingCount !== count($ids)) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        // 步骤3：递归收集所有后代节点 ID
        $ids = $this->withDescendantIds($ids);

        // 步骤4：收集待删除菜单的权限标识
        $permissions = Menu::query()->whereIn('id', $ids)->pluck('permission')->all();

        // 步骤5：删除菜单记录
        Menu::query()->whereIn('id', $ids)->delete();

        // 步骤6：清理 Spatie 权限表中的对应记录
        Permission::query()->where('guard_name', 'api')->whereIn('name', $permissions)->delete();
    }

    /** 查找菜单节点，不存在时抛出统一业务异常。 */
    private function findMenu(int $id): Menu
    {
        $menu = Menu::query()->find($id);
        if (! $menu) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $menu;
    }

    /**
     * 递归收集待删除菜单的全部后代主键。
     *
     * 使用 BFS（广度优先）逐层查找子节点：
     * - 以传入 ID 为起点，查找所有子节点
     * - 再以子节点为起点继续查找，直到没有新的后代
     */
    private function withDescendantIds(array $ids): array
    {
        // 初始待查队列
        $allIds = collect($ids);
        $pending = $ids;

        // BFS 逐层收集所有后代节点 ID
        while ($pending !== []) {
            // 查找当前层所有节点的子节点
            $children = Menu::query()->whereIn('parent_id', $pending)->pluck('id')->all();
            // 去重：只保留尚未收集到的 ID
            $pending = array_values(array_diff($children, $allIds->all()));
            // 合并到结果集
            $allIds = $allIds->merge($pending)->unique()->values();
        }

        return $allIds->all();
    }

    /**
     * 补齐菜单默认字段并清理不同节点类型的无效字段。
     *
     * - 菜单节点（menu）：清空 button_code，保留路由字段
     * - 按钮节点（button）：清空路由字段（name/path/component/redirect），保留 button_code
     * - 所有节点补齐 sort、status 等默认值
     */
    private function withDefaults(array $data): array
    {
        // 移除 id，防止被误写入
        unset($data['id']);

        // 补齐通用默认值
        $data['sort'] ??= 0;
        $data['status'] ??= 1;
        $data['is_link'] ??= '';
        $data['is_hide'] ??= false;
        $data['is_full'] ??= false;
        $data['is_affix'] ??= false;
        $data['is_keep_alive'] ??= true;

        // 按节点类型清理无效字段
        if (($data['type'] ?? null) === Menu::TYPE_BUTTON) {
            // 按钮节点：清空路由相关字段
            $data['name'] = null;
            $data['path'] = null;
            $data['component'] = null;
            $data['redirect'] = null;
        } else {
            // 菜单节点：清空按钮编码
            $data['button_code'] = null;
        }

        return $data;
    }

    /** 递归构建前端动态路由树。 */
    private function buildRouterTree($menus, ?int $parentId = null): array
    {
        return $menus
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Menu $menu) use ($menus) {
                $item = $menu->toRouterArray();
                $children = $this->buildRouterTree($menus, $menu->id);
                if ($children) {
                    $item['children'] = $children;
                }

                return $item;
            })
            ->all();
    }

    /** 递归构建后台菜单管理树。 */
    private function buildManageTree($menus, ?int $parentId = null): array
    {
        return $menus
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Menu $menu) use ($menus) {
                $item = $menu->toManageArray();
                $children = $this->buildManageTree($menus, $menu->id);
                if ($children) {
                    $item['children'] = $children;
                }

                return $item;
            })
            ->all();
    }
}
