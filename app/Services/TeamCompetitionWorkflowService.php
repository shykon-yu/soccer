<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionStage;
use App\Models\CompetitionTeamFixture;
use App\Models\LeagueMembership;
use App\Models\TeamGuest;
use App\Models\TeamStaff;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TeamCompetitionWorkflowService
{
    public function __construct(private readonly CompetitionService $competitionService) {}

    public function startLeague(User $user, int $competitionId, array $data): Competition
    {
        return DB::transaction(function () use ($user, $competitionId, $data) {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $this->assertTeamCompetition($competition);
            $this->assertCanManage($user, $competition);
            if ($competition->status !== Competition::STATUS_REGISTRATION) {
                throw new BusinessException('只有报名中的团体赛可以开启循环赛', ApiCode::PARAM_ERROR, 422);
            }

            $entries = $competition->entries()->with('team')->where('entry_type', CompetitionEntry::TYPE_TEAM)->orderBy('id')->get();
            if ($entries->count() < 2) {
                throw new BusinessException('至少需要 2 支战队才能开启循环赛', ApiCode::PARAM_ERROR, 422);
            }
            $stage = $competition->stages()->whereIn('type', ['group', 'league'])->orderBy('sort')->first();
            if (! $stage) {
                throw new BusinessException('团体赛模板缺少循环赛阶段', ApiCode::PARAM_ERROR, 422);
            }
            if ($stage->teamFixtures()->exists()) {
                throw new BusinessException('循环赛对阵已经生成', ApiCode::RESOURCE_EXISTS, 409);
            }

            $dates = $this->availableDates($data['start_date'], $data['end_date'], (bool) $data['include_weekends']);
            [$firstHalfDates, $secondHalfDates] = $this->splitDates($dates, $data['start_date'], $data['end_date']);
            $rounds = $this->roundRobinRounds($entries);
            $firstLeg = collect($rounds)->flatMap(fn ($pairs, $round) => collect($pairs)->map(fn ($pair) => [
                'home' => $pair[0], 'away' => $pair[1], 'round' => $round + 1, 'leg' => 1,
            ]))->values();
            $secondLeg = $firstLeg->map(fn ($fixture) => [
                'home' => $fixture['away'], 'away' => $fixture['home'], 'round' => $fixture['round'], 'leg' => 2,
            ]);
            $firstSchedule = $this->assignDates($firstLeg, $firstHalfDates);
            $secondSchedule = $this->assignDates($secondLeg, $secondHalfDates);

            $sequence = 1;
            foreach ($firstSchedule->concat($secondSchedule) as $fixture) {
                $stage->teamFixtures()->create([
                    'competition_id' => $competition->id,
                    'home_entry_id' => $fixture['home']->id,
                    'away_entry_id' => $fixture['away']->id,
                    'round_label' => '循环赛第 '.$fixture['round'].' 轮'.($fixture['leg'] === 2 ? ' · 次回合' : ' · 首回合'),
                    'round_number' => $fixture['round'],
                    'sequence' => $sequence++,
                    'leg_number' => $fixture['leg'],
                    'scheduled_at' => $fixture['date']->copy()->setTime(20, 0),
                ]);
            }

            $stage->update([
                'status' => 'in_progress',
                'rules' => array_merge($stage->rules ?: [], [
                    'schedule_start_date' => Carbon::parse($data['start_date'])->toDateString(),
                    'schedule_end_date' => Carbon::parse($data['end_date'])->toDateString(),
                    'include_weekends' => (bool) $data['include_weekends'],
                ]),
            ]);
            $competition->stages()->whereKeyNot($stage->id)->update(['status' => 'pending']);
            $competition->update([
                'status' => Competition::STATUS_IN_PROGRESS,
                'starts_at' => Carbon::parse($data['start_date'])->startOfDay(),
                'reserved_count' => $entries->count(),
            ]);

            return $this->competitionService->detail($competition->id);
        });
    }

    public function playerOptions(User $user, int $fixtureId): array
    {
        $fixture = CompetitionTeamFixture::query()->with(['competition', 'homeEntry.team', 'awayEntry.team'])->find($fixtureId);
        if (! $fixture) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }
        if (! $this->canManage($user, $fixture->competition) && ! $this->canReportForFixture($user, $fixture)) {
            throw new BusinessException('没有查看本场队员的权限', ApiCode::PERMISSION_DENIED, 403);
        }

        return [
            'home' => $this->teamPlayers((int) $fixture->homeEntry->team_id),
            'away' => $this->teamPlayers((int) $fixture->awayEntry->team_id),
        ];
    }

    public function reportFixture(User $user, int $fixtureId, array $data): Competition
    {
        return DB::transaction(function () use ($user, $fixtureId, $data) {
            $fixture = CompetitionTeamFixture::query()
                ->with(['competition', 'stage', 'homeEntry.team', 'awayEntry.team'])
                ->whereKey($fixtureId)
                ->lockForUpdate()
                ->first();
            if (! $fixture) {
                throw BusinessException::fromCode(ApiCode::NOT_FOUND);
            }
            $this->assertTeamCompetition($fixture->competition);
            if ($fixture->status !== CompetitionTeamFixture::STATUS_PENDING || ! $fixture->home_entry_id || ! $fixture->away_entry_id) {
                throw new BusinessException('当前团体对阵不能报分', ApiCode::PARAM_ERROR, 422);
            }
            if (! $this->canManage($user, $fixture->competition) && ! $this->canReportForFixture($user, $fixture)) {
                throw new BusinessException('只有赛事管理或对阵战队管理可以报分', ApiCode::PERMISSION_DENIED, 403);
            }

            $homePlayerIds = $this->teamPlayerIds((int) $fixture->homeEntry->team_id);
            $awayPlayerIds = $this->teamPlayerIds((int) $fixture->awayEntry->team_id);
            $users = User::query()->whereIn('id', collect($data['player_matches'])->flatMap(fn ($row) => [
                $row['home_user_id'], $row['away_user_id'],
            ])->unique())->get()->keyBy('id');
            $homeScore = 0;
            $awayScore = 0;
            foreach (array_values($data['player_matches']) as $index => $row) {
                if (! $homePlayerIds->contains((int) $row['home_user_id']) || ! $awayPlayerIds->contains((int) $row['away_user_id'])) {
                    throw new BusinessException('参赛队员不属于对应战队', ApiCode::PARAM_ERROR, 422);
                }
                if ((int) $row['home_score'] > (int) $row['away_score']) {
                    $homeScore++;
                } elseif ((int) $row['away_score'] > (int) $row['home_score']) {
                    $awayScore++;
                }
                $fixture->playerMatches()->create([
                    'sequence' => $index + 1,
                    'home_user_id' => $row['home_user_id'],
                    'away_user_id' => $row['away_user_id'],
                    'home_player_name' => $users[(int) $row['home_user_id']]->nickname,
                    'away_player_name' => $users[(int) $row['away_user_id']]->nickname,
                    'home_score' => $row['home_score'],
                    'away_score' => $row['away_score'],
                ]);
            }

            $winnerEntryId = $homeScore === $awayScore ? null : ($homeScore > $awayScore ? $fixture->home_entry_id : $fixture->away_entry_id);
            if (str_contains($fixture->stage->type, 'knockout') && $homeScore === $awayScore) {
                $winnerEntryId = (int) ($data['winner_entry_id'] ?? 0);
                if (! in_array($winnerEntryId, [$fixture->home_entry_id, $fixture->away_entry_id], true)) {
                    throw new BusinessException('淘汰赛战队比分相同时必须选择晋级战队', ApiCode::PARAM_ERROR, 422);
                }
            }
            $fixture->update([
                'home_score' => $homeScore,
                'away_score' => $awayScore,
                'winner_entry_id' => $winnerEntryId,
                'status' => CompetitionTeamFixture::STATUS_COMPLETED,
                'reported_by_user_id' => $user->id,
                'reported_at' => now(),
            ]);

            if (str_contains($fixture->stage->type, 'knockout')) {
                $this->advanceWinner($fixture->fresh());
            } elseif ($fixture->stage->teamFixtures()->where('status', '!=', CompetitionTeamFixture::STATUS_COMPLETED)->doesntExist()) {
                $fixture->stage()->update(['status' => 'completed']);
            }

            return $this->competitionService->detail($fixture->competition_id);
        });
    }

    public function startKnockout(User $user, int $competitionId, array $data): Competition
    {
        return DB::transaction(function () use ($user, $competitionId, $data) {
            $competition = Competition::query()->whereKey($competitionId)->lockForUpdate()->first();
            $this->assertTeamCompetition($competition);
            $this->assertCanManage($user, $competition);
            if ($competition->status !== Competition::STATUS_IN_PROGRESS) {
                throw new BusinessException('当前团体赛不在循环赛阶段', ApiCode::PARAM_ERROR, 422);
            }
            $leagueStage = $competition->stages()->whereIn('type', ['group', 'league'])->orderBy('sort')->firstOrFail();
            if ($leagueStage->teamFixtures()->doesntExist() || $leagueStage->teamFixtures()->where('status', '!=', 'completed')->exists()) {
                throw new BusinessException('全部循环赛完成后才能开启淘汰赛', ApiCode::PARAM_ERROR, 422);
            }
            $knockoutStage = $competition->stages()->where('type', 'knockout')->orderByDesc('sort')->first();
            if (! $knockoutStage) {
                throw new BusinessException('团体赛模板缺少淘汰赛阶段', ApiCode::PARAM_ERROR, 422);
            }
            $size = (int) $data['knockout_size'];
            $standings = collect($this->standings($competition, $leagueStage));
            if ($size > $standings->count()) {
                throw new BusinessException('淘汰赛名额不能超过已报名战队数', ApiCode::PARAM_ERROR, 422);
            }
            $entries = $competition->entries()->with('team')->get()->keyBy('id');
            $qualified = $standings->take($size)->map(fn ($row) => $entries[(int) $row['entry_id']])->values();
            $pairs = $this->knockoutPairs($competition, $qualified, $data);
            $this->generateKnockoutFixtures($competition, $knockoutStage, $size, $pairs);

            $leagueStage->update(['status' => 'completed']);
            $knockoutStage->update([
                'status' => 'in_progress',
                'rules' => array_merge($knockoutStage->rules ?: [], [
                    'knockout_size' => $size,
                    'pairing_mode' => $data['pairing_mode'],
                ]),
            ]);
            $competition->update(['status' => Competition::STATUS_KNOCKOUT, 'knockout_size' => $size]);

            return $this->competitionService->detail($competition->id);
        });
    }

    public function award(User $user, int $competitionId, array $honors): Competition
    {
        $competition = Competition::query()->find($competitionId);
        $this->assertTeamCompetition($competition);
        $this->assertCanManage($user, $competition);
        if ($competition->status !== Competition::STATUS_AWAITING_AWARDS) {
            throw new BusinessException('团体赛决赛结束后才能颁奖', ApiCode::PARAM_ERROR, 422);
        }

        return $this->competitionService->finish($competitionId, $honors);
    }

    public function standings(Competition $competition, ?CompetitionStage $stage = null): array
    {
        $stage ??= $competition->stages()->whereIn('type', ['group', 'league'])->orderBy('sort')->first();
        $entries = $competition->entries()->with('team')->where('entry_type', CompetitionEntry::TYPE_TEAM)->get();
        $fixtures = $stage ? $stage->teamFixtures()->get() : collect();

        return TeamStandingCalculator::calculate($entries, $fixtures);
    }

    private function roundRobinRounds(Collection $entries): array
    {
        $rotation = $entries->values()->all();
        if (count($rotation) % 2 === 1) {
            $rotation[] = null;
        }
        $participantCount = count($rotation);
        $rounds = [];
        for ($round = 0; $round < $participantCount - 1; $round++) {
            $pairs = [];
            for ($index = 0; $index < $participantCount / 2; $index++) {
                $left = $rotation[$index];
                $right = $rotation[$participantCount - 1 - $index];
                if (! $left || ! $right) {
                    continue;
                }
                $swap = $index === 0 && $round % 2 === 1;
                $pairs[] = $swap ? [$right, $left] : [$left, $right];
            }
            $rounds[] = $pairs;
            $fixed = array_shift($rotation);
            $last = array_pop($rotation);
            array_unshift($rotation, $fixed, $last);
        }

        return $rounds;
    }

    private function availableDates(string $start, string $end, bool $includeWeekends): Collection
    {
        $dates = collect(CarbonPeriod::create(Carbon::parse($start)->startOfDay(), Carbon::parse($end)->startOfDay()))
            ->filter(fn (Carbon $date) => $includeWeekends || $date->isWeekday())
            ->map(fn (Carbon $date) => $date->copy())
            ->values();
        if ($dates->count() < 2) {
            throw new BusinessException('比赛时间范围内可用比赛日不足', ApiCode::PARAM_ERROR, 422);
        }

        return $dates;
    }

    private function splitDates(Collection $dates, string $start, string $end): array
    {
        $startDate = Carbon::parse($start)->startOfDay();
        $midpoint = $startDate->copy()->addSeconds((int) floor($startDate->diffInSeconds(Carbon::parse($end)->endOfDay()) / 2));
        $first = $dates->filter(fn (Carbon $date) => $date->lte($midpoint))->values();
        $second = $dates->filter(fn (Carbon $date) => $date->gt($midpoint))->values();
        if ($first->isEmpty() || $second->isEmpty()) {
            throw new BusinessException('主客场两个阶段都必须有可用比赛日', ApiCode::PARAM_ERROR, 422);
        }

        return [$first, $second];
    }

    private function assignDates(Collection $fixtures, Collection $dates): Collection
    {
        $loads = $dates->mapWithKeys(fn (Carbon $date) => [$date->toDateString() => 0]);
        $teamsByDate = $dates->mapWithKeys(fn (Carbon $date) => [$date->toDateString() => []]);

        return $fixtures->map(function ($fixture, $fixtureIndex) use ($fixtures, $dates, &$loads, &$teamsByDate) {
            $homeId = $fixture['home']->id;
            $awayId = $fixture['away']->id;
            $desiredIndex = $fixtures->count() <= 1
                ? 0
                : (int) round($fixtureIndex * ($dates->count() - 1) / ($fixtures->count() - 1));
            $dateIndexes = $dates->mapWithKeys(fn (Carbon $candidate, $index) => [$candidate->toDateString() => $index]);
            $date = $dates->sortBy(fn (Carbon $candidate) => sprintf(
                '%05d-%05d-%s',
                abs($dateIndexes[$candidate->toDateString()] - $desiredIndex),
                $loads[$candidate->toDateString()],
                $candidate->toDateString()
            ))->first(function (Carbon $candidate) use ($homeId, $awayId, $teamsByDate) {
                $used = $teamsByDate[$candidate->toDateString()];

                return ! in_array($homeId, $used, true) && ! in_array($awayId, $used, true);
            });
            if (! $date) {
                throw new BusinessException('比赛日不足，无法保证同一战队一天只赛一场', ApiCode::PARAM_ERROR, 422);
            }
            $key = $date->toDateString();
            $loads->put($key, $loads[$key] + 1);
            $teamsByDate->put($key, [...$teamsByDate[$key], $homeId, $awayId]);

            return [...$fixture, 'date' => $date];
        });
    }

    private function knockoutPairs(Competition $competition, Collection $qualified, array $data): Collection
    {
        if ($data['pairing_mode'] === 'random') {
            $qualified = $qualified->shuffle()->values();

            return collect(range(0, intdiv($qualified->count(), 2) - 1))
                ->map(fn ($index) => [$qualified[$index * 2], $qualified[$index * 2 + 1], null]);
        }
        if ($data['pairing_mode'] === 'custom') {
            $entries = $competition->entries()->with('team')->get()->keyBy('id');
            $seen = collect();
            $pairs = collect($data['pairs'] ?? [])->map(function ($pair) use ($entries, $seen) {
                $home = $entries->get((int) $pair['home_entry_id']);
                $away = $entries->get((int) $pair['away_entry_id']);
                if (! $home || ! $away || $seen->contains($home->id) || $seen->contains($away->id)) {
                    throw new BusinessException('自定义淘汰赛对阵包含无效或重复战队', ApiCode::PARAM_ERROR, 422);
                }
                $seen->push($home->id, $away->id);

                return [$home, $away, $pair['scheduled_at'] ?? null];
            });
            if ($pairs->count() !== intdiv((int) $data['knockout_size'], 2)) {
                throw new BusinessException('自定义对阵数量与淘汰赛名额不一致', ApiCode::PARAM_ERROR, 422);
            }

            return $pairs;
        }

        return collect(range(0, intdiv($qualified->count(), 2) - 1))
            ->map(fn ($index) => [$qualified[$index], $qualified[$qualified->count() - $index - 1], null]);
    }

    private function generateKnockoutFixtures(Competition $competition, CompetitionStage $stage, int $size, Collection $pairs): void
    {
        $stage->teamFixtures()->delete();
        $roundCount = (int) log($size, 2);
        $sequence = 1;
        for ($round = 1; $round <= $roundCount; $round++) {
            $matchCount = intdiv($size, 2 ** $round);
            for ($position = 1; $position <= $matchCount; $position++) {
                $pair = $round === 1 ? $pairs[$position - 1] : null;
                $stage->teamFixtures()->create([
                    'competition_id' => $competition->id,
                    'home_entry_id' => $pair[0]->id ?? null,
                    'away_entry_id' => $pair[1]->id ?? null,
                    'round_label' => $this->knockoutRoundLabel($matchCount * 2),
                    'round_number' => $round,
                    'sequence' => $sequence++,
                    'scheduled_at' => ! empty($pair[2]) ? Carbon::parse($pair[2]) : null,
                ]);
            }
        }
    }

    private function advanceWinner(CompetitionTeamFixture $fixture): void
    {
        $roundFixtures = CompetitionTeamFixture::query()
            ->where('stage_id', $fixture->stage_id)
            ->where('round_number', $fixture->round_number)
            ->orderBy('sequence')
            ->get();
        $position = $roundFixtures->search(fn ($item) => $item->id === $fixture->id);
        $nextRound = CompetitionTeamFixture::query()
            ->where('stage_id', $fixture->stage_id)
            ->where('round_number', $fixture->round_number + 1)
            ->orderBy('sequence')
            ->get();
        if ($nextRound->isEmpty()) {
            $fixture->stage()->update(['status' => 'completed']);
            $fixture->competition()->update(['status' => Competition::STATUS_AWAITING_AWARDS]);

            return;
        }
        $next = $nextRound[(int) floor($position / 2)];
        $column = $position % 2 === 0 ? 'home_entry_id' : 'away_entry_id';
        $next->update([$column => $fixture->winner_entry_id]);
    }

    private function teamPlayers(int $teamId): array
    {
        return User::query()
            ->where(function ($query) use ($teamId) {
                $query->whereHas('memberships', fn ($membership) => $membership->where('team_id', $teamId))
                    ->orWhereHas('teamGuests', fn ($guest) => $guest->where('team_id', $teamId));
            })
            ->where('status', 1)
            ->orderBy('nickname')
            ->get(['id', 'nickname', 'username'])
            ->map(fn ($user) => ['id' => $user->id, 'name' => $user->nickname ?: $user->username])
            ->all();
    }

    private function teamPlayerIds(int $teamId): Collection
    {
        return LeagueMembership::query()->where('team_id', $teamId)->pluck('user_id')
            ->merge(TeamGuest::query()->where('team_id', $teamId)->pluck('user_id'))
            ->unique()->map(fn ($id) => (int) $id)->values();
    }

    private function canReportForFixture(User $user, CompetitionTeamFixture $fixture): bool
    {
        return TeamStaff::query()
            ->where('user_id', $user->id)
            ->whereIn('team_id', [$fixture->homeEntry?->team_id, $fixture->awayEntry?->team_id])
            ->whereIn('role', [TeamStaff::ROLE_CAPTAIN, TeamStaff::ROLE_MANAGER])
            ->exists();
    }

    private function assertTeamCompetition(?Competition $competition): void
    {
        if (! $competition || $competition->type !== Competition::TYPE_TEAM) {
            throw new BusinessException('比赛不存在或不是联盟团体赛', ApiCode::NOT_FOUND, 404);
        }
    }

    private function assertCanManage(User $user, Competition $competition): void
    {
        if (! $this->canManage($user, $competition)) {
            throw new BusinessException('没有当前团体赛的管理权限', ApiCode::PERMISSION_DENIED, 403);
        }
    }

    private function canManage(User $user, Competition $competition): bool
    {
        if ($user->hasRole('管理员')) {
            return true;
        }
        if (! $user->hasAnyRole(['联盟主席', '联盟管理'])) {
            return false;
        }

        return $user->memberships()->where('league_id', $competition->league_id)->exists();
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
