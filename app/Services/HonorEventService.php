<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\Competition;
use App\Models\HonorEvent;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class HonorEventService
{
    private const RANK_TITLES = [1 => '冠军', 2 => '亚军', 3 => '季军', 4 => '殿军'];

    public function paginate(array $filters): LengthAwarePaginator
    {
        return $this->query($filters)
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->paginate((int) ($filters['pageSize'] ?? 10), ['*'], 'page', (int) ($filters['pageNum'] ?? 1));
    }

    public function publicList(): Collection
    {
        return $this->query([])
            ->where(function ($query) {
                $query->where('source', HonorEvent::SOURCE_MANUAL)
                    ->orWhereHas('competition', fn ($competition) => $competition->where('status', Competition::STATUS_COMPLETED));
            })
            ->orderByDesc('ended_at')
            ->orderByDesc('id')
            ->get();
    }

    public function create(array $data): HonorEvent
    {
        return DB::transaction(function () use ($data) {
            $event = HonorEvent::create($this->eventPayload($data) + ['source' => HonorEvent::SOURCE_MANUAL]);
            $this->syncAwards($event, $data['awards']);

            return $event->load(['league', 'team', 'awards']);
        });
    }

    public function update(array $data): HonorEvent
    {
        return DB::transaction(function () use ($data) {
            $event = $this->find((int) $data['id']);
            $this->assertManual($event);
            $event->update($this->eventPayload($data));
            $this->syncAwards($event, $data['awards']);

            return $event->fresh(['league', 'team', 'awards']);
        });
    }

    public function delete(int $id): void
    {
        $event = $this->find($id);
        $this->assertManual($event);
        $event->delete();
    }

    private function query(array $filters)
    {
        return HonorEvent::query()
            ->with(['league', 'team', 'awards'])
            ->when(! empty($filters['competition_name']), fn ($query) => $query->where('competition_name', 'like', '%'.$filters['competition_name'].'%'))
            ->when(! empty($filters['organizer_type']), fn ($query) => $query->where('organizer_type', $filters['organizer_type']))
            ->when(! empty($filters['competition_type']), fn ($query) => $query->where('competition_type', $filters['competition_type']))
            ->when(! empty($filters['league_id']), fn ($query) => $query->where('league_id', $filters['league_id']))
            ->when(! empty($filters['team_id']), fn ($query) => $query->where('team_id', $filters['team_id']))
            ->when(! empty($filters['source']), fn ($query) => $query->where('source', $filters['source']));
    }

    private function eventPayload(array $data): array
    {
        return [
            'organizer_type' => $data['organizer_type'],
            'league_id' => $data['organizer_type'] === 'league' ? (int) $data['league_id'] : null,
            'team_id' => $data['organizer_type'] === 'team' ? (int) $data['team_id'] : null,
            'competition_type' => $data['competition_type'],
            'competition_name' => trim($data['competition_name']),
            'season' => ! empty($data['season']) ? trim($data['season']) : null,
            'ended_at' => $data['ended_at'] ?? null,
            'notes' => ! empty($data['notes']) ? trim($data['notes']) : null,
        ];
    }

    private function syncAwards(HonorEvent $event, array $awards): void
    {
        foreach ($awards as $award) {
            $event->awards()->updateOrCreate(
                ['rank' => (int) $award['rank']],
                [
                    'competition_id' => null,
                    'entry_id' => null,
                    'title' => self::RANK_TITLES[(int) $award['rank']],
                    'owner_name' => trim($award['owner_name']),
                ]
            );
        }
    }

    private function find(int $id): HonorEvent
    {
        $event = HonorEvent::query()->find($id);
        if (! $event) {
            throw BusinessException::fromCode(ApiCode::NOT_FOUND);
        }

        return $event;
    }

    private function assertManual(HonorEvent $event): void
    {
        if ($event->source !== HonorEvent::SOURCE_MANUAL) {
            throw new BusinessException('比赛自动生成的荣誉请通过比赛颁奖流程维护', ApiCode::PARAM_ERROR, 422);
        }
    }
}
