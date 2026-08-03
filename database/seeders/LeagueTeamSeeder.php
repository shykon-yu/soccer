<?php

namespace Database\Seeders;

use App\Models\League;
use App\Models\Team;
use Illuminate\Database\Seeder;

class LeagueTeamSeeder extends Seeder
{
    private const LEAGUE_TEAMS = [
        '实况联盟' => ['狂龙军团', '飙', 'NO.1', 'FZS', 'WRH', 'AJ天涯', '逆戟鯨', '狂飙'],
        '情怀联盟' => ['情怀'],
        '国际联盟' => ['四季豆'],
    ];

    public function run(): void
    {
        foreach (self::LEAGUE_TEAMS as $leagueName => $teamNames) {
            $league = League::query()->updateOrCreate(
                ['name' => $leagueName],
                ['status' => 1]
            );

            foreach ($teamNames as $teamName) {
                Team::query()->updateOrCreate(
                    ['league_id' => $league->id, 'name' => $teamName],
                    ['status' => 1]
                );
            }
        }
    }
}
