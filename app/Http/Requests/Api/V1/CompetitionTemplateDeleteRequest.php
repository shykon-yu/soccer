<?php

namespace App\Http\Requests\Api\V1;

class CompetitionTemplateDeleteRequest extends BaseRequest
{
    public function rules(): array
    {
        return ['id' => ['required', 'integer', 'exists:competition_templates,id']];
    }
}
