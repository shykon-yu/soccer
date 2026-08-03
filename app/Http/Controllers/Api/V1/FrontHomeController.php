<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\FrontHomeResource;
use App\Services\FrontHomeService;
use Illuminate\Http\JsonResponse;

class FrontHomeController extends BaseController
{
    public function __construct(private readonly FrontHomeService $frontHomeService) {}

    /** 返回无需登录即可查看的前台首页赛事和联盟概览。 */
    public function overview(): JsonResponse
    {
        return $this->resource(new FrontHomeResource($this->frontHomeService->overview()));
    }
}
