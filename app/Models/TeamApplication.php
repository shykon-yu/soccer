<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamApplication extends Model
{
    public const TYPE_JOIN = 'join';

    public const TYPE_GUEST = 'guest';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = ['team_id', 'user_id', 'type', 'status', 'reviewed_by', 'reviewed_at', 'review_note'];

    protected $casts = ['reviewed_at' => 'datetime'];

    /** 获取申请目标战队。 */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** 获取提交申请的用户。 */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 获取处理申请的队长或管理。 */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
