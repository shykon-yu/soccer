<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\CompetitionTemplateDeleteRequest;
use App\Http\Requests\Api\V1\CompetitionTemplateListRequest;
use App\Http\Requests\Api\V1\CompetitionTemplateOptionsRequest;
use App\Http\Requests\Api\V1\CompetitionTemplateSaveRequest;
use App\Http\Resources\Api\V1\CompetitionTemplateResource;
use App\Services\CompetitionTemplateService;
use Illuminate\Http\JsonResponse;

/**
 * 比赛模板管理接口
 *
 * 提供模板的增删改查，所有接口需 JWT 鉴权。
 * 路由前缀: POST /api/v1/competition-template/
 */
class CompetitionTemplateController extends BaseController
{
    public function __construct(private readonly CompetitionTemplateService $competitionTemplateService) {}

    /** 模板列表（分页 + 多条件筛选） */
    public function list(CompetitionTemplateListRequest $request): JsonResponse
    {
        $list = $this->competitionTemplateService->paginate($request->validated());

        return $this->resourceCollection(CompetitionTemplateResource::collection($list));
    }

    /** 获取可选模板列表（创建比赛时选择模板用，只返回启用状态的模板） */
    public function options(CompetitionTemplateOptionsRequest $request): JsonResponse
    {
        $templates = $this->competitionTemplateService->options($request->validated());

        return $this->success(CompetitionTemplateResource::collection($templates)->resolve());
    }

    /** 新增模板 */
    public function add(CompetitionTemplateSaveRequest $request): JsonResponse
    {
        $template = $this->competitionTemplateService->create($request->validated());

        return $this->created(data: CompetitionTemplateResource::make($template));
    }

    /** 编辑模板（阶段同步策略：有 id 则更新，无 id 则新增，缺失则删除） */
    public function edit(CompetitionTemplateSaveRequest $request): JsonResponse
    {
        $template = $this->competitionTemplateService->update($request->validated());

        return $this->updated(data: CompetitionTemplateResource::make($template));
    }

    /** 删除模板（软删除，已被比赛使用的模板不允许删除） */
    public function delete(CompetitionTemplateDeleteRequest $request): JsonResponse
    {
        $this->competitionTemplateService->delete((int) $request->validated('id'));

        return $this->deleted('比赛模板删除成功');
    }
}
