<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Validator;

class HonorEventSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:honor_events,id'],
            'organizer_type' => ['required', 'in:league,team'],
            'league_id' => ['nullable', 'integer', 'exists:leagues,id', 'required_if:organizer_type,league'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id', 'required_if:organizer_type,team'],
            'competition_type' => ['required', 'in:team,cup,league,kof'],
            'competition_name' => ['required', 'string', 'max:160'],
            'season' => ['nullable', 'string', 'max:80'],
            'ended_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'awards' => ['required', 'array', 'size:4'],
            'awards.*.rank' => ['required', 'integer', 'between:1,4', 'distinct'],
            'awards.*.owner_name' => ['required', 'string', 'max:160'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            $allowed = $this->input('organizer_type') === 'league'
                ? ['team', 'cup', 'league']
                : ['cup', 'league', 'kof'];
            if (! in_array($this->input('competition_type'), $allowed, true)) {
                $validator->errors()->add('competition_type', '赛事类型与荣誉归属范围不匹配');
            }

            if ($this->input('organizer_type') === 'league' && $this->filled('team_id')) {
                $validator->errors()->add('team_id', '联盟荣誉不能指定战队归属');
            }
            if ($this->input('organizer_type') === 'team' && $this->filled('league_id')) {
                $validator->errors()->add('league_id', '战队荣誉不能指定联盟归属');
            }
        });
    }
}
