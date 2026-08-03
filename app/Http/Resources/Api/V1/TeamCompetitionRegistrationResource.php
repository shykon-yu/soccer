<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class TeamCompetitionRegistrationResource extends JsonResource
{
    /** 返回联盟页团体赛报名卡片及当前用户可操作战队。 */
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['id'],
            'name' => $this->resource['name'],
            'season' => $this->resource['season'],
            'league_id' => $this->resource['league_id'],
            'league_name' => $this->resource['league_name'],
            'registration_deadline' => $this->resource['registration_deadline'],
            'registration_limit' => $this->resource['registration_limit'],
            'registered_count' => $this->resource['registered_count'],
            'registration_open' => $this->resource['registration_open'],
            'eligible_teams' => $this->resource['eligible_teams'],
        ];
    }
}
