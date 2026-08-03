<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\DashboardStatisticsResource;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;

class DashboardController extends BaseController
{
    public function __construct(
        private readonly DashboardService $dashboardService
    ) {}

    /** 返回后台首页联盟运营统计数据。 */
    public function statistics(): JsonResponse
    {
        return $this->resource(
            new DashboardStatisticsResource($this->dashboardService->statistics())
        );
    }
}
