<?php

namespace Tests\Feature;

use App\Exceptions\Api\BusinessException;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\CompetitionTemplateService;
use App\Services\CupWorkflowService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamCupWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_fixed_32_player_team_cup_runs_from_registration_to_awards(): void
    {
        $competitionService = app(CompetitionService::class);
        $templateService = app(CompetitionTemplateService::class);
        $workflow = app(CupWorkflowService::class);
        $league = League::create(['name' => '32 人杯赛测试联盟', 'status' => 1]);
        $team = Team::create(['league_id' => $league->id, 'name' => '32 人杯赛测试战队', 'status' => 1]);
        $users = collect(range(1, 33))->map(function ($index) use ($league, $team) {
            $user = User::create([
                'username' => 'team_cup_player_'.$index,
                'nickname' => '杯赛选手'.$index,
                'password' => Hash::make('password123'),
                'status' => 1,
            ]);
            LeagueMembership::create([
                'user_id' => $user->id,
                'league_id' => $league->id,
                'team_id' => $team->id,
            ]);

            return $user;
        });
        $manager = $users->first();
        TeamStaff::create(['team_id' => $team->id, 'user_id' => $manager->id, 'role' => TeamStaff::ROLE_CAPTAIN]);

        $template = $templateService->create([
            'name' => '测试战队 32 人完整杯赛',
            'organizer_type' => 'team',
            'type' => 'cup',
            'registration_limit' => 32,
            'is_fixed_participants' => true,
            'status' => true,
            'stages' => [
                ['type' => 'group', 'name' => '总赛区小组赛', 'rules' => [
                    'group_count' => 8, 'qualify_count' => 16, 'scoring_mode' => 'single', 'team_assignment' => 'none',
                ]],
                ['type' => 'knockout', 'name' => '总赛区淘汰赛', 'rules' => [
                    'knockout_size' => 16, 'pairing_mode' => 'cross', 'scoring_mode' => 'single', 'avoid_same_source' => true,
                ]],
            ],
        ]);
        $competition = $competitionService->create([
            'template_id' => $template->id,
            'organizer_type' => 'team',
            'team_id' => $team->id,
            'type' => 'cup',
            'name' => '战队 32 人完整流程测试杯',
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => now()->addDay()->toDateTimeString(),
        ]);

        foreach ($users->take(32) as $user) {
            $entry = $competitionService->registerUser($user, $competition->id);
            $this->assertNotNull($entry->groups->first());
        }
        $competition->refresh();
        $this->assertSame(32, $competition->reserved_count);
        $groups = $competition->stages()->where('type', 'group')->firstOrFail()->groups()->orderBy('sort')->get();
        $this->assertCount(8, $groups);
        $this->assertTrue($groups->every(fn ($group) => $group->capacity === 4 && $group->reserved_count === 4));

        try {
            $competitionService->registerUser($users->last(), $competition->id);
            $this->fail('第 33 名选手不应报名成功');
        } catch (BusinessException $exception) {
            $this->assertSame('比赛报名名额已满', $exception->getMessage());
        }

        $this->actingAs($manager, 'api')->postJson('/api/v1/competition/start-group', ['id' => $competition->id])
            ->assertOk()
            ->assertJsonPath('data.status', Competition::STATUS_IN_PROGRESS);
        $detail = $competitionService->detail($competition->id);
        $this->assertSame(Competition::STATUS_IN_PROGRESS, $detail->status);
        $this->assertSame(48, $detail->matches()->whereNotNull('group_id')->count());

        $groupMatches = $detail->matches()->whereNotNull('group_id')->with(['homeEntry.user', 'awayEntry.user'])->get();
        $firstGroupMatch = $groupMatches->shift();
        $this->actingAs($firstGroupMatch->homeEntry->user, 'api')->postJson('/api/v1/competition/front/report-score', [
            'match_id' => $firstGroupMatch->id,
            'home_score' => 2,
            'away_score' => 0,
        ])->assertOk()->assertJsonPath('data.status', 'reported');
        $this->actingAs($firstGroupMatch->awayEntry->user, 'api')->postJson('/api/v1/competition/front/review-score', [
            'match_id' => $firstGroupMatch->id,
            'approved' => true,
        ])->assertOk()->assertJsonFragment(['id' => $firstGroupMatch->id, 'status' => 'completed']);
        foreach ($groupMatches as $match) {
            $workflow->reportScore($match->homeEntry->user, $match->id, ['home_score' => 2, 'away_score' => 0]);
            $workflow->reviewScore($match->awayEntry->user, $match->id, true, null);
        }

        $this->actingAs($manager, 'api')->postJson('/api/v1/competition/start-knockout', ['id' => $competition->id])
            ->assertOk()
            ->assertJsonPath('data.status', Competition::STATUS_KNOCKOUT);
        $detail = $competitionService->detail($competition->id);
        $this->assertSame(Competition::STATUS_KNOCKOUT, $detail->status);
        $knockoutStage = $detail->stages->firstWhere('type', 'knockout');
        $this->assertSame(15, $knockoutStage->matches()->count());
        $this->assertCount(8, $knockoutStage->matches()->where('round_number', 1)->get());

        for ($round = 1; $round <= 4; $round++) {
            $matches = $knockoutStage->matches()->where('round_number', $round)->orderBy('sequence')->get();
            foreach ($matches as $index => $match) {
                $match->load(['homeEntry.user', 'awayEntry.user']);
                $this->assertNotNull($match->homeEntry);
                $this->assertNotNull($match->awayEntry);
                if ($round === 1 && $index === 0) {
                    $workflow->reportScore($match->homeEntry->user, $match->id, [
                        'home_score' => 1,
                        'away_score' => 1,
                        'winner_entry_id' => $match->away_entry_id,
                        'tie_break_type' => 'away_goals',
                    ]);
                } else {
                    $workflow->reportScore($match->homeEntry->user, $match->id, ['home_score' => 1, 'away_score' => 0]);
                }
                $workflow->reviewScore($match->awayEntry->user, $match->id, true, null);
            }
        }

        $competition->refresh();
        $this->assertSame(Competition::STATUS_AWAITING_AWARDS, $competition->status);
        $final = $knockoutStage->matches()->where('round_number', 4)->firstOrFail()->fresh(['homeEntry', 'awayEntry', 'winnerEntry']);
        $semiFinals = $knockoutStage->matches()->where('round_number', 3)->orderBy('sequence')->get();
        $champion = $final->winnerEntry;
        $runnerUp = $final->home_entry_id === $champion->id ? $final->awayEntry : $final->homeEntry;
        $semiLosers = $semiFinals->map(fn ($match) => $match->home_entry_id === $match->winner_entry_id
            ? $match->awayEntry
            : $match->homeEntry);
        $honorEntries = collect([$champion, $runnerUp, ...$semiLosers]);
        $honors = $honorEntries->values()->map(fn (CompetitionEntry $entry, $index) => [
            'rank' => $index + 1,
            'entry_id' => $entry->id,
            'owner_name' => $entry->displayName(),
        ])->all();

        $this->actingAs($manager, 'api')->postJson('/api/v1/competition/finish', [
            'id' => $competition->id,
            'honors' => $honors,
        ])->assertOk()->assertJsonPath('data.status', Competition::STATUS_COMPLETED);
        $completed = $competitionService->detail($competition->id);
        $this->assertSame(Competition::STATUS_COMPLETED, $completed->status);
        $this->assertCount(4, $completed->honors);
        $this->assertSame($champion->id, $completed->honors->firstWhere('rank', 1)->entry_id);
        $this->assertDatabaseHas('honor_events', [
            'competition_id' => $competition->id,
            'source' => 'competition',
            'organizer_type' => 'team',
            'competition_type' => 'cup',
        ]);
        $this->assertTrue($completed->honors->every(fn ($honor) => $honor->honor_event_id !== null));
    }
}
