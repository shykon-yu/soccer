<?php

namespace App\Http\Requests\Api\V1;

class CompetitionUserRegisterRequest extends BaseRequest
{
    /** 验证个人杯赛或联赛报名的赛事主键。 */
    public function rules(): array
    {
        return [
            'competition_id' => ['required', 'integer', 'exists:competitions,id'],
        ];
    }
}
