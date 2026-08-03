<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 联盟成员关系模型（用户在各联盟的唯一主战队）
 *
 * 每个用户在一个联盟内只有一条记录，指向其主战队。
 * 这是用户参与联盟级赛事的基础关联。
 */
class LeagueMembership extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'league_id', 'team_id'];

    /** 获取该联盟成员关系对应的用户。 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 获取成员所属联盟。 */
    public function league(): BelongsTo
    {
        return $this->belongsTo(League::class);
    }

    /** 获取用户在该联盟的唯一主战队。 */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
