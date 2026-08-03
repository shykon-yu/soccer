<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * 联盟模型
 *
 * 联盟是系统的顶级组织单元，一个联盟下有多个战队。
 * 用户在联盟内通过 LeagueMembership 关联唯一主战队。
 *
 * 关键字段：
 * - name: 联盟名称
 * - status: 0=禁用 1=启用，禁用的联盟不会出现在下拉选项中
 */
class League extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'status'];

    /** 获取联盟下属战队。 */
    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    /** 获取联盟内所有用户主战队关系。 */
    public function memberships(): HasMany
    {
        return $this->hasMany(LeagueMembership::class);
    }

    /** 获取由该联盟组织的赛事。 */
    public function competitions(): HasMany
    {
        return $this->hasMany(Competition::class);
    }

    /** 获取联盟荣誉事件记录。 */
    public function honorEvents(): HasMany
    {
        return $this->hasMany(HonorEvent::class);
    }
}
