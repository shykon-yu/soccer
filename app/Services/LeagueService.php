<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\League;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * 联盟管理业务层
 *
 * 负责联盟的 CRUD，删除前检查是否有关联数据。
 * options() 方法返回启用联盟及其启用战队，供全站下拉选择复用。
 */
class LeagueService
{
    /**
     * 按名称和状态分页查询联盟基础资料。
     *
     * 步骤：
     * 1. 预加载战队数量和成员数量统计
     * 2. 支持按联盟名称模糊搜索
     * 3. 支持按启用状态筛选
     * 4. 按名称排序分页返回
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return League::query()
            // 步骤1：预加载关联统计
            ->withCount(['teams', 'memberships'])
            // 步骤2：名称模糊搜索
            ->when(! empty($filters['name']), fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            // 步骤3：状态筛选
            ->when(array_key_exists('status', $filters), fn ($query) => $query->where('status', $filters['status']))
            // 步骤4：排序分页
            ->orderBy('name')
            ->paginate((int) ($filters['pageSize'] ?? 10), ['*'], 'page', (int) ($filters['pageNum'] ?? 1));
    }

    /**
     * 获取启用联盟及其启用战队，供下拉选择复用。
     *
     * 步骤：
     * 1. 筛选启用的联盟（status = 1）
     * 2. 预加载各联盟下启用的战队
     * 3. 按联盟 ID 排序返回
     *
     * 全站下拉选择器（如赛事报名选队）统一调用此方法获取数据源。
     */
    public function options(): Collection
    {
        return League::query()
            // 步骤1：仅启用联盟
            ->where('status', 1)
            // 步骤2：预加载启用战队
            ->with(['teams' => fn ($query) => $query->where('status', 1)->orderBy('id')])
            // 步骤3：按 ID 排序返回
            ->orderBy('id')
            ->get();
    }

    /**
     * 创建联盟基础资料。
     *
     * - 名称自动 trim 去空格
     * - 未传 status 时默认启用（status = 1）
     */
    public function create(array $data): League
    {
        return League::create(['name' => trim($data['name']), 'status' => $data['status'] ?? 1]);
    }

    /**
     * 更新联盟名称和启用状态。
     *
     * 步骤：
     * 1. 查找目标联盟
     * 2. 更新名称（自动 trim）和状态
     * 3. 刷新模型并重新统计战队数、成员数
     */
    public function update(array $data): League
    {
        // 步骤1：查找目标联盟
        $league = $this->find((int) $data['id']);

        // 步骤2：更新名称和状态
        $league->update(['name' => trim($data['name']), 'status' => $data['status'] ?? 1]);

        // 步骤3：刷新并重新加载关联统计
        return $league->fresh()->loadCount(['teams', 'memberships']);
    }

    /**
     * 删除空联盟；存在战队、成员或赛事时拒绝删除。
     *
     * 步骤：
     * 1. 查找目标联盟
     * 2. 检查是否存在关联数据（战队/成员/赛事/荣誉），有则拒绝
     * 3. 执行删除
     */
    public function delete(int $id): void
    {
        // 步骤1：查找目标联盟
        $league = $this->find($id);

        // 步骤2：检查关联数据，存在则拒绝删除
        if ($league->teams()->exists() || $league->memberships()->exists() || $league->competitions()->exists() || $league->honorEvents()->exists()) {
            throw new BusinessException('联盟已有战队、成员、赛事或荣誉，不能删除', ApiCode::RESOURCE_EXISTS, 409);
        }

        // 步骤3：执行删除
        $league->delete();
    }

    /** 查找联盟，不存在时转换为统一业务异常。 */
    private function find(int $id): League
    {
        $league = League::query()->find($id);
        if (! $league) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $league;
    }
}
