<?php

namespace App\Http\Requests\Api\V1;

class TeamManagerSetRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'is_manager' => ['required', 'boolean'],
        ];
    }
}
