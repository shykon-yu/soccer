<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** 为用户增加用户名年度修改限制的时间依据。 */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('username_changed_at')
                ->nullable()
                ->after('username')
                ->comment('用户名最近一次修改时间，用于限制一年只能修改一次');
        });
    }

    /** 移除用户名最近修改时间字段。 */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username_changed_at');
        });
    }
};
