<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->boolean('is_fixed_participants')->default(false)->after('registration_limit')->comment('是否按固定报名人数执行');
            $table->unsignedInteger('reserved_count')->default(0)->after('is_fixed_participants')->comment('已原子占用的报名名额');
        });

        Schema::table('competition_groups', function (Blueprint $table) {
            $table->unsignedSmallInteger('capacity')->nullable()->after('sort')->comment('小组固定名额');
            $table->unsignedSmallInteger('reserved_count')->default(0)->after('capacity')->comment('小组已原子占用名额');
        });

        Schema::table('competition_matches', function (Blueprint $table) {
            $table->foreignId('winner_entry_id')->nullable()->after('away_entry_id')->constrained('competition_entries')->nullOnDelete();
            $table->string('tie_break_type', 30)->nullable()->after('away_score')->comment('平局决胜方式，如 away_goals 客场进球');
            $table->foreignId('reported_by_user_id')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->timestamp('reported_at')->nullable()->after('reported_by_user_id');
            $table->foreignId('reviewed_by_user_id')->nullable()->after('reported_at')->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable()->after('reviewed_by_user_id');
            $table->string('review_note', 500)->nullable()->after('reviewed_at')->comment('比分确认或驳回说明');
        });

        DB::table('competitions')->orderBy('id')->chunkById(100, function ($competitions) {
            foreach ($competitions as $competition) {
                $count = DB::table('competition_entries')->where('competition_id', $competition->id)->count();
                DB::table('competitions')->where('id', $competition->id)->update(['reserved_count' => $count]);
            }
        });

        DB::table('competition_groups')->orderBy('id')->chunkById(100, function ($groups) {
            foreach ($groups as $group) {
                $count = DB::table('competition_group_entries')->where('group_id', $group->id)->count();
                DB::table('competition_groups')->where('id', $group->id)->update(['reserved_count' => $count]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('competition_matches', function (Blueprint $table) {
            $table->dropConstrainedForeignId('winner_entry_id');
            $table->dropConstrainedForeignId('reported_by_user_id');
            $table->dropConstrainedForeignId('reviewed_by_user_id');
            $table->dropColumn(['tie_break_type', 'reported_at', 'reviewed_at', 'review_note']);
        });
        Schema::table('competition_groups', function (Blueprint $table) {
            $table->dropColumn(['capacity', 'reserved_count']);
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn(['is_fixed_participants', 'reserved_count']);
        });
    }
};
