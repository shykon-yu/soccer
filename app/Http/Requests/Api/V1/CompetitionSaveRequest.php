<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class CompetitionSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:competitions,id'],
            'template_id' => ['nullable', 'integer', 'exists:competition_templates,id', 'required_unless:type,kof'],
            'organizer_type' => ['required', 'in:league,team'],
            'league_id' => ['nullable', 'integer', 'exists:leagues,id', 'required_if:organizer_type,league'],
            'team_id' => ['nullable', 'integer', 'exists:teams,id', 'required_if:organizer_type,team'],
            'type' => ['required', 'in:team,cup,league,kof'],
            'name' => ['required', 'string', 'max:160'],
            'season' => ['nullable', 'string', 'max:80'],
            'format' => ['nullable', 'required_without:template_id', Rule::in(['group_knockout', 'knockout', 'round_robin'])],
            'status' => ['nullable', Rule::in(['registration', 'in_progress', 'knockout', 'awaiting_awards', 'completed'])],
            'registration_deadline' => ['nullable', 'date', 'required_if:type,cup,league'],
            'registration_limit' => ['nullable', 'integer', 'min:2', 'max:4096'],
            'group_count' => [
                'nullable',
                Rule::requiredIf(fn () => ! $this->filled('template_id') && $this->input('format') === 'group_knockout'),
                'integer',
                'min:1',
                'max:64',
            ],
            'knockout_size' => [
                'nullable',
                Rule::requiredIf(fn () => ! $this->filled('template_id') && $this->input('type') === 'cup'),
                Rule::in([8, 16, 32, 64]),
            ],
            'starts_at' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
