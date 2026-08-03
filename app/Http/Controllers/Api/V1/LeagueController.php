<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LeagueDeleteRequest;
use App\Http\Requests\Api\V1\LeagueListRequest;
use App\Http\Requests\Api\V1\LeagueSaveRequest;
use App\Http\Resources\Api\V1\LeagueManageResource;
use App\Http\Resources\Api\V1\LeagueOptionResource;
use App\Services\LeagueService;
use Illuminate\Http\JsonResponse;

/**
 * 联盟管理接口
 *
 * 提供联盟的增删改查和下拉选项。
 * 路由前缀: POST /api/v1/league/
 */
class LeagueController extends BaseController
{
    public function __construct(
        private readonly LeagueService $leagueService
    ) {}

    /** 获取启用联盟和战队下拉选项。 */
    public function options(): JsonResponse
    {
        $list = $this->leagueService->options();

        return $this->resource(resource: LeagueOptionResource::collection($list));
    }

    /** 分页查询联盟基础资料。 */
    public function list(LeagueListRequest $request): JsonResponse
    {
        $list = $this->leagueService->paginate($request->validated());

        return $this->resourceCollection(LeagueManageResource::collection($list));
    }

    /** 创建联盟。 */
    public function add(LeagueSaveRequest $request): JsonResponse
    {
        $league = $this->leagueService->create($request->validated());

        return $this->created(new LeagueManageResource($league), '联盟创建成功');
    }

    /** 更新联盟名称和状态。 */
    public function edit(LeagueSaveRequest $request): JsonResponse
    {
        $league = $this->leagueService->update($request->validated());

        return $this->updated(new LeagueManageResource($league), '联盟更新成功');
    }

    /** 删除不存在业务关联的空联盟。 */
    public function delete(LeagueDeleteRequest $request): JsonResponse
    {
        $this->leagueService->delete((int) $request->validated('id'));

        return $this->deleted('联盟删除成功');
    }
}
