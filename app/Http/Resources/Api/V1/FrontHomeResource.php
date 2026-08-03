<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class FrontHomeResource extends JsonResource
{
    /** 返回前台首页公开聚合数据。 */
    public function toArray($request): array
    {
        return [
            'statistics' => $this->resource['statistics'],
            'active_competitions' => $this->resource['active_competitions'],
            'top_teams' => $this->resource['top_teams'],
            'latest_champions' => $this->resource['latest_champions'],
        ];
    }
}
