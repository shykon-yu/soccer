<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * 战队模型
 *
 * 战队隶属于联盟，是用户参与团队赛的基本单位。
 * 每个战队有唯一队长和最多5名管理（通过 TeamStaff 表管理）。
 *
 * 关键字段：
 * - league_id: 所属联盟
 * - name: 战队名称（同一联盟内唯一）
 * - status: 0=禁用 1=启用
 */
class Team extends Model
{
    use HasFactory;

    protected $fillable = ['league_id', 'name', 'status'];

    /** 获取战队所属联盟。 */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** 获取战队正式成员关系。 */
    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    /** 获取由该战队组织的赛事。 */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    /** 获取战队荣誉事件记录。 */
    public function honorEvents(): HasMany
    {
        return $this->hasMany(HonorEvent::class);
    }

    /** 获取战队嘉宾关系。 */
    public function guests(): HasMany
    {
        return $this->hasMany(TeamGuest::class);
    }

    /** 获取战队队长和管理职务。 */
    public function staff(): HasMany
    {
        return $this->hasMany(TeamStaff::class);
    }

    /** 获取发送到该战队的加入或嘉宾申请。 */
    public function applications(): HasMany
    {
        return $this->hasMany(TeamApplication::class);
    }

    /** 获取战队当前唯一队长职务记录。 */
    public function captain(): HasOne
    {
        return $this->hasOne(TeamStaff::class)->where('role', TeamStaff::ROLE_CAPTAIN);
    }
}
