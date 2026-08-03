<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * 战队管理业务层
 *
 * 负责战队的 CRUD 及队长/管理职务的同步。
 * 队长设置时自动赋予「战队队长」角色，移除时自动收回。
 * 管理设置时自动赋予「战队管理」角色，移除时自动收回。
 * 每队最多 1 名队长 + 5 名管理。
 */
class TeamService
{
    /**
     * 按联盟、名称和状态分页查询战队资料。
     *
     * 步骤：
     * 1. 预加载所属联盟、队长信息、管理列表
     * 2. 预加载成员数量统计
     * 3. 支持按联盟 ID、战队名称、启用状态筛选
     * 4. 按联盟 ID + 名称排序分页返回
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Team::query()
            // 步骤1：预加载关联数据
            ->with(['league', 'captain.user', 'staff.user'])
            // 步骤2：预加载成员统计
            ->withCount('memberships')
            // 步骤3：多条件筛选
            ->when(! empty($filters['league_id']), fn ($query) => $query->where('league_id', $filters['league_id']))
            ->when(! empty($filters['name']), fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when(array_key_exists('status', $filters), fn ($query) => $query->where('status', $filters['status']))
            // 步骤4：排序分页
            ->orderBy('league_id')->orderBy('name')
            ->paginate((int) ($filters['pageSize'] ?? 10), ['*'], 'page', (int) ($filters['pageNum'] ?? 1));
    }

    /** 创建战队基础资料。 */
    public function create(array $data): Team
    {
        return Team::create(['league_id' => $data['league_id'], 'name' => trim($data['name']), 'status' => $data['status'] ?? 1]);
    }

    /**
     * 更新战队资料，并按提交内容同步队长、管理职务和全局角色。
     *
     * 整个操作在数据库事务中执行，保证战队资料、职务、角色的一致性。
     *
     * 步骤：
     * 1. 开启事务，查找目标战队
     * 2. 更新战队基础字段（联盟、名称、状态）
     * 3. 若提交了 captain_user_id 字段，同步队长职务
     * 4. 若提交了 manager_user_ids 字段，同步管理职务
     * 5. 刷新模型并返回最新数据
     */
    public function update(array $data): Team
    {
        return DB::transaction(function () use ($data) {
            // 步骤1：查找目标战队
            $team = $this->find((int) $data['id']);

            // 步骤2：更新基础字段
            $team->update(['league_id' => $data['league_id'], 'name' => trim($data['name']), 'status' => $data['status'] ?? 1]);

            // 步骤3：同步队长（仅当字段存在时处理）
            if (array_key_exists('captain_user_id', $data)) {
                $this->syncCaptain($team, ! empty($data['captain_user_id']) ? (int) $data['captain_user_id'] : null);
            }

            // 步骤4：同步管理（仅当字段存在时处理）
            if (array_key_exists('manager_user_ids', $data)) {
                $this->syncManagers(
                    $team,
                    array_map('intval', $data['manager_user_ids'] ?? []),
                    $team->captain()->value('user_id') // 传入当前队长 ID 用于互斥校验
                );
            }

            // 步骤5：刷新并返回最新数据
            return $team->fresh(['league', 'captain.user', 'staff.user'])->loadCount('memberships');
        });
    }

    /**
     * 获取本队正式成员及当前职务，供系统管理员选择队长和管理。
     *
     * 步骤：
     * 1. 查找战队并预加载现有职务记录
     * 2. 将职务记录按 user_id 建立索引映射
     * 3. 查询正式成员并附带用户信息
     * 4. 组装返回数据：成员 id/username/nickname + 当前职务角色
     */
    public function memberOptions(int $teamId): array
    {
        // 步骤1：查找战队，预加载职务记录
        $team = $this->find($teamId)->load('staff');

        // 步骤2：按 user_id 建立职务索引
        $staffByUser = $team->staff->keyBy('user_id');

        // 步骤3-4：查询正式成员并组装数据
        return $team->memberships()
            ->with('user')
            ->get()
            ->map(fn ($membership) => [
                'id' => $membership->user->id,
                'username' => $membership->user->username,
                'nickname' => $membership->user->nickname,
                'staff_role' => $staffByUser->get($membership->user->id)?->role, // 当前职务角色
            ])->values()->all();
    }

    /**
     * 删除空战队；存在成员、嘉宾、职务、申请或赛事时拒绝删除。
     *
     * 步骤：
     * 1. 查找目标战队
     * 2. 逐一检查六类关联数据是否存在，任一存在则拒绝
     * 3. 执行删除
     */
    public function delete(int $id): void
    {
        // 步骤1：查找目标战队
        $team = $this->find($id);

        // 步骤2：检查关联数据，任一存在则拒绝删除
        if (
            $team->memberships()->exists()
            || $team->competitions()->exists()
            || $team->guests()->exists()
            || $team->staff()->exists()
            || $team->applications()->exists()
            || $team->honorEvents()->exists()
        ) {
            throw new BusinessException('战队已有成员、嘉宾、职务、申请、赛事或荣誉，不能删除', ApiCode::RESOURCE_EXISTS, 409);
        }

        // 步骤3：执行删除
        $team->delete();
    }

    /** 查找战队，不存在时转换为统一业务异常。 */
    private function find(int $id): Team
    {
        $team = Team::query()->find($id);
        if (! $team) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $team;
    }

