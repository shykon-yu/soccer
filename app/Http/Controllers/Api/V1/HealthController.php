<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\DataResource;
use Illuminate\Http\JsonResponse;

class HealthController extends BaseController
{
    /** 返回 API 服务健康状态和当前时间。 */
    public function index(): JsonResponse
    {
        return $this->resource(new DataResource([
            'status' => 'ok',
            'version' => 'v1',
            'time' => now()->toDateTimeString(),
        ]));
    }
}
