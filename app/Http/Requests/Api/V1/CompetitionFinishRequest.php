<?php

namespace App\Http\Requests\Api\V1;

class CompetitionFinishRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:competitions,id'],
            'honors' => ['required', 'array', 'size:4'],
            'honors.*.rank' => ['required', 'integer', 'between:1,4', 'distinct'],
            'honors.*.entry_id' => ['nullable', 'integer', 'exists:competition_entries,id'],
            'honors.*.owner_name' => ['required', 'string', 'max:160'],
        ];
    }
}
