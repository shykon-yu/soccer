<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class TeamCompetitionStartKnockoutRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:competitions,id'],
            'knockout_size' => ['required', 'integer', Rule::in([2, 4, 8, 16, 32, 64])],
            'pairing_mode' => ['required', Rule::in(['cross', 'random', 'custom'])],
            'pairs' => ['nullable', 'required_if:pairing_mode,custom', 'array'],
            'pairs.*.home_entry_id' => ['required', 'integer', 'exists:competition_entries,id'],
            'pairs.*.away_entry_id' => ['required', 'integer', 'different:pairs.*.home_entry_id', 'exists:competition_entries,id'],
            'pairs.*.scheduled_at' => ['nullable', 'date'],
        ];
    }
}
