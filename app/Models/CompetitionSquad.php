<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CompetitionSquad extends Model
{
    protected $fillable = ['competition_id', 'name'];

    /** 获取临时组团参加的拳皇赛事。 */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** 获取拳皇临时组团内的用户成员。 */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'competition_squad_members', 'squad_id', 'user_id')->withTimestamps();
    }
}
