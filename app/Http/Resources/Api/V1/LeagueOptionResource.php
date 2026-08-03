<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 联盟下拉选项 API 返回格式化
 *
 * 返回联盟及其启用战队列表，供全站级联选择复用。
 */
class LeagueOptionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'teams' => $this->teams->map(fn ($team) => [
                'id' => $team->id,
                'name' => $team->name,
            ])->values()->all(),
        ];
    }
}
