<?php

namespace App\Http\Requests\Api\V1;

class CompetitionMatchReviewRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'match_id' => ['required', 'integer', 'exists:competition_matches,id'],
            'approved' => ['required', 'boolean'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }
}
