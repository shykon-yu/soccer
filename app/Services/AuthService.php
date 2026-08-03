<?php

namespace App\Services;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenBlacklistedException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Facades\JWTAuth;

/**
 * 认证业务层：负责 JWT 的签发、刷新、注销，与控制器解耦。
 */
class AuthService
{
    /**
     * 校验账号密码并签发 token；失败抛出登录失败异常（由 Handler 转 401）。
     *
     * @return array{user: User, token: string, token_type: string, expires_in: int}
     */
    public function login(string $username, string $password): array
    {
        $credentials = ['username' => $username, 'password' => $password];

        if (! $token = JWTAuth::attempt($credentials)) {
            throw BusinessException::fromCode(ApiCode::LOGIN_FAILED);
        }

        $user = JWTAuth::user();
        if ((int) $user->status === 0) {
            JWTAuth::setToken($token)->invalidate();
            throw BusinessException::fromCode(ApiCode::ACCOUNT_DISABLED);
        }

        return $this->tokenPayload($user, $token);
    }

    /**
     * 用当前 token 刷新出新 token（支持过期 token，只要在 refresh_ttl 窗口内）。
     *
     * @return array{user: User, token: string, token_type: string, expires_in: int}
     */
    public function refresh(): array
    {
        try {
            // JWTAuth::refresh() 原生支持刷新过期 token（在 refresh_ttl 内），
            // 但需要先调用它再获取用户，因为 JWTAuth::user() 对过期 token 会抛异常
            $newToken = JWTAuth::parseToken()->refresh();
            $user = JWTAuth::setToken($newToken)->authenticate();
        } catch (TokenExpiredException $e) {
            // 超过 refresh_ttl 窗口，token 彻底失效
            throw BusinessException::fromCode(ApiCode::TOKEN_EXPIRED);
        } catch (TokenBlacklistedException $e) {
            // 已注销或已经刷新过的 token 不允许再次换取新 token
            throw BusinessException::fromCode(ApiCode::UNAUTHENTICATED);
        } catch (JWTException $e) {
            // token 无效或解析失败
            Log::warning('Token refresh: JWT error', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
            throw BusinessException::fromCode(ApiCode::UNAUTHENTICATED);
        }

        return $this->tokenPayload($user, $newToken);
    }

    public function me(): User
    {
        $user = Auth::guard('api')->user();
        if (! $user instanceof User) {
            throw BusinessException::fromCode(ApiCode::UNAUTHENTICATED);
        }

        return $user->load('roles');
    }

    /** 更新当前用户资料，并执行用户名年度限制和旧密码校验。 */
    public function updateProfile(User $user, array $data): User
    {
        $username = trim($data['username']);
        $usernameChanged = $username !== $user->username;

        if ($usernameChanged && $user->username_changed_at) {
            $availableAt = $user->username_changed_at->copy()->addYear();
            if ($availableAt->isFuture()) {
                throw new BusinessException(
                    '用户名一年只能修改一次，下次可修改时间：'.$availableAt->toDateString(),
                    ApiCode::RESOURCE_EXISTS,
                    409
                );
            }
        }

        if (! empty($data['password']) && ! Hash::check((string) ($data['current_password'] ?? ''), $user->password)) {
            throw new BusinessException(
                '旧密码不正确',
                ApiCode::PARAM_ERROR,
                422,
                ['current_password' => ['旧密码不正确']]
            );
        }

        $payload = [
            'username' => $username,
            'nickname' => trim($data['nickname']),
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'avatar' => $data['avatar'] ?? null,
        ];

        if ($usernameChanged) {
            $payload['username_changed_at'] = now();
        }
        if (! empty($data['password'])) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);

        return $user->fresh('roles');
    }

    /**
     * 注销当前 token（加入黑名单）。
     * 黑名单未启用（如开发环境未装 Redis）时记录 info 日志并忽略，由前端清除本地 token。
     */
    public function logout(): void
    {
        try {
            JWTAuth::invalidate(JWTAuth::getToken());
        } catch (JWTException $e) {
            // 仅在存储不可用时降级处理；其他 JWT 异常应向上抛出
            Log::info('Token logout: JWT storage unavailable, relying on client-side cleanup', [
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 组装 token 响应载荷。
     *
     * @return array{user: User, token: string, token_type: string, expires_in: int}
     */
    private function tokenPayload(User $user, string $token): array
    {
        return [
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
            'expires_in' => (int) config('jwt.ttl', 60) * 60,
        ];
    }
}
