<?php

namespace App\Http\Requests\Api\V1;

class TeamFixtureOptionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['fixture_id' => ['required', 'integer', 'exists:competition_team_fixtures,id']];
    }
}
