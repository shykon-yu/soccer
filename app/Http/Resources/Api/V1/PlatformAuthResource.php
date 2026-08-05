<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

class PlatformAuthResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'user' => [
                'id' => $this->id,
                'username' => $this->username,
                'nickname' => $this->nickname,
                'status' => (int) $this->status,
                'platform_access_expires_at' => $this->platform_access_expires_at?->toIso8601String(),
            ],
        ];
    }
}
