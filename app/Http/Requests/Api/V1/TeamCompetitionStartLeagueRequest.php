<?php

namespace App\Http\Requests\Api\V1;

class TeamCompetitionStartLeagueRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:competitions,id'],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:start_date'],
            'include_weekends' => ['required', 'boolean'],
        ];
    }
}
