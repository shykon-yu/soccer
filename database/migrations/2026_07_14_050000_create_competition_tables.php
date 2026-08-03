<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competitions', function (Blueprint $table) {
            $table->id();
            $table->string('organizer_type', 20)->comment('赛事组织范围：league 联盟级，team 战队级');
            $table->foreignId('league_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('type', 40)->comment('赛事类型：team 团体赛，cup 杯赛，league 联赛，kof 拳皇赛');
            $table->string('name', 160)->comment('赛事名称');
            $table->string('season', 80)->nullable()->comment('赛事届次或赛季名称');
            $table->string('format', 40)->comment('比赛模式：group_knockout 小组加淘汰，knockout 直接淘汰，round_robin 循环赛');
            $table->string('status', 40)->default('registration')->comment('赛事状态：报名、进行中、淘汰赛、待颁奖或已完成');
            $table->timestamp('registration_deadline')->nullable()->comment('报名截止时间');
            $table->unsignedInteger('registration_limit')->nullable()->comment('最大报名名额');
            $table->unsignedSmallInteger('group_count')->nullable()->comment('小组赛分组数量');
            $table->timestamp('starts_at')->nullable()->comment('计划开始时间');
            $table->timestamp('ended_at')->nullable()->comment('比赛实际结束时间');
            $table->timestamp('awarded_at')->nullable()->comment('完成颁奖时间');
            $table->text('notes')->nullable()->comment('赛事补充说明');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_type', 'type', 'status']);
            $table->index(['league_id', 'type']);
            $table->index(['team_id', 'type']);
        });

        Schema::create('competition_squads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('name', 120)->comment('拳皇赛临时组团名称');
            $table->timestamps();
            $table->unique(['competition_id', 'name']);
        });

        Schema::create('competition_squad_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('squad_id')->constrained('competition_squads')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['squad_id', 'user_id']);
        });

        Schema::create('competition_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('entry_type', 20)->comment('参赛对象类型：user 用户，team 战队，squad 临时组团');
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('team_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('squad_id')->nullable()->constrained('competition_squads')->cascadeOnDelete();
            $table->unsignedInteger('seed')->nullable()->comment('抽签或编排使用的种子顺位');
            $table->string('status', 20)->default('registered')->comment('报名状态，如 registered 已报名');
            $table->timestamps();

            $table->unique(['competition_id', 'user_id']);
            $table->unique(['competition_id', 'team_id']);
            $table->unique(['competition_id', 'squad_id']);
            $table->index(['competition_id', 'status']);
        });

        Schema::create('competition_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->string('type', 30)->comment('阶段类型：group 小组赛，knockout 淘汰赛，league 联赛');
            $table->string('name', 80)->comment('阶段显示名称');
            $table->unsignedSmallInteger('sort')->default(0)->comment('阶段执行和展示顺序');
            $table->string('status', 20)->default('pending')->comment('阶段状态：pending 待开始等');
            $table->timestamps();
            $table->unique(['competition_id', 'type']);
        });

        Schema::create('competition_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stage_id')->constrained('competition_stages')->cascadeOnDelete();
            $table->string('name', 40)->comment('小组名称，如 A组');
            $table->unsignedSmallInteger('sort')->default(0)->comment('小组展示顺序');
            $table->timestamps();
            $table->unique(['stage_id', 'name']);
        });

        Schema::create('competition_group_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('competition_groups')->cascadeOnDelete();
            $table->foreignId('entry_id')->constrained('competition_entries')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['group_id', 'entry_id']);
        });

        Schema::create('competition_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('competition_stages')->cascadeOnDelete();
            $table->foreignId('group_id')->nullable()->constrained('competition_groups')->nullOnDelete();
            $table->foreignId('home_entry_id')->nullable()->constrained('competition_entries')->nullOnDelete();
            $table->foreignId('away_entry_id')->nullable()->constrained('competition_entries')->nullOnDelete();
            $table->string('round_label', 80)->nullable()->comment('轮次显示名称，如半决赛或第 3 轮');
            $table->unsignedSmallInteger('round_number')->nullable()->comment('轮次排序编号');
            $table->unsignedSmallInteger('sequence')->default(0)->comment('同轮比赛排列顺序');
            $table->unsignedSmallInteger('home_score')->nullable()->comment('主队或主选手得分');
            $table->unsignedSmallInteger('away_score')->nullable()->comment('客队或客选手得分');
            $table->string('status', 20)->default('pending')->comment('报分状态：pending 未完赛，completed 完赛');
            $table->timestamp('scheduled_at')->nullable()->comment('计划比赛时间');
            $table->timestamps();

            $table->index(['competition_id', 'status']);
            $table->index(['stage_id', 'round_number', 'sequence']);
        });

        Schema::create('competition_honors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('entry_id')->nullable()->constrained('competition_entries')->nullOnDelete();
            $table->unsignedTinyInteger('rank')->comment('最终名次：1 冠军，2 亚军，3 季军，4 殿军');
            $table->string('title', 20)->comment('名次中文称号');
            $table->string('owner_name', 160)->comment('获奖对象名称快照，避免改名影响历史');
            $table->timestamps();
            $table->unique(['competition_id', 'rank']);
            $table->index(['rank', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_honors');
        Schema::dropIfExists('competition_matches');
        Schema::dropIfExists('competition_group_entries');
        Schema::dropIfExists('competition_groups');
        Schema::dropIfExists('competition_stages');
        Schema::dropIfExists('competition_entries');
        Schema::dropIfExists('competition_squad_members');
        Schema::dropIfExists('competition_squads');
        Schema::dropIfExists('competitions');
    }
};
