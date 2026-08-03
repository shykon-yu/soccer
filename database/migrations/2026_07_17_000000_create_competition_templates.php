<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('competition_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160)->comment('比赛模板名称');
            $table->string('organizer_type', 20)->comment('适用范围：league 联盟级，team 战队级');
            $table->string('type', 40)->comment('适用比赛类型：team 团体赛，cup 杯赛，league 联赛');
            $table->unsignedInteger('registration_limit')->nullable()->comment('固定或建议报名人数');
            $table->boolean('is_fixed_participants')->default(false)->comment('是否要求报名人数等于模板人数');
            $table->boolean('status')->default(true)->comment('是否启用');
            $table->text('notes')->nullable()->comment('模板说明');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['organizer_type', 'type', 'status']);
        });

        Schema::create('competition_template_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('template_id')->constrained('competition_templates')->cascadeOnDelete();
            $table->string('type', 40)->comment('阶段类型：area_group、area_knockout、group、knockout、league');
            $table->string('name', 80)->comment('阶段名称');
            $table->unsignedSmallInteger('sort')->default(0)->comment('流程顺序');
            $table->json('rules')->nullable()->comment('分区、分组、晋级、对阵和计分规则');
            $table->timestamps();

            $table->unique(['template_id', 'sort']);
        });

        Schema::table('competitions', function (Blueprint $table) {
            $table->foreignId('template_id')->nullable()->after('id')->constrained('competition_templates')->nullOnDelete();
            $table->string('template_name', 160)->nullable()->after('template_id')->comment('创建比赛时的模板名称快照');
        });

        Schema::table('competition_stages', function (Blueprint $table) {
            $table->index('competition_id', 'competition_stages_competition_id_index');
        });
        Schema::table('competition_stages', function (Blueprint $table) {
            $table->dropUnique(['competition_id', 'type']);
            $table->foreignId('template_stage_id')->nullable()->after('id')->constrained('competition_template_stages')->nullOnDelete();
            $table->json('rules')->nullable()->after('status')->comment('创建比赛时的阶段规则快照');
            $table->index(['competition_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('competition_stages', function (Blueprint $table) {
            $table->dropIndex(['competition_id', 'type']);
            $table->dropConstrainedForeignId('template_stage_id');
            $table->dropColumn('rules');
            $table->unique(['competition_id', 'type']);
        });
        Schema::table('competition_stages', function (Blueprint $table) {
            $table->dropIndex('competition_stages_competition_id_index');
        });
        Schema::table('competitions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('template_id');
            $table->dropColumn('template_name');
        });
        Schema::dropIfExists('competition_template_stages');
        Schema::dropIfExists('competition_templates');
    }
};
