<?php

namespace App\Services;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\User;

class DashboardService
{
    /** 汇总联盟、战队、用户数量及战队成员分布。 */
    public function statistics(): array
    {
        $leagueUserCount = LeagueMembership::query()->count();
        $leagueCount = League::query()->where('status', 1)->count();
        $teamCount = Team::query()->where('status', 1)->count();

        $teamDistribution = LeagueMembership::query()
            ->join('teams', 'teams.id', '=', 'league_memberships.team_id')
            ->selectRaw('teams.name AS team_name, COUNT(*) AS user_count')
            ->groupBy('teams.id', 'teams.name')
            ->orderByDesc('user_count')
            ->orderBy('team_name')
            ->get()
            ->map(function ($item) use ($leagueUserCount) {
                $userCount = (int) $item->user_count;

                return [
                    'team' => $item->team_name,
                    'user_count' => $userCount,
                    'ratio' => $leagueUserCount > 0
                        ? round($userCount * 100 / $leagueUserCount, 2)
                        : 0,
                ];
            })
            ->all();

        return [
            'league_count' => $leagueCount,
            'team_count' => $teamCount,
            'user_count' => User::query()->count(),
            'league_user_count' => $leagueUserCount,
            'team_distribution' => $teamDistribution,
        ];
    }
}
