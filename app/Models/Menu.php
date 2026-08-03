<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 菜单模型
 *
 * 同时承载后台菜单树和按钮权限两种数据。
 * - type=menu: 页面菜单节点，可配置路由、组件、图标等
 * - type=button: 按钮权限节点，挂载在菜单下，通过 button_code 标识具体操作
 *
 * 菜单与 Spatie Permission 联动：
 * - permission 字段既用于菜单可见性控制，也是 Spatie 权限标识
 * - 创建菜单时自动创建同名 Permission
 * - 删除菜单时自动清理关联 Permission
 */
class Menu extends Model
{
    use HasFactory;

    /** 节点类型：页面菜单 */
    public const TYPE_MENU = 'menu';

    /** 节点类型：按钮权限 */
    public const TYPE_BUTTON = 'button';

    protected $fillable = [
        'parent_id',
        'type',
        'title',
        'name',
        'path',
        'component',
        'redirect',
        'icon',
        'permission',
        'button_code',
        'sort',
        'status',
        'is_link',
        'is_hide',
        'is_full',
        'is_affix',
        'is_keep_alive',
    ];

    protected $casts = [
        'parent_id' => 'integer',
        'sort' => 'integer',
        'status' => 'integer',
        'is_hide' => 'boolean',
        'is_full' => 'boolean',
        'is_affix' => 'boolean',
        'is_keep_alive' => 'boolean',
    ];

    /** 获取当前菜单的上级节点。 */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** 获取按顺序排列的直接子菜单和按钮。 */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort')->orderBy('id');
    }

    /** 查询启用状态的菜单节点。 */
    public function scopeEnabled($query)
    {
        return $query->where('status', 1);
    }

    /** 查询页面菜单节点。 */
    public function scopeMenus($query)
    {
        return $query->where('type', self::TYPE_MENU);
    }

    /** 查询按钮权限节点。 */
    public function scopeButtons($query)
    {
        return $query->where('type', self::TYPE_BUTTON);
    }

    /** 转换为前端动态路由需要的数据结构。 */
    public function toRouterArray(): array
    {
        $router = [
            'path' => $this->path,
            'name' => $this->name,
            'meta' => [
                'icon' => $this->icon ?: 'Menu',
                'title' => $this->title,
                'isLink' => $this->is_link ?: '',
                'isHide' => (bool) $this->is_hide,
                'isFull' => (bool) $this->is_full,
                'isAffix' => (bool) $this->is_affix,
                'isKeepAlive' => (bool) $this->is_keep_alive,
            ],
        ];

        if ($this->component) {
            $router['component'] = $this->component;
        }

        if ($this->redirect) {
            $router['redirect'] = $this->redirect;
        }

        return $router;
    }

    /** 转换为后台菜单管理页面需要的数据结构。 */
    public function toManageArray(): array
    {
        return [
            'id' => $this->id,
            'parent_id' => $this->parent_id,
            'type' => $this->type,
            'title' => $this->title,
            'name' => $this->name,
            'path' => $this->path,
            'component' => $this->component,
            'redirect' => $this->redirect,
            'icon' => $this->icon,
            'permission' => $this->permission,
            'button_code' => $this->button_code,
            'sort' => $this->sort,
            'status' => $this->status,
            'meta' => [
                'icon' => $this->icon ?: 'Menu',
                'title' => $this->title,
                'isLink' => $this->is_link ?: '',
                'isHide' => (bool) $this->is_hide,
                'isFull' => (bool) $this->is_full,
                'isAffix' => (bool) $this->is_affix,
                'isKeepAlive' => (bool) $this->is_keep_alive,
            ],
        ];
    }
}
