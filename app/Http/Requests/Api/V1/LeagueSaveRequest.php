<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Validation\Rule;

/**
 * 联盟新增/编辑请求校验
 *
 * 共用同一个 Request，通过 id 区分新增/编辑。
 * 联盟名称全局唯一。
 */
class LeagueSaveRequest extends BaseRequest
{
    public function rules(): array
    {
        $id = $this->input('id');

        return [
            'id' => [$id ? 'required' : 'prohibited', 'integer', 'exists:leagues,id'],
            'name' => ['required', 'string', 'max:120', Rule::unique('leagues', 'name')->ignore($id)],
            'status' => ['nullable', 'integer', 'in:0,1'],
        ];
    }
}
