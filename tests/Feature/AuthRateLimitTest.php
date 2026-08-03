<?php

namespace Tests\Feature;

use App\Constants\ApiCode;
use Tests\TestCase;

class AuthRateLimitTest extends TestCase
{
    public function test_login_rate_limit_is_isolated_by_username_behind_platform_proxy(): void
    {
        $server = ['REMOTE_ADDR' => '127.0.0.23'];
        $password = 'secret';

        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->withServerVariables($server)->postJson('/api/v1/auth/login', [
                'username' => 'missing-rate-limit-a',
                'password' => $password,
            ])->assertUnauthorized();
        }

        $this->withServerVariables($server)->postJson('/api/v1/auth/login', [
            'username' => 'missing-rate-limit-b',
            'password' => $password,
        ])->assertUnauthorized();

        $this->withServerVariables($server)->postJson('/api/v1/auth/login', [
            'username' => 'missing-rate-limit-a',
            'password' => $password,
        ])->assertStatus(429)
            ->assertJsonPath('code', ApiCode::TOO_MANY_REQUESTS)
            ->assertJsonPath('message', ApiCode::message(ApiCode::TOO_MANY_REQUESTS));
    }
}
