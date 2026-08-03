<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\MenuDeleteRequest;
use App\Http\Requests\Api\V1\MenuSaveRequest;
use App\Http\Resources\Api\V1\DataResource;
use App\Http\Resources\Api\V1\MenuResource;
use App\Services\MenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

/**
 * 菜单管理接口
 *
 * 提供后台菜单树和按钮权限的增删改查。
 * authMenus/authButtons 为登录后前端获取动态路由和按钮权限的入口。
 * 路由前缀: POST /api/v1/menu/
 */
class MenuController extends BaseController
{
    public function __construct(
        private readonly MenuService $menuService
    ) {}

    /** 返回当前用户可访问的后台动态菜单。 */
    public function authMenus(): JsonResponse
    {
        $authMenus = $this->menuService->authMenus(Auth::guard('api')->user());
        return $this->resource(resource: DataResource::make($authMenus));
    }

    /** 返回当前用户按页面分组的按钮权限。 */
    public function authButtons(): JsonResponse
    {
        return $this->resource(new DataResource($this->menuService->authButtons(Auth::guard('api')->user())));
    }

    /** 返回菜单管理使用的完整递归树。 */
    public function list(): JsonResponse
    {
        $listTree = $this->menuService->list();
        return $this->resource(resource: DataResource::make($listTree));
    }

    /** 创建菜单或按钮节点。 */
    public function add(MenuSaveRequest $request): JsonResponse
    {
        $returnMenu = $this->menuService->create($request->validated());
        return $this->created(data: menuResource::make($returnMenu));
    }

    /** 更新菜单或按钮节点。 */
    public function edit(MenuSaveRequest $request): JsonResponse
    {
        $returnMenu = $this->menuService->update($request->validated());
        return $this->updated(data: menuResource::make($returnMenu));
    }

    /** 删除菜单节点及其所有后代。 */
    public function delete(MenuDeleteRequest $request): JsonResponse
    {
        $this->menuService->delete($request->validated('id'));

        return $this->deleted();
    }
}
