<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->unsignedTinyInteger('knockout_size')
                ->nullable()
                ->after('group_count')
                ->comment('淘汰赛正赛名额，仅允许 8、16、32、64');
        });
    }

    public function down(): void
    {
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropColumn('knockout_size');
        });
    }
};
