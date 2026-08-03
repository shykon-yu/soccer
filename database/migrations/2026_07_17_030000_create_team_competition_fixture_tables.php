<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_team_fixtures', function (Blueprint $table) {
            $table->id();
            $table->foreignId('competition_id')->constrained()->cascadeOnDelete();
            $table->foreignId('stage_id')->constrained('competition_stages')->cascadeOnDelete();
            $table->foreignId('home_entry_id')->nullable()->constrained('competition_entries')->nullOnDelete();
            $table->foreignId('away_entry_id')->nullable()->constrained('competition_entries')->nullOnDelete();
            $table->foreignId('winner_entry_id')->nullable()->constrained('competition_entries')->nullOnDelete();
            $table->string('round_label', 80)->nullable()->comment('循环轮次或淘汰赛轮次名称');
            $table->unsignedSmallInteger('round_number')->comment('阶段内轮次编号');
            $table->unsignedSmallInteger('sequence')->comment('阶段内对阵顺序');
            $table->unsignedTinyInteger('leg_number')->default(1)->comment('主客回合：1 首回合，2 次回合');
            $table->unsignedSmallInteger('home_score')->nullable()->comment('主队获胜的队员场次数');
            $table->unsignedSmallInteger('away_score')->nullable()->comment('客队获胜的队员场次数');
            $table->string('status', 20)->default('pending')->comment('pending 未完赛，completed 已完赛');
            $table->timestamp('scheduled_at')->nullable()->comment('团体对阵日期时间');
            $table->foreignId('reported_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable();
            $table->timestamps();

            $table->unique(['stage_id', 'sequence']);
            $table->index(['competition_id', 'status']);
            $table->index(['stage_id', 'round_number', 'leg_number']);
            $table->index(['scheduled_at', 'status']);
        });

        Schema::create('competition_team_fixture_matches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('fixture_id')->constrained('competition_team_fixtures')->cascadeOnDelete();
            $table->unsignedSmallInteger('sequence')->comment('团体对阵内的队员场次顺序');
            $table->foreignId('home_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('away_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('home_player_name', 160)->comment('主队队员名称快照');
            $table->string('away_player_name', 160)->comment('客队队员名称快照');
            $table->unsignedSmallInteger('home_score')->comment('主队队员比分');
            $table->unsignedSmallInteger('away_score')->comment('客队队员比分');
            $table->timestamps();

            $table->unique(['fixture_id', 'sequence']);
            $table->index(['fixture_id', 'home_user_id']);
            $table->index(['fixture_id', 'away_user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('competition_team_fixture_matches');
        Schema::dropIfExists('competition_team_fixtures');
    }
};
