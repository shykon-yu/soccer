<?php

namespace Tests\Feature;

use App\Constants\ApiCode;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PlatformAccessTest extends TestCase
{
    use DatabaseTransactions;

    public function test_platform_login_requires_active_platform_access_without_affecting_soccer_login(): void
    {
        $user = User::create([
            'username' => 'platform_access_expired_user',
            'nickname' => '平台到期用户',
            'password' => Hash::make('password123'),
            'status' => 1,
            'platform_access_expires_at' => now()->subMinute(),
        ]);

        $this->postJson('/api/v1/auth/platform-login', [
            'username' => $user->username,
            'password' => 'password123',
        ])->assertForbidden()
            ->assertJsonPath('code', ApiCode::PLATFORM_ACCESS_EXPIRED)
            ->assertJsonPath('message', '平台使用权限已到期，请联系管理员');

        $this->postJson('/api/v1/auth/login', [
            'username' => $user->username,
            'password' => 'password123',
        ])->assertOk()->assertJsonPath('code', ApiCode::SUCCESS);
    }

    public function test_platform_access_can_be_granted_extended_and_revoked(): void
    {
        $user = User::create([
            'username' => 'platform_access_grant_user',
            'nickname' => '平台授权用户',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
        $service = app(UserService::class);

        $granted = $service->setPlatformAccess($user->id, 1);
        $this->assertTrue($granted->platform_access_expires_at->isFuture());

        $extended = $service->setPlatformAccess($user->id, 3);
        $this->assertTrue($extended->platform_access_expires_at->greaterThan(
            $granted->platform_access_expires_at->copy()->addMonthsNoOverflow(2)
        ));

        $this->postJson('/api/v1/auth/platform-login', [
            'username' => $user->username,
            'password' => 'password123',
        ])->assertOk()
            ->assertJsonPath('code', ApiCode::SUCCESS)
            ->assertJsonPath('data.user.username', $user->username)
            ->assertJsonStructure(['data' => ['user' => ['platform_access_expires_at']]]);

        $revoked = $service->setPlatformAccess($user->id, 0);
        $this->assertNull($revoked->platform_access_expires_at);
    }
}
