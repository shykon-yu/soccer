<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionHonor;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;
use App\Models\CompetitionTeamFixture;
use App\Models\CompetitionTemplate;
use App\Models\CompetitionTemplateStage;
use App\Models\HonorEvent;
use App\Models\Team;
use App\Models\TeamStaff;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CompetitionService
{
    private const RANK_TITLES = [1 => '冠军', 2 => '亚军', 3 => '季军', 4 => '殿军'];

    /** 按组织范围和赛事类型分页查询历届比赛。 */
    public function paginate(array $filters): LengthAwarePaginator
    {
        return Competition::query()
            ->with(['league', 'team'])
            ->withCount('entries')
            ->where('organizer_type', $filters['organizer_type'])
            ->where('type', $filters['type'])
            ->when(! empty($filters['name']), fn ($query) => $query->where('name', 'like', '%'.$filters['name'].'%'))
            ->when(! empty($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['pageSize'] ?? 10), ['*'], 'page', (int) ($filters['pageNum'] ?? 1));
    }

    /** 获取赛事阶段、小组、比分和荣誉完整详情。 */
    public function detail(int $id): Competition
    {
        return $this->loadDetail($this->find($id));
    }

    /** 按登录用户可见范围分页查询杯赛、联赛或拳皇赛。 */
    public function frontPaginate(User $user, array $filters): LengthAwarePaginator
    {
        return $this->accessibleQuery($user)
            ->with(['league', 'team'])
            ->withCount('entries')
            ->withExists([
                'entries as current_user_registered' => fn (Builder $query) => $query->where('user_id', $user->id),
            ])
            ->where('type', $filters['type'])
            ->when(
                $filters['scope'] === 'completed',
                fn (Builder $query) => $query->where('status', Competition::STATUS_COMPLETED),
                fn (Builder $query) => $query->where('status', '!=', Competition::STATUS_COMPLETED)
            )
            ->orderByDesc($filters['scope'] === 'completed' ? 'ended_at' : 'starts_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['pageSize'] ?? 8), ['*'], 'page', (int) ($filters['pageNum'] ?? 1));
    }

    /** 在登录用户可见范围内查询赛事完整详情，避免通过 ID 越权访问。 */
    public function frontDetail(User $user, int $id): Competition
    {
        $competition = $this->accessibleQuery($user)
            ->whereIn('type', [Competition::TYPE_CUP, Competition::TYPE_LEAGUE, Competition::TYPE_KOF])
            ->find($id);

        if (! $competition) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $this->loadDetail($competition);
    }

    /** 返回指定月份内已经排期的联盟团体赛战队对阵。 */
    public function teamCalendar(array $filters): Collection
    {
        $start = Carbon::create((int) $filters['year'], (int) $filters['month'], 1)->startOfMonth();
        $end = $start->copy()->endOfMonth();

        return CompetitionTeamFixture::query()
            ->with(['competition', 'stage', 'homeEntry.team', 'awayEntry.team', 'winnerEntry.team'])
            ->whereBetween('scheduled_at', [$start, $end])
            ->whereHas('competition', function (Builder $query) use ($filters) {
                $query->where('organizer_type', Competition::ORGANIZER_LEAGUE)
                    ->where('type', Competition::TYPE_TEAM)
                    ->when(! empty($filters['league_id']), fn (Builder $leagueQuery) => $leagueQuery->where('league_id', $filters['league_id']));
            })
            ->orderBy('scheduled_at')
            ->orderBy('sequence')
            ->get();
    }

    /** 返回当前用户所在联盟的报名中团体赛及其有权操作的战队。 */
    public function teamRegistrationOptions(User $user): array
    {
        $leagueIds = $user->memberships()->pluck('league_id')->unique()->values();
        if ($leagueIds->isEmpty()) {
            return [];
        }

        $staff = $user->teamStaff()
            ->with('team')
            ->get()
            ->filter(fn (TeamStaff $item) => $item->team && $leagueIds->contains($item->team->league_id));
        $staffTeamIds = $staff->pluck('team_id')->unique()->values();

        return Competition::query()
            ->with('league')
            ->withCount('entries')
            ->with(['entries' => fn ($query) => $query->whereIn('team_id', $staffTeamIds)])
            ->where('organizer_type', Competition::ORGANIZER_LEAGUE)
            ->where('type', Competition::TYPE_TEAM)
            ->where('status', Competition::STATUS_REGISTRATION)
            ->whereIn('league_id', $leagueIds)
            ->orderBy('registration_deadline')
            ->orderByDesc('id')
            ->get()
            ->map(function (Competition $competition) use ($staff) {
                $registeredTeamIds = $competition->entries->pluck('team_id');

                return [
                    'id' => $competition->id,
                    'name' => $competition->name,
                    'season' => $competition->season,
                    'league_id' => $competition->league_id,
                    'league_name' => $competition->league?->name,
                    'registration_deadline' => $competition->registration_deadline?->toDateTimeString(),
                    'registration_limit' => $competition->registration_limit,
                    'registered_count' => $competition->entries_count,
                    'registration_open' => $this->registrationIsOpen($competition, $competition->entries_count),
                    'eligible_teams' => $staff
                        ->filter(fn (TeamStaff $item) => (int) $item->team->league_id === (int) $competition->league_id)
                        ->map(fn (TeamStaff $item) => [
                            'id' => $item->team_id,
                            'name' => $item->team->name,
                            'role' => $item->role,
                            'is_registered' => $registeredTeamIds->contains($item->team_id),
                        ])->values()->all(),
                ];
            })->values()->all();
    }

    /** 当前用户报名自己有权查看的个人杯赛或个人联赛。 */
    public function registerUser(User $user, int $competitionId): CompetitionEntry
    {
        return DB::transaction(function () use ($user, $competitionId) {
            $competition = $this->accessibleQuery($user)
                ->whereKey($competitionId)
                ->first();

            if (! $competition) {
                throw BusinessException::fromCode(ApiCode::NOT_FOUND);
            }
            if (! in_array($competition->type, [Competition::TYPE_CUP, Competition::TYPE_LEAGUE], true)) {
                throw new BusinessException('该比赛不支持个人报名', ApiCode::PARAM_ERROR, 422);
            }

            $this->assertRegistrationOpen($competition);
            if ($competition->entries()->where('user_id', $user->id)->exists()) {
                throw new BusinessException('你已经报名该比赛', ApiCode::RESOURCE_EXISTS, 409);
            }

            $this->reserveRegistrationSlot($competition);
            $entry = $competition->entries()->create([
                'entry_type' => CompetitionEntry::TYPE_USER,
                'user_id' => $user->id,
                'status' => CompetitionEntry::STATUS_REGISTERED,
            ]);
            $this->assignFixedGroupSlot($competition, $entry);

            return $entry->load('groups');
        });
    }

    /** 队长或管理代表本战队报名所属联盟发布的团体赛。 */
    public function registerTeam(User $user, int $competitionId, int $teamId): CompetitionEntry
    {
        return DB::transaction(function () use ($user, $competitionId, $teamId) {
            $competition = Competition::query()
                ->whereKey($competitionId)
                ->where('organizer_type', Competition::ORGANIZER_LEAGUE)
                ->where('type', Competition::TYPE_TEAM)
                ->lockForUpdate()
                ->first();

            if (! $competition) {
                throw new BusinessException('该比赛不支持战队报名', ApiCode::NOT_FOUND, 404);
            }

            $team = Team::query()
                ->whereKey($teamId)
                ->where('league_id', $competition->league_id)
                ->where('status', 1)
                ->first();
            if (! $team) {
                throw new BusinessException('战队不属于该比赛联盟', ApiCode::PARAM_ERROR, 422);
            }

            $hasPermission = TeamStaff::query()
                ->where('team_id', $teamId)
                ->where('user_id', $user->id)
                ->whereIn('role', [TeamStaff::ROLE_CAPTAIN, TeamStaff::ROLE_MANAGER])
                ->exists();
            if (! $hasPermission) {
                throw new BusinessException('只有战队队长或管理可以报名', ApiCode::PERMISSION_DENIED, 403);
            }

            $this->assertRegistrationOpen($competition);
            if ($competition->entries()->where('team_id', $teamId)->exists()) {
                throw new BusinessException('该战队已经报名', ApiCode::RESOURCE_EXISTS, 409);
            }

            $this->reserveRegistrationSlot($competition);
            $entry = $competition->entries()->create([
                'entry_type' => CompetitionEntry::TYPE_TEAM,
                'team_id' => $teamId,
                'status' => CompetitionEntry::STATUS_REGISTERED,
            ]);
            $this->assignFixedGroupSlot($competition, $entry);

            return $entry->load('groups');
        });
    }

    /** 创建赛事，并根据比赛模式生成阶段及小组容器。 */
    public function create(array $data): Competition
    {
        return DB::transaction(function () use ($data) {
            $payload = $this->normalize($data);
            $competition = Competition::create($payload);
            $this->syncStages($competition);
            $this->initializeGroupCapacities($competition);

            return $this->detail($competition->id);
        });
    }

    /** 更新赛事设置；已有比分时禁止改变比赛模式。 */
    public function update(array $data): Competition
    {
        return DB::transaction(function () use ($data) {
            $competition = $this->find((int) $data['id']);
            $oldTemplateId = $competition->template_id;
            $oldFormat = $competition->format;
            $oldStatus = $competition->status;
            $oldKnockoutSize = $competition->knockout_size;
            $payload = $this->normalize($data);
            $competition->update($payload);

            if ($oldTemplateId !== $competition->template_id && $competition->entries()->exists()) {
                throw new BusinessException('已有报名记录，不能更换比赛模板', ApiCode::RESOURCE_EXISTS, 409);
            }

            if ($oldKnockoutSize !== $competition->knockout_size && ($competition->matches()->exists() || $competition->teamFixtures()->exists())) {
                throw new BusinessException('已生成比分表，不能修改淘汰赛名额', ApiCode::RESOURCE_EXISTS, 409);
            }

            if ($oldTemplateId !== $competition->template_id || $oldFormat !== $competition->format || $competition->stages()->doesntExist()) {
                if ($competition->matches()->exists() || $competition->teamFixtures()->exists()) {
                    throw new BusinessException('已生成比分表，不能修改比赛模式', ApiCode::RESOURCE_EXISTS, 409);
                }
                $competition->stages()->delete();
                $this->syncStages($competition);
                $this->initializeGroupCapacities($competition);
            } elseif ($competition->format === Competition::FORMAT_GROUP_KNOCKOUT) {
                $this->syncGroups($competition);
            }

            if ($competition->type === Competition::TYPE_CUP) {
                if ($oldStatus === Competition::STATUS_REGISTRATION && $competition->status !== Competition::STATUS_REGISTRATION) {
                    $this->initializeCupSchedule($competition);
                } elseif ($oldStatus !== Competition::STATUS_KNOCKOUT && $competition->status === Competition::STATUS_KNOCKOUT) {
                    $this->seedGroupKnockout($competition);
                }
            }

            return $this->detail($competition->id);
        });
    }

    /** 软删除赛事及后台展示记录。 */
    public function delete(int $id): void
    {
        $this->find($id)->delete();
    }

    /** 结束赛事、写入四个名次荣誉并标记完成。 */
    public function finish(int $id, array $honors): Competition
    {
        return DB::transaction(function () use ($id, $honors) {
            $competition = $this->find($id);
            $honorEvent = HonorEvent::query()->updateOrCreate(
                ['competition_id' => $competition->id],
                [
                    'source' => HonorEvent::SOURCE_COMPETITION,
                    'organizer_type' => $competition->organizer_type,
                    'league_id' => $competition->league_id,
                    'team_id' => $competition->team_id,
                    'competition_type' => $competition->type,
                    'competition_name' => $competition->name,
                    'season' => $competition->season,
                    'ended_at' => $competition->ended_at ?: now(),
                ]
            );
            foreach ($honors as $honor) {
                if (! empty($honor['entry_id'])) {
                    $belongs = $competition->entries()->whereKey($honor['entry_id'])->exists();
                    if (! $belongs) {
                        throw new BusinessException('获奖对象不属于当前比赛', ApiCode::PARAM_ERROR, 422);
                    }
                }
                CompetitionHonor::query()->updateOrCreate(
                    ['competition_id' => $competition->id, 'rank' => (int) $honor['rank']],
                    [
                        'honor_event_id' => $honorEvent->id,
                        'entry_id' => $honor['entry_id'] ?? null,
                        'title' => self::RANK_TITLES[(int) $honor['rank']],
                        'owner_name' => trim($honor['owner_name']),
                    ]
                );
            }
            $competition->update([
                'status' => Competition::STATUS_COMPLETED,
                'ended_at' => $competition->ended_at ?: now(),
                'awarded_at' => now(),
            ]);

            return $this->detail($competition->id);
        });
    }

    /** 加载前后台赛事详情共同使用的阶段、分组、对阵和荣誉关系。 */
    private function loadDetail(Competition $competition): Competition
    {
        return $competition->load([
            'league', 'team', 'honors', 'entries.user', 'entries.team',
            'stages.groups',
            'stages.groups.entries.user',
            'stages.groups.entries.team',
            'stages.groups.entries.squad',
            'stages.groups.matches',
            'stages.matches.homeEntry.user',
            'stages.matches.homeEntry.team',
            'stages.matches.homeEntry.squad',
            'stages.matches.awayEntry.user',
            'stages.matches.awayEntry.team',
            'stages.matches.awayEntry.squad',
            'stages.matches.winnerEntry.user',
            'stages.matches.winnerEntry.team',
            'stages.teamFixtures.homeEntry.team',
            'stages.teamFixtures.awayEntry.team',
            'stages.teamFixtures.winnerEntry.team',
            'stages.teamFixtures.playerMatches',
        ])->loadCount('entries');
    }

    /** 校验报名状态、截止时间和剩余名额。 */
    private function assertRegistrationOpen(Competition $competition): void
    {
        if ($competition->status !== Competition::STATUS_REGISTRATION) {
            throw new BusinessException('当前比赛不在报名阶段', ApiCode::PARAM_ERROR, 422);
        }
        if ($competition->registration_deadline && $competition->registration_deadline->isPast()) {
            throw new BusinessException('比赛报名已截止', ApiCode::PARAM_ERROR, 422);
        }

        $registeredCount = $competition->entries()->count();
        if ($competition->registration_limit !== null && $registeredCount >= $competition->registration_limit) {
            throw new BusinessException('比赛报名名额已满', ApiCode::RESOURCE_EXISTS, 409);
        }
    }

    /** 根据赛事状态、截止时间和当前报名数判断是否仍可报名。 */
    private function registrationIsOpen(Competition $competition, int $registeredCount): bool
    {
        return $competition->status === Competition::STATUS_REGISTRATION
            && (! $competition->registration_deadline || $competition->registration_deadline->isFuture())
            && ($competition->registration_limit === null || $registeredCount < $competition->registration_limit);
    }

    /** 规范组织范围、赛制和可用赛事类型组合。 */
    private function normalize(array $data): array
    {
        unset($data['id']);
        $organizerType = $data['organizer_type'];
        $type = $data['type'];

        $allowed = $organizerType === Competition::ORGANIZER_LEAGUE
            ? [Competition::TYPE_TEAM, Competition::TYPE_CUP, Competition::TYPE_LEAGUE]
            : [Competition::TYPE_CUP, Competition::TYPE_LEAGUE, Competition::TYPE_KOF];
        if (! in_array($type, $allowed, true)) {
            throw new BusinessException('赛事类型与管理范围不匹配', ApiCode::PARAM_ERROR, 422);
        }

        if (! empty($data['template_id'])) {
            $template = CompetitionTemplate::query()->with('stages')->where('status', true)->find($data['template_id']);
            if (! $template) {
                throw new BusinessException('比赛模板不存在或已停用', ApiCode::PARAM_ERROR, 422);
            }
            if ($template->organizer_type !== $organizerType || $template->type !== $type) {
                throw new BusinessException('比赛模板与当前比赛级别或类型不匹配', ApiCode::PARAM_ERROR, 422);
            }

            $data['template_name'] = $template->name;
            $data['format'] = $this->templateFormat($template);
            $data['group_count'] = $this->templateGroupCount($template);
            $data['knockout_size'] = $this->templateKnockoutSize($template);
            if ($template->is_fixed_participants) {
                $data['registration_limit'] = $template->registration_limit;
            } else {
                $data['registration_limit'] ??= $template->registration_limit;
            }
            $data['is_fixed_participants'] = $template->is_fixed_participants;
        }

        if ($type === Competition::TYPE_LEAGUE && empty($data['template_id'])) {
            $data['format'] = Competition::FORMAT_ROUND_ROBIN;
            $data['group_count'] = null;
        }
        if ($type !== Competition::TYPE_CUP && $type !== Competition::TYPE_TEAM) {
            $data['knockout_size'] = null;
        } elseif ($data['format'] === Competition::FORMAT_KNOCKOUT && $data['registration_limit'] && (int) $data['registration_limit'] > (int) $data['knockout_size']) {
            throw new BusinessException('直接淘汰赛的报名名额不能超过淘汰赛名额', ApiCode::PARAM_ERROR, 422);
        }
        if ($data['format'] !== Competition::FORMAT_GROUP_KNOCKOUT) {
            $data['group_count'] = null;
        }

        $data['league_id'] = $organizerType === Competition::ORGANIZER_LEAGUE ? $data['league_id'] : null;
        $data['team_id'] = $organizerType === Competition::ORGANIZER_TEAM ? $data['team_id'] : null;
        $data['status'] ??= Competition::STATUS_REGISTRATION;
        $data['reserved_count'] ??= 0;
        $data['is_fixed_participants'] ??= false;

        return $data;
    }

    /** 按比赛模式创建小组赛、淘汰赛或联赛阶段。 */
    private function syncStages(Competition $competition): void
    {
        if ($competition->template_id) {
            $template = CompetitionTemplate::query()->with('stages')->find($competition->template_id);
            if (! $template) {
                throw new BusinessException('比赛模板不存在', ApiCode::NOT_FOUND, 404);
            }

            foreach ($template->stages as $templateStage) {
                $stage = $competition->stages()->create([
                    'template_stage_id' => $templateStage->id,
                    'type' => $templateStage->type,
                    'name' => $templateStage->name,
                    'sort' => $templateStage->sort,
                    'rules' => $templateStage->rules ?: [],
                ]);
                if (in_array($templateStage->type, [CompetitionTemplateStage::TYPE_GROUP, CompetitionTemplateStage::TYPE_AREA_GROUP], true)) {
                    $this->createStageGroups($stage, (int) ($templateStage->rules['group_count'] ?? 1));
                }
            }

            return;
        }

        if ($competition->format === Competition::FORMAT_GROUP_KNOCKOUT) {
            CompetitionStage::create(['competition_id' => $competition->id, 'type' => 'group', 'name' => '小组赛', 'sort' => 10]);
            CompetitionStage::create(['competition_id' => $competition->id, 'type' => 'knockout', 'name' => '淘汰赛', 'sort' => 20]);
            $this->syncGroups($competition);

            return;
        }

        $type = $competition->format === Competition::FORMAT_KNOCKOUT ? 'knockout' : 'league';
        $name = $type === 'knockout' ? '淘汰赛' : '联赛赛程';
        CompetitionStage::create(['competition_id' => $competition->id, 'type' => $type, 'name' => $name, 'sort' => 10]);
    }

    private function createStageGroups(CompetitionStage $stage, int $count): void
    {
        for ($index = 1; $index <= max(1, $count); $index++) {
            $stage->groups()->create(['name' => $this->groupName($index), 'sort' => $index]);
        }
    }

    /** 固定人数模板按报名上限给各小组设置容量，余数依次分配到前面的小组。 */
    private function initializeGroupCapacities(Competition $competition): void
    {
        if (! $competition->is_fixed_participants || ! $competition->registration_limit) {
            return;
        }

        $stage = $competition->stages()->whereIn('type', ['group', 'area_group'])->first();
        if (! $stage) {
            return;
        }

        $groups = $stage->groups()->orderBy('sort')->get();
        if ($groups->isEmpty()) {
            return;
        }

        $baseCapacity = intdiv($competition->registration_limit, $groups->count());
        $remainder = $competition->registration_limit % $groups->count();
        foreach ($groups->values() as $index => $group) {
            $group->update([
                'capacity' => $baseCapacity + ($index < $remainder ? 1 : 0),
                'reserved_count' => 0,
            ]);
        }
    }

    /** 使用条件更新原子占用比赛报名名额，避免并发报名超卖。 */
    private function reserveRegistrationSlot(Competition $competition): void
    {
        $query = Competition::query()
            ->whereKey($competition->id)
            ->where('status', Competition::STATUS_REGISTRATION)
            ->where(function (Builder $query) {
                $query->whereNull('registration_deadline')->orWhere('registration_deadline', '>', now());
            });

        if ($competition->registration_limit !== null) {
            $query->whereColumn('reserved_count', '<', 'registration_limit');
        }

        if ($query->increment('reserved_count') !== 1) {
            throw new BusinessException('比赛报名名额已满或报名已结束', ApiCode::RESOURCE_EXISTS, 409);
        }
    }

    /** 固定人数小组赛报名后立即随机占用一个仍有库存的小组名额。 */
    private function assignFixedGroupSlot(Competition $competition, CompetitionEntry $entry): void
    {
        if (! $competition->is_fixed_participants || $competition->format !== Competition::FORMAT_GROUP_KNOCKOUT) {
            return;
        }

        $stage = $competition->stages()->whereIn('type', ['group', 'area_group'])->first();
        $groupIds = $stage?->groups()->whereNotNull('capacity')->inRandomOrder()->pluck('id') ?? collect();
        foreach ($groupIds as $groupId) {
            $reserved = DB::table('competition_groups')
                ->where('id', $groupId)
                ->whereColumn('reserved_count', '<', 'capacity')
                ->increment('reserved_count');
            if ($reserved === 1) {
                $entry->groups()->attach($groupId);

                return;
            }
        }

        throw new BusinessException('小组名额已满，请稍后重试', ApiCode::RESOURCE_EXISTS, 409);
    }

    private function templateFormat(CompetitionTemplate $template): string
    {
        $types = $template->stages->pluck('type');
        if ($types->contains(CompetitionTemplateStage::TYPE_LEAGUE)) {
            return Competition::FORMAT_ROUND_ROBIN;
        }
        if ($types->contains(CompetitionTemplateStage::TYPE_GROUP) || $types->contains(CompetitionTemplateStage::TYPE_AREA_GROUP)) {
            return Competition::FORMAT_GROUP_KNOCKOUT;
        }

        return Competition::FORMAT_KNOCKOUT;
    }

    private function templateGroupCount(CompetitionTemplate $template): ?int
    {
        $stage = $template->stages->first(fn ($stage) => in_array($stage->type, ['group', 'area_group'], true));

        return $stage ? (int) ($stage->rules['group_count'] ?? 1) : null;
    }

    private function templateKnockoutSize(CompetitionTemplate $template): ?int
    {
        $stage = $template->stages->last(fn ($stage) => in_array($stage->type, ['knockout', 'area_knockout'], true));

        return $stage ? (int) ($stage->rules['knockout_size'] ?? 0) ?: null : null;
    }

    /** 同步小组赛分组数量，保留已有有效分组。 */
    private function syncGroups(Competition $competition): void
    {
        $stage = $competition->stages()->where('type', 'group')->first();
        if (! $stage) {
            return;
        }

        $count = max(1, (int) $competition->group_count);
        $stage->groups()->where('sort', '>', $count)->delete();
        for ($index = 1; $index <= $count; $index++) {
            $stage->groups()->updateOrCreate(
                ['sort' => $index],
                ['name' => $this->groupName($index)]
            );
        }
    }

    /** 杯赛离开报名阶段时锁定报名名单并生成小组赛或直接淘汰赛赛程。 */
    private function initializeCupSchedule(Competition $competition): void
    {
        if ($competition->matches()->exists()) {
            return;
        }

        $entries = $competition->entries()->orderByRaw('seed IS NULL')->orderBy('seed')->orderBy('id')->get();
        if ($entries->count() < 2) {
            throw new BusinessException('杯赛至少需要 2 名选手才能开始', ApiCode::PARAM_ERROR, 422);
        }

        if ($competition->format === Competition::FORMAT_GROUP_KNOCKOUT) {
            $this->generateGroupSchedule($competition, $entries);
            $this->generateKnockoutSchedule($competition, collect());

            if ($competition->status === Competition::STATUS_KNOCKOUT) {
                $this->seedGroupKnockout($competition);
            }

            return;
        }

        $this->generateKnockoutSchedule($competition, $entries);
    }

    /** 蛇形分配报名选手到各小组，并生成每组单循环比分表。 */
    private function generateGroupSchedule(Competition $competition, $entries): void
    {
        $groups = $competition->stages()->where('type', 'group')->firstOrFail()->groups()->orderBy('sort')->get();
        foreach ($entries->values() as $index => $entry) {
            $round = intdiv($index, $groups->count());
            $offset = $index % $groups->count();
            $groupIndex = $round % 2 === 0 ? $offset : $groups->count() - $offset - 1;
            $groups[$groupIndex]->entries()->attach($entry->id);
        }

        foreach ($groups as $group) {
            $groupEntries = $group->entries()->orderBy('competition_entries.id')->get()->values();
            $sequence = 1;
            for ($home = 0; $home < $groupEntries->count(); $home++) {
                for ($away = $home + 1; $away < $groupEntries->count(); $away++) {
                    CompetitionMatch::create([
                        'competition_id' => $competition->id,
                        'stage_id' => $group->stage_id,
                        'group_id' => $group->id,
                        'home_entry_id' => $groupEntries[$home]->id,
                        'away_entry_id' => $groupEntries[$away]->id,
                        'round_label' => $group->name,
                        'round_number' => 1,
                        'sequence' => $sequence++,
                    ]);
                }
            }
        }
    }

    /** 生成指定 8/16/32/64 人签表的全部轮次，首轮按种子高低对位。 */
    private function generateKnockoutSchedule(Competition $competition, $entries): void
    {
        $size = (int) $competition->knockout_size;
        if (! in_array($size, [8, 16, 32, 64], true)) {
            throw new BusinessException('淘汰赛名额必须是 8、16、32 或 64', ApiCode::PARAM_ERROR, 422);
        }
        if ($entries->count() > $size) {
            throw new BusinessException('晋级人数超过淘汰赛名额', ApiCode::PARAM_ERROR, 422);
        }

        $stage = $competition->stages()->where('type', 'knockout')->firstOrFail();
        $stage->matches()->delete();
        $entries = $entries->values();
        $roundCount = (int) log($size, 2);

        for ($round = 1; $round <= $roundCount; $round++) {
            $matchCount = intdiv($size, 2 ** $round);
            for ($sequence = 1; $sequence <= $matchCount; $sequence++) {
                $homeEntry = null;
                $awayEntry = null;
                if ($round === 1) {
                    $homeEntry = $entries->get($sequence - 1);
                    $awayEntry = $entries->get($size - $sequence);
                }
                CompetitionMatch::create([
                    'competition_id' => $competition->id,
                    'stage_id' => $stage->id,
                    'home_entry_id' => $homeEntry?->id,
                    'away_entry_id' => $awayEntry?->id,
                    'round_label' => $this->knockoutRoundLabel($matchCount * 2),
                    'round_number' => $round,
                    'sequence' => $sequence,
                ]);
            }
        }
    }

    /** 小组赛全部完赛后按积分、净胜球、进球数选出淘汰赛名额并生成签表。 */
    private function seedGroupKnockout(Competition $competition): void
    {
        if ($competition->format !== Competition::FORMAT_GROUP_KNOCKOUT) {
            return;
        }

        $competition = $this->loadDetail($competition);
        $groupStage = $competition->stages->firstWhere('type', 'group');
        if (! $groupStage || $groupStage->matches->contains(fn ($match) => $match->status !== 'completed')) {
            throw new BusinessException('小组赛全部完赛后才能开始淘汰赛', ApiCode::PARAM_ERROR, 422);
        }

        $standings = $groupStage->groups->map(fn ($group) => $this->groupStandings($group)->values());
        $qualified = collect();
        $maxGroupSize = $standings->max(fn ($rows) => $rows->count()) ?? 0;
        for ($rank = 0; $rank < $maxGroupSize && $qualified->count() < $competition->knockout_size; $rank++) {
            foreach ($standings as $rows) {
                if ($rows->has($rank)) {
                    $qualified->push($rows[$rank]['entry']);
                }
                if ($qualified->count() >= $competition->knockout_size) {
                    break;
                }
            }
        }
        $this->generateKnockoutSchedule($competition, $qualified);
    }

    /** 计算单个小组积分排名，依次比较积分、净胜球、进球数和报名 ID。 */
    private function groupStandings($group)
    {
        $rows = $group->entries->mapWithKeys(fn ($entry) => [$entry->id => [
            'entry' => $entry, 'points' => 0, 'goal_difference' => 0, 'goals_for' => 0,
        ]]);
        foreach ($group->matches->where('status', 'completed') as $match) {
            $home = $rows[$match->home_entry_id];
            $away = $rows[$match->away_entry_id];
            $home['goals_for'] += $match->home_score;
            $away['goals_for'] += $match->away_score;
            $home['goal_difference'] += $match->home_score - $match->away_score;
            $away['goal_difference'] += $match->away_score - $match->home_score;
            if ($match->home_score === $match->away_score) {
                $home['points']++;
                $away['points']++;
            } elseif ($match->home_score > $match->away_score) {
                $home['points'] += 3;
            } else {
                $away['points'] += 3;
            }
            $rows[$match->home_entry_id] = $home;
            $rows[$match->away_entry_id] = $away;
        }

        return $rows->sortByDesc(fn ($row) => sprintf('%05d%05d%05d%010d', $row['points'], $row['goal_difference'] + 1000, $row['goals_for'], 9999999999 - $row['entry']->id))->values();
    }

    /** 根据当前轮次参赛规模返回足球淘汰赛常用轮次名称。 */
    private function knockoutRoundLabel(int $participants): string
    {
        return match ($participants) {
            2 => '决赛',
            4 => '半决赛',
            8 => '四分之一决赛',
            16 => '十六强',
            32 => '三十二强',
            64 => '六十四强',
            default => $participants.'强',
        };
    }

    /** 根据顺序生成 A组、B组等小组名称。 */
    private function groupName(int $index): string
    {
        return chr(64 + (($index - 1) % 26) + 1).'组';
    }

    /** 查找赛事，不存在时抛出统一业务异常。 */
    private function find(int $id): Competition
    {
        $competition = Competition::query()->find($id);
        if (! $competition) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $competition;
    }

    /**
     * 构造当前用户赛事数据范围：所在联盟、正式战队以及担任嘉宾的战队。
     */
    private function accessibleQuery(User $user): Builder
    {
        if ($user->hasRole('管理员')) {
            return Competition::query();
        }

        $leagueIds = $user->memberships()->pluck('league_id')->unique()->values();
        $teamIds = $user->memberships()->pluck('team_id')
            ->merge($user->teamGuests()->pluck('team_id'))
            ->unique()
            ->values();

        return Competition::query()->where(function (Builder $query) use ($leagueIds, $teamIds) {
            $query->where(function (Builder $leagueQuery) use ($leagueIds) {
                $leagueQuery->where('organizer_type', Competition::ORGANIZER_LEAGUE);
                $leagueIds->isEmpty()
                    ? $leagueQuery->whereRaw('1 = 0')
                    : $leagueQuery->whereIn('league_id', $leagueIds);
            })->orWhere(function (Builder $teamQuery) use ($teamIds) {
                $teamQuery->where('organizer_type', Competition::ORGANIZER_TEAM);
                $teamIds->isEmpty()
                    ? $teamQuery->whereRaw('1 = 0')
                    : $teamQuery->whereIn('team_id', $teamIds);
            });
        });
    }
}
