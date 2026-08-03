<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\RoleAssignPermissionsRequest;
use App\Http\Requests\Api\V1\RoleDeleteRequest;
use App\Http\Requests\Api\V1\RoleListRequest;
use App\Http\Requests\Api\V1\RoleSaveRequest;
use App\Http\Resources\Api\V1\DataResource;
use App\Http\Resources\Api\V1\RoleResource;
use App\Services\RoleService;
use Illuminate\Http\JsonResponse;

/**
 * 角色管理接口
 *
 * 角色使用 Spatie Permission 的 Role 模型，与后台菜单权限体系联动。
 * 路由前缀: POST /api/v1/role/
 */
class RoleController extends BaseController
{
    public function __construct(
        private readonly RoleService $roleService
    ) {}

    /** 分页查询角色列表。 */
    public function list(RoleListRequest $request): JsonResponse
    {
        $list = $this->roleService->list($request->validated());

        return $this->resourceCollection(RoleResource::collection($list));
    }

    /** 返回全部角色下拉选项。 */
    public function all(): JsonResponse
    {
        $list = $this->roleService->all();

        return $this->resource(resource: DataResource::make($list));
    }

    /** 创建角色并分配权限。 */
    public function add(RoleSaveRequest $request): JsonResponse
    {
        $role = $this->roleService->create($request->validated());

        return $this->created(data: RoleResource::make($role));
    }

    /** 更新角色及其权限。 */
    public function edit(RoleSaveRequest $request): JsonResponse
    {
        $role = $this->roleService->update($request->validated());

        return $this->updated(data: RoleResource::make($role));
    }

    /** 单独重新分配角色权限。 */
    public function assignPermissions(RoleAssignPermissionsRequest $request): JsonResponse
    {
        $data = $request->validated();
        $role = $this->roleService->assignPermissions((int) $data['id'], $data['permissions'] ?? []);

        return $this->updated(data: RoleResource::make($role), message: '权限分配成功');
    }

    /** 删除指定角色。 */
    public function delete(RoleDeleteRequest $request): JsonResponse
    {
        $this->roleService->delete($request->validated('id'));

        return $this->deleted();
    }

    /** 返回菜单和按钮组成的权限树。 */
    public function permissionTree(): JsonResponse
    {
        $list = $this->roleService->permissionTree();

        return $this->resource(resource: DataResource::make($list));
    }
}
