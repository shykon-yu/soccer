<?php

namespace App\Http\Requests\Api\V1;

class CompetitionTemplateOptionsRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'organizer_type' => ['required', 'in:league,team'],
            'type' => ['required', 'in:team,cup,league'],
        ];
    }
}
