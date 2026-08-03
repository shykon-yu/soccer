<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTemplate;
use App\Models\Team;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\CupWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class DemoTeamCupWorkflowSeeder extends Seeder
{
    private const READY_NAME = '战队杯赛演示 · 32 人等待开赛';

    private const COMPLETED_NAME = '战队杯赛演示 · 32 人完整流程';

    public function run(): void
    {
        DB::transaction(function () {
            $team = Team::query()->withCount('memberships')->having('memberships_count', '>=', 32)->orderByDesc('memberships_count')->first();
            $template = CompetitionTemplate::query()
                ->where('name', '战队 32 人小组杯赛')
                ->where('status', true)
                ->first();
            $admin = User::query()->whereHas('roles', fn ($query) => $query->where('name', '管理员'))->first();
            if (! $team || ! $template || ! $admin) {
                throw new RuntimeException('演示杯赛需要至少 32 人的战队、32 人模板和管理员账号');
            }

            $users = $team->memberships()->with('user')->orderBy('id')->limit(32)->get()->pluck('user')->filter()->values();
            foreach ([self::READY_NAME, self::COMPLETED_NAME] as $name) {
                Competition::withTrashed()->where('name', $name)->get()->each->forceDelete();
            }

            $ready = $this->createCompetition($team, $template, self::READY_NAME);
            $this->registerPlayers($ready, $users);

            $completed = $this->createCompetition($team, $template, self::COMPLETED_NAME);
            $this->registerPlayers($completed, $users);
            $this->completeCompetition($completed, $admin);
        });

        $this->command?->info('战队 32 人杯赛等待开赛和完整流程演示数据已生成');
    }

    private function createCompetition(Team $team, CompetitionTemplate $template, string $name): Competition
    {
        return app(CompetitionService::class)->create([
            'template_id' => $template->id,
            'organizer_type' => Competition::ORGANIZER_TEAM,
            'team_id' => $team->id,
            'type' => Competition::TYPE_CUP,
            'name' => $name,
            'season' => '2026 流程演示',
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => now()->addDays(7)->toDateTimeString(),
            'notes' => '用于验证报名库存、即时分组、小组报分确认、淘汰自动晋级和颁奖流程。',
        ]);
    }

    private function registerPlayers(Competition $competition, $users): void
    {
        foreach ($users as $user) {
            app(CompetitionService::class)->registerUser($user, $competition->id);
        }
    }

    private function completeCompetition(Competition $competition, User $admin): void
    {
        $workflow = app(CupWorkflowService::class);
        $competition = $workflow->startGroupStage($admin, $competition->id);
        foreach ($competition->matches()->whereNotNull('group_id')->get() as $match) {
            $workflow->reportScore($admin, $match->id, [
                'home_score' => ($match->sequence % 3) + 1,
                'away_score' => $match->sequence % 2,
            ]);
            $workflow->reviewScore($admin, $match->id, true, '演示数据自动确认');
        }

        $competition = $workflow->startKnockoutStage($admin, $competition->id);
        $stage = $competition->stages->firstWhere('type', 'knockout');
        for ($round = 1; $round <= 4; $round++) {
            foreach ($stage->matches()->where('round_number', $round)->orderBy('sequence')->get() as $index => $match) {
                $match->refresh();
                $data = ['home_score' => 2, 'away_score' => 0];
                if ($round === 1 && $index === 0) {
                    $data = [
                        'home_score' => 1,
                        'away_score' => 1,
                        'winner_entry_id' => $match->away_entry_id,
                        'tie_break_type' => 'away_goals',
                    ];
                }
                $workflow->reportScore($admin, $match->id, $data);
                $workflow->reviewScore($admin, $match->id, true, '演示数据自动确认');
            }
        }

        $final = $stage->matches()->where('round_number', 4)->firstOrFail()->fresh(['homeEntry', 'awayEntry', 'winnerEntry']);
        $semiFinals = $stage->matches()->where('round_number', 3)->orderBy('sequence')->get();
        $champion = $final->winnerEntry;
        $runnerUp = $final->home_entry_id === $champion->id ? $final->awayEntry : $final->homeEntry;
        $semiLosers = $semiFinals->map(fn ($match) => $match->home_entry_id === $match->winner_entry_id
            ? $match->awayEntry
            : $match->homeEntry);
        $honors = collect([$champion, $runnerUp, ...$semiLosers])->values()->map(fn (CompetitionEntry $entry, $index) => [
            'rank' => $index + 1,
            'entry_id' => $entry->id,
            'owner_name' => $entry->displayName(),
        ])->all();
        $workflow->award($admin, $competition->id, $honors);
    }
}
