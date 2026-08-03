<?php

namespace Tests\Feature;

use App\Http\Resources\Api\V1\CompetitionResource;
use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\League;
use App\Models\User;
use App\Services\CompetitionService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CupScheduleTest extends TestCase
{
    use DatabaseTransactions;

    /** 验证 8 人直接淘汰赛生成完整七场签表并返回首轮选手名字。 */
    public function test_direct_cup_generates_complete_knockout_bracket(): void
    {
        $service = app(CompetitionService::class);
        $league = League::create(['name' => '直接淘汰测试联盟', 'status' => 1]);
        $competition = $service->create($this->payload($league, Competition::FORMAT_KNOCKOUT, 8));
        $this->registerUsers($competition, 8, 'direct');

        $detail = $service->update([
            ...$this->payload($league, Competition::FORMAT_KNOCKOUT, 8),
            'id' => $competition->id,
            'status' => Competition::STATUS_IN_PROGRESS,
        ]);
        $resource = (new CompetitionResource($detail))->resolve();
        $knockout = collect($resource['stages'])->firstWhere('type', 'knockout');

        $this->assertSame(7, $detail->matches()->count());
        $this->assertSame(3, count($knockout['bracket']['rounds']));
        $this->assertCount(4, $knockout['bracket']['rounds'][0]['matches']);
        $this->assertSame('direct_1', $knockout['bracket']['rounds'][0]['matches'][0]['home_name']);
        $this->assertSame('direct_8', $knockout['bracket']['rounds'][0]['matches'][0]['away_name']);
    }

    /** 验证小组杯赛自动分组、生成循环赛、返回积分榜和淘汰赛占位结构。 */
    public function test_group_cup_generates_group_schedule_standings_and_bracket_slots(): void
    {
        $service = app(CompetitionService::class);
        $league = League::create(['name' => '小组杯赛测试联盟', 'status' => 1]);
        $competition = $service->create($this->payload($league, Competition::FORMAT_GROUP_KNOCKOUT, 8, 2));
        $this->registerUsers($competition, 4, 'group');

        $detail = $service->update([
            ...$this->payload($league, Competition::FORMAT_GROUP_KNOCKOUT, 8, 2),
            'id' => $competition->id,
            'status' => Competition::STATUS_IN_PROGRESS,
        ]);
        $resource = (new CompetitionResource($detail))->resolve();
        $groupStage = collect($resource['stages'])->firstWhere('type', 'group');
        $knockout = collect($resource['stages'])->firstWhere('type', 'knockout');

        $this->assertSame(2, collect($groupStage['matches'])->count());
        $this->assertCount(2, $groupStage['groups']);
        $this->assertCount(2, $groupStage['groups'][0]['standings']);
        $this->assertSame(7, collect($knockout['bracket']['rounds'])->sum(fn ($round) => count($round['matches'])));
        $this->assertSame('待定', $knockout['bracket']['rounds'][0]['matches'][0]['home_name']);

        $detail->matches()->whereNotNull('group_id')->update([
            'home_score' => 1,
            'away_score' => 0,
            'status' => 'completed',
        ]);
        $knockoutDetail = $service->update([
            ...$this->payload($league, Competition::FORMAT_GROUP_KNOCKOUT, 8, 2),
            'id' => $competition->id,
            'status' => Competition::STATUS_KNOCKOUT,
        ]);
        $knockoutResource = (new CompetitionResource($knockoutDetail))->resolve();
        $knockoutStage = collect($knockoutResource['stages'])->firstWhere('type', 'knockout');
        $this->assertNotSame('待定', $knockoutStage['bracket']['rounds'][0]['matches'][0]['home_name']);
    }

    /** 构造杯赛新增或编辑使用的完整参数。 */
    private function payload(League $league, string $format, int $knockoutSize, ?int $groupCount = null): array
    {
        return [
            'organizer_type' => Competition::ORGANIZER_LEAGUE,
            'league_id' => $league->id,
            'type' => Competition::TYPE_CUP,
            'name' => '杯赛赛程测试',
            'season' => '测试届次',
            'format' => $format,
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => now()->addDay()->toDateTimeString(),
            'registration_limit' => $format === Competition::FORMAT_KNOCKOUT ? $knockoutSize : 32,
            'group_count' => $groupCount,
            'knockout_size' => $knockoutSize,
            'starts_at' => now()->addDays(2)->toDateTimeString(),
        ];
    }

    /** 创建指定数量用户并写入杯赛报名记录。 */
    private function registerUsers(Competition $competition, int $count, string $prefix): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $user = User::create([
                'username' => $prefix.'_'.$index,
                'nickname' => $prefix.'_'.$index,
                'password' => Hash::make('password123'),
                'status' => 1,
            ]);
            $competition->entries()->create([
                'entry_type' => CompetitionEntry::TYPE_USER,
                'user_id' => $user->id,
                'seed' => $index,
                'status' => CompetitionEntry::STATUS_REGISTERED,
            ]);
        }
    }
}
