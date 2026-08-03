<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamGuest extends Model
{
    protected $fillable = ['team_id', 'user_id'];

    /** 获取嘉宾关系所属战队。 */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** 获取担任战队嘉宾的用户。 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
