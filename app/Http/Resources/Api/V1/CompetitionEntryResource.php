<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionEntryResource extends JsonResource
{
    /** 返回个人或战队报名成功后的报名记录。 */
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'competition_id' => $this->competition_id,
            'entry_type' => $this->entry_type,
            'user_id' => $this->user_id,
            'team_id' => $this->team_id,
            'status' => $this->status,
            'group' => $this->whenLoaded('groups', fn () => $this->groups->first() ? [
                'id' => $this->groups->first()->id,
                'name' => $this->groups->first()->name,
            ] : null),
            'created_at' => $this->created_at?->toDateTimeString(),
        ];
    }
}
