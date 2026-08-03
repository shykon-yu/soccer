<?php

namespace App\Http\Requests\Api\V1;

class CompetitionListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'pageNum' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
            'organizer_type' => ['required', 'in:league,team'],
            'type' => ['required', 'in:team,cup,league,kof'],
            'name' => ['nullable', 'string', 'max:160'],
            'status' => ['nullable', 'in:registration,in_progress,knockout,awaiting_awards,completed'],
        ];
    }
}
