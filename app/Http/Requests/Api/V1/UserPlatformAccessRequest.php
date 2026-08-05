<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

class UserPlatformAccessRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:users,id'],
            'months' => ['required', 'integer', Rule::in([0, 1, 3, 6, 12])],
        ];
    }
}
