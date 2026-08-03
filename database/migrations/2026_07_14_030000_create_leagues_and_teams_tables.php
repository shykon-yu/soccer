<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leagues', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80)->unique()->comment('联盟名称');
            $table->unsignedTinyInteger('status')->default(1)->comment('联盟状态：1 启用，0 禁用');
            $table->timestamps();
        });

        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('league_id')->constrained()->cascadeOnDelete();
            $table->string('name', 80)->comment('战队名称，同一联盟内唯一');
            $table->unsignedTinyInteger('status')->default(1)->comment('战队状态：1 启用，0 禁用');
            $table->timestamps();
            $table->unique(['league_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teams');
        Schema::dropIfExists('leagues');
    }
};
