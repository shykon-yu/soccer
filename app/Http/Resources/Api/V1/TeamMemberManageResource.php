<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 战队成员选项 API 返回格式化
 *
 * 直接透传 Service 返回的数组数据。
 */
class TeamMemberManageResource extends JsonResource
{
    public function toArray($request): array
    {
        return (array) $this->resource;
    }
}
