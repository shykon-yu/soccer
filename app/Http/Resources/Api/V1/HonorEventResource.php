<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class HonorEventResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'competition_id' => $this->competition_id,
            'source' => $this->source,
            'organizer_type' => $this->organizer_type,
            'organizer_id' => $this->organizer_type === 'league' ? $this->league_id : $this->team_id,
            'organizer_name' => $this->organizer_type === 'league' ? $this->league?->name : $this->team?->name,
            'league_id' => $this->league_id,
            'team_id' => $this->team_id,
            'competition_type' => $this->competition_type,
            'competition_name' => $this->competition_name,
            'season' => $this->season,
            'ended_at' => $this->ended_at?->toDateTimeString(),
            'notes' => $this->notes,
            'awards' => $this->whenLoaded('awards', fn () => $this->awards->map(fn ($award) => [
                'id' => $award->id,
                'entry_id' => $award->entry_id,
                'rank' => $award->rank,
                'title' => $award->title,
                'owner_name' => $award->owner_name,
            ])->values()->all()),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
