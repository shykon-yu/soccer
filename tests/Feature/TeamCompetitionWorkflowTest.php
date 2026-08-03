<?php

namespace Tests\Feature;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTeamFixture;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\CompetitionTemplateService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class TeamCompetitionWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_team_competition_runs_double_round_robin_knockout_and_awards(): void
    {
        $admin = User::create([
            'username' => 'team_competition_admin',
            'nickname' => '团体赛管理员',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
        Role::findOrCreate('管理员', 'api');
        $admin->assignRole('管理员');
        $league = League::create(['name' => '团体赛流程测试联盟', 'status' => 1]);
        $teams = collect(range(1, 4))->map(function ($index) use ($league) {
            $team = Team::create(['league_id' => $league->id, 'name' => '团体测试战队'.$index, 'status' => 1]);
            $players = collect(range(1, 2))->map(function ($playerIndex) use ($index, $league, $team) {
                $user = User::create([
                    'username' => "team_fixture_{$index}_{$playerIndex}",
                    'nickname' => "团体{$index}队员{$playerIndex}",
                    'password' => Hash::make('password123'),
                    'status' => 1,
                ]);
                LeagueMembership::create(['user_id' => $user->id, 'league_id' => $league->id, 'team_id' => $team->id]);

                return $user;
            });
            TeamStaff::create(['team_id' => $team->id, 'user_id' => $players->first()->id, 'role' => TeamStaff::ROLE_CAPTAIN]);
            $team->setRelation('testPlayers', $players);

            return $team;
        });

        $template = app(CompetitionTemplateService::class)->create([
            'name' => '四队双循环淘汰测试模板',
            'organizer_type' => 'league',
            'type' => 'team',
            'registration_limit' => null,
            'is_fixed_participants' => false,
            'status' => true,
            'stages' => [
                ['type' => 'group', 'name' => '团体循环赛', 'rules' => [
                    'group_count' => 1, 'qualify_count' => 4, 'scoring_mode' => 'home_away_points', 'team_assignment' => 'none',
                ]],
                ['type' => 'knockout', 'name' => '团体淘汰赛', 'rules' => [
                    'knockout_size' => 4, 'pairing_mode' => 'ranking', 'scoring_mode' => 'single', 'avoid_same_source' => false,
                ]],
            ],
        ]);
        $competition = app(CompetitionService::class)->create([
            'template_id' => $template->id,
            'organizer_type' => 'league',
            'league_id' => $league->id,
            'type' => 'team',
            'name' => '四队团体赛完整流程',
            'season' => '2026 测试赛季',
            'status' => Competition::STATUS_REGISTRATION,
        ]);
        foreach ($teams as $team) {
            app(CompetitionService::class)->registerTeam($team->testPlayers->first(), $competition->id, $team->id);
        }

        $this->actingAs($admin, 'api')->postJson('/api/v1/competition/team/start-league', [
            'id' => $competition->id,
            'start_date' => '2026-08-01',
            'end_date' => '2026-09-30',
            'include_weekends' => false,
        ])->assertOk()->assertJsonPath('data.status', Competition::STATUS_IN_PROGRESS);

        $competition = app(CompetitionService::class)->detail($competition->id);
        $leagueStage = $competition->stages->firstWhere('type', 'group');
        $fixtures = $leagueStage->teamFixtures;
        $this->assertCount(12, $fixtures);
        $this->assertCount(6, $fixtures->where('leg_number', 1));
        $this->assertCount(6, $fixtures->where('leg_number', 2));
        $this->assertTrue($fixtures->where('leg_number', 1)->every(fn ($fixture) => $fixture->scheduled_at->lt('2026-09-01')));
        $this->assertTrue($fixtures->where('leg_number', 2)->every(fn ($fixture) => $fixture->scheduled_at->gte('2026-09-01')));
        $this->assertTrue($fixtures->groupBy(fn ($fixture) => $fixture->scheduled_at->toDateString())->every(function ($day) {
            $entryIds = $day->flatMap(fn ($fixture) => [$fixture->home_entry_id, $fixture->away_entry_id]);

            return $entryIds->unique()->count() === $entryIds->count();
        }));
        $firstLegHomeCounts = $fixtures->where('leg_number', 1)->countBy('home_entry_id');
        $this->assertLessThanOrEqual(1, $firstLegHomeCounts->max() - $firstLegHomeCounts->min());
        $this->assertGreaterThan(20, $fixtures->where('leg_number', 1)->max('scheduled_at')->diffInDays(
            $fixtures->where('leg_number', 1)->min('scheduled_at')
        ));
        $this->getJson('/api/v1/competition/team-calendar?league_id='.$league->id.'&year=2026&month=8')
            ->assertOk()
            ->assertJsonCount(6, 'data.list')
            ->assertJsonPath('data.list.0.competition_name', '四队团体赛完整流程');

        foreach ($fixtures as $fixture) {
            $fixture->load(['homeEntry.team.memberships.user', 'awayEntry.team.memberships.user']);
            $this->actingAs($admin, 'api')->postJson('/api/v1/competition/team/report-fixture', [
                'fixture_id' => $fixture->id,
                'player_matches' => [[
                    'home_user_id' => $fixture->homeEntry->team->memberships->first()->user_id,
                    'away_user_id' => $fixture->awayEntry->team->memberships->first()->user_id,
                    'home_score' => 2,
                    'away_score' => 1,
                ]],
            ])->assertOk();
        }

        $this->getJson('/api/v1/competition/team-overview?league_id='.$league->id)
            ->assertOk()
            ->assertJsonPath('data.current_competition.id', $competition->id)
            ->assertJsonPath('data.current_competition.name', '四队团体赛完整流程')
            ->assertJsonCount(4, 'data.team_standings')
            ->assertJsonPath('data.team_standings.0.played', 6)
            ->assertJsonCount(4, 'data.player_standings')
            ->assertJsonPath('data.player_standings.0.played', 6)
            ->assertJsonPath('data.player_standings.0.points', 9);
        $this->actingAs($admin, 'api')->postJson('/api/v1/competition/detail', ['id' => $competition->id])
            ->assertOk()
            ->assertJsonPath('data.stages.0.team_standings.0.played', 6)
            ->assertJsonPath('data.stages.0.team_standings.0.points', 9);

        $this->actingAs($admin, 'api')->postJson('/api/v1/competition/team/start-knockout', [
            'id' => $competition->id,
            'knockout_size' => 4,
            'pairing_mode' => 'cross',
        ])->assertOk()->assertJsonPath('data.status', Competition::STATUS_KNOCKOUT);
        $competition = app(CompetitionService::class)->detail($competition->id);
        $knockoutStage = $competition->stages->firstWhere('type', 'knockout');
        $this->assertCount(3, $knockoutStage->teamFixtures);

        foreach ([1, 2] as $round) {
            $roundFixtures = $knockoutStage->teamFixtures()->where('round_number', $round)->orderBy('sequence')->get();
            foreach ($roundFixtures as $fixture) {
                $fixture->load(['homeEntry.team.memberships', 'awayEntry.team.memberships']);
                $this->assertNotNull($fixture->homeEntry);
                $this->assertNotNull($fixture->awayEntry);
                $this->actingAs($admin, 'api')->postJson('/api/v1/competition/team/report-fixture', [
                    'fixture_id' => $fixture->id,
                    'player_matches' => [[
                        'home_user_id' => $fixture->homeEntry->team->memberships->first()->user_id,
                        'away_user_id' => $fixture->awayEntry->team->memberships->first()->user_id,
                        'home_score' => 1,
                        'away_score' => 0,
                    ]],
                ])->assertOk();
            }
        }

        $competition->refresh();
        $this->assertSame(Competition::STATUS_AWAITING_AWARDS, $competition->status);
        $final = CompetitionTeamFixture::query()->where('stage_id', $knockoutStage->id)->where('round_number', 2)->firstOrFail();
        $entries = $competition->entries()->where('entry_type', CompetitionEntry::TYPE_TEAM)->get();
        $honors = $entries->take(4)->values()->map(fn ($entry, $index) => [
            'rank' => $index + 1,
            'entry_id' => $entry->id,
            'owner_name' => $entry->displayName(),
        ])->all();
        $honors[0]['entry_id'] = $final->winner_entry_id;
        $honors[0]['owner_name'] = $final->winnerEntry->displayName();

        $this->actingAs($admin, 'api')->postJson('/api/v1/competition/finish', [
            'id' => $competition->id,
            'honors' => $honors,
        ])->assertOk()->assertJsonPath('data.status', Competition::STATUS_COMPLETED);
        $this->assertDatabaseHas('honor_events', [
            'competition_id' => $competition->id,
            'competition_type' => Competition::TYPE_TEAM,
        ]);
        $overviewResponse = $this->getJson('/api/v1/competition/team-overview?league_id='.$league->id)
            ->assertOk()
            ->assertJsonPath('data.current_competition', null)
            ->assertJsonPath('data.history.0.competition_id', $competition->id)
            ->assertJsonPath('data.history.0.name', '四队团体赛完整流程')
            ->assertJsonCount(4, 'data.history.0.honors')
            ->assertJsonMissingPath('data.history.0.standings');
        $eventId = (int) $overviewResponse->json('data.history.0.id');
        $this->getJson('/api/v1/competition/team-history-detail?id='.$eventId)
            ->assertOk()
            ->assertJsonPath('data.competition_id', $competition->id)
            ->assertJsonCount(4, 'data.honors')
            ->assertJsonCount(4, 'data.standings');
    }
}
