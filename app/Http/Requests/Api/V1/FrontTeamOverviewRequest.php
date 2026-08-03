<?php

namespace App\Http\Requests\Api\V1;

class FrontTeamOverviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'league_id' => ['required', 'integer', 'exists:leagues,id'],
        ];
    }
}
