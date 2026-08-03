<?php

namespace App\Services;

use App\Models\Competition;
use App\Models\HonorEvent;
use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\User;

class FrontHomeService
{
    /** 聚合前台首页公开展示的规模、进行中赛事、热门战队和最新冠军。 */
    public function overview(): array
    {
        return [
            'statistics' => [
                'league_count' => League::query()->where('status', 1)->count(),
                'team_count' => Team::query()->where('status', 1)->count(),
                'user_count' => User::query()->where('status', 1)->count(),
                'membership_count' => LeagueMembership::query()->count(),
            ],
            'active_competitions' => $this->activeCompetitions(),
            'top_teams' => $this->topTeams(),
            'latest_champions' => $this->latestChampions(),
        ];
    }

    /** 查询报名中和进行中的最新赛事，首页仅展示摘要。 */
    private function activeCompetitions(): array
    {
        return Competition::query()
            ->with(['league', 'team'])
            ->withCount('entries')
            ->where('status', '!=', Competition::STATUS_COMPLETED)
            ->orderByRaw("CASE status WHEN 'knockout' THEN 1 WHEN 'in_progress' THEN 2 WHEN 'registration' THEN 3 ELSE 4 END")
            ->orderByDesc('starts_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get()
            ->map(fn (Competition $competition) => [
                'id' => $competition->id,
                'name' => $competition->name,
                'season' => $competition->season,
                'type' => $competition->type,
                'format' => $competition->format,
                'status' => $competition->status,
                'organizer_name' => $competition->organizer_type === Competition::ORGANIZER_LEAGUE
                    ? $competition->league?->name
                    : $competition->team?->name,
                'registered_count' => $competition->entries_count,
                'registration_limit' => $competition->registration_limit,
                'starts_at' => $competition->starts_at?->toDateTimeString(),
            ])->all();
    }

    /** 按正式成员数返回首页战队热度排行。 */
    private function topTeams(): array
    {
        return Team::query()
            ->with('league')
            ->withCount('memberships')
            ->where('status', 1)
            ->orderByDesc('memberships_count')
            ->orderBy('name')
            ->limit(8)
            ->get()
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'league_name' => $team->league?->name,
                'member_count' => $team->memberships_count,
            ])->all();
    }

    /** 返回最近完成赛事的冠军，用于首页荣耀时刻。 */
    private function latestChampions(): array
    {
        return HonorEvent::query()
            ->with(['league', 'team', 'awards' => fn ($query) => $query->where('rank', 1)])
            ->whereHas('awards', fn ($query) => $query->where('rank', 1))
            ->where(function ($query) {
                $query->where('source', HonorEvent::SOURCE_MANUAL)
                    ->orWhereHas('competition', fn ($competition) => $competition->where('status', Competition::STATUS_COMPLETED));
            })
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->limit(4)
            ->get()
            ->map(fn (HonorEvent $event) => [
                'id' => $event->awards->first()->id,
                'competition_id' => $event->competition_id,
                'competition_name' => $event->competition_name,
                'season' => $event->season,
                'type' => $event->competition_type,
                'organizer_name' => $event->organizer_type === Competition::ORGANIZER_LEAGUE
                    ? $event->league?->name
                    : $event->team?->name,
                'owner_name' => $event->awards->first()->owner_name,
            ])->all();
    }
}
