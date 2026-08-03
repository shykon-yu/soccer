<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** 为新增字段上线前已有的杯赛补齐默认 16 人淘汰赛签表。 */
    public function up(): void
    {
        DB::table('competitions')
            ->where('type', 'cup')
            ->whereNull('knockout_size')
            ->update(['knockout_size' => 16]);
    }

    /** 数据回填不回滚，避免误删迁移后由管理员确认的签表设置。 */
    public function down(): void {}
};
