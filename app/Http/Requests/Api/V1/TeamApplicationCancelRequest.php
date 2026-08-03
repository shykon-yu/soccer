<?php

namespace App\Http\Requests\Api\V1;

class TeamApplicationCancelRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'exists:team_applications,id']];
    }
}
