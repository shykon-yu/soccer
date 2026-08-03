<?php

namespace App\Http\Requests\Api\V1;

class HonorEventDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'exists:honor_events,id']];
    }
}
