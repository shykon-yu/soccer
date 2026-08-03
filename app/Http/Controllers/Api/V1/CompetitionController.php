<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CompetitionActionRequest;
use App\Http\Requests\Api\V1\CompetitionDeleteRequest;
use App\Http\Requests\Api\V1\CompetitionDetailRequest;
use App\Http\Requests\Api\V1\CompetitionFinishRequest;
use App\Http\Requests\Api\V1\CompetitionListRequest;
use App\Http\Requests\Api\V1\CompetitionMatchReportRequest;
use App\Http\Requests\Api\V1\CompetitionMatchReviewRequest;
use App\Http\Requests\Api\V1\CompetitionSaveRequest;
use App\Http\Requests\Api\V1\CompetitionTeamRegisterRequest;
use App\Http\Requests\Api\V1\CompetitionUserRegisterRequest;
use App\Http\Requests\Api\V1\FrontCompetitionDetailRequest;
use App\Http\Requests\Api\V1\FrontCompetitionListRequest;
use App\Http\Requests\Api\V1\FrontTeamCalendarRequest;
use App\Http\Requests\Api\V1\FrontTeamHistoryDetailRequest;
use App\Http\Requests\Api\V1\FrontTeamOverviewRequest;
use App\Http\Requests\Api\V1\TeamCompetitionStartKnockoutRequest;
use App\Http\Requests\Api\V1\TeamCompetitionStartLeagueRequest;
use App\Http\Requests\Api\V1\TeamFixtureOptionsRequest;
use App\Http\Requests\Api\V1\TeamFixtureReportRequest;
use App\Http\Resources\Api\V1\CompetitionEntryResource;
use App\Http\Resources\Api\V1\CompetitionMatchResource;
use App\Http\Resources\Api\V1\CompetitionResource;
use App\Http\Resources\Api\V1\CompetitionTeamFixtureResource;
use App\Http\Resources\Api\V1\FrontTeamCompetitionHistoryResource;
use App\Http\Resources\Api\V1\FrontTeamCompetitionOverviewResource;
use App\Http\Resources\Api\V1\TeamCompetitionRegistrationResource;
use App\Models\Competition;
use App\Services\CompetitionService;
use App\Services\CupWorkflowService;
use App\Services\FrontTeamCompetitionService;
use App\Services\TeamCompetitionWorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompetitionController extends BaseController
{
    public function __construct(
        private readonly CompetitionService $competitionService,
        private readonly CupWorkflowService $cupWorkflowService,
        private readonly TeamCompetitionWorkflowService $teamCompetitionWorkflowService,
        private readonly FrontTeamCompetitionService $frontTeamCompetitionService
    ) {}

    /** 分页查询指定组织范围和类型的历届赛事。 */
    public function list(CompetitionListRequest $request): JsonResponse
    {
        $list = $this->competitionService->paginate($request->validated());

        return $this->resourceCollection(CompetitionResource::collection($list));
    }

    /** 查询单场赛事的阶段、比分与荣誉详情。 */
    public function detail(CompetitionDetailRequest $request): JsonResponse
    {
        return $this->resource(new CompetitionResource($this->competitionService->detail((int) $request->validated('id'))));
    }

    /** 按当前用户的联盟、正式战队和嘉宾战队范围分页查询前台赛事。 */
    public function frontList(FrontCompetitionListRequest $request): JsonResponse
    {
        $list = $this->competitionService->frontPaginate($request->user(), $request->validated());

        return $this->resourceCollection(CompetitionResource::collection($list));
    }

    /** 在当前用户可见范围内查询赛事阶段、小组和比分详情。 */
    public function frontDetail(FrontCompetitionDetailRequest $request): JsonResponse
    {
        return $this->resource(new CompetitionResource(
            $this->competitionService->frontDetail($request->user(), (int) $request->validated('id'))
        ));
    }

    /** 按联盟和月份返回前台团体赛赛历。 */
    public function teamCalendar(FrontTeamCalendarRequest $request): JsonResponse
    {
        $fixtures = $this->competitionService->teamCalendar($request->validated());

        return $this->resourceCollection(CompetitionTeamFixtureResource::collection($fixtures));
    }

    /** 返回联盟前台团体赛的当前赛事、积分榜、个人榜和历史档案。 */
    public function teamOverview(FrontTeamOverviewRequest $request): JsonResponse
    {
        $overview = $this->frontTeamCompetitionService->overview((int) $request->validated('league_id'));

        return $this->resource(FrontTeamCompetitionOverviewResource::make($overview));
    }

    /** 按需返回单届联盟团体赛的积分和荣誉详情。 */
    public function teamHistoryDetail(FrontTeamHistoryDetailRequest $request): JsonResponse
    {
        $history = $this->frontTeamCompetitionService->historyDetail((int) $request->validated('id'));

        return $this->resource(FrontTeamCompetitionHistoryResource::make($history));
    }

    /** 返回当前用户所在联盟中可报名的团体赛及可操作战队。 */
    public function teamRegistrationOptions(Request $request): JsonResponse
    {
        return $this->success(
            TeamCompetitionRegistrationResource::collection(
                $this->competitionService->teamRegistrationOptions($request->user())
            )->resolve()
        );
    }

    /** 当前登录用户报名个人杯赛或联赛。 */
    public function registerUser(CompetitionUserRegisterRequest $request): JsonResponse
    {
        $entry = $this->competitionService->registerUser(
            $request->user(),
            (int) $request->validated('competition_id')
        );

        return $this->created(CompetitionEntryResource::make($entry), '报名成功');
    }

    /** 队长或管理代表战队报名联盟团体赛。 */
    public function registerTeam(CompetitionTeamRegisterRequest $request): JsonResponse
    {
        $entry = $this->competitionService->registerTeam(
            $request->user(),
            (int) $request->validated('competition_id'),
            (int) $request->validated('team_id')
        );

        return $this->created(CompetitionEntryResource::make($entry), '战队报名成功');
    }

    public function startGroup(CompetitionActionRequest $request): JsonResponse
    {
        $competition = $this->cupWorkflowService->startGroupStage(
            $request->user(),
            (int) $request->validated('id')
        );

        return $this->updated(CompetitionResource::make($competition), '小组赛已开启');
    }

    public function startKnockout(CompetitionActionRequest $request): JsonResponse
    {
        $competition = $this->cupWorkflowService->startKnockoutStage(
            $request->user(),
            (int) $request->validated('id')
        );

        return $this->updated(CompetitionResource::make($competition), '淘汰赛已开启');
    }

    public function startTeamLeague(TeamCompetitionStartLeagueRequest $request): JsonResponse
    {
        $data = $request->validated();
        $competition = $this->teamCompetitionWorkflowService->startLeague(
            $request->user(),
            (int) $data['id'],
            $data
        );

        return $this->updated(CompetitionResource::make($competition), '团体循环赛已开启并完成排期');
    }

    public function startTeamKnockout(TeamCompetitionStartKnockoutRequest $request): JsonResponse
    {
        $data = $request->validated();
        $competition = $this->teamCompetitionWorkflowService->startKnockout(
            $request->user(),
            (int) $data['id'],
            $data
        );

        return $this->updated(CompetitionResource::make($competition), '团体淘汰赛已开启');
    }

    public function teamFixtureOptions(TeamFixtureOptionsRequest $request): JsonResponse
    {
        return $this->success($this->teamCompetitionWorkflowService->playerOptions(
            $request->user(),
            (int) $request->validated('fixture_id')
        ));
    }

    public function reportTeamFixture(TeamFixtureReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $competition = $this->teamCompetitionWorkflowService->reportFixture(
            $request->user(),
            (int) $data['fixture_id'],
            $data
        );

        return $this->updated(CompetitionResource::make($competition), '团体比分已提交');
    }

    public function reportScore(CompetitionMatchReportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $match = $this->cupWorkflowService->reportScore(
            $request->user(),
            (int) $data['match_id'],
            $data
        );

        return $this->updated(data: CompetitionMatchResource::make($match), message: '比分已提交，等待确认');
    }

    public function reviewScore(CompetitionMatchReviewRequest $request): JsonResponse
    {
        $data = $request->validated();
        $competition = $this->cupWorkflowService->reviewScore(
            $request->user(),
            (int) $data['match_id'],
            (bool) $data['approved'],
            $data['note'] ?? null
        );

        return $this->updated(CompetitionResource::make($competition), $data['approved'] ? '比分已确认' : '比分已驳回');
    }

    /** 创建赛事并返回 Resource 包装后的完整数据。 */
    public function add(CompetitionSaveRequest $request): JsonResponse
    {
        $competition = $this->competitionService->create($request->validated());

        return $this->created(new CompetitionResource($competition), '比赛创建成功');
    }

    /** 更新赛事基础设置和阶段配置。 */
    public function edit(CompetitionSaveRequest $request): JsonResponse
    {
        $competition = $this->competitionService->update($request->validated());

        return $this->updated(new CompetitionResource($competition), '比赛更新成功');
    }

    /** 删除指定赛事。 */
    public function delete(CompetitionDeleteRequest $request): JsonResponse
    {
        $this->competitionService->delete((int) $request->validated('id'));

        return $this->deleted('比赛删除成功');
    }

    /** 结束赛事并提交冠军至殿军四个名次。 */
    public function finish(CompetitionFinishRequest $request): JsonResponse
    {
        $data = $request->validated();
        $competition = $this->competitionService->detail((int) $data['id']);
        $competition = match ($competition->type) {
            Competition::TYPE_CUP => $this->cupWorkflowService->award($request->user(), $competition->id, $data['honors']),
            Competition::TYPE_TEAM => $this->teamCompetitionWorkflowService->award($request->user(), $competition->id, $data['honors']),
            default => $this->competitionService->finish($competition->id, $data['honors']),
        };

        return $this->updated(new CompetitionResource($competition), '颁奖完成');
    }
}
