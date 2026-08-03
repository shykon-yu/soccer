<?php

namespace Tests\Feature;

use App\Exceptions\Api\BusinessException;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\TeamApplication;
use App\Models\TeamStaff;
use App\Models\User;
use App\Services\TeamMembershipService;
use App\Services\TeamService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TeamMembershipWorkflowTest extends TestCase
{
    use DatabaseTransactions;

    public function test_team_join_guest_review_and_manager_limit(): void
    {
        $service = app(TeamMembershipService::class);
        $teamService = app(TeamService::class);
        $league = League::create(['name' => '测试审批联盟', 'status' => 1]);
        $teamA = Team::create(['league_id' => $league->id, 'name' => '测试甲队', 'status' => 1]);
        $teamB = Team::create(['league_id' => $league->id, 'name' => '测试乙队', 'status' => 1]);
        $captainA = $this->user('captain_a');
        $captainB = $this->user('captain_b');
        $applicant = $this->user('applicant');

        LeagueMembership::create(['user_id' => $captainA->id, 'league_id' => $league->id, 'team_id' => $teamA->id]);
        LeagueMembership::create(['user_id' => $captainB->id, 'league_id' => $league->id, 'team_id' => $teamB->id]);
        LeagueMembership::create(['user_id' => $applicant->id, 'league_id' => $league->id, 'team_id' => $teamB->id]);
        $teamService->update([
            'id' => $teamA->id, 'league_id' => $league->id, 'name' => $teamA->name,
            'status' => 1, 'captain_user_id' => $captainA->id,
        ]);
        $teamService->update([
            'id' => $teamB->id, 'league_id' => $league->id, 'name' => $teamB->name,
            'status' => 1, 'captain_user_id' => $captainB->id,
        ]);

        $join = $service->apply($applicant, $teamA->id, TeamApplication::TYPE_JOIN);
        $service->review($captainA, $join->id, TeamApplication::STATUS_APPROVED, null);
        $this->assertDatabaseHas('league_memberships', [
            'user_id' => $applicant->id,
            'league_id' => $league->id,
            'team_id' => $teamA->id,
        ]);

        $guest = $service->apply($applicant, $teamB->id, TeamApplication::TYPE_GUEST);
        $service->review($captainB, $guest->id, TeamApplication::STATUS_APPROVED, null);
        $this->assertDatabaseHas('team_guests', ['team_id' => $teamB->id, 'user_id' => $applicant->id]);
        $this->assertSame($teamA->id, LeagueMembership::where('user_id', $applicant->id)->where('league_id', $league->id)->value('team_id'));

        for ($index = 1; $index <= TeamStaff::MAX_MANAGERS; $index++) {
            $manager = $this->user('manager_'.$index);
            LeagueMembership::create(['user_id' => $manager->id, 'league_id' => $league->id, 'team_id' => $teamA->id]);
            $service->setManager($captainA, $teamA->id, $manager->id, true);
        }
        $this->assertSame(TeamStaff::MAX_MANAGERS, TeamStaff::where('team_id', $teamA->id)->where('role', TeamStaff::ROLE_MANAGER)->count());

        $sixth = $this->user('manager_6');
        LeagueMembership::create(['user_id' => $sixth->id, 'league_id' => $league->id, 'team_id' => $teamA->id]);
        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('每个战队最多设置 5 名管理');
        $service->setManager($captainA, $teamA->id, $sixth->id, true);
    }

    public function test_system_team_editor_can_assign_captain_and_managers(): void
    {
        $teamService = app(TeamService::class);
        $league = League::create(['name' => '测试职务联盟', 'status' => 1]);
        $team = Team::create(['league_id' => $league->id, 'name' => '测试职务队', 'status' => 1]);
        $captain = $this->user('system_captain');
        $managers = collect(range(1, TeamStaff::MAX_MANAGERS))
            ->map(fn ($index) => $this->user('system_manager_'.$index));

        collect([$captain])->concat($managers)->each(fn (User $user) => LeagueMembership::create([
            'user_id' => $user->id,
            'league_id' => $league->id,
            'team_id' => $team->id,
        ]));

        $updated = $teamService->update([
            'id' => $team->id,
            'league_id' => $league->id,
            'name' => $team->name,
            'status' => 1,
            'captain_user_id' => $captain->id,
            'manager_user_ids' => $managers->pluck('id')->all(),
        ]);

        $this->assertSame($captain->id, $updated->captain->user_id);
        $this->assertEqualsCanonicalizing(
            $managers->pluck('id')->all(),
            $updated->staff->where('role', TeamStaff::ROLE_MANAGER)->pluck('user_id')->all()
        );
        $this->assertTrue($captain->fresh()->hasRole('战队队长'));
        $managers->each(fn (User $manager) => $this->assertTrue($manager->fresh()->hasRole('战队管理')));

        $remainingManager = $managers->first();
        $teamService->update([
            'id' => $team->id,
            'league_id' => $league->id,
            'name' => $team->name,
            'status' => 1,
            'captain_user_id' => $captain->id,
            'manager_user_ids' => [$remainingManager->id],
        ]);

        $this->assertSame(1, TeamStaff::where('team_id', $team->id)->where('role', TeamStaff::ROLE_MANAGER)->count());
        $this->assertTrue($remainingManager->fresh()->hasRole('战队管理'));
        $managers->skip(1)->each(fn (User $manager) => $this->assertFalse($manager->fresh()->hasRole('战队管理')));

        $teamService->update([
            'id' => $team->id,
            'league_id' => $league->id,
            'name' => $team->name,
            'status' => 1,
            'captain_user_id' => null,
            'manager_user_ids' => [$remainingManager->id],
        ]);
        $this->assertDatabaseMissing('team_staff', [
            'team_id' => $team->id,
            'role' => TeamStaff::ROLE_CAPTAIN,
        ]);
        $this->assertFalse($captain->fresh()->hasRole('战队队长'));
    }

    public function test_system_team_editor_rejects_non_member_manager(): void
    {
        $teamService = app(TeamService::class);
        $league = League::create(['name' => '测试职务校验联盟', 'status' => 1]);
        $team = Team::create(['league_id' => $league->id, 'name' => '测试职务校验队', 'status' => 1]);
        $outsider = $this->user('system_outsider');

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('只能将本队队员设置为管理');
        $teamService->update([
            'id' => $team->id,
            'league_id' => $league->id,
            'name' => $team->name,
            'status' => 1,
            'manager_user_ids' => [$outsider->id],
        ]);
    }

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
