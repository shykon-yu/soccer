<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompetitionHonor extends Model
{
    protected $fillable = ['honor_event_id', 'competition_id', 'entry_id', 'rank', 'title', 'owner_name'];

    protected $casts = [
        'honor_event_id' => 'integer',
        'competition_id' => 'integer',
        'entry_id' => 'integer',
        'rank' => 'integer',
    ];

    public function event(): BelongsTo
    {
        return $this->belongsTo(HonorEvent::class, 'honor_event_id');
    }

    /** 获取荣誉所属赛事。 */
    public function competition(): BelongsTo
    {
        return $this->belongsTo(Competition::class);
    }

    /** 获取获奖报名对象；历史对象删除后可为空。 */
    public function entry(): BelongsTo
    {
        return $this->belongsTo(CompetitionEntry::class);
    }
}
