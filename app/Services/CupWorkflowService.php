<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Competition;
use App\Models\CompetitionMatch;
use App\Models\CompetitionStage;
use App\Models\TeamStaff;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CupWorkflowService
{
    public function __construct(private readonly CompetitionService $competitionService) {}

    /** 锁定报名名单，必要时完成分组，并生成小组循环赛空白比分记录。 */
    public function startGroupStage(User $user, int $competitionId): Competition
    {
        return DB::transaction(function () use ($user, $competitionId) {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $this->assertCup($competition);
            $this->assertCanManage($user, $competition);
            if ($competition->status !== Competition::STATUS_REGISTRATION) {
                throw new BusinessException('只有报名中的比赛可以开启小组赛', ApiCode::PARAM_ERROR, 422);
            }

            $entries = $competition->entries()->orderBy('id')->get();
            if ($entries->count() < 2) {
                throw new BusinessException('至少需要 2 名选手才能开启比赛', ApiCode::PARAM_ERROR, 422);
            }
            if ($competition->is_fixed_participants && $entries->count() !== $competition->registration_limit) {
                throw new BusinessException('固定名额比赛必须报满后才能开启小组赛', ApiCode::PARAM_ERROR, 422);
            }

            $stage = $competition->stages()->whereIn('type', ['group', 'area_group'])->first();
            if (! $stage) {
                throw new BusinessException('当前模板没有小组赛阶段', ApiCode::PARAM_ERROR, 422);
            }
            if (! $competition->is_fixed_participants) {
                $this->assignGroupsAfterRegistration($stage, $entries);
            }
            if ($stage->groups()->whereHas('entries')->count() !== $stage->groups()->count()) {
                throw new BusinessException('存在没有选手的小组，不能开启小组赛', ApiCode::PARAM_ERROR, 422);
            }
            if ($stage->groups()->withCount('entries')->get()->sum('entries_count') !== $entries->count()) {
                throw new BusinessException('部分报名选手尚未完成分组', ApiCode::PARAM_ERROR, 422);
            }

            if ($stage->matches()->doesntExist()) {
                $this->generateGroupMatches($competition, $stage);
            }
            $stage->update(['status' => 'in_progress']);
            $competition->stages()->whereKeyNot($stage->id)->update(['status' => 'pending']);
            $competition->update([
                'status' => Competition::STATUS_IN_PROGRESS,
                'starts_at' => $competition->starts_at ?: now(),
                'reserved_count' => $entries->count(),
            ]);

            return $this->competitionService->detail($competition->id);
        });
    }

    /** 小组赛全部确认后计算晋级者，并按模板规则生成完整淘汰签表。 */
    public function startKnockoutStage(User $user, int $competitionId): Competition
    {
        return DB::transaction(function () use ($user, $competitionId) {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $this->assertCup($competition);
            $this->assertCanManage($user, $competition);
            if ($competition->status !== Competition::STATUS_IN_PROGRESS) {
                throw new BusinessException('当前比赛不在小组赛阶段', ApiCode::PARAM_ERROR, 422);
            }

            $groupStage = $competition->stages()->whereIn('type', ['group', 'area_group'])->firstOrFail();
            if ($groupStage->matches()->doesntExist() || $groupStage->matches()->where('status', '!=', 'completed')->exists()) {
                throw new BusinessException('全部小组赛比分确认后才能开启淘汰赛', ApiCode::PARAM_ERROR, 422);
            }
            $knockoutStage = $competition->stages()->whereIn('type', ['knockout', 'area_knockout'])->orderByDesc('sort')->first();
            if (! $knockoutStage) {
                throw new BusinessException('当前模板没有淘汰赛阶段', ApiCode::PARAM_ERROR, 422);
            }

            $groups = $groupStage->groups()->with(['entries.user', 'matches'])->orderBy('sort')->get();
            $standings = $groups->mapWithKeys(fn ($group) => [$group->id => $this->groupStandings($group)]);
            $size = (int) ($knockoutStage->rules['knockout_size'] ?? $competition->knockout_size);
            $pairs = $this->buildFirstRoundPairs(
                $groups,
                $standings,
                $size,
                $knockoutStage->rules['pairing_mode'] ?? 'random',
                (bool) ($knockoutStage->rules['avoid_same_source'] ?? false)
            );
            $this->generateKnockoutMatches($competition, $knockoutStage, $size, $pairs);

            $groupStage->update(['status' => 'completed']);
            $knockoutStage->update(['status' => 'in_progress']);
            $competition->update(['status' => Competition::STATUS_KNOCKOUT]);

            return $this->competitionService->detail($competition->id);
        });
    }

    /** 参赛选手或赛事管理提交比分，比分进入待确认状态。 */
    public function reportScore(User $user, int $matchId, array $data): CompetitionMatch
    {
        return DB::transaction(function () use ($user, $matchId, $data) {
            $match = CompetitionMatch::query()
                ->with(['competition', 'stage', 'homeEntry', 'awayEntry'])
                ->whereKey($matchId)
                ->lockForUpdate()
                ->first();
            if (! $match) {
                throw BusinessException::fromCode(ApiCode::NOT_FOUND);
            }
            $this->assertCup($match->competition);
            if (! in_array($match->competition->status, [Competition::STATUS_IN_PROGRESS, Competition::STATUS_KNOCKOUT], true)) {
                throw new BusinessException('当前比赛阶段不能报分', ApiCode::PARAM_ERROR, 422);
            }
            if ($match->status !== 'pending' || ! $match->home_entry_id || ! $match->away_entry_id) {
                throw new BusinessException('当前对阵不能报分', ApiCode::PARAM_ERROR, 422);
            }
            if (! $this->isParticipant($user, $match) && ! $this->canManage($user, $match->competition)) {
                throw new BusinessException('只有对阵选手或赛事管理可以报分', ApiCode::PERMISSION_DENIED, 403);
            }

            $homeScore = (int) $data['home_score'];
            $awayScore = (int) $data['away_score'];
            $winnerEntryId = $homeScore === $awayScore
                ? null
                : ($homeScore > $awayScore ? $match->home_entry_id : $match->away_entry_id);
            $tieBreakType = null;
            if ($homeScore === $awayScore && str_contains($match->stage->type, 'knockout')) {
                $winnerEntryId = (int) ($data['winner_entry_id'] ?? 0);
                if (! in_array($winnerEntryId, [$match->home_entry_id, $match->away_entry_id], true)) {
                    throw new BusinessException('平局时必须选择本场晋级选手', ApiCode::PARAM_ERROR, 422);
                }
                $tieBreakType = $data['tie_break_type'] ?? null;
                if ($tieBreakType !== 'away_goals') {
                    throw new BusinessException('淘汰赛平局必须选择客场进球决胜', ApiCode::PARAM_ERROR, 422);
                }
            }

            $match->update([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'winner_entry_id' => $winnerEntryId,
                'tie_break_type' => $tieBreakType,
                'status' => 'reported',
                'reported_by_user_id' => $user->id,
                'reported_at' => now(),
                'reviewed_by_user_id' => null,
                'reviewed_at' => null,
                'review_note' => null,
            ]);

            return $match->fresh(['homeEntry.user', 'awayEntry.user', 'winnerEntry.user']);
        });
    }

    /** 对手或赛事管理确认/驳回已上报比分；确认淘汰赛比分后自动推进胜者。 */
    public function reviewScore(User $user, int $matchId, bool $approved, ?string $note): Competition
    {
        return DB::transaction(function () use ($user, $matchId, $approved, $note) {
            $match = CompetitionMatch::query()
                ->with(['competition', 'stage', 'homeEntry', 'awayEntry'])
                ->whereKey($matchId)
                ->lockForUpdate()
                ->first();
            if (! $match) {
                throw BusinessException::fromCode(ApiCode::NOT_FOUND);
            }
            if ($match->status !== 'reported') {
                throw new BusinessException('只有待确认比分可以审核', ApiCode::PARAM_ERROR, 422);
            }
            $manager = $this->canManage($user, $match->competition);
            if (! $manager && (! $this->isParticipant($user, $match) || (int) $match->reported_by_user_id === (int) $user->id)) {
                throw new BusinessException('只有对手或赛事管理可以确认比分', ApiCode::PERMISSION_DENIED, 403);
            }

            if (! $approved) {
                $match->update([
                    'home_score' => null,
                    'away_score' => null,
                    'winner_entry_id' => null,
                    'tie_break_type' => null,
                    'status' => 'pending',
                    'reported_by_user_id' => null,
                    'reported_at' => null,
                    'reviewed_by_user_id' => $user->id,
                    'reviewed_at' => now(),
                    'review_note' => $note,
                ]);

                return $this->competitionService->detail($match->competition_id);
            }

            $match->update([
                'status' => 'completed',
                'reviewed_by_user_id' => $user->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
            if (str_contains($match->stage->type, 'knockout')) {
                $this->advanceKnockoutWinner($match->fresh());
            } elseif ($match->stage->matches()->where('status', '!=', 'completed')->doesntExist()) {
                $match->stage->update(['status' => 'completed']);
            }

            return $this->competitionService->detail($match->competition_id);
        });
    }

    /** 待颁奖杯赛由赛事管理确认四个名次并写入荣誉室。 */
    public function award(User $user, int $competitionId, array $honors): Competition
    {
        $competition = Competition::query()->find($competitionId);
        $this->assertCup($competition);
        $this->assertCanManage($user, $competition);
        if ($competition->status !== Competition::STATUS_AWAITING_AWARDS) {
            throw new BusinessException('淘汰赛结束后才能颁奖', ApiCode::PARAM_ERROR, 422);
        }

        return $this->competitionService->finish($competitionId, $honors);
    }

    private function assignGroupsAfterRegistration(CompetitionStage $stage, Collection $entries): void
    {
        $groups = $stage->groups()->orderBy('sort')->get();
        if ($groups->isEmpty()) {
            throw new BusinessException('比赛没有可用小组', ApiCode::PARAM_ERROR, 422);
        }

        DB::table('competition_group_entries')->whereIn('group_id', $groups->pluck('id'))->delete();
        $entries = $entries->shuffle()->values();
        $baseCapacity = intdiv($entries->count(), $groups->count());
        $remainder = $entries->count() % $groups->count();
        foreach ($groups->values() as $index => $group) {
            $capacity = $baseCapacity + ($index < $remainder ? 1 : 0);
            $group->update(['capacity' => $capacity, 'reserved_count' => $capacity]);
        }
        foreach ($entries as $index => $entry) {
            $groups[$index % $groups->count()]->entries()->attach($entry->id);
        }
    }

    private function generateGroupMatches(Competition $competition, CompetitionStage $stage): void
    {
        foreach ($stage->groups()->with('entries')->orderBy('sort')->get() as $group) {
            $entries = $group->entries->values();
            $sequence = 1;
            for ($home = 0; $home < $entries->count(); $home++) {
                for ($away = $home + 1; $away < $entries->count(); $away++) {
                    CompetitionMatch::create([
                        'competition_id' => $competition->id,
                        'stage_id' => $stage->id,
                        'group_id' => $group->id,
                        'home_entry_id' => $entries[$home]->id,
                        'away_entry_id' => $entries[$away]->id,
                        'round_label' => $group->name,
                        'round_number' => 1,
                        'sequence' => $sequence++,
                    ]);
                }
            }
        }
    }

    private function groupStandings($group): Collection
    {
        $rows = $group->entries->mapWithKeys(fn ($entry) => [$entry->id => [
            'entry' => $entry,
            'points' => 0,
            'goal_difference' => 0,
            'goals_for' => 0,
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

        return $rows->sortByDesc(fn ($row) => sprintf(
            '%05d%05d%05d%010d',
            $row['points'],
            $row['goal_difference'] + 1000,
            $row['goals_for'],
            9999999999 - $row['entry']->id
        ))->values();
    }

    private function buildFirstRoundPairs($groups, Collection $standings, int $size, string $mode, bool $avoidSameSource): Collection
    {
        $qualified = collect();
        $maxRank = $standings->max(fn ($rows) => $rows->count()) ?? 0;
        for ($rank = 0; $rank < $maxRank && $qualified->count() < $size; $rank++) {
            foreach ($groups as $group) {
                $row = $standings[$group->id]->get($rank);
                if ($row) {
                    $qualified->push(['entry' => $row['entry'], 'group_id' => $group->id, 'rank' => $rank + 1]);
                }
                if ($qualified->count() === $size) {
                    break;
                }
            }
        }
        if ($qualified->count() !== $size) {
            throw new BusinessException('实际晋级人数与淘汰赛签位不一致', ApiCode::PARAM_ERROR, 422);
        }

        if ($mode === 'cross' && $size === $groups->count() * 2 && $groups->count() % 2 === 0) {
            $pairs = collect();
            foreach ([false, true] as $reverse) {
                for ($index = 0; $index < $groups->count(); $index += 2) {
                    $left = $standings[$groups[$index]->id][$reverse ? 1 : 0]['entry'];
                    $right = $standings[$groups[$index + 1]->id][$reverse ? 0 : 1]['entry'];
                    $pairs->push([$left, $right]);
                }
            }

            return $pairs;
        }

        if ($mode === 'ranking') {
            $entries = $qualified->pluck('entry')->values();

            return collect(range(0, intdiv($size, 2) - 1))->map(fn ($index) => [$entries[$index], $entries[$size - $index - 1]]);
        }

        for ($attempt = 0; $attempt < 100; $attempt++) {
            $shuffled = $qualified->shuffle()->values();
            $pairs = collect(range(0, intdiv($size, 2) - 1))->map(fn ($index) => [$shuffled[$index * 2], $shuffled[$index * 2 + 1]]);
            if (! $avoidSameSource || $pairs->every(fn ($pair) => $pair[0]['group_id'] !== $pair[1]['group_id'])) {
                return $pairs->map(fn ($pair) => [$pair[0]['entry'], $pair[1]['entry']]);
            }
        }

        throw new BusinessException('无法生成满足同组回避条件的淘汰赛对阵', ApiCode::PARAM_ERROR, 422);
    }

    private function generateKnockoutMatches(Competition $competition, CompetitionStage $stage, int $size, Collection $pairs): void
    {
        if (! in_array($size, [2, 4, 8, 16, 32, 64], true) || $pairs->count() !== intdiv($size, 2)) {
            throw new BusinessException('淘汰赛签位或首轮对阵数量不正确', ApiCode::PARAM_ERROR, 422);
        }

        $stage->matches()->delete();
        $roundCount = (int) log($size, 2);
        for ($round = 1; $round <= $roundCount; $round++) {
            $matchCount = intdiv($size, 2 ** $round);
            for ($sequence = 1; $sequence <= $matchCount; $sequence++) {
                $pair = $round === 1 ? $pairs[$sequence - 1] : null;
                CompetitionMatch::create([
                    'competition_id' => $competition->id,
                    'stage_id' => $stage->id,
                    'home_entry_id' => $pair[0]->id ?? null,
                    'away_entry_id' => $pair[1]->id ?? null,
                    'round_label' => $this->knockoutRoundLabel($matchCount * 2),
                    'round_number' => $round,
                    'sequence' => $sequence,
                ]);
            }
        }
    }

    private function advanceKnockoutWinner(CompetitionMatch $match): void
    {
        $nextMatch = CompetitionMatch::query()
            ->where('stage_id', $match->stage_id)
            ->where('round_number', $match->round_number + 1)
            ->where('sequence', (int) ceil($match->sequence / 2))
            ->lockForUpdate()
            ->first();
        if (! $nextMatch) {
            $match->stage()->update(['status' => 'completed']);
            $match->competition()->update(['status' => Competition::STATUS_AWAITING_AWARDS]);

            return;
        }

        $column = $match->sequence % 2 === 1 ? 'home_entry_id' : 'away_entry_id';
        if ($nextMatch->{$column} && (int) $nextMatch->{$column} !== (int) $match->winner_entry_id) {
            throw new BusinessException('下一轮对阵位置已经被占用', ApiCode::RESOURCE_EXISTS, 409);
        }
        $nextMatch->update([$column => $match->winner_entry_id]);
    }

    private function assertCup(?Competition $competition): void
    {
        if (! $competition || $competition->type !== Competition::TYPE_CUP) {
            throw new BusinessException('比赛不存在或不是个人杯赛', ApiCode::NOT_FOUND, 404);
        }
    }

    private function assertCanManage(User $user, Competition $competition): void
    {
        if (! $this->canManage($user, $competition)) {
            throw new BusinessException('没有当前比赛的管理权限', ApiCode::PERMISSION_DENIED, 403);
        }
    }

    private function canManage(User $user, Competition $competition): bool
    {
        if ($user->hasRole('管理员')) {
            return true;
        }

        return $competition->organizer_type === Competition::ORGANIZER_TEAM
            && TeamStaff::query()
                ->where('team_id', $competition->team_id)
                ->where('user_id', $user->id)
                ->whereIn('role', [TeamStaff::ROLE_CAPTAIN, TeamStaff::ROLE_MANAGER])
                ->exists();
    }

    private function isParticipant(User $user, CompetitionMatch $match): bool
    {
        return (int) $match->homeEntry?->user_id === (int) $user->id
            || (int) $match->awayEntry?->user_id === (int) $user->id;
    }

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
}
