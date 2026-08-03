<?php

namespace App\Http\Requests\Api\V1;

class CompetitionTemplateListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'pageNum' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
            'name' => ['nullable', 'string', 'max:160'],
            'organizer_type' => ['nullable', 'in:league,team'],
            'type' => ['nullable', 'in:team,cup,league'],
            'status' => ['nullable', 'boolean'],
        ];
    }
}
