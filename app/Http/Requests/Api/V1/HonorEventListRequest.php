<?php

namespace App\Http\Requests\Api\V1;

class HonorEventListRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'pageNum' => ['nullable', 'integer', 'min:1'],
            'pageSize' => ['nullable', 'integer', 'min:1', 'max:100'],
            'competition_name' => ['nullable', 'string', 'max:160'],
            'organizer_type' => ['nullable', 'in:league,team'],
            'competition_type' => ['nullable', 'in:team,cup,league,kof'],
            'league_id' => ['nullable', 'integer', 'exists:leagues,id'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id'],
            'source' => ['nullable', 'in:competition,manual'],
        ];
    }
}
