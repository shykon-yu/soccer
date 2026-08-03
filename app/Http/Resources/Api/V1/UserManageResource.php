<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 用户后台管理 API 返回格式化
 *
 * 用于后台用户管理页面，包含角色、联盟-战队归属等管理信息。
 * id 强制转为 string 避免前端大数精度丢失。
 */
class UserManageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => (string) $this->id,
            'username' => $this->username,
            'nickname' => $this->nickname,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'status' => (int) ($this->status ?? 1),
            'roles' => $this->whenLoaded('roles', fn () => $this->roles->pluck('name')->values()->all(), []),
            'role_ids' => $this->whenLoaded('roles', fn () => $this->roles->pluck('id')->values()->all(), []),
            'memberships' => $this->whenLoaded('memberships', function () {
                return $this->memberships->map(fn ($m) => [
                    'id' => $m->id,
                    'league_id' => $m->league_id,
                    'league_name' => $m->league->name ?? '',
                    'team_id' => $m->team_id,
                    'team_name' => $m->team->name ?? '',
                ])->values()->all();
            }, []),
            'createTime' => $this->created_at?->toDateTimeString(),
            'created_at' => $this->created_at?->toDateTimeString(),
            'updated_at' => $this->updated_at?->toDateTimeString(),
        ];
    }
}
