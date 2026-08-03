<?php

namespace App\Http\Requests\Api\V1;

class TeamApplicationReviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:team_applications,id'],
            'decision' => ['required', 'in:approved,rejected'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
