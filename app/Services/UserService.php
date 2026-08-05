<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

/**
 * 用户管理业务层
 *
 * 负责用户的 CRUD、状态变更、密码重置、角色分配。
 * 用户在各联盟的唯一主战队关系通过 memberships 维护（一个用户在每个联盟只能有一个主战队）。
 */
class UserService
{
    /** 按账号、昵称、联盟、战队和状态查询用户列表。 */
    public function list(array $filters)
    {
        $pageSize = (int) ($filters['pageSize'] ?? 10);
        $page = (int) ($filters['pageNum'] ?? $filters['page'] ?? 1);

        return User::query()
            ->with(['roles', 'memberships.league', 'memberships.team'])
            ->when(! empty($filters['username']), function ($query) use ($filters) {
                $query->where('username', 'like', '%'.$filters['username'].'%');
            })
            ->when(! empty($filters['nickname']), function ($query) use ($filters) {
                $query->where('nickname', 'like', '%'.$filters['nickname'].'%');
            })
            ->when(! empty($filters['league_id']), function ($query) use ($filters) {
                $query->whereHas('memberships', function ($membership) use ($filters) {
                    $membership->where('league_id', $filters['league_id']);
                });
            })
            ->when(! empty($filters['team_id']), function ($query) use ($filters) {
                $query->whereHas('memberships', function ($membership) use ($filters) {
                    $membership->where('team_id', $filters['team_id']);
                });
            })
            // 不要status=0时候
            ->when(isset($filters['status']) && $filters['status'] !== '', function ($query) use ($filters) {
                $query->where('status', $filters['status']);
            })
            ->orderByDesc('id')
            ->paginate($pageSize, ['*'], 'page', $page);
    }

    /** 创建用户并同步角色及各联盟主战队关系。 */
    public function create(array $data): User
    {
        return DB::transaction(function () use ($data) {
            $user = User::create([
                'username' => $data['username'],
                'nickname' => $data['nickname'] ?? $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'status' => $data['status'] ?? 1,
                'password' => Hash::make($data['password'] ?? 'shikuang8'),
            ]);

            $this->syncRoles($user, $data['role_ids'] ?? []);
            $this->syncMemberships($user, $data['memberships'] ?? []);

            return $user->fresh(['roles', 'memberships.league', 'memberships.team']);
        });
    }

    /** 更新用户资料，并按提交结果重建角色和联盟关系。 */
    public function update(array $data): User
    {
        $user = $this->findUser((int) $data['id']);

        return DB::transaction(function () use ($data, $user) {
            $payload = [
                'username' => $data['username'],
                'nickname' => $data['nickname'] ?? $data['username'],
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'avatar' => $data['avatar'] ?? null,
                'status' => $data['status'] ?? 1,
            ];

            if (! empty($data['password'])) {
                $payload['password'] = Hash::make($data['password']);
            }

            $user->update($payload);
            if (array_key_exists('role_ids', $data)) {
                $this->syncRoles($user, $data['role_ids']);
            }
            $this->syncMemberships($user, $data['memberships'] ?? []);

            return $user->fresh(['roles', 'memberships.league', 'memberships.team']);
        });
    }

    /** 批量软删除用户，管理员账号不允许删除。 */
    public function delete(array|int|string $ids): void
    {
        $ids = is_array($ids) ? $ids : [$ids];
        $ids = collect($ids)->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        if ($ids === []) {
            throw BusinessException::fromCode(ApiCode::PARAM_ERROR);
        }

        $existingCount = User::query()->whereIn('id', $ids)->count();
        if ($existingCount !== count($ids)) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        User::query()->whereIn('id', $ids)->delete();
    }

    /** 启用或禁用指定用户账号。 */
    public function changeStatus(int $id, int $status): User
    {
        $user = $this->findUser($id);
        $user->update(['status' => $status]);

        return $user->fresh('roles');
    }

    /** 重置用户密码；未指定时使用系统默认密码。 */
    public function resetPassword(int $id, ?string $password = null): void
    {
        $user = $this->findUser($id);
        $user->update(['password' => Hash::make($password ?: 'admin123456')]);
    }

    /** 为用户重新分配后台角色。 */
    public function assignRoles(int $id, array $roleIds): User
    {
        $user = $this->findUser($id);
        $this->syncRoles($user, $roleIds);

        return $user->fresh('roles');
    }

    /** 开通、续期或取消用户的对战平台使用权限。 */
    public function setPlatformAccess(int $id, int $months): User
    {
        return DB::transaction(function () use ($id, $months) {
            $user = User::query()->lockForUpdate()->find($id);
            if (! $user) {
                throw BusinessException::fromCode(ApiCode::NOT_FOUND);
            }

            if ($months === 0) {
                $user->platform_access_expires_at = null;
            } else {
                $startsAt = $user->platform_access_expires_at?->isFuture()
                    ? $user->platform_access_expires_at->copy()
                    : now();
                $user->platform_access_expires_at = $startsAt->addMonthsNoOverflow($months);
            }
            $user->save();

            return $user->fresh(['roles', 'memberships.league', 'memberships.team']);
        });
    }

    /** 返回用户状态下拉选项。 */
    public function statusOptions(): array
    {
        return [
            ['userLabel' => '启用', 'userValue' => 1],
            ['userLabel' => '禁用', 'userValue' => 0],
        ];
    }

    /** 获取可分配的全部后台角色。 */
    public function roles(): Collection
    {
        return Role::query()
            ->where('guard_name', 'api')
            ->orderBy('id')
            ->get(['id', 'name']);
    }

    /** 查找用户，不存在时抛出统一业务异常。 */
    private function findUser(int $id): User
    {
        $user = User::query()->with(['roles', 'memberships.league', 'memberships.team'])->find($id);
        if (! $user) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $user;
    }

    /** 根据角色主键同步用户角色。 */
    private function syncRoles(User $user, array $roleIds): void
    {
        $roles = Role::query()
            ->where('guard_name', 'api')
            ->whereIn('id', $roleIds)
            ->get();

        $user->syncRoles($roles);
    }

    /** 同步用户在各联盟的唯一主战队关系。 */
    private function syncMemberships(User $user, array $memberships): void
    {
        $leagueIds = collect($memberships)->pluck('league_id')->map(function ($id) {
            return (int) $id;
        })->unique()->values()->all();

        $membershipsToDelete = $user->memberships();
        if ($leagueIds !== []) {
            $membershipsToDelete->whereNotIn('league_id', $leagueIds);
        }
        $membershipsToDelete->delete();

        foreach ($memberships as $membership) {
            $user->memberships()->updateOrCreate(
                ['league_id' => (int) $membership['league_id']],
                ['team_id' => (int) $membership['team_id']]
            );
        }
    }
}
