<?php

namespace Tests\Feature;

use App\Exceptions\Api\BusinessException;
use App\Models\Competition;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\TeamGuest;
use App\Models\User;
use App\Services\CompetitionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class FrontCompetitionAccessTest extends TestCase
{
    use DatabaseTransactions;

    /** 验证前台赛事仅包含用户所在联盟、正式战队和嘉宾战队的数据。 */
    public function test_front_competitions_follow_user_membership_and_guest_scope(): void
    {
        $service = app(CompetitionService::class);
        $user = $this->user('front_competition_user');
        $league = League::create(['name' => '前台赛事联盟', 'status' => 1]);
        $otherLeague = League::create(['name' => '不可见赛事联盟', 'status' => 1]);
        $team = Team::create(['league_id' => $league->id, 'name' => '前台正式队', 'status' => 1]);
        $guestTeam = Team::create(['league_id' => $otherLeague->id, 'name' => '前台嘉宾队', 'status' => 1]);
        $hiddenTeam = Team::create(['league_id' => $otherLeague->id, 'name' => '前台不可见队', 'status' => 1]);

        LeagueMembership::create(['user_id' => $user->id, 'league_id' => $league->id, 'team_id' => $team->id]);
        TeamGuest::create(['user_id' => $user->id, 'team_id' => $guestTeam->id]);

        $leagueCup = $this->competition('所在联盟杯赛', Competition::ORGANIZER_LEAGUE, $league->id, null, Competition::TYPE_CUP);
        $teamCup = $this->competition('正式战队杯赛', Competition::ORGANIZER_TEAM, null, $team->id, Competition::TYPE_CUP);
        $guestCup = $this->competition('嘉宾战队杯赛', Competition::ORGANIZER_TEAM, null, $guestTeam->id, Competition::TYPE_CUP);
        $hiddenCup = $this->competition('不可见战队杯赛', Competition::ORGANIZER_TEAM, null, $hiddenTeam->id, Competition::TYPE_CUP);
        $completedCup = $this->competition(
            '已结束联盟杯赛',
            Competition::ORGANIZER_LEAGUE,
            $league->id,
            null,
            Competition::TYPE_CUP,
            Competition::STATUS_COMPLETED
        );

        $ongoing = $service->frontPaginate($user, ['type' => 'cup', 'scope' => 'ongoing', 'pageSize' => 10]);
        $this->assertEqualsCanonicalizing(
            [$leagueCup->id, $teamCup->id, $guestCup->id],
            collect($ongoing->items())->pluck('id')->all()
        );

        $completed = $service->frontPaginate($user, ['type' => 'cup', 'scope' => 'completed', 'pageSize' => 10]);
        $this->assertSame([$completedCup->id], collect($completed->items())->pluck('id')->all());
        $this->assertSame($guestCup->id, $service->frontDetail($user, $guestCup->id)->id);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/competition/front/list', [
                'type' => 'cup',
                'scope' => 'ongoing',
                'pageNum' => 1,
                'pageSize' => 10,
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonCount(3, 'data.list')
            ->assertJsonPath('data.total', 3);

        $this->expectException(BusinessException::class);
        $service->frontDetail($user, $hiddenCup->id);
    }

    /** 验证管理员在前台可以查看全部联盟和战队发布的比赛。 */
    public function test_administrator_can_view_all_front_competitions(): void
    {
        $service = app(CompetitionService::class);
        $administrator = $this->user('front_competition_administrator');
        Role::findOrCreate('管理员', 'api');
        $administrator->assignRole('管理员');

        $league = League::create(['name' => '管理员可见联盟', 'status' => 1]);
        $team = Team::create(['league_id' => $league->id, 'name' => '管理员可见战队', 'status' => 1]);
        $leagueCup = $this->competition('管理员查看联盟杯赛', Competition::ORGANIZER_LEAGUE, $league->id, null, Competition::TYPE_CUP);
        $teamCup = $this->competition('管理员查看战队杯赛', Competition::ORGANIZER_TEAM, null, $team->id, Competition::TYPE_CUP);

        $page = $service->frontPaginate($administrator, [
            'type' => Competition::TYPE_CUP,
            'scope' => 'ongoing',
            'pageSize' => 100,
        ]);
        $competitionIds = collect($page->items())->pluck('id');

        $this->assertTrue($competitionIds->contains($leagueCup->id));
        $this->assertTrue($competitionIds->contains($teamCup->id));
        $this->assertSame($leagueCup->id, $service->frontDetail($administrator, $leagueCup->id)->id);
        $this->assertSame($teamCup->id, $service->frontDetail($administrator, $teamCup->id)->id);
    }

    /** 创建测试赛事。 */
    private function competition(
        string $name,
        string $organizerType,
        ?int $leagueId,
        ?int $teamId,
        string $type,
        string $status = Competition::STATUS_IN_PROGRESS
    ): Competition {
        return Competition::create([
            'organizer_type' => $organizerType,
            'league_id' => $leagueId,
            'team_id' => $teamId,
            'type' => $type,
            'name' => $name,
            'format' => $type === Competition::TYPE_LEAGUE
                ? Competition::FORMAT_ROUND_ROBIN
                : Competition::FORMAT_GROUP_KNOCKOUT,
            'status' => $status,
            'starts_at' => now(),
            'ended_at' => $status === Competition::STATUS_COMPLETED ? now() : null,
        ]);
    }

    /** 创建启用状态的测试用户。 */
    private function user(string $username): User
    {
        return User::create([
            'username' => $username,
            'nickname' => $username,
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
    }
}
