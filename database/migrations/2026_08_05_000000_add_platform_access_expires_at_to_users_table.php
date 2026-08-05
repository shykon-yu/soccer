<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('platform_access_expires_at')
                ->nullable()
                ->after('status')
                ->index()
                ->comment('对战平台使用权限到期时间，空值表示未开通');
        });

        // 保持迁移前已有账号可继续测试；后续新账号必须由管理员明确授权。
        DB::table('users')
            ->whereNull('deleted_at')
            ->update(['platform_access_expires_at' => now()->addYear()]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['platform_access_expires_at']);
            $table->dropColumn('platform_access_expires_at');
        });
    }
};
