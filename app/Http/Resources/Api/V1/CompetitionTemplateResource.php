<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 比赛模板 API 返回字段格式化
 *
 * stages 仅在预加载后返回（whenLoaded），避免 N+1 查询。
 */
class CompetitionTemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'organizer_type' => $this->organizer_type,
            'type' => $this->type,
            'registration_limit' => $this->registration_limit,
            'is_fixed_participants' => $this->is_fixed_participants,
            'status' => $this->status,
            'notes' => $this->notes,
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn ($stage) => [
                'id' => $stage->id,
                'type' => $stage->type,
                'name' => $stage->name,
                'sort' => $stage->sort,
                'rules' => $stage->rules ?: [],
            ])->values()->all()),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
