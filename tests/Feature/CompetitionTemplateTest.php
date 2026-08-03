<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\League;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\CompetitionTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompetitionTemplateTest extends TestCase
{
    use DatabaseTransactions;

    public function test_template_api_returns_paginated_resource_and_excludes_kof(): void
    {
        $user = User::create([
            'username' => 'template_admin',
            'nickname' => '模板管理员',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $response = $this->actingAs($user, 'api')->postJson('/api/v1/competition-template/add', [
            'name' => '接口测试模板',
            'organizer_type' => 'team',
            'type' => 'cup',
            'registration_limit' => 8,
            'is_fixed_participants' => true,
            'status' => true,
            'stages' => [[
                'type' => 'knockout',
                'name' => '总赛区淘汰赛',
                'rules' => ['knockout_size' => 8, 'pairing_mode' => 'random', 'scoring_mode' => 'single'],
            ]],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.name', '接口测试模板')
            ->assertJsonPath('data.stages.0.rules.knockout_size', 8);

        $this->actingAs($user, 'api')->postJson('/api/v1/competition-template/list', ['pageNum' => 1, 'pageSize' => 10])
            ->assertOk()
            ->assertJsonStructure(['data' => ['list', 'total', 'pageNum', 'pageSize']]);

        $this->actingAs($user, 'api')->postJson('/api/v1/competition-template/add', [
            'name' => '错误拳皇模板',
            'organizer_type' => 'team',
            'type' => 'kof',
            'is_fixed_participants' => false,
            'status' => true,
            'stages' => [],
        ])->assertUnprocessable();
    }

    public function test_competition_copies_template_stages_and_rules_as_snapshot(): void
    {
        $templateService = app(CompetitionTemplateService::class);
        $competitionService = app(CompetitionService::class);
        $league = League::create(['name' => '模板快照测试联盟', 'status' => 1]);
        $template = $templateService->create([
            'name' => '32 人小组模板',
            'organizer_type' => 'league',
            'type' => 'cup',
            'registration_limit' => 32,
            'is_fixed_participants' => true,
            'status' => true,
            'stages' => [
                ['type' => 'group', 'name' => '总赛区小组赛', 'rules' => ['group_count' => 8, 'qualify_count' => 16, 'scoring_mode' => 'single']],
                ['type' => 'knockout', 'name' => '总赛区淘汰赛', 'rules' => ['knockout_size' => 16, 'pairing_mode' => 'cross', 'scoring_mode' => 'single']],
            ],
        ]);

        $competition = $competitionService->create([
            'template_id' => $template->id,
            'organizer_type' => 'league',
            'league_id' => $league->id,
            'type' => 'cup',
            'name' => '模板生成的比赛',
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => now()->addDay()->toDateTimeString(),
        ]);

        $this->assertSame($template->id, $competition->template_id);
        $this->assertSame('32 人小组模板', $competition->template_name);
        $this->assertSame(Competition::FORMAT_GROUP_KNOCKOUT, $competition->format);
        $this->assertSame(8, $competition->group_count);
        $this->assertSame(16, $competition->knockout_size);
        $this->assertCount(2, $competition->stages);
        $this->assertSame(8, $competition->stages->firstWhere('type', 'group')->groups->count());

        $user = User::create([
            'username' => 'template_competition_admin',
            'nickname' => '模板比赛管理员',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
        $this->actingAs($user, 'api')->postJson('/api/v1/competition/add', [
            'template_id' => $template->id,
            'organizer_type' => 'league',
            'league_id' => $league->id,
            'type' => 'cup',
            'name' => '接口模板生成的比赛',
            'format' => 'group_knockout',
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => now()->addDay()->toDateTimeString(),
            'registration_limit' => 32,
        ])->assertCreated()
            ->assertJsonPath('data.group_count', 8)
            ->assertJsonPath('data.knockout_size', 16);

        $templateService->update([
            'id' => $template->id,
            'name' => '模板后来改名',
            'organizer_type' => 'league',
            'type' => 'cup',
            'registration_limit' => 16,
            'is_fixed_participants' => true,
            'status' => true,
            'stages' => [[
                'type' => 'knockout', 'name' => '新淘汰赛',
                'rules' => ['knockout_size' => 16, 'pairing_mode' => 'random', 'scoring_mode' => 'single'],
            ]],
        ]);

        $competition->refresh()->load('stages');
        $this->assertSame('32 人小组模板', $competition->template_name);
        $this->assertCount(2, $competition->stages);
        $this->assertSame('cross', $competition->stages->firstWhere('type', 'knockout')->rules['pairing_mode']);
    }
}
