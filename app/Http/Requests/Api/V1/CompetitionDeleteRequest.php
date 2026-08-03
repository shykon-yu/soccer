<?php

namespace App\Http\Requests\Api\V1;

class CompetitionDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'exists:competitions,id']];
    }
}
