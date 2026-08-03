<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use DatabaseTransactions;

    /** 验证普通前台用户只能通过个人资料接口更新自己的公开资料。 */
    public function test_user_can_update_own_profile_without_backend_role(): void
    {
        $user = User::create([
            'username' => 'profile_user',
            'nickname' => '旧昵称',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/profile', [
                'username' => 'profile_user_new',
                'nickname' => '新昵称',
                'email' => 'profile@example.com',
                'phone' => '13800000000',
                'current_password' => 'password123',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertOk()
            ->assertJsonPath('code', 0)
            ->assertJsonPath('data.username', 'profile_user_new')
            ->assertJsonPath('data.nickname', '新昵称')
            ->assertJsonPath('data.phone', '13800000000');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'username' => 'profile_user_new',
            'nickname' => '新昵称',
            'email' => 'profile@example.com',
            'phone' => '13800000000',
        ]);
        $this->assertTrue(Hash::check('newpassword123', $user->fresh()->password));

        // 用户名不变时，昵称仍然可以重复修改。
        $this->postJson('/api/v1/auth/profile', [
            'username' => 'profile_user_new',
            'nickname' => '再次修改昵称',
        ])->assertOk()->assertJsonPath('data.nickname', '再次修改昵称');

        // 一年内再次修改用户名会被业务层拒绝。
        $this->postJson('/api/v1/auth/profile', [
            'username' => 'profile_user_third',
            'nickname' => '再次修改昵称',
        ])->assertStatus(409)
            ->assertJsonPath('code', 1005);
    }

    /** 验证修改密码时必须提供正确的旧密码。 */
    public function test_profile_password_change_requires_correct_current_password(): void
    {
        $user = User::create([
            'username' => 'profile_password_user',
            'nickname' => '密码用户',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/profile', [
                'username' => $user->username,
                'nickname' => $user->nickname,
                'current_password' => 'wrong-password',
                'password' => 'newpassword123',
                'password_confirmation' => 'newpassword123',
            ])
            ->assertStatus(422)
            ->assertJsonPath('message', '旧密码不正确');

        $this->assertTrue(Hash::check('password123', $user->fresh()->password));
    }

    /** 验证个人资料修改用户名时会拒绝数据库中已存在的用户名。 */
    public function test_profile_username_must_be_unique(): void
    {
        $user = User::create([
            'username' => 'unique_profile_user',
            'nickname' => '唯一用户',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);
        User::create([
            'username' => 'occupied_username',
            'nickname' => '占用用户',
            'password' => Hash::make('password123'),
            'status' => 1,
        ]);

        $this->actingAs($user, 'api')
            ->postJson('/api/v1/auth/profile', [
                'username' => 'occupied_username',
                'nickname' => $user->nickname,
            ])
            ->assertStatus(422)
            ->assertJsonPath('code', 422);

        $this->assertSame('unique_profile_user', $user->fresh()->username);
    }
}
