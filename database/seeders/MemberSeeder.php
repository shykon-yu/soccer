<?php

namespace Database\Seeders;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;
use Spatie\Permission\Models\Role;

class MemberSeeder extends Seeder
{
    public function run(): void
    {
        $members = $this->members();
        $password = Hash::make('shikuang8');
        $memberRole = Role::findByName('队员', 'api');
        $usernames = array_column($members, 'username');
        $league = League::query()->where('name', '实况联盟')->firstOrFail();
        $teams = Team::query()->where('league_id', $league->id)->get()->keyBy('name');
        $memberUserIds = [];

        User::query()
            ->where('id', '<>', 1)
            ->whereNotIn('username', $usernames)
            ->delete();

        foreach ($members as $member) {
            $user = User::withTrashed()->updateOrCreate(
                ['username' => $member['username']],
                [
                    'nickname' => $member['nickname'],
                    'email' => null,
                    'phone' => null,
                    'password' => $password,
                    'status' => 1,
                ]
            );
            if ($user->trashed()) {
                $user->restore();
            }
            $user->syncRoles([$memberRole]);
            $team = $teams->get($member['team']);
            if (! $team) {
                throw new RuntimeException('未找到战队目录：'.$member['team']);
            }
            LeagueMembership::query()->updateOrCreate(
                ['user_id' => $user->id, 'league_id' => $league->id],
                ['team_id' => $team->id]
            );
            $memberUserIds[] = $user->id;
        }

        LeagueMembership::query()
            ->where('league_id', $league->id)
            ->whereNotIn('user_id', $memberUserIds)
            ->delete();
    }

    private function members(): array
    {
        $path = database_path('seeders/data/shikuang_members.txt');
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new RuntimeException('无法读取实况联盟成员种子文件');
        }

        $usedUsernames = [];

        return collect($lines)->map(function (string $line, int $index) use (&$usedUsernames) {
            $line = trim($line);
            $parts = explode('-', $line, 2);
            if (count($parts) !== 2 || trim($parts[0]) === '' || trim($parts[1]) === '') {
                throw new RuntimeException('成员种子格式错误，第 '.($index + 1).' 行应为“战队-用户昵称”');
            }

            $team = trim($parts[0]);
            $name = trim($parts[1]);
            $username = $name;
            $suffix = 2;
            while (isset($usedUsernames[$username])) {
                $username = $name.$suffix;
                $suffix++;
            }
            $usedUsernames[$username] = true;

            return [
                'username' => $username,
                'team' => $team,
                'nickname' => $team.'-'.$name,
            ];
        })->values()->all();
    }
}
