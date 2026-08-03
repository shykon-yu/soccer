<?php

namespace Database\Seeders;

use App\Models\Competition;
use App\Models\CompetitionEntry;
use App\Models\CompetitionTemplate;
use App\Models\League;
use App\Models\User;
use App\Services\CompetitionService;
use App\Services\TeamCompetitionWorkflowService;
use Illuminate\Database\Seeder;

class DemoTeamCompetitionSeeder extends Seeder
{
    public function run(): void
    {
        if (Competition::query()->where('name', '团体赛演示 · 双循环赛程')->exists()) {
            return;
        }

        $admin = User::query()->where('username', 'admin')->firstOrFail();
        $league = League::query()->where('name', '实况联盟')->firstOrFail();
        $teams = $league->teams()->where('status', 1)->whereHas('memberships')->orderBy('id')->limit(4)->get();
        $template = CompetitionTemplate::query()
            ->where('organizer_type', 'league')
            ->where('type', 'team')
            ->where('status', true)
            ->firstOrFail();

        $competition = app(CompetitionService::class)->create([
            'template_id' => $template->id,
            'organizer_type' => 'league',
            'league_id' => $league->id,
            'type' => 'team',
            'name' => '团体赛演示 · 双循环赛程',
            'season' => '2026 秋季团体联赛',
            'status' => Competition::STATUS_REGISTRATION,
            'registration_deadline' => '2026-07-31 23:59:59',
        ]);
        foreach ($teams as $team) {
            $competition->entries()->create([
                'entry_type' => CompetitionEntry::TYPE_TEAM,
                'team_id' => $team->id,
                'status' => CompetitionEntry::STATUS_REGISTERED,
            ]);
        }
        $competition->update(['reserved_count' => $teams->count()]);

        $competition = app(TeamCompetitionWorkflowService::class)->startLeague($admin, $competition->id, [
            'start_date' => '2026-08-03',
            'end_date' => '2026-09-30',
            'include_weekends' => false,
        ]);
        foreach ($competition->stages->firstWhere('type', 'group')->teamFixtures->take(2) as $fixture) {
            $fixture->load(['homeEntry.team.memberships', 'awayEntry.team.memberships']);
            app(TeamCompetitionWorkflowService::class)->reportFixture($admin, $fixture->id, [
                'player_matches' => [[
                    'home_user_id' => $fixture->homeEntry->team->memberships->first()->user_id,
                    'away_user_id' => $fixture->awayEntry->team->memberships->first()->user_id,
                    'home_score' => 2,
                    'away_score' => 1,
                ]],
            ]);
        }
    }
}
