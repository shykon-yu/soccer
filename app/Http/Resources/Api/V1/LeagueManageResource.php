<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 联盟管理列表 API 返回格式化
 *
 * team_count 和 member_count 通过 withCount 预加载，避免 N+1 查询。
 */
class LeagueManageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'status' => $this->status,
            'team_count' => $this->whenCounted('teams'),
            'member_count' => $this->whenCounted('memberships'),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
