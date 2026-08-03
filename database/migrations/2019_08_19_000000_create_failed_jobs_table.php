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
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique()->comment('失败任务全局唯一标识');
            $table->text('connection')->comment('任务使用的队列连接');
            $table->text('queue')->comment('任务所在队列名称');
            $table->longText('payload')->comment('失败任务原始载荷');
            $table->longText('exception')->comment('任务失败异常堆栈');
            $table->timestamp('failed_at')->useCurrent()->comment('任务失败时间');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('failed_jobs');
    }
};
