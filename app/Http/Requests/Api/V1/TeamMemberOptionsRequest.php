<?php

namespace App\Http\Requests\Api\V1;

class TeamMemberOptionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['team_id' => ['required', 'integer', 'exists:teams,id']];
    }
}
