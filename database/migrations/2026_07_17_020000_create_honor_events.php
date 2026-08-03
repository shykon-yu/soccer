<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('honor_events')) {
            Schema::create('honor_events', function (Blueprint $table) {
                $table->id();
                $table->foreignId('competition_id')->nullable()->unique()->constrained()->cascadeOnDelete();
                $table->string('source', 20)->default('manual')->comment('荣誉来源：competition 比赛颁奖，manual 历史录入');
                $table->string('organizer_type', 20)->comment('荣誉归属范围：league 联盟级，team 战队级');
                $table->foreignId('league_id')->nullable()->constrained()->restrictOnDelete();
                $table->foreignId('team_id')->nullable()->constrained()->restrictOnDelete();
                $table->string('competition_type', 40)->comment('赛事类型：team 团体赛，cup 杯赛，league 联赛，kof 拳皇赛');
                $table->string('competition_name', 160)->comment('赛事名称快照');
                $table->string('season', 80)->nullable()->comment('赛事届次或赛季名称');
                $table->timestamp('ended_at')->nullable()->comment('赛事结束时间');
                $table->text('notes')->nullable()->comment('历史荣誉补充说明');
                $table->timestamps();

                $table->index(['organizer_type', 'competition_type', 'ended_at']);
                $table->index(['league_id', 'competition_type']);
                $table->index(['team_id', 'competition_type']);
            });
        }

        if (! Schema::hasColumn('competition_honors', 'honor_event_id')) {
            Schema::table('competition_honors', function (Blueprint $table) {
                $table->foreignId('honor_event_id')->nullable()->after('id')->constrained('honor_events')->cascadeOnDelete();
            });
        }

        $this->changeCompetitionIdNullable(true);

        Schema::table('competition_honors', function (Blueprint $table) {
            $table->unique(['honor_event_id', 'rank']);
        });

        DB::table('competitions')
            ->join('competition_honors', 'competition_honors.competition_id', '=', 'competitions.id')
            ->select('competitions.*')
            ->distinct()
            ->orderBy('competitions.id')
            ->get()
            ->each(function ($competition) {
                $eventId = DB::table('honor_events')->insertGetId([
                    'competition_id' => $competition->id,
                    'source' => 'competition',
                    'organizer_type' => $competition->organizer_type,
                    'league_id' => $competition->league_id,
                    'team_id' => $competition->team_id,
                    'competition_type' => $competition->type,
                    'competition_name' => $competition->name,
                    'season' => $competition->season,
                    'ended_at' => $competition->ended_at ?: $competition->awarded_at,
                    'notes' => null,
                    'created_at' => $competition->awarded_at ?: $competition->created_at,
                    'updated_at' => $competition->updated_at,
                ]);

                DB::table('competition_honors')
                    ->where('competition_id', $competition->id)
                    ->update(['honor_event_id' => $eventId]);
            });
    }

    public function down(): void
    {
        DB::table('competition_honors')->whereNull('competition_id')->delete();

        Schema::table('competition_honors', function (Blueprint $table) {
            $table->dropUnique(['honor_event_id', 'rank']);
            $table->dropConstrainedForeignId('honor_event_id');
        });

        $this->changeCompetitionIdNullable(false);

        Schema::dropIfExists('honor_events');
    }

    private function changeCompetitionIdNullable(bool $nullable): void
    {
        $driver = DB::connection()->getDriverName();
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE competition_honors MODIFY competition_id BIGINT UNSIGNED '.($nullable ? 'NULL' : 'NOT NULL'));

            return;
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE competition_honors ALTER COLUMN competition_id '.($nullable ? 'DROP' : 'SET').' NOT NULL');

            return;
        }

        throw new RuntimeException('荣誉档案迁移暂不支持当前数据库驱动：'.$driver);
    }
};
