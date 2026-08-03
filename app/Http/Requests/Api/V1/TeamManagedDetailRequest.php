<?php

namespace App\Http\Requests\Api\V1;

class TeamManagedDetailRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['team_id' => ['required', 'integer', 'exists:teams,id']];
    }
}
