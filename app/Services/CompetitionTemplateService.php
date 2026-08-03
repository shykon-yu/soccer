<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\CompetitionTemplate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CompetitionTemplateService
{
    /**
     * 模板列表分页查询
     *
     * 支持按名称(模糊)、举办方级别、比赛类型、启用状态筛选。
     * 默认排序：启用优先 → 最新创建优先。
     */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return CompetitionTemplate::query()
            ->with('stages')
            ->when(! empty($filters['name']), fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when(! empty($filters['organizer_type']), fn ($query) => $query->where('organizer_type', $filters['organizer_type']))
            ->when(! empty($filters['type']), fn ($query) => $query->where('type', $filters['type']))
            ->when(array_key_exists('status', $filters), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->paginate((int) ($filters['pageSize'] ?? 10), ['*'], 'page', (int) ($filters['pageNum'] ?? 1));
    }

    /**
     * 获取可选模板列表（供创建比赛时选择模板用）
     *
     * 只返回启用状态(status=true)的模板，按名称排序。
     * 拳皇赛不走模板，调用方需自行排除。
     */
    public function options(array $filters)
    {
        return CompetitionTemplate::query()
            ->with('stages')
            ->where('organizer_type', $filters['organizer_type'])
            ->where('type', $filters['type'])
            ->where('status', true)
            ->orderBy('name')
            ->get();
    }

    /**
     * 创建模板（事务内：建主表 → 建阶段）
     *
     * stages 为前端提交的完整阶段数组，无 id 字段（均为新建阶段）。
     */
    public function create(array $data): CompetitionTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = CompetitionTemplate::create($this->templatePayload($data));
            $this->syncStages($template, $data['stages']);

            return $template->load('stages');
        });
    }

    /**
     * 更新模板（事务内：查模板 → 更新主表 → 同步阶段增删改）
     *
     * 阶段同步策略：有 id 的更新，无 id 的新增，不在 stages 数组中的删除。
     * 不使用"先删后建"，保证已存在的 stage id 不变。
     */
    public function update(array $data): CompetitionTemplate
    {
        return DB::transaction(function () use ($data) {
            $template = $this->find((int) $data['id']);
            $template->update($this->templatePayload($data));
            $this->syncStages($template, $data['stages']);
            return $template->load('stages');
        });
    }

    /**
     * 删除模板（软删除）
     *
     * 已被比赛使用的模板不允许删除，只能停用(status=false)。
     * 软删除后阶段数据不级联删除（因为软删除不触发数据库 CASCADE）。
     */
    public function delete(int $id): void
    {
        $template = $this->find($id);
        if ($template->competitions()->exists()) {
            throw new BusinessException('模板已被比赛使用，请停用模板而不是删除', ApiCode::RESOURCE_EXISTS, 409);
        }
        $template->delete();
    }

    /**
     * 从请求数据中提取模板主表字段
     * 过滤掉 stages 等不需要写入主表的数据
     */
    private function templatePayload(array $data): array
    {
        return collect($data)->only([
            'name', 'organizer_type', 'type', 'registration_limit', 'is_fixed_participants', 'status', 'notes',
        ])->all();
    }

    /**
     * 同步模板阶段（增删改）
     *
     * - 前端传来的 stage 有 id(>0) 则更新已有阶段
     * - 前端传来的 stage 无 id 则创建新阶段
     * - 前端传来的 stages 数组中不包含的已有阶段将被删除
     * - sort 按数组顺序自动分配: 10, 20, 30...
     */
    private function syncStages(CompetitionTemplate $template, array $stages): void
    {
        // 需要保留的阶段 ID
        $keepIds = collect($stages)
            ->pluck('id')
            ->filter(fn($id) => ($id ?? 0) > 0)
            ->unique()
            ->values()
            ->all();

        // 删除前端移除的阶段
        $template->stages()->whereNotIn('id', $keepIds)->delete();

        foreach ($stages as $index => $stage) {
            $payload = [
                'type'  => $stage['type'],
                'name'  => $stage['name'],
                'sort'  => ($index + 1) * 10,
                'rules' => $stage['rules'] ?? [],
            ];

            if (($stage['id'] ?? 0) > 0) {
                $template->stages()->where('id', $stage['id'])->update($payload);
            } else {
                $template->stages()->create($payload);
            }
        }
    }

    /**
     * 根据 ID 查找模板，不存在时抛异常
     */
    private function find(int $id): CompetitionTemplate
    {
        $template = CompetitionTemplate::query()->find($id);
        if (! $template) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $template;
    }
}
