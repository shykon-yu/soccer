<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * 菜单 API 返回格式化
 *
 * 直接透传 Menu::toManageArray() 的结果。
 */
class MenuResource extends JsonResource
{
    public function toArray($request): array
    {
        return (array) $this->resource;
    }
}
