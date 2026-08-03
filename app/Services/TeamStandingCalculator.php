<?php

namespace App\Services;

use App\Models\CompetitionTeamFixture;
use Illuminate\Support\Collection;

class TeamStandingCalculator
{
    /** 使用已加载的参赛记录和战队对阵计算唯一口径的积分榜。 */
    public static function calculate(Collection $entries, Collection $fixtures): array
    {
        $rows = $entries->mapWithKeys(fn ($entry) => [$entry->id => [
            'entry_id' => $entry->id,
            'team_id' => $entry->team_id,
            'name' => $entry->displayName(),
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'score_for' => 0,
            'score_against' => 0,
            'score_difference' => 0,
            'points' => 0,
        ]]);

        foreach ($fixtures->where('status', CompetitionTeamFixture::STATUS_COMPLETED) as $fixture) {
            if (! isset($rows[$fixture->home_entry_id], $rows[$fixture->away_entry_id])) {
                continue;
            }
            $home = $rows[$fixture->home_entry_id];
            $away = $rows[$fixture->away_entry_id];
            $home['played']++;
            $away['played']++;
            $home['score_for'] += $fixture->home_score;
            $home['score_against'] += $fixture->away_score;
            $away['score_for'] += $fixture->away_score;
            $away['score_against'] += $fixture->home_score;
            if ($fixture->home_score === $fixture->away_score) {
                $home['drawn']++;
                $away['drawn']++;
                $home['points']++;
                $away['points']++;
            } elseif ($fixture->home_score > $fixture->away_score) {
                $home['won']++;
                $away['lost']++;
                $home['points'] += 3;
            } else {
                $away['won']++;
                $home['lost']++;
                $away['points'] += 3;
            }
            $rows[$fixture->home_entry_id] = $home;
            $rows[$fixture->away_entry_id] = $away;
        }

        return $rows->map(function ($row) {
            $row['score_difference'] = $row['score_for'] - $row['score_against'];

            return $row;
        })->sortByDesc(fn ($row) => sprintf(
            '%05d%05d%05d%010d',
            $row['points'],
            $row['score_difference'] + 1000,
            $row['score_for'],
            9999999999 - $row['entry_id']
        ))->values()->map(fn ($row, $index) => ['rank' => $index + 1, ...$row])->all();
    }
}
