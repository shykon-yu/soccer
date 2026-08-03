<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Menu;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/**
 * 角色管理业务层
 *
 * 角色使用 Spatie Permission 管理，guard_name 固定为 'api'。
 * 权限树从 Menu 表动态构建，包含菜单权限和按钮权限。
 */
class RoleService
{
    /**
     * 按角色名分页查询角色及权限。
     *
     * 步骤：
     * 1. 提取分页参数
     * 2. 查询 api guard 下的角色，支持按名称模糊搜索
     * 3. 预加载角色的权限列表
     */
    public function list(array $filters)
    {
        // 步骤1：提取分页参数
        $pageSize = (int) ($filters['pageSize'] ?? 10);
        $page = (int) ($filters['pageNum'] ?? $filters['page'] ?? 1);

        // 步骤2-3：查询角色并预加载权限
        return Role::query()
            ->with('permissions')
            ->when(! empty($filters['name']), fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->where('guard_name', 'api')
            ->orderBy('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /** 获取全部角色简要选项。 */
    public function all()
    {
        return Role::query()
            ->where('guard_name', 'api')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /**
     * 创建角色并同步菜单和按钮权限。
     *
     * 步骤：
     * 1. 创建角色记录（固定 guard_name = 'api'）
     * 2. 校验并同步权限标识到 Spatie 表
     * 3. 刷新模型，返回带权限的角色数据
     */
    public function create(array $data):role
    {
        // 步骤1：创建角色记录
        $role = Role::create(['name' => $data['name'], 'guard_name' => 'api']);

        // 步骤2：同步权限
        $this->syncPermissions($role, $data['permissions'] ?? []);

        // 步骤3：刷新并返回带权限的角色
        return $role->fresh('permissions');
    }

    /**
     * 更新角色名称和权限集合。
     *
     * 步骤：
     * 1. 查找目标角色
     * 2. 更新角色名称
     * 3. 重新同步权限集合（syncPermissions 会覆盖旧权限）
     */
    public function update(array $data):role
    {
        // 步骤1：查找目标角色
        $role = $this->findRole((int) $data['id']);

        // 步骤2：更新名称
        $role->update(['name' => $data['name']]);

        // 步骤3：重新同步权限
        $this->syncPermissions($role, $data['permissions'] ?? []);

        return $role->fresh('permissions');
    }

    /** 单独重新分配指定角色权限。 */
    public function assignPermissions(int $id, array $permissions):role
    {
        $role = $this->findRole($id);
        $this->syncPermissions($role, $permissions);

        return $role->fresh('permissions');
    }

    /**
     * 批量删除非系统保护角色。
     *
     * 步骤：
     * 1. 标准化 ID 数组
     * 2. 校验传入的 ID 是否全部存在
     * 3. 执行批量删除
     */
    public function delete(array|int|string $ids): void
    {
        // 步骤1：标准化 ID 数组
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = collect($ids)->map(fn ($id) => (int) $id)->unique()->values()->all();

        if ($ids === []) {
            throw BusinessException::fromCode(ApiCode::PARAM_ERROR);
        }

        // 步骤2：校验 ID 是否全部存在
        $existingCount = Role::query()->whereIn('id', $ids)->where('guard_name', 'api')->count();
        if ($existingCount !== count($ids)) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        // 步骤3：批量删除
        Role::query()->whereIn('id', $ids)->where('guard_name', 'api')->delete();
    }

    /**
     * 构建角色弹窗使用的递归菜单和按钮权限树。
     *
     * 步骤：
     * 1. 查询全部菜单节点（含菜单和按钮）
     * 2. 递归构建权限树，每个节点包含 id/label/type/permission/button_code
     */
    public function permissionTree(): array
    {
        // 步骤1：查询全部菜单节点
        $menus = Menu::query()->orderBy('sort')->orderBy('id')->get();

        // 步骤2：递归构建权限树
        return $this->buildPermissionTree($menus);
    }

    /** 查找角色，不存在时抛出统一业务异常。 */
    private function findRole(int $id): Role
    {
        $role = Role::query()->where('guard_name', 'api')->find($id);
        if (! $role) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $role;
    }

    /**
     * 校验权限标识后同步角色权限。
     *
     * 步骤：
     * 1. 遍历传入的权限名称，逐个在 Spatie 表中 findOrCreate
     * 2. 调用 Spatie 的 syncPermissions 覆盖角色权限集合
     *
     * 注意：syncPermissions 会先清空角色所有旧权限，再分配新权限，
     * 因此传入的 permissions 数组必须是完整的最终权限集合。
     */
    private function syncPermissions(Role $role, array $permissions): void
    {
        // 步骤1：逐个 findOrCreate 确保权限标识存在
        $permissionModels = collect($permissions)
            ->filter()
            ->map(fn (string $name) => Permission::findOrCreate($name, 'api'))
            ->all();

        // 步骤2：覆盖式同步角色权限
        $role->syncPermissions($permissionModels);
    }

    /**
     * 递归构建菜单及按钮权限节点。
     *
     * 从顶层（parent_id = null）开始，逐层递归：
     * - 菜单节点作为父级，其按钮子节点挂在 children 中
     * - 每个节点包含 id/label/type/permission/button_code 供前端权限树展示
     */
    private function buildPermissionTree($menus, ?int $parentId = null): array
    {
        return $menus
            // 筛选当前层级的节点
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Menu $menu) use ($menus) {
                // 构建当前节点数据
                $item = [
                    'id' => $menu->id,
                    'label' => $menu->title,
                    'type' => $menu->type,
                    'permission' => $menu->permission,
                    'button_code' => $menu->button_code,
                ];
                // 递归查找子节点
                $children = $this->buildPermissionTree($menus, $menu->id);
                if ($children) {
                    $item['children'] = $children;
                }

                return $item;
            })
            ->all();
    }
}
