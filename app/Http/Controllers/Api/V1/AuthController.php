<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Api\V1\LoginRequest;
use App\Http\Requests\Api\V1\ProfileUpdateRequest;
use App\Http\Resources\Api\V1\AuthTokenResource;
use App\Http\Resources\Api\V1\PlatformAuthResource;
use App\Http\Resources\Api\V1\UserResource;
use App\Services\AuthService;
use Illuminate\Http\JsonResponse;

/**
 * 认证控制器：仅做「接收请求 → 调用 AuthService → 用 ApiResponse 统一返回」。
 * 业务逻辑（签发/刷新/注销 token）下沉到 App\Services\AuthService。
 */
class AuthController extends BaseController
{
    public function __construct(
        private readonly AuthService $authService
    ) {}

    /**
     * 登录签发 token
     * POST /api/v1/auth/login
     *
     * 成功返回：
     * {"code":0,"message":"登录成功",
     *  "data":{"token":"xxx","token_type":"Bearer","expires_in":3600,
     *          "user":{"id":1,"name":"...","email":"...","roles":[...]}}}
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $result = $this->authService->login(
            $request->username,
            $request->password
        );

        return $this->resource(resource: AuthTokenResource::make($result), message: '登录成功');
    }

    /** Go 对战平台专用认证：校验平台期限但不签发 Laravel token。 */
    public function platformLogin(LoginRequest $request): JsonResponse
    {
        $user = $this->authService->authenticatePlatform($request->username, $request->password);

        return $this->resource(PlatformAuthResource::make($user), '平台登录验证成功');
    }

    /**
     * 当前登录用户
     * GET /api/v1/auth/me
     */
    public function me(): JsonResponse
    {
        return $this->resource(new UserResource($this->authService->me()), '获取成功');
    }

    /** 更新当前登录用户自己的前台个人资料。 */
    public function updateProfile(ProfileUpdateRequest $request): JsonResponse
    {
        return $this->updated(
            new UserResource($this->authService->updateProfile($request->user(), $request->validated())),
            '个人信息更新成功'
        );
    }

    /**
     * 刷新 token
     * POST /api/v1/auth/refresh
     */
    public function refresh(): JsonResponse
    {
        $result = $this->authService->refresh();

        return $this->resource(new AuthTokenResource($result), '刷新成功');
    }

    /**
     * 注销（使当前 token 失效）
     * POST /api/v1/auth/logout
     */
    public function logout(): JsonResponse
    {
        $this->authService->logout();

        return $this->deleted('退出成功');
    }
}
