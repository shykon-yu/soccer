<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use Tymon\JWTAuth\Contracts\JWTSubject;

/**
 * 用户模型
 *
 * 同时作为 JWT 认证主体和 Spatie RBAC 权限载体。
 * 用户在各联盟内通过 LeagueMembership 关联唯一主战队。
 *
 * 关键字段：
 * - username: 登录账号，一年只能修改一次
 * - status: 0=禁用 1=启用，禁用后无法登录，已签发的 token 会被作废
 * - username_changed_at: 用户名最近一次修改时间，用于限制年度修改频率
 */
class User extends Authenticatable implements JWTSubject
{
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'api';

    protected $fillable = [
        'username',
        'username_changed_at',
        'nickname',
        'avatar',
        'status',
        'platform_access_expires_at',
        'email',
        'phone',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'username_changed_at' => 'datetime',
        'platform_access_expires_at' => 'datetime',
    ];

    /** 返回 JWT subject 使用的用户主键。 */
    public function getJWTIdentifier()
    {
        return $this->getKey();
    }

    /** 返回需要写入 JWT 的自定义声明。 */
    public function getJWTCustomClaims()
    {
        return [];
    }

    /** 获取用户在各联盟的唯一主战队关系。 */
    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    /** 获取用户拥有的全部战队嘉宾身份。 */
    public function teamGuests(): HasMany
    {
        return $this->hasMany(TeamGuest::class);
    }

    /** 获取用户担任队长或管理的战队职务。 */
    public function teamStaff(): HasMany
    {
        return $this->hasMany(TeamStaff::class);
    }

    /** 获取用户提交的战队加入和嘉宾申请。 */
    public function teamApplications(): HasMany
    {
        return $this->hasMany(TeamApplication::class);
    }
}
