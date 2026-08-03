<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class FrontTeamCompetitionHistoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->resource['id'],
            'competition_id' => $this->resource['competition_id'],
            'name' => $this->resource['name'],
            'season' => $this->resource['season'],
            'starts_at' => $this->resource['starts_at'],
            'ended_at' => $this->resource['ended_at'],
            'honors' => $this->resource['honors'],
            'standings' => $this->resource['standings'],
        ];
    }
}
