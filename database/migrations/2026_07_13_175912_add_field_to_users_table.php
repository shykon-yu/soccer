<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('username')->unique()->after('name')->comment('用户唯一登录名');
            $table->renameColumn('name', 'nickname');
            $table->string('avatar')->nullable()->after('email')->comment('用户头像资源地址');
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('username');
            $table->renameColumn('nickname', 'name');
            $table->dropColumn('avatar');
            $table->dropSoftDeletes();
        });
    }
};
