<?php

namespace Database\Seeders;

use App\Models\CompetitionTemplate;
use Illuminate\Database\Seeder;

class CompetitionTemplateSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'name' => '战队 8 人今日之星', 'organizer_type' => 'team', 'type' => 'cup',
                'registration_limit' => 8, 'is_fixed_participants' => true,
                'stages' => [
                    ['type' => 'knockout', 'name' => '总赛区淘汰赛', 'rules' => ['knockout_size' => 8, 'pairing_mode' => 'random', 'scoring_mode' => 'single', 'avoid_same_source' => false]],
                ],
            ],
            [
                'name' => '战队 16 人今日之星', 'organizer_type' => 'team', 'type' => 'cup',
                'registration_limit' => 16, 'is_fixed_participants' => true,
                'stages' => [
                    ['type' => 'knockout', 'name' => '总赛区淘汰赛', 'rules' => ['knockout_size' => 16, 'pairing_mode' => 'random', 'scoring_mode' => 'single', 'avoid_same_source' => false]],
                ],
            ],
            [
                'name' => '战队 32 人小组杯赛', 'organizer_type' => 'team', 'type' => 'cup',
                'registration_limit' => 32, 'is_fixed_participants' => true,
                'stages' => [
                    ['type' => 'group', 'name' => '总赛区小组赛', 'rules' => ['group_count' => 8, 'qualify_count' => 16, 'scoring_mode' => 'single', 'team_assignment' => 'none']],
                    ['type' => 'knockout', 'name' => '总赛区淘汰赛', 'rules' => ['knockout_size' => 16, 'pairing_mode' => 'cross', 'scoring_mode' => 'single', 'avoid_same_source' => true]],
                ],
            ],
            [
                'name' => '联盟 32 人个人杯赛', 'organizer_type' => 'league', 'type' => 'cup',
                'registration_limit' => 32, 'is_fixed_participants' => true,
                'stages' => [
                    ['type' => 'group', 'name' => '总赛区小组赛', 'rules' => ['group_count' => 8, 'qualify_count' => 16, 'scoring_mode' => 'home_away_combined', 'team_assignment' => 'none']],
                    ['type' => 'knockout', 'name' => '总赛区淘汰赛', 'rules' => ['knockout_size' => 16, 'pairing_mode' => 'cross', 'scoring_mode' => 'single', 'avoid_same_source' => true]],
                ],
            ],
            [
                'name' => '联盟团体循环加淘汰赛', 'organizer_type' => 'league', 'type' => 'team',
                'registration_limit' => null, 'is_fixed_participants' => false,
                'stages' => [
                    ['type' => 'group', 'name' => '团体循环赛', 'rules' => ['group_count' => 1, 'qualify_count' => 8, 'scoring_mode' => 'home_away_points', 'team_assignment' => 'none']],
                    ['type' => 'knockout', 'name' => '团体淘汰赛', 'rules' => ['knockout_size' => 8, 'pairing_mode' => 'ranking', 'scoring_mode' => 'single', 'avoid_same_source' => false]],
                ],
            ],
            [
                'name' => '战队个人循环联赛', 'organizer_type' => 'team', 'type' => 'league',
                'registration_limit' => null, 'is_fixed_participants' => false,
                'stages' => [
                    ['type' => 'league', 'name' => '个人循环联赛', 'rules' => ['scoring_mode' => 'home_away_points', 'team_assignment' => 'none']],
                ],
            ],
            [
                'name' => '联盟个人循环联赛', 'organizer_type' => 'league', 'type' => 'league',
                'registration_limit' => null, 'is_fixed_participants' => false,
                'stages' => [
                    ['type' => 'league', 'name' => '个人循环联赛', 'rules' => ['scoring_mode' => 'home_away_points', 'team_assignment' => 'none']],
                ],
            ],
        ];

        foreach ($templates as $data) {
            $stages = $data['stages'];
            unset($data['stages']);
            $template = CompetitionTemplate::query()->updateOrCreate(
                ['name' => $data['name'], 'organizer_type' => $data['organizer_type'], 'type' => $data['type']],
                [...$data, 'status' => true]
            );
            $template->stages()->delete();
            foreach ($stages as $index => $stage) {
                $template->stages()->create([...$stage, 'sort' => ($index + 1) * 10]);
            }
        }
    }
}
