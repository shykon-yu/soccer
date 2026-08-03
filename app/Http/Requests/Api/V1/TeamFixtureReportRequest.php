<?php

namespace App\Http\Requests\Api\V1;

class TeamFixtureReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'fixture_id' => ['required', 'integer', 'exists:competition_team_fixtures,id'],
            'winner_entry_id' => ['nullable', 'integer', 'exists:competition_entries,id'],
            'player_matches' => ['required', 'array', 'min:1', 'max:64'],
            'player_matches.*.home_user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'player_matches.*.away_user_id' => ['required', 'integer', 'distinct', 'exists:users,id'],
            'player_matches.*.home_score' => ['required', 'integer', 'min:0', 'max:99'],
            'player_matches.*.away_score' => ['required', 'integer', 'min:0', 'max:99'],
        ];
    }
}
