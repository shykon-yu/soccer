<?php

namespace Tests\Feature;

use App\Models\HonorEvent;
use App\Models\League;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class HonorEventManagementTest extends TestCase
{
    use DatabaseTransactions;

    public function test_admin_can_manage_manual_honor_and_public_list_returns_archive(): void
    {
        $user = User::create([
            'username' => 'honor_event_admin',
            'nickname' => '荣誉管理员',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
        $league = League::create(['name' => '历史荣誉测试联盟', 'status' => 1]);
        $team = Team::create(['league_id' => $league->id, 'name' => '历史荣誉测试战队', 'status' => 1]);
        $payload = [
            'organizer_type' => 'team',
            'team_id' => $team->id,
            'competition_type' => 'kof',
            'competition_name' => '平台上线前拳皇杯',
            'season' => '2020 第一届',
            'ended_at' => '2020-08-01 00:00:00',
            'awards' => [
                ['rank' => 1, 'owner_name' => '历史冠军'],
                ['rank' => 2, 'owner_name' => '历史亚军'],
                ['rank' => 3, 'owner_name' => '历史季军'],
                ['rank' => 4, 'owner_name' => '历史殿军'],
            ],
        ];

        $created = $this->actingAs($user, 'api')->postJson('/api/v1/honor-event/add', $payload)
            ->assertCreated()
            ->assertJsonPath('data.source', HonorEvent::SOURCE_MANUAL)
            ->assertJsonPath('data.competition_id', null)
            ->assertJsonCount(4, 'data.awards');
        $eventId = $created->json('data.id');

        $this->getJson('/api/v1/honors')
            ->assertOk()
            ->assertJsonFragment([
                'id' => $eventId,
                'organizer_type' => 'team',
                'competition_type' => 'kof',
                'competition_name' => '平台上线前拳皇杯',
                'owner_name' => '历史冠军',
            ]);

        $payload['id'] = $eventId;
        $payload['season'] = '2020 修订届次';
        $payload['awards'][0]['owner_name'] = '修订冠军';
        $this->actingAs($user, 'api')->postJson('/api/v1/honor-event/edit', $payload)
            ->assertOk()
            ->assertJsonPath('data.season', '2020 修订届次')
            ->assertJsonPath('data.awards.0.owner_name', '修订冠军');

        $this->actingAs($user, 'api')->postJson('/api/v1/honor-event/list', [
            'pageNum' => 1,
            'pageSize' => 10,
            'organizer_type' => 'team',
            'competition_type' => 'kof',
            'competition_name' => '平台上线前拳皇杯',
        ])->assertOk()->assertJsonPath('data.total', 1);

        $this->actingAs($user, 'api')->postJson('/api/v1/honor-event/delete', ['id' => $eventId])
            ->assertOk();
        $this->assertDatabaseMissing('honor_events', ['id' => $eventId]);
        $this->assertDatabaseMissing('competition_honors', ['honor_event_id' => $eventId]);
    }
}
