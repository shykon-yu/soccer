<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionMatchResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'competition_id' => $this->competition_id,
            'stage_id' => $this->stage_id,
            'group_id' => $this->group_id,
            'home_entry_id' => $this->home_entry_id,
            'away_entry_id' => $this->away_entry_id,
            'winner_entry_id' => $this->winner_entry_id,
            'home_name' => $this->homeEntry?->displayName() ?: '待定',
            'away_name' => $this->awayEntry?->displayName() ?: '待定',
            'winner_name' => $this->winnerEntry?->displayName(),
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'tie_break_type' => $this->tie_break_type,
            'status' => $this->status,
            'reported_at' => $this->reported_at?->toDateTimeString(),
        ];
    }
}
