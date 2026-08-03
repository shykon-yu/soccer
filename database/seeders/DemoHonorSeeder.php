<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionHonor;
use App\Models\League;
use App\Models\Team;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoHonorSeeder extends Seeder
{
    private const PREFIX = '荣誉演示 · ';

    /** 为四类赛事各生成一届冠军至殿军，供首页和荣誉室预览。 */
    public function run(): void
    {
        DB::transaction(function () {
            $league = League::query()->where('name', '实况联盟')->firstOrFail();
            $team = Team::query()->where('league_id', $league->id)->where('name', 'WRH')->firstOrFail();
            $userNames = $league->memberships()->with('user')->orderBy('id')->limit(12)->get()
                ->pluck('user.nickname')->filter()->values();
            if ($userNames->count() < 12) {
                throw new RuntimeException('生成模拟荣誉至少需要 12 名实况联盟成员');
            }

            Competition::withTrashed()->where('name', 'like', self::PREFIX.'%')->get()->each->forceDelete();
            $definitions = [
                [
                    'type' => Competition::TYPE_CUP,
                    'name' => self::PREFIX.'个人冠军杯',
                    'format' => Competition::FORMAT_GROUP_KNOCKOUT,
                    'owners' => $userNames->slice(0, 4)->values()->all(),
                    'days_ago' => 8,
                ],
                [
                    'type' => Competition::TYPE_LEAGUE,
                    'name' => self::PREFIX.'个人超级联赛',
                    'format' => Competition::FORMAT_ROUND_ROBIN,
                    'owners' => $userNames->slice(4, 4)->values()->all(),
                    'days_ago' => 16,
                ],
                [
                    'type' => Competition::TYPE_KOF,
                    'name' => self::PREFIX.'战队拳皇赛',
                    'format' => Competition::FORMAT_KNOCKOUT,
                    'owners' => $userNames->slice(8, 4)->values()->all(),
                    'days_ago' => 24,
                ],
                [
                    'type' => Competition::TYPE_TEAM,
                    'name' => self::PREFIX.'联盟团体杯',
                    'format' => Competition::FORMAT_GROUP_KNOCKOUT,
                    'owners' => ['WRH', 'FZS', 'NO.1', '飙'],
                    'days_ago' => 32,
                ],
            ];

            foreach ($definitions as $definition) {
                $isTeamOrganizer = $definition['type'] === Competition::TYPE_KOF;
                $competition = Competition::create([
                    'organizer_type' => $isTeamOrganizer ? Competition::ORGANIZER_TEAM : Competition::ORGANIZER_LEAGUE,
                    'league_id' => $isTeamOrganizer ? null : $league->id,
                    'team_id' => $isTeamOrganizer ? $team->id : null,
                    'type' => $definition['type'],
                    'name' => $definition['name'],
                    'season' => '2026 荣誉赛季',
                    'format' => $definition['format'],
                    'status' => Competition::STATUS_COMPLETED,
                    'ended_at' => now()->subDays($definition['days_ago']),
                    'awarded_at' => now()->subDays($definition['days_ago']),
                    'notes' => '用于预览首页冠军和荣誉室四类赛事展示。',
                ]);

                foreach ($definition['owners'] as $index => $ownerName) {
                    $rank = $index + 1;
                    CompetitionHonor::create([
                        'competition_id' => $competition->id,
                        'rank' => $rank,
                        'title' => [1 => '冠军', 2 => '亚军', 3 => '季军', 4 => '殿军'][$rank],
                        'owner_name' => $ownerName,
                    ]);
                }
            }
        });

        $this->command?->info('四类赛事模拟荣誉已生成');
    }
}
