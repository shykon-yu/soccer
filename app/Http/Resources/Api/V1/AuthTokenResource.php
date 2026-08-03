<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 认证 Token API 返回格式化
 *
 * 登录和刷新 Token 时使用，返回 token + 用户信息。
 */
class AuthTokenResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'token' => $this->resource['token'],
            'token_type' => $this->resource['token_type'],
            'expires_in' => $this->resource['expires_in'],
            'user' => $this->when(
                isset($this->resource['user']),
                fn () => new UserResource($this->resource['user']->load('roles'))
            ),
        ];
    }
}
