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
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CompetitionRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    /** 验证个人赛报名、重复报名、截止时间和名额限制。 */
    public function test_user_can_register_personal_competition_with_registration_rules(): void
    {
        $service = app(CompetitionService::class);
        [$user, $league, $team] = $this->memberContext('personal_register');
        $competition = $this->competition($league, Competition::TYPE_CUP, 2);

        $entry = $service->registerUser($user, $competition->id);
        $this->assertSame(CompetitionEntry::TYPE_USER, $entry->entry_type);
        $this->assertSame($user->id, $entry->user_id);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/competition/front/list', [
                'type' => 'cup',
                'scope' => 'ongoing',
                'pageNum' => 1,
                'pageSize' => 20,
            ])
            ->assertOk()
            ->assertJsonFragment([
                'id' => $competition->id,
                'is_registered' => true,
                'registration_open' => true,
            ]);

        $this->assertBusinessException(
            fn () => $service->registerUser($user, $competition->id),
            '你已经报名该比赛'
        );

        $fullCompetition = $this->competition($league, Competition::TYPE_LEAGUE, 1);
        $fullCompetition->entries()->create([
            'entry_type' => CompetitionEntry::TYPE_USER,
            'user_id' => $this->user('other_personal_user')->id,
            'status' => CompetitionEntry::STATUS_REGISTERED,
        ]);
        $this->assertBusinessException(
            fn () => $service->registerUser($user, $fullCompetition->id),
            '比赛报名名额已满'
        );

        $expiredCompetition = $this->competition($league, Competition::TYPE_CUP, 8, now()->subMinute());
        $this->assertBusinessException(
            fn () => $service->registerUser($user, $expiredCompetition->id),
            '比赛报名已截止'
        );

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/competition/front/register-user', ['competition_id' => $this->competition($league)->id])
            ->assertCreated()
            ->assertJsonPath('code', 0);
    }

    /** 验证只有战队队长或管理可以代表本队报名联盟团体赛。 */
    public function test_only_team_staff_can_register_team_competition(): void
    {
        $service = app(CompetitionService::class);
        [$captain, $league, $team] = $this->memberContext('team_captain');
        TeamStaff::create(['team_id' => $team->id, 'user_id' => $captain->id, 'role' => TeamStaff::ROLE_CAPTAIN]);
        $competition = $this->competition($league, Competition::TYPE_TEAM, 8);

        $options = $service->teamRegistrationOptions($captain);
        $this->assertSame($competition->id, $options[0]['id']);
        $this->assertSame($team->id, $options[0]['eligible_teams'][0]['id']);

        $entry = $service->registerTeam($captain, $competition->id, $team->id);
        $this->assertSame(CompetitionEntry::TYPE_TEAM, $entry->entry_type);
        $this->assertSame($team->id, $entry->team_id);

        $member = $this->user('ordinary_team_member');
        LeagueMembership::create(['user_id' => $member->id, 'league_id' => $league->id, 'team_id' => $team->id]);
        $this->assertBusinessException(
            fn () => $service->registerTeam($member, $competition->id, $team->id),
            '只有战队队长或管理可以报名'
        );
    }

    /** 创建测试用户、联盟、战队和正式成员关系。 */
    private function memberContext(string $username): array
    {
        $user = $this->user($username);
        $league = League::create(['name' => '报名联盟_'.$username, 'status' => 1]);
        $team = Team::create(['league_id' => $league->id, 'name' => '报名战队_'.$username, 'status' => 1]);
        LeagueMembership::create(['user_id' => $user->id, 'league_id' => $league->id, 'team_id' => $team->id]);

        return [$user, $league, $team];
    }

    /** 创建报名阶段的测试赛事。 */
    private function competition(
        League $league,
        string $type = Competition::TYPE_CUP,
        int $limit = 8,
        $deadline = null
    ): Competition {
        return Competition::create([
            'organizer_type' => Competition::ORGANIZER_LEAGUE,
            'league_id' => $league->id,
            'type' => $type,
            'name' => '报名测试赛事_'.uniqid(),
            'format' => $type === Competition::TYPE_LEAGUE
                ? Competition::FORMAT_ROUND_ROBIN
                : Competition::FORMAT_GROUP_KNOCKOUT,
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => $deadline ?: now()->addDay(),
            'registration_limit' => $limit,
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

    /** 断言报名业务异常及其提示。 */
    private function assertBusinessException(callable $callback, string $message): void
    {
        try {
            $callback();
            $this->fail('预期抛出报名业务异常');
        } catch (BusinessException $exception) {
            $this->assertSame($message, $exception->getMessage());
        }
    }
}
