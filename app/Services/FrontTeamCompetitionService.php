<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Competition;
use App\Models\CompetitionTeamFixture;
use App\Models\HonorEvent;
use Illuminate\Support\Collection;

class FrontTeamCompetitionService
{
    public function __construct(private readonly TeamCompetitionWorkflowService $workflowService) {}

    /** 聚合联盟前台使用的当前团体赛、两类榜单和历届荣誉。 */
    public function overview(int $leagueId): array
    {
        $competition = $this->currentCompetition($leagueId);

        return [
            'league_id' => $leagueId,
            'current_competition' => $competition ? $this->competitionSummary($competition) : null,
            'team_standings' => $competition ? $this->workflowService->standings($competition) : [],
            'player_standings' => $competition ? $this->playerStandings($competition) : [],
            'history' => $this->history($leagueId),
        ];
    }

    /** 按荣誉档案加载单届团体赛积分与最终名次。 */
    public function historyDetail(int $eventId): array
    {
        $event = HonorEvent::query()
            ->with(['awards', 'competition'])
            ->where('organizer_type', Competition::ORGANIZER_LEAGUE)
            ->where('competition_type', Competition::TYPE_TEAM)
            ->find($eventId);
        if (! $event) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $this->historyData($event, true);
    }

    private function currentCompetition(int $leagueId): ?Competition
    {
        return Competition::query()
            ->where('organizer_type', Competition::ORGANIZER_LEAGUE)
            ->where('league_id', $leagueId)
            ->where('type', Competition::TYPE_TEAM)
            ->where('status', '!=', Competition::STATUS_COMPLETED)
            ->orderByRaw("CASE status WHEN 'knockout' THEN 1 WHEN 'awaiting_awards' THEN 2 WHEN 'in_progress' THEN 3 WHEN 'registration' THEN 4 ELSE 5 END")
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->first();
    }

    private function competitionSummary(Competition $competition): array
    {
        return [
            'id' => $competition->id,
            'name' => $competition->name,
            'season' => $competition->season,
            'status' => $competition->status,
            'starts_at' => $competition->starts_at?->toDateTimeString(),
            'ended_at' => $competition->ended_at?->toDateTimeString(),
        ];
    }

    /** 按队员在已完成战队对阵中的实际比分计算个人榜。 */
    private function playerStandings(Competition $competition): array
    {
        $rows = collect();
        $fixtures = CompetitionTeamFixture::query()
            ->with(['homeEntry.team', 'awayEntry.team', 'playerMatches'])
            ->where('competition_id', $competition->id)
            ->where('status', CompetitionTeamFixture::STATUS_COMPLETED)
            ->get();

        foreach ($fixtures as $fixture) {
            foreach ($fixture->playerMatches as $match) {
                $this->recordPlayerResult(
                    $rows,
                    (int) $match->home_user_id,
                    (int) $fixture->homeEntry?->team_id,
                    $match->home_player_name,
                    $fixture->homeEntry?->displayName() ?: '待定',
                    (int) $match->home_score,
                    (int) $match->away_score
                );
                $this->recordPlayerResult(
                    $rows,
                    (int) $match->away_user_id,
                    (int) $fixture->awayEntry?->team_id,
                    $match->away_player_name,
                    $fixture->awayEntry?->displayName() ?: '待定',
                    (int) $match->away_score,
                    (int) $match->home_score
                );
            }
        }

        return $rows->map(function ($row) {
            $row['score_difference'] = $row['score_for'] - $row['score_against'];
            $row['win_rate'] = $row['played'] ? (int) round($row['won'] / $row['played'] * 100) : 0;

            return $row;
        })->sort(function ($left, $right) {
            return [$right['points'], $right['score_difference'], $right['score_for'], -$right['user_id']]
                <=> [$left['points'], $left['score_difference'], $left['score_for'], -$left['user_id']];
        })->values()->map(function ($row, $index) {
            $row['rank'] = $index + 1;

            return $row;
        })->all();
    }

    private function recordPlayerResult(
        Collection $rows,
        int $userId,
        int $teamId,
        string $name,
        string $teamName,
        int $scoreFor,
        int $scoreAgainst
    ): void {
        $key = $teamId.':'.$userId;
        $row = $rows->get($key, [
            'user_id' => $userId,
            'team_id' => $teamId,
            'name' => $name,
            'team_name' => $teamName,
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'score_for' => 0,
            'score_against' => 0,
            'score_difference' => 0,
            'win_rate' => 0,
            'points' => 0,
        ]);
        $row['played']++;
        $row['score_for'] += $scoreFor;
        $row['score_against'] += $scoreAgainst;
        if ($scoreFor > $scoreAgainst) {
            $row['won']++;
            $row['points'] += 3;
        } elseif ($scoreFor === $scoreAgainst) {
            $row['drawn']++;
            $row['points']++;
        } else {
            $row['lost']++;
        }
        $rows->put($key, $row);
    }

    /** 荣誉档案同时覆盖正常完赛和上线前手工录入的团体赛历史。 */
    private function history(int $leagueId): array
    {
        return HonorEvent::query()
            ->with(['awards', 'competition'])
            ->where('organizer_type', Competition::ORGANIZER_LEAGUE)
            ->where('league_id', $leagueId)
            ->where('competition_type', Competition::TYPE_TEAM)
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->limit(20)
            ->get()
            ->map(fn (HonorEvent $event) => $this->historyData($event, false))
            ->all();
    }

    private function historyData(HonorEvent $event, bool $withStandings): array
    {
        $competition = $event->competition;
        $data = [
            'id' => $event->id,
            'competition_id' => $event->competition_id,
            'name' => $event->competition_name,
            'season' => $event->season,
            'starts_at' => $competition?->starts_at?->toDateTimeString(),
            'ended_at' => $event->ended_at?->toDateTimeString(),
            'honors' => $event->awards->map(fn ($award) => [
                'rank' => $award->rank,
                'title' => $award->title,
                'owner_name' => $award->owner_name,
            ])->values()->all(),
        ];
        if ($withStandings) {
            $data['standings'] = $competition ? $this->workflowService->standings($competition) : [];
        }

        return $data;
    }
}