    /**
     * 同步本队唯一队长，清空或换任时一并清理旧队长的冗余角色。
     *
     * 步骤：
     * 1. 若设置了新队长，校验其必须为本队正式成员
     * 2. 查找并删除旧队长职务记录，收集旧队长用户 ID
     * 3. 若设置了新队长：创建/更新队长职务，赋予「战队队长」全局角色
     *    - 若新队长之前是管理，检查其是否在其他队仍有管理职务，若无则移除「战队管理」角色
     * 4. 清理旧队长的全局角色：若旧队长在其他队不再担任队长，移除「战队队长」角色
     */
    private function syncCaptain(Team $team, ?int $userId): void
    {
        // 步骤1：校验新队长必须是正式成员
        if ($userId) {
            $isMember = $team->memberships()->where('user_id', $userId)->exists();
            if (! $isMember) {
                throw new BusinessException('队长必须是该战队现有成员', ApiCode::PARAM_ERROR, 422);
            }
        }

        // 步骤2：查找并删除旧队长职务，收集旧队长 ID（排除与新队长相同的）
        $oldCaptainIds = $team->staff()->where('role', TeamStaff::ROLE_CAPTAIN)
            ->when($userId, fn ($query) => $query->where('user_id', '<>', $userId))
            ->pluck('user_id');
        $team->staff()->where('role', TeamStaff::ROLE_CAPTAIN)
            ->when($userId, fn ($query) => $query->where('user_id', '<>', $userId))
            ->delete();

        // 步骤3：设置新队长
        if ($userId) {
            // 检查新队长是否原为管理（后续可能需要移除管理角色）
            $wasManager = $team->staff()->where('user_id', $userId)->where('role', TeamStaff::ROLE_MANAGER)->exists();

            // 创建或更新队长职务
            TeamStaff::query()->updateOrCreate(
                ['team_id' => $team->id, 'user_id' => $userId],
                ['role' => TeamStaff::ROLE_CAPTAIN]
            );

            // 赋予「战队队长」全局角色
            User::query()->findOrFail($userId)->assignRole('战队队长');

            // 若新队长原为管理，且在其他队也没有管理职务，则移除「战队管理」角色
            if ($wasManager) {
                $newCaptain = User::query()->findOrFail($userId);
                if (! $newCaptain->teamStaff()->where('role', TeamStaff::ROLE_MANAGER)->exists()) {
                    $newCaptain->removeRole('战队管理');
                }
            }
        }

        // 步骤4：清理旧队长的「战队队长」角色（若不再担任任何队的队长）
        User::query()->whereIn('id', $oldCaptainIds)->get()->each(function (User $user) {
            if (! $user->teamStaff()->where('role', TeamStaff::ROLE_CAPTAIN)->exists()) {
                $user->removeRole('战队队长');
            }
        });
    }

    /**
     * 批量同步本队管理，校验正式成员、队长互斥和五人名额限制。
     *
     * 步骤：
     * 1. 去重后校验数量上限（最多 5 名管理）
     * 2. 校验队长不能同时担任管理（与队长互斥）
     * 3. 校验所有管理候选人必须是本队正式成员
     * 4. 对比新旧管理列表，删除被移除的管理职务
     * 5. 逐一遍历新管理列表，创建/更新职务并赋予「战队管理」全局角色
     * 6. 清理被移除者的「战队管理」角色（若不在任何队担任管理）
     */
    private function syncManagers(Team $team, array $managerUserIds, ?int $captainUserId): void
    {
        // 步骤1：去重 + 上限校验
        $managerUserIds = array_values(array_unique($managerUserIds));
        if (count($managerUserIds) > TeamStaff::MAX_MANAGERS) {
            throw new BusinessException('每个战队最多设置 5 名管理', ApiCode::RESOURCE_EXISTS, 409);
        }

        // 步骤2：队长互斥校验
        if ($captainUserId && in_array($captainUserId, $managerUserIds, true)) {
            throw new BusinessException('队长不能同时担任战队管理', ApiCode::PARAM_ERROR, 422);
        }

        // 步骤3：正式成员校验
        $memberCount = $team->memberships()->whereIn('user_id', $managerUserIds)->distinct()->count('user_id');
        if ($memberCount !== count($managerUserIds)) {
            throw new BusinessException('只能将本队队员设置为管理', ApiCode::PARAM_ERROR, 422);
        }

        // 步骤4：对比新旧列表，删除被移除的管理职务
        $oldManagerIds = $team->staff()->where('role', TeamStaff::ROLE_MANAGER)->pluck('user_id');
        $removedManagerIds = $oldManagerIds->diff($managerUserIds);
        $team->staff()->where('role', TeamStaff::ROLE_MANAGER)->whereNotIn('user_id', $managerUserIds)->delete();

        // 步骤5：逐一遍历新管理列表，创建职务并赋予全局角色
        foreach ($managerUserIds as $userId) {
            TeamStaff::query()->updateOrCreate(
                ['team_id' => $team->id, 'user_id' => $userId],
                ['role' => TeamStaff::ROLE_MANAGER]
            );
            User::query()->findOrFail($userId)->assignRole('战队管理');
        }

        // 步骤6：清理被移除者的「战队管理」角色（若不再担任任何队的管理）
        User::query()->whereIn('id', $removedManagerIds)->get()->each(function (User $user) {
            if (! $user->teamStaff()->where('role', TeamStaff::ROLE_MANAGER)->exists()) {
                $user->removeRole('战队管理');
            }
        });
    }
}
