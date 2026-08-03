<?php

namespace App\Http\Requests\Api\V1;

class TeamApplyRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'team_id' => ['required', 'integer', 'exists:teams,id'],
            'type' => ['required', 'in:join,guest'],
        ];
    }
}
