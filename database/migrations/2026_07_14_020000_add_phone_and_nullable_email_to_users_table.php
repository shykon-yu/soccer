<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NULL');

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 32)->nullable()->unique()->after('email')->comment('用户联系电话');
            $table->string('league', 80)->nullable()->after('phone')->comment('临时联盟名称，后续迁移为关系表');
            $table->string('team', 80)->nullable()->after('league')->comment('临时战队名称，后续迁移为关系表');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn('phone');
            $table->dropColumn(['league', 'team']);
        });

        DB::statement("UPDATE users SET email = CONCAT('user-', id, '@example.invalid') WHERE email IS NULL");
        DB::statement('ALTER TABLE users MODIFY email VARCHAR(255) NOT NULL');
    }
};
