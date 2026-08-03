<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class CompetitionMatchReportRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'match_id' => ['required', 'integer', 'exists:competition_matches,id'],
            'home_score' => ['required', 'integer', 'min:0', 'max:999'],
            'away_score' => ['required', 'integer', 'min:0', 'max:999'],
            'winner_entry_id' => ['nullable', 'integer', 'exists:competition_entries,id'],
            'tie_break_type' => ['nullable', Rule::in(['away_goals'])],
        ];
    }
}
