<?php

namespace App\Http\Requests\Api\V1;

class CompetitionTeamRegisterRequest extends BaseRequest
{
    /** 验证联盟团体赛报名的赛事和战队主键。 */
    public function rules(): array
    {
        return [
            'competition_id' => ['required', 'integer', 'exists:competitions,id'],
            'team_id' => ['required', 'integer', 'exists:teams,id'],
        ];
    }
}
