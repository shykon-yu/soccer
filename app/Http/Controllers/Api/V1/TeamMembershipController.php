<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\TeamApplicationCancelRequest;
use App\Http\Requests\Api\V1\TeamApplicationReviewRequest;
use App\Http\Requests\Api\V1\TeamApplyRequest;
use App\Http\Requests\Api\V1\TeamManagedDetailRequest;
use App\Http\Requests\Api\V1\TeamManagerSetRequest;
use App\Http\Resources\Api\V1\TeamDirectoryResource;
use App\Http\Resources\Api\V1\TeamMemberManageResource;
use App\Services\TeamMembershipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class TeamMembershipController extends BaseController
{
    public function __construct(private readonly TeamMembershipService $teamMembershipService) {}

    /** 返回前台公开战队目录。 */
    public function directory(): JsonResponse
    {
        $list = $this->teamMembershipService->directory();

        return $this->resourceCollection(TeamDirectoryResource::collection($list));
    }

    /** 返回当前用户的主战队、嘉宾和待审批申请。 */
    public function context(): JsonResponse
    {
        return $this->resource(new TeamMemberManageResource($this->teamMembershipService->context(Auth::guard('api')->user())));
    }

    /** 提交加入、转队或嘉宾申请。 */
    public function apply(TeamApplyRequest $request): JsonResponse
    {
        $data = $request->validated();
        $application = $this->teamMembershipService->apply(Auth::guard('api')->user(), (int) $data['team_id'], $data['type']);

        return $this->created(new TeamMemberManageResource($application->toArray()), '申请已提交');
    }

    /** 取消当前用户自己的待审批申请。 */
    public function cancel(TeamApplicationCancelRequest $request): JsonResponse
    {
        $this->teamMembershipService->cancel(Auth::guard('api')->user(), (int) $request->validated('id'));

        return $this->deleted('申请已取消');
    }

    /** 返回当前用户有权管理的战队。 */
    public function managedTeams(): JsonResponse
    {
        return $this->resource(new TeamMemberManageResource($this->teamMembershipService->managedTeams(Auth::guard('api')->user())));
    }

    /** 返回指定自有战队的成员管理详情。 */
    public function manageDetail(TeamManagedDetailRequest $request): JsonResponse
    {
        return $this->resource(new TeamMemberManageResource(
            $this->teamMembershipService->manageDetail(Auth::guard('api')->user(), (int) $request->validated('team_id'))
        ));
    }

    /** 由队长或管理审批加入和嘉宾申请。 */
    public function review(TeamApplicationReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->teamMembershipService->review(
            Auth::guard('api')->user(),
            (int) $data['id'],
            $data['decision'],
            $data['review_note'] ?? null
        );

        return $this->updated(null, $data['decision'] === 'approved' ? '申请已通过' : '申请已拒绝');
    }

    /** 由队长设置或取消本队管理。 */
    public function setManager(TeamManagerSetRequest $request): JsonResponse
    {
        $data = $request->validated();
        $this->teamMembershipService->setManager(
            Auth::guard('api')->user(),
            (int) $data['team_id'],
            (int) $data['user_id'],
            (bool) $data['is_manager']
        );

        return $this->updated(null, $data['is_manager'] ? '已设置为战队管理' : '已取消战队管理');
    }
}
