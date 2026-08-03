<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionTeamFixtureResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'competition_id' => $this->competition_id,
            'competition_name' => $this->whenLoaded('competition', fn () => $this->competition?->name),
            'competition_season' => $this->whenLoaded('competition', fn () => $this->competition?->season),
            'stage_id' => $this->stage_id,
            'stage_name' => $this->whenLoaded('stage', fn () => $this->stage?->name),
            'home_entry_id' => $this->home_entry_id,
            'away_entry_id' => $this->away_entry_id,
            'winner_entry_id' => $this->winner_entry_id,
            'home_name' => $this->homeEntry?->displayName() ?: '待定',
            'away_name' => $this->awayEntry?->displayName() ?: '待定',
            'winner_name' => $this->winnerEntry?->displayName(),
            'round_label' => $this->round_label,
            'round_number' => $this->round_number,
            'sequence' => $this->sequence,
            'leg_number' => $this->leg_number,
            'home_score' => $this->home_score,
            'away_score' => $this->away_score,
            'status' => $this->status,
            'scheduled_at' => $this->scheduled_at?->toDateTimeString(),
            'reported_at' => $this->reported_at?->toDateTimeString(),
            'player_matches' => $this->whenLoaded('playerMatches', fn () => $this->playerMatches->map(fn ($match) => [
                'id' => $match->id,
                'sequence' => $match->sequence,
                'home_user_id' => $match->home_user_id,
                'away_user_id' => $match->away_user_id,
                'home_player_name' => $match->home_player_name,
                'away_player_name' => $match->away_player_name,
                'home_score' => $match->home_score,
                'away_score' => $match->away_score,
            ])->values()->all()),
        ];
    }
}
