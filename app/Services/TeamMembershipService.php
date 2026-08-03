<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\TeamApplication;
use App\Models\TeamGuest;
use App\Models\TeamStaff;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeamMembershipService
{
    /** 获取前台按联盟分组的公开战队目录和成员统计。 */
    public function directory()
    {
        return League::query()
            ->where('status', 1)
            ->with(['teams' => fn ($query) => $query->where('status', 1)->withCount(['memberships', 'guests'])->orderBy('name')])
            ->orderBy('name')
            ->get();
    }

    /** 获取当前用户主战队、嘉宾身份和待审批申请。 */
    public function context(User $user): array
    {
        return [
            'memberships' => $user->memberships()->get(['id', 'league_id', 'team_id'])->toArray(),
            'guest_team_ids' => $user->teamGuests()->pluck('team_id')->map(fn ($id) => (int) $id)->all(),
            'applications' => $user->teamApplications()
                ->where('status', TeamApplication::STATUS_PENDING)
                ->get(['id', 'team_id', 'type', 'status', 'created_at'])
                ->toArray(),
        ];
    }

    /** 提交加入、转队或嘉宾申请，并阻止重复待审批申请。 */
    public function apply(User $user, int $teamId, string $type): TeamApplication
    {
        $team = Team::query()->where('status', 1)->find($teamId);
        if (! $team) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        $membership = $user->memberships()->where('league_id', $team->league_id)->first();
        if ($type === TeamApplication::TYPE_JOIN && $membership?->team_id === $team->id) {
            throw new BusinessException('你已经是该战队成员', ApiCode::RESOURCE_EXISTS, 409);
        }
        if ($type === TeamApplication::TYPE_GUEST) {
            if ($membership?->team_id === $team->id) {
                throw new BusinessException('本队成员无需申请嘉宾', ApiCode::RESOURCE_EXISTS, 409);
            }
            if ($user->teamGuests()->where('team_id', $team->id)->exists()) {
                throw new BusinessException('你已经是该战队嘉宾', ApiCode::RESOURCE_EXISTS, 409);
            }
        }

        $pending = TeamApplication::query()
            ->where('team_id', $team->id)
            ->where('user_id', $user->id)
            ->where('status', TeamApplication::STATUS_PENDING)
            ->exists();
        if ($pending) {
            throw new BusinessException('该申请正在等待审批', ApiCode::RESOURCE_EXISTS, 409);
        }

        return TeamApplication::create([
            'team_id' => $team->id,
            'user_id' => $user->id,
            'type' => $type,
            'status' => TeamApplication::STATUS_PENDING,
        ]);
    }

    /** 取消当前用户自己尚未审批的申请。 */
    public function cancel(User $user, int $applicationId): void
    {
        $application = TeamApplication::query()
            ->where('user_id', $user->id)
            ->where('status', TeamApplication::STATUS_PENDING)
            ->find($applicationId);
        if (! $application) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }
        $application->update(['status' => TeamApplication::STATUS_CANCELLED]);
    }

    /** 获取当前用户具有队长或管理职务的战队摘要。 */
    public function managedTeams(User $user): array
    {
        return TeamStaff::query()
            ->where('user_id', $user->id)
            ->with(['team.league'])
            ->get()
            ->map(function (TeamStaff $staff) {
                $team = $staff->team;

                return [
                    'id' => $team->id,
                    'name' => $team->name,
                    'league_name' => $team->league->name,
                    'staff_role' => $staff->role,
                    'member_count' => $team->memberships()->count(),
                    'guest_count' => $team->guests()->count(),
                    'pending_count' => $team->applications()->where('status', TeamApplication::STATUS_PENDING)->count(),
                ];
            })->values()->all();
    }

    /** 获取单个可管理战队的成员、嘉宾、申请和管理名额。 */
    public function manageDetail(User $user, int $teamId): array
    {
        $staff = $this->staff($user, $teamId);
        $team = Team::query()->with('league')->findOrFail($teamId);
        $staffByUser = TeamStaff::query()->where('team_id', $teamId)->get()->keyBy('user_id');

        $members = LeagueMembership::query()
            ->where('team_id', $teamId)
            ->with('user')
            ->orderBy('id')
            ->get()
            ->map(function (LeagueMembership $membership) use ($staffByUser) {
                $memberStaff = $staffByUser->get($membership->user_id);

                return [
                    'id' => $membership->user->id,
                    'username' => $membership->user->username,
                    'nickname' => $membership->user->nickname,
                    'staff_role' => $memberStaff?->role,
                ];
            })->values()->all();

        $guests = TeamGuest::query()
            ->where('team_id', $teamId)
            ->with('user')
            ->get()
            ->map(fn (TeamGuest $guest) => [
                'id' => $guest->user->id,
                'username' => $guest->user->username,
                'nickname' => $guest->user->nickname,
            ])->values()->all();

        $applications = TeamApplication::query()
            ->where('team_id', $teamId)
            ->where('status', TeamApplication::STATUS_PENDING)
            ->with('user')
            ->orderBy('created_at')
            ->get()
            ->map(fn (TeamApplication $application) => [
                'id' => $application->id,
                'type' => $application->type,
                'user_id' => $application->user_id,
                'username' => $application->user->username,
                'nickname' => $application->user->nickname,
                'created_at' => $application->created_at?->toDateTimeString(),
            ])->values()->all();

        return [
            'team' => ['id' => $team->id, 'name' => $team->name, 'league_name' => $team->league->name],
            'current_role' => $staff->role,
            'manager_count' => $staffByUser->where('role', TeamStaff::ROLE_MANAGER)->count(),
            'manager_limit' => TeamStaff::MAX_MANAGERS,
            'members' => $members,
            'guests' => $guests,
            'applications' => $applications,
        ];
    }

    /** 审批加入或嘉宾申请；加入审批通过时同步同联盟主战队。 */
    public function review(User $reviewer, int $applicationId, string $decision, ?string $note): void
    {
        DB::transaction(function () use ($reviewer, $applicationId, $decision, $note) {
            $application = TeamApplication::query()
                ->where('status', TeamApplication::STATUS_PENDING)
                ->lockForUpdate()
                ->find($applicationId);
            if (! $application) {
                throw BusinessException::fromCode(ApiCode::NOT_FOUND);
            }
            $this->staff($reviewer, $application->team_id);

            if ($decision === TeamApplication::STATUS_APPROVED) {
                $team = Team::query()->findOrFail($application->team_id);
                if ($application->type === TeamApplication::TYPE_JOIN) {
                    $oldMembership = LeagueMembership::query()
                        ->where('user_id', $application->user_id)
                        ->where('league_id', $team->league_id)
                        ->first();
                    if ($oldMembership && $oldMembership->team_id !== $team->id) {
                        TeamStaff::query()->where('team_id', $oldMembership->team_id)->where('user_id', $application->user_id)->delete();
                    }
                    LeagueMembership::query()->updateOrCreate(
                        ['user_id' => $application->user_id, 'league_id' => $team->league_id],
                        ['team_id' => $team->id]
                    );
                    TeamGuest::query()->where('team_id', $team->id)->where('user_id', $application->user_id)->delete();
                } else {
                    $isMember = LeagueMembership::query()
                        ->where('user_id', $application->user_id)
                        ->where('team_id', $team->id)
                        ->exists();
                    if ($isMember) {
                        throw new BusinessException('申请人已经是本队成员', ApiCode::RESOURCE_EXISTS, 409);
                    }
                    TeamGuest::query()->firstOrCreate(['team_id' => $team->id, 'user_id' => $application->user_id]);
                }
            }

            $application->update([
                'status' => $decision,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
        });
    }

    /** 由队长任免本队管理，并强制最多五名管理。 */
    public function setManager(User $captain, int $teamId, int $userId, bool $isManager): void
    {
        DB::transaction(function () use ($captain, $teamId, $userId, $isManager) {
            $this->staff($captain, $teamId, true);
            $isMember = LeagueMembership::query()->where('team_id', $teamId)->where('user_id', $userId)->exists();
            if (! $isMember) {
                throw new BusinessException('只能将本队队员设置为管理', ApiCode::PARAM_ERROR, 422);
            }

            $targetStaff = TeamStaff::query()->where('team_id', $teamId)->where('user_id', $userId)->first();
            if ($targetStaff?->role === TeamStaff::ROLE_CAPTAIN) {
                throw new BusinessException('不能修改队长职务', ApiCode::PARAM_ERROR, 422);
            }

            if ($isManager) {
                $managerCount = TeamStaff::query()->where('team_id', $teamId)->where('role', TeamStaff::ROLE_MANAGER)->count();
                if (! $targetStaff && $managerCount >= TeamStaff::MAX_MANAGERS) {
                    throw new BusinessException('每个战队最多设置 5 名管理', ApiCode::RESOURCE_EXISTS, 409);
                }
                TeamStaff::query()->updateOrCreate(
                    ['team_id' => $teamId, 'user_id' => $userId],
                    ['role' => TeamStaff::ROLE_MANAGER]
                );
                User::query()->findOrFail($userId)->assignRole('战队管理');

                return;
            }

            TeamStaff::query()->where('team_id', $teamId)->where('user_id', $userId)->where('role', TeamStaff::ROLE_MANAGER)->delete();
            $target = User::query()->findOrFail($userId);
            if (! $target->teamStaff()->where('role', TeamStaff::ROLE_MANAGER)->exists()) {
                $target->removeRole('战队管理');
            }
        });
    }

    /** 校验当前用户是否拥有指定战队职务，可额外限定必须为队长。 */
    private function staff(User $user, int $teamId, bool $captainOnly = false): TeamStaff
    {
        $query = TeamStaff::query()->where('team_id', $teamId)->where('user_id', $user->id);
        if ($captainOnly) {
            $query->where('role', TeamStaff::ROLE_CAPTAIN);
        }
        $staff = $query->first();
        if (! $staff) {
            throw new BusinessException('你没有该战队的管理权限', ApiCode::FORBIDDEN, 403);
        }

        return $staff;
    }
}
