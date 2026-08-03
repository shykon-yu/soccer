<?php

namespace App\Http\Requests\Api\V1;

class FrontTeamHistoryDetailRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:honor_events,id'],
        ];
    }
}
