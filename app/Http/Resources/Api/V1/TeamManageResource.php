<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TeamStaff;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 战队管理列表 API 返回格式化
 *
 * captain 和 staff 通过预加载获取，member_count 通过 withCount 获取。
 */
class TeamManageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'league_id' => $this->league_id,
            'league_name' => $this->league?->name,
            'name' => $this->name,
            'status' => $this->status,
            'member_count' => $this->whenCounted('memberships'),
            'captain_user_id' => $this->captain?->user_id,
            'captain_name' => $this->captain?->user?->nickname,
            'manager_user_ids' => $this->whenLoaded('staff', fn () => $this->staff
                ->where('role', TeamStaff::ROLE_MANAGER)
                ->pluck('user_id')->values()->all()),
            'manager_names' => $this->whenLoaded('staff', fn () => $this->staff
                ->where('role', TeamStaff::ROLE_MANAGER)
                ->map(fn ($staff) => $staff->user?->nickname ?? $staff->user?->username)
                ->filter()->values()->all()),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
