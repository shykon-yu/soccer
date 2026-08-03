<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class FrontTeamCompetitionOverviewResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'league_id' => $this->resource['league_id'],
            'current_competition' => $this->resource['current_competition'],
            'team_standings' => $this->resource['team_standings'],
            'player_standings' => $this->resource['player_standings'],
            'history' => $this->resource['history'],
        ];
    }
}
