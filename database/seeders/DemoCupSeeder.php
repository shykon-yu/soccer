<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\League;
use App\Services\CompetitionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoCupSeeder extends Seeder
{
    private const COMPETITION_NAME = '模拟杯赛 · 2026 夏季冠军杯';

    /** 重建一届带完整小组积分和 16 人淘汰赛签表的演示杯赛。 */
    public function run(): void
    {
        DB::transaction(function () {
            $league = League::query()->where('name', '实况联盟')->firstOrFail();
            $members = $league->memberships()->with('user')->orderBy('id')->limit(16)->get();
            if ($members->count() < 16) {
                throw new RuntimeException('生成模拟杯赛至少需要 16 名实况联盟成员');
            }

            Competition::withTrashed()
                ->where('name', self::COMPETITION_NAME)
                ->get()
                ->each->forceDelete();

            $service = app(CompetitionService::class);
            $payload = $this->payload($league->id);
            $competition = $service->create($payload);

            foreach ($members->values() as $index => $membership) {
                $competition->entries()->create([
                    'entry_type' => CompetitionEntry::TYPE_USER,
                    'user_id' => $membership->user_id,
                    'seed' => $index + 1,
                    'status' => CompetitionEntry::STATUS_REGISTERED,
                ]);
            }

            $competition = $service->update([
                ...$payload,
                'id' => $competition->id,
                'status' => Competition::STATUS_IN_PROGRESS,
            ]);

            foreach ($competition->matches()->whereNotNull('group_id')->get() as $match) {
                $homeScore = ($match->sequence + $match->group_id) % 4;
                $awayScore = (($match->sequence * 2) + $match->group_id) % 3;
                if ($homeScore === $awayScore) {
                    $homeScore = ($homeScore + 1) % 5;
                }
                $match->update([
                    'home_score' => $homeScore,
                    'away_score' => $awayScore,
                    'status' => 'completed',
                ]);
            }

            $service->update([
                ...$payload,
                'id' => $competition->id,
                'status' => Competition::STATUS_KNOCKOUT,
            ]);
        });

        $this->command?->info(self::COMPETITION_NAME.' 已生成');
    }

    /** 返回模拟杯赛创建和状态流转共用的完整参数。 */
    private function payload(int $leagueId): array
    {
        return [
            'organizer_type' => Competition::ORGANIZER_LEAGUE,
            'league_id' => $leagueId,
            'type' => Competition::TYPE_CUP,
            'name' => self::COMPETITION_NAME,
            'season' => '2026 演示赛季',
            'format' => Competition::FORMAT_GROUP_KNOCKOUT,
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => now()->subDay()->toDateTimeString(),
            'registration_limit' => 16,
            'group_count' => 4,
            'knockout_size' => 16,
            'starts_at' => now()->subHours(2)->toDateTimeString(),
            'notes' => '用于预览小组积分榜、16 人淘汰赛签表和图片导出效果。',
        ];
    }
}
