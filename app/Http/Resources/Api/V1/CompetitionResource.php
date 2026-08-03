<?php

namespace App\Http\Resources\Api\V1;

use App\Models\CompetitionEntry;
use App\Services\TeamStandingCalculator;
use Illuminate\Http\Resources\Json\JsonResource;

class CompetitionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'template_name' => $this->template_name,
            'organizer_type' => $this->organizer_type,
            'organizer_id' => $this->organizer_type === 'league' ? $this->league_id : $this->team_id,
            'organizer_name' => $this->organizer_type === 'league' ? $this->league?->name : $this->team?->name,
            'league_id' => $this->league_id,
            'team_id' => $this->team_id,
            'type' => $this->type,
            'name' => $this->name,
            'season' => $this->season,
            'format' => $this->format,
            'status' => $this->status,
            'registration_deadline' => $this->registration_deadline?->toDateTimeString(),
            'registration_limit' => $this->registration_limit,
            'is_fixed_participants' => (bool) $this->is_fixed_participants,
            'reserved_count' => $this->reserved_count,
            'remaining_slots' => $this->registration_limit === null
                ? null
                : max(0, $this->registration_limit - $this->reserved_count),
            'group_count' => $this->group_count,
            'knockout_size' => $this->knockout_size,
            'registered_count' => $this->whenCounted('entries'),
            'is_registered' => $this->when(
                isset($this->current_user_registered),
                fn () => (bool) $this->current_user_registered
            ),
            'registration_open' => $this->when(
                isset($this->entries_count),
                fn () => $this->status === 'registration'
                    && (! $this->registration_deadline || $this->registration_deadline->isFuture())
                    && ($this->registration_limit === null || $this->entries_count < $this->registration_limit)
            ),
            'starts_at' => $this->starts_at?->toDateTimeString(),
            'ended_at' => $this->ended_at?->toDateTimeString(),
            'awarded_at' => $this->awarded_at?->toDateTimeString(),
            'notes' => $this->notes,
            'created_at' => $this->created_at?->toDateTimeString(),
            'stages' => $this->whenLoaded('stages', fn () => $this->stages->map(fn ($stage) => [
                'id' => $stage->id,
                'type' => $stage->type,
                'name' => $stage->name,
                'status' => $stage->status,
                'rules' => $stage->rules ?: [],
                'groups' => $stage->relationLoaded('groups')
                    ? $stage->groups->map(fn ($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'capacity' => $group->capacity,
                        'reserved_count' => $group->reserved_count,
                        'remaining_slots' => $group->capacity === null ? null : max(0, $group->capacity - $group->reserved_count),
                        'standings' => $this->groupStandings($group),
                    ])->values()->all()
                    : [],
                'matches' => $stage->relationLoaded('matches')
                    ? $stage->matches->map(fn ($match) => [
                        'id' => $match->id,
                        'group_id' => $match->group_id,
                        'round_label' => $match->round_label,
                        'round_number' => $match->round_number,
                        'sequence' => $match->sequence,
                        'home_entry_id' => $match->home_entry_id,
                        'away_entry_id' => $match->away_entry_id,
                        'winner_entry_id' => $match->winner_entry_id,
                        'home_name' => $match->homeEntry?->displayName() ?: '待定',
                        'away_name' => $match->awayEntry?->displayName() ?: '待定',
                        'winner_name' => $match->winnerEntry?->displayName(),
                        'home_score' => $match->home_score,
                        'away_score' => $match->away_score,
                        'tie_break_type' => $match->tie_break_type,
                        'status' => $match->status,
                        'reported_by_user_id' => $match->reported_by_user_id,
                        'reported_at' => $match->reported_at?->toDateTimeString(),
                        'reviewed_at' => $match->reviewed_at?->toDateTimeString(),
                        'review_note' => $match->review_note,
                        'can_report' => $this->canReportMatch($request->user(), $match),
                        'can_review' => $this->canReviewMatch($request->user(), $match),
                        'scheduled_at' => $match->scheduled_at?->toDateTimeString(),
                    ])->values()->all()
                    : [],
                'team_fixtures' => $stage->relationLoaded('teamFixtures')
                    ? CompetitionTeamFixtureResource::collection($stage->teamFixtures)->resolve($request)
                    : [],
                'team_standings' => $stage->relationLoaded('teamFixtures') && in_array($stage->type, ['group', 'league'], true)
                    ? TeamStandingCalculator::calculate(
                        $this->entries->where('entry_type', CompetitionEntry::TYPE_TEAM),
                        $stage->teamFixtures
                    )
                    : [],
                'bracket' => str_contains($stage->type, 'knockout') && $stage->relationLoaded('matches')
                    ? $this->knockoutBracket($stage)
                    : null,
            ])->values()->all()),
            'honors' => $this->whenLoaded('honors', fn () => $this->honors->map(fn ($honor) => [
                'rank' => $honor->rank,
                'title' => $honor->title,
                'entry_id' => $honor->entry_id,
                'owner_name' => $honor->owner_name,
            ])->values()->all()),
        ];
    }

    /** 将小组比分转换为前台积分榜，按积分、净胜球、进球数排序。 */
    private function groupStandings($group): array
    {
        if (! $group->relationLoaded('entries')) {
            return [];
        }

        $rows = $group->entries->mapWithKeys(fn ($entry) => [$entry->id => [
            'entry_id' => $entry->id,
            'name' => $entry->displayName(),
            'played' => 0,
            'won' => 0,
            'drawn' => 0,
            'lost' => 0,
            'goals_for' => 0,
            'goals_against' => 0,
            'goal_difference' => 0,
            'points' => 0,
        ]]);

        foreach ($group->matches->where('status', 'completed') as $match) {
            if (! isset($rows[$match->home_entry_id], $rows[$match->away_entry_id])) {
                continue;
            }
            $home = $rows[$match->home_entry_id];
            $away = $rows[$match->away_entry_id];
            $home['played']++;
            $away['played']++;
            $home['goals_for'] += $match->home_score;
            $home['goals_against'] += $match->away_score;
            $away['goals_for'] += $match->away_score;
            $away['goals_against'] += $match->home_score;
            if ($match->home_score === $match->away_score) {
                $home['drawn']++;
                $away['drawn']++;
                $home['points']++;
                $away['points']++;
            } elseif ($match->home_score > $match->away_score) {
                $home['won']++;
                $away['lost']++;
                $home['points'] += 3;
            } else {
                $away['won']++;
                $home['lost']++;
                $away['points'] += 3;
            }
            $home['goal_difference'] = $home['goals_for'] - $home['goals_against'];
            $away['goal_difference'] = $away['goals_for'] - $away['goals_against'];
            $rows[$match->home_entry_id] = $home;
            $rows[$match->away_entry_id] = $away;
        }

        return $rows
            ->sortByDesc(fn ($row) => sprintf('%05d%05d%05d%010d', $row['points'], $row['goal_difference'] + 1000, $row['goals_for'], 9999999999 - $row['entry_id']))
            ->values()
            ->map(fn ($row, $index) => ['rank' => $index + 1, ...$row])
            ->all();
    }

    /** 将淘汰赛比分表按轮次整理为固定位置签表，并标注后续轮次来源。 */
    private function knockoutBracket($stage): array
    {
        return [
            'size' => $stage->rules['knockout_size'] ?? $this->knockout_size,
            'rounds' => $stage->matches
                ->groupBy('round_number')
                ->map(function ($matches, $roundNumber) {
                    return [
                        'round_number' => (int) $roundNumber,
                        'label' => $matches->first()?->round_label,
                        'matches' => $matches->values()->map(function ($match) use ($roundNumber) {
                            $isFirstRound = (int) $roundNumber === 1;
                            $emptyName = $this->format === 'group_knockout' && $this->status === 'in_progress' ? '待定' : '轮空';
                            $previousHome = ($match->sequence * 2) - 1;
                            $previousAway = $match->sequence * 2;

                            return [
                                'id' => $match->id,
                                'position' => $match->sequence,
                                'home_name' => $match->homeEntry?->displayName() ?: ($isFirstRound ? $emptyName : '待定'),
                                'away_name' => $match->awayEntry?->displayName() ?: ($isFirstRound ? $emptyName : '待定'),
                                'home_score' => $match->home_score,
                                'away_score' => $match->away_score,
                                'winner_entry_id' => $match->winner_entry_id,
                                'winner_name' => $match->winnerEntry?->displayName(),
                                'tie_break_type' => $match->tie_break_type,
                                'status' => $match->status,
                                'home_source' => $isFirstRound ? null : '上轮第 '.$previousHome.' 场胜者',
                                'away_source' => $isFirstRound ? null : '上轮第 '.$previousAway.' 场胜者',
                            ];
                        })->all(),
                    ];
                })->values()->all(),
        ];
    }

    private function canReportMatch($user, $match): bool
    {
        if (! $user || $match->status !== 'pending') {
            return false;
        }

        return (int) $match->homeEntry?->user_id === (int) $user->id
            || (int) $match->awayEntry?->user_id === (int) $user->id;
    }

    private function canReviewMatch($user, $match): bool
    {
        if (! $user || $match->status !== 'reported' || (int) $match->reported_by_user_id === (int) $user->id) {
            return false;
        }

        return (int) $match->homeEntry?->user_id === (int) $user->id
            || (int) $match->awayEntry?->user_id === (int) $user->id;
    }
}
