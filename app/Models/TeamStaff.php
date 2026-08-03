<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamStaff extends Model
{
    public const ROLE_CAPTAIN = 'captain';

    public const ROLE_MANAGER = 'manager';

    public const MAX_MANAGERS = 5;

    protected $table = 'team_staff';

    protected $fillable = ['team_id', 'user_id', 'role'];

    /** 获取职务所属战队。 */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** 获取担任队长或管理的用户。 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
