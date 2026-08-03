<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 战队目录 API 返回格式化
 *
 * 前台展示用，按联盟分组展示战队列表及其成员/嘉宾数量。
 */
class TeamDirectoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'teams' => $this->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
                'member_count' => $team->memberships_count,
                'guest_count' => $team->guests_count,
            ])->values()->all(),
        ];
    }
}
