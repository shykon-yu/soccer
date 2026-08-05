<?php

namespace Database\Seeders;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Spatie\Permission\Models\Role;

class MemberSeeder extends Seeder
{
    private const LEAGUE_NAME = 'WEL职业联盟';

    private const DEFAULT_PASSWORD = '123456';

    private const EXCLUDED_MEMBERS = [
        'WRH-比安',
        'FZS-子龙',
        'FZS-K仔',
    ];

    private const TEAM_ALIASES = [
        '逆戟鯨' => '逆戟鲸',
    ];

    public function run(): void
    {
        $members = $this->members();
        $password = Hash::make(self::DEFAULT_PASSWORD);
        $memberRole = Role::findOrCreate('队员', 'api');
        $hasPlatformAccess = Schema::hasColumn('users', 'platform_access_expires_at');
        $createdUsers = 0;
        $reusedUsers = 0;
        $createdTeams = 0;

        DB::transaction(function () use (
            $members,
            $password,
            $memberRole,
            $hasPlatformAccess,
            &$createdUsers,
            &$reusedUsers,
            &$createdTeams
        ) {
            $league = League::query()->firstOrCreate(
                ['name' => self::LEAGUE_NAME],
                ['status' => 1]
            );
            $teams = Team::query()->where('league_id', $league->id)->get()->keyBy('name');
            $usedUsernames = User::withTrashed()->pluck('id', 'username')->all();

            foreach ($members as $member) {
                $teamName = self::TEAM_ALIASES[$member['team']] ?? $member['team'];
                $team = $teams->get($teamName);
                if (! $team) {
                    $team = Team::query()->create([
                        'league_id' => $league->id,
                        'name' => $teamName,
                        'status' => 1,
                    ]);
                    $teams->put($teamName, $team);
                    $createdTeams++;
                }

                $nickname = $teamName.'-'.$member['name'];
                $user = User::withTrashed()
                    ->where('nickname', $nickname)
                    ->whereHas('memberships', function ($query) use ($league, $team) {
                        $query->where('league_id', $league->id)->where('team_id', $team->id);
                    })
                    ->first();

                if ($user) {
                    if ($user->trashed()) {
                        $user->restore();
                    }
                    $reusedUsers++;
                } else {
                    $username = $this->availableUsername($member['name'], $usedUsernames);
                    $attributes = [
                        'username' => $username,
                        'nickname' => $nickname,
                        'email' => null,
                        'phone' => null,
                        'password' => $password,
                        'status' => 1,
                    ];
                    if ($hasPlatformAccess) {
                        $attributes['platform_access_expires_at'] = now()->addYear();
                    }
                    $user = User::query()->create($attributes);
                    $usedUsernames[$username] = $user->id;
                    $createdUsers++;
                }

                $user->assignRole($memberRole);
                LeagueMembership::query()->updateOrCreate(
                    ['user_id' => $user->id, 'league_id' => $league->id],
                    ['team_id' => $team->id]
                );
            }
        });

        $this->command?->info(sprintf(
            'WEL成员导入完成：新建用户 %d，复用用户 %d，新建战队 %d，跳过指定成员 %d。',
            $createdUsers,
            $reusedUsers,
            $createdTeams,
            count(self::EXCLUDED_MEMBERS)
        ));
    }

    private function members(): array
    {
        $path = database_path('seeders/data/shikuang_members.txt');
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('无法读取 WEL 职业联盟成员种子文件');
        }

        return collect($lines)->map(function (string $line, int $index) {
            $line = trim($line);
            $parts = explode('-', $line, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new RuntimeException('成员种子格式错误，第 '.($index + 1).' 行应为“战队-用户名”');
            }

            return [
                'source' => $line,
                'team' => trim($parts[0]),
                'name' => trim($parts[1]),
            ];
        })->reject(fn (array $member) => in_array($member['source'], self::EXCLUDED_MEMBERS, true))
            ->values()
            ->all();
    }

    private function availableUsername(string $name, array $usedUsernames): string
    {
        $username = $name;
        $suffix = 2;
        while (isset($usedUsernames[$username])) {
            $username = $name.$suffix;
            $suffix++;
        }

        return $username;
    }
}
