<?php

namespace App\Http\Requests\Api\V1;

class CompetitionDetailRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'exists:competitions,id']];
    }
}
