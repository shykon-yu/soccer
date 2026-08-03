<?php

namespace App\Http\Requests\Api\V1;

class FrontCompetitionDetailRequest extends BaseRequest
{
    /** 验证前台赛事详情主键，数据范围由 Service 根据登录用户校验。 */
    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:competitions,id'],
        ];
    }
}
