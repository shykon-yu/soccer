<?php

namespace Tests\Feature;

use App\Models\League;
use App\Models\LeagueMembership;
use App\Models\User;
use Database\Seeders\MemberSeeder;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class MemberSeederTest extends TestCase
{
    use DatabaseTransactions;

    public function test_member_import_is_safe_idempotent_and_uses_expected_account_rules(): void
    {
        Role::findOrCreate('队员', 'api');
        User::query()->create([
            'username' => '狂龙军团-TC',
            'nickname' => '已有重名账号',
            'password' => Hash::make('existing-password'),
            'status' => 1,
        ]);

        app(MemberSeeder::class)->run();

        $league = League::query()->where('name', 'WEL职业联盟')->firstOrFail();
        $this->assertDatabaseHas('teams', ['league_id' => $league->id, 'name' => 'FZS']);
        $this->assertDatabaseHas('teams', ['league_id' => $league->id, 'name' => '逆戟鲸']);
        $this->assertDatabaseMissing('teams', ['league_id' => $league->id, 'name' => '逆戟鯨']);
        $this->assertDatabaseMissing('users', ['nickname' => 'WRH-比安']);
        $this->assertDatabaseMissing('users', ['nickname' => 'FZS-子龙']);
        $this->assertDatabaseMissing('users', ['nickname' => 'FZS-K仔']);

        $imported = User::query()->where('nickname', '狂龙军团-TC')->firstOrFail();
        $this->assertSame('狂龙军团-TC2', $imported->username);
        $this->assertTrue(Hash::check('123456', $imported->password));
        $this->assertTrue($imported->platform_access_expires_at->isFuture());
        $this->assertTrue($imported->hasRole('队员'));

        $firstMembershipCount = LeagueMembership::query()->where('league_id', $league->id)->count();
        $firstUserCount = User::query()->whereHas('memberships', fn ($query) => $query->where('league_id', $league->id))->count();
        app(MemberSeeder::class)->run();

        $this->assertSame($firstMembershipCount, LeagueMembership::query()->where('league_id', $league->id)->count());
        $this->assertSame(
            $firstUserCount,
            User::query()->whereHas('memberships', fn ($query) => $query->where('league_id', $league->id))->count()
        );
        $this->assertSame(1, User::query()->where('nickname', '狂龙军团-TC')->count());
        $this->assertSame(304, User::query()->whereIn('nickname', $this->expectedNicknames())->count());
    }

    private function expectedNicknames(): array
    {
        return collect(file(database_path('seeders/data/shikuang_members.txt'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES))
            ->reject(fn (string $line) => in_array($line, ['WRH-比安', 'FZS-子龙', 'FZS-K仔'], true))
            ->map(function (string $line) {
                [$team, $name] = explode('-', trim($line), 2);

                return ($team === '逆戟鯨' ? '逆戟鲸' : $team).'-'.$name;
            })->values()->all();
    }
}
