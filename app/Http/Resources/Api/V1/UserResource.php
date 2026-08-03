<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 用户前台资料 API 返回格式化
 *
 * 用于登录响应、个人信息查看等前台场景，包含用户名修改限制信息。
 */
class UserResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'avatar' => $this->avatar,
            'username' => $this->username,
            'username_changed_at' => $this->username_changed_at?->toDateTimeString(),
            'username_change_available_at' => $this->username_changed_at?->copy()->addYear()->toDateTimeString(),
            'nickname' => $this->nickname,
            'email' => $this->email,
            'phone' => $this->phone,
            'status' => $this->status,
            'roles' => $this->whenLoaded('roles', fn () => $this->getRoleNames()),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
