<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->unique(['id', 'league_id'], 'teams_id_league_unique');
        });

        Schema::create('league_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('league_id');
            $table->unsignedBigInteger('team_id');
            $table->timestamps();

            $table->unique(['user_id', 'league_id'], 'league_memberships_user_league_unique');
            $table->foreign('league_id')->references('id')->on('leagues')->cascadeOnDelete();
            $table->foreign(['team_id', 'league_id'], 'league_memberships_team_league_foreign')
                ->references(['id', 'league_id'])
                ->on('teams')
                ->cascadeOnDelete();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['league', 'team']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('league', 80)->nullable()->after('phone')->comment('回滚时恢复的联盟名称');
            $table->string('team', 80)->nullable()->after('league')->comment('回滚时恢复的战队名称');
        });

        Schema::dropIfExists('league_memberships');

        Schema::table('teams', function (Blueprint $table) {
            $table->dropUnique('teams_id_league_unique');
        });
    }
};
