<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\TeamDeleteRequest;
use App\Http\Requests\Api\V1\TeamListRequest;
use App\Http\Requests\Api\V1\TeamMemberOptionsRequest;
use App\Http\Requests\Api\V1\TeamSaveRequest;
use App\Http\Resources\Api\V1\TeamManageResource;
use App\Services\TeamService;
use Illuminate\Http\JsonResponse;

/**
 * 战队管理接口
 *
 * 提供战队的增删改查，以及队长/管理职务设置。
 * 路由前缀: POST /api/v1/team/
 */
class TeamController extends BaseController
{
    public function __construct(private readonly TeamService $teamService) {}

    /** 分页查询系统战队基础资料。 */
    public function list(TeamListRequest $request): JsonResponse
    {
        $list = $this->teamService->paginate($request->validated());

        return $this->resourceCollection(TeamManageResource::collection($list));
    }

    /** 创建战队。 */
    public function add(TeamSaveRequest $request): JsonResponse
    {
        $team = $this->teamService->create($request->validated());

        return $this->created(new TeamManageResource($team), '战队创建成功');
    }

    /** 更新战队资料并可同步指定队长和最多五名战队管理。 */
    public function edit(TeamSaveRequest $request): JsonResponse
    {
        $team = $this->teamService->update($request->validated());

        return $this->updated(new TeamManageResource($team), '战队更新成功');
    }

    /** 删除不存在业务关联的空战队。 */
    public function delete(TeamDeleteRequest $request): JsonResponse
    {
        $this->teamService->delete((int) $request->validated('id'));

        return $this->deleted('战队删除成功');
    }

    /** 获取本队正式成员，供队长和战队管理下拉选择。 */
    public function memberOptions(TeamMemberOptionsRequest $request): JsonResponse
    {
        return $this->success($this->teamService->memberOptions((int) $request->validated('team_id')));
    }
}
