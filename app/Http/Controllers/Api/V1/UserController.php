<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\UserAssignRolesRequest;
use App\Http\Requests\Api\V1\UserDeleteRequest;
use App\Http\Requests\Api\V1\UserListRequest;
use App\Http\Requests\Api\V1\UserPlatformAccessRequest;
use App\Http\Requests\Api\V1\UserResetPasswordRequest;
use App\Http\Requests\Api\V1\UserSaveRequest;
use App\Http\Requests\Api\V1\UserStatusRequest;
use App\Http\Resources\Api\V1\DataResource;
use App\Http\Resources\Api\V1\UserManageResource;
use App\Services\UserService;
use Illuminate\Http\JsonResponse;

/**
 * 用户管理接口
 *
 * 提供用户的增删改查、状态变更、密码重置、角色分配。
 * 每个接口都有对应的 Spatie 权限控制（permission 中间件）。
 * 路由前缀: POST /api/v1/user/
 */
class UserController extends BaseController
{
    public function __construct(private readonly UserService $userService)
    {
        // 权限控制：菜单权限控制页面可见性，按钮权限控制操作能力
        $this->middleware('permission:menu:accountManage')->only(['list', 'treeList', 'status', 'roles']);
        $this->middleware('permission:button:accountManage:add')->only('add');
        $this->middleware('permission:button:accountManage:edit')->only('edit');
        $this->middleware('permission:button:accountManage:delete')->only('delete');
        $this->middleware('permission:button:accountManage:change')->only('changeStatus');
        $this->middleware('permission:button:accountManage:reset')->only('resetPassword');
        $this->middleware('permission:button:accountManage:assignRole')->only('assignRoles');
        $this->middleware('permission:button:accountManage:edit')->only('setPlatformAccess');
    }

    /** 分页查询用户及联盟战队关系。 */
    public function list(UserListRequest $request): JsonResponse
    {
        $list = $this->userService->list($request->validated());

        return $this->resourceCollection(UserManageResource::collection($list));
    }

    /** 返回兼容树形用户选择器的数据。 */
    public function treeList(UserListRequest $request): JsonResponse
    {
        $list = $this->userService->list($request->validated());

        return $this->resourceCollection(UserManageResource::collection($list));
    }

    /** 创建用户并同步角色与联盟关系。 */
    public function add(UserSaveRequest $request): JsonResponse
    {
        $user = $this->userService->create($request->validated());

        return $this->created(data: UserManageResource::make($user));
    }

    /** 更新用户资料、角色和联盟关系。 */
    public function edit(UserSaveRequest $request): JsonResponse
    {
        $user = $this->userService->update($request->validated());

        return $this->updated(data: UserManageResource::make($user));
    }

    /** 批量删除用户。 */
    public function delete(UserDeleteRequest $request): JsonResponse
    {
        $this->userService->delete($request->validated('id'));

        return $this->deleted();
    }

    /** 修改用户启用状态。 */
    public function changeStatus(UserStatusRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userService->changeStatus((int) $data['id'], (int) $data['status']);

        return $this->updated(data: UserManageResource::make($user), message: '状态更新成功');
    }

    /** 重置用户登录密码。 */
    public function resetPassword(UserResetPasswordRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->userService->resetPassword((int) $data['id'], $data['password'] ?? null);

        return $this->updated(null, '密码重置成功');
    }

    /** 为用户重新分配角色。 */
    public function assignRoles(UserAssignRolesRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userService->assignRoles((int) $data['id'], $data['role_ids'] ?? []);

        return $this->updated(data: UserManageResource::make($user), message: '角色分配成功');
    }

    /** 开通、续期或取消用户的对战平台使用权限。 */
    public function setPlatformAccess(UserPlatformAccessRequest $request): JsonResponse
    {
        $data = $request->validated();
        $user = $this->userService->setPlatformAccess((int) $data['id'], (int) $data['months']);

        return $this->updated(
            data: UserManageResource::make($user),
            message: (int) $data['months'] === 0 ? '平台权限已取消' : '平台权限已更新'
        );
    }

    /** 返回用户状态下拉选项。 */
    public function status(): JsonResponse
    {
        return $this->resource(new DataResource($this->userService->statusOptions()));
    }

    /** 返回可分配角色列表。 */
    public function roles(): JsonResponse
    {
        return $this->resource(new DataResource($this->userService->roles()->toArray()));
    }
}
