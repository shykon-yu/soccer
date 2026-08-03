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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->comment('用户显示名称，后续迁移重命名为 nickname');
            $table->string('email')->unique()->comment('登录联系邮箱，可由后续迁移调整为可空');
            $table->timestamp('email_verified_at')->nullable()->comment('邮箱验证完成时间');
            $table->string('password')->comment('加密后的登录密码');
            $table->rememberToken();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('users');
    }
};
