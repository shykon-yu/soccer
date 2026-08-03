<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class DashboardStatisticsResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'league_count' => $this->resource['league_count'],
            'team_count' => $this->resource['team_count'],
            'user_count' => $this->resource['user_count'],
            'league_user_count' => $this->resource['league_user_count'],
            'team_distribution' => $this->resource['team_distribution'],
        ];
    }
}
