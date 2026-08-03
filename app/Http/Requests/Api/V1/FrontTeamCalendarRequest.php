<?php

namespace App\Http\Requests\Api\V1;

class FrontTeamCalendarRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'league_id' => ['nullable', 'integer', 'exists:leagues,id'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'month' => ['required', 'integer', 'between:1,12'],
        ];
    }
}
