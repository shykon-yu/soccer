<?php

namespace App\Constants;

/**
 * 前后端统一的错误码表。
 *
 * 约定（与前端 soccer_vue 对齐）：
 *  - 成功固定为 0，对应前端 constants SUCCESS_CODE = 0；
 *  - 返回结构统一为 { code, message, data, errors? }；
 *  - 前端 axios 拦截器：code === 0 视为成功；code === 401 触发登出；
 *    其余非 0 直接 ElMessage.error(response.data.message) 展示 message 文案。
 *
 * 因此「错误码展示」依赖本表维护的 code => 文案 映射：
 * 控制器 / 异常只需抛出 ApiCode 常量，前端即可拿到统一、可读的中文提示。
 */
class ApiCode
{
    // 成功（与前端 SUCCESS_CODE 对齐）
    public const SUCCESS = 0;

    // ---- HTTP 语义错误码（与前端拦截器分支保持一致）----
    public const BAD_REQUEST = 400;

    public const UNAUTHENTICATED = 401;   // 前端据此登出

    public const FORBIDDEN = 403;

    public const NOT_FOUND = 404;

    public const METHOD_NOT_ALLOWED = 405;

    public const VALIDATION_FAILED = 422;

    public const TOO_MANY_REQUESTS = 429;

    public const SERVER_ERROR = 500;

    // ---- 业务错误码（1000 起，按模块递增，便于前端按 code 精确展示）----
    public const PARAM_ERROR = 1000;        // 参数错误

    public const LOGIN_FAILED = 1001;       // 登录失败

    public const ACCOUNT_DISABLED = 1002;   // 账号被禁用

    public const TOKEN_EXPIRED = 1003;      // token 过期

    public const PERMISSION_DENIED = 1004;  // 权限不足

    public const RESOURCE_EXISTS = 1005;    // 资源已存在

    public const FAIL = 1006;               // 通用业务失败（ApiResponse::fail() 默认用）

    public const UPLOAD_FAILED = 1007;      // 上传失败

    public const PLATFORM_ACCESS_EXPIRED = 1008; // 对战平台使用权限未开通或已到期

    /**
     * code => 前端展示文案（ElMessage.error 直接展示）。
     */
    protected static array $messages = [
        self::SUCCESS => 'success',
        self::BAD_REQUEST => '请求参数错误',
        self::UNAUTHENTICATED => '未登录或登录已过期',
        self::FORBIDDEN => '没有访问权限',
        self::NOT_FOUND => '请求的资源不存在',
        self::METHOD_NOT_ALLOWED => '请求方法不被允许',
        self::VALIDATION_FAILED => '提交的数据校验失败',
        self::TOO_MANY_REQUESTS => '请求过于频繁，请稍后再试',
        self::SERVER_ERROR => '服务器内部错误',

        self::PARAM_ERROR => '参数错误',
        self::LOGIN_FAILED => '用户名或密码错误',
        self::ACCOUNT_DISABLED => '账号已被禁用',
        self::TOKEN_EXPIRED => '登录状态已过期，请重新登录',
        self::PERMISSION_DENIED => '权限不足',
        self::RESOURCE_EXISTS => '资源已存在',
        self::FAIL => '操作失败，请稍后重试',
        self::UPLOAD_FAILED => '文件上传失败',
        self::PLATFORM_ACCESS_EXPIRED => '平台使用权限已到期，请联系管理员',
    ];

    /**
     * code => 默认 HTTP 状态码（供 BusinessException::fromCode 使用）。
     */
    protected static array $httpStatuses = [
        self::SUCCESS => 200,
        self::BAD_REQUEST => 400,
        self::UNAUTHENTICATED => 401,
        self::FORBIDDEN => 403,
        self::NOT_FOUND => 404,
        self::METHOD_NOT_ALLOWED => 405,
        self::VALIDATION_FAILED => 422,
        self::TOO_MANY_REQUESTS => 429,
        self::SERVER_ERROR => 500,

        self::PARAM_ERROR => 400,
        self::LOGIN_FAILED => 401,
        self::ACCOUNT_DISABLED => 403,
        self::TOKEN_EXPIRED => 401,
        self::PERMISSION_DENIED => 403,
        self::RESOURCE_EXISTS => 409,
        self::FAIL => 400,
        self::UPLOAD_FAILED => 400,
        self::PLATFORM_ACCESS_EXPIRED => 403,
    ];

    /**
     * 取 code 对应的展示文案；未知 code 回退到通用提示。
     */
    public static function message(int $code): string
    {
        return self::$messages[$code] ?? '未知错误';
    }

    /**
     * 取 code 对应的默认 HTTP 状态码。
     */
    public static function httpStatus(int $code): int
    {
        return self::$httpStatuses[$code] ?? 400;
    }

    /**
     * 是否为已登记的 code。
     */
    public static function has(int $code): bool
    {
        return isset(self::$messages[$code]);
    }
}
