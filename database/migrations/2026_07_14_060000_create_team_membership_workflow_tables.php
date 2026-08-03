<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('team_guests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
            $table->index(['user_id', 'team_id']);
        });

        Schema::create('team_staff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 20)->comment('战队职务：captain 队长，manager 管理');
            $table->timestamps();
            $table->unique(['team_id', 'user_id']);
            $table->index(['team_id', 'role']);
        });

        Schema::create('team_applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20)->comment('申请类型：join 加入或转队，guest 申请嘉宾');
            $table->string('status', 20)->default('pending')->comment('审批状态：pending 待审批，approved 通过，rejected 拒绝，cancelled 取消');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->comment('审批完成时间');
            $table->string('review_note', 500)->nullable()->comment('队长或管理填写的审批备注');
            $table->timestamps();

            $table->index(['team_id', 'status', 'type']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('team_applications');
        Schema::dropIfExists('team_staff');
        Schema::dropIfExists('team_guests');
    }
};
