<?php

namespace App\Exceptions;

use App\Constants\ApiCode;
use App\Exceptions\Api\BusinessException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Exceptions\TokenExpiredException;
use Tymon\JWTAuth\Exceptions\TokenInvalidException;

class Handler extends ExceptionHandler
{
    /**
     * A list of exception types with their corresponding custom log levels.
     *
     * @var array<class-string<\Throwable>, \Psr\Log\LogLevel::*>
     */
    protected $levels = [
        //
    ];

    /**
     * A list of the exception types that are not reported.
     *
     * @var array<int, class-string<\Throwable>>
     */
    protected $dontReport = [
        //
    ];

    /**
     * A list of the inputs that are never flashed to the session on validation exceptions.
     *
     * @var array<int, string>
     */
    protected $dontFlash = [
        'current_password',
        'password',
        'password_confirmation',
    ];

    /**
     * Register the exception handling callbacks for the application.
     *
     * @return void
     */
    public function register()
    {
        $this->reportable(function (Throwable $e) {
            //
        });
    }

    public function render($request, Throwable $e)
    {
        if ($this->shouldReturnApiResponse($request)) {
            return $this->renderApiException($request, $e);
        }

        return parent::render($request, $e);
    }

    private function shouldReturnApiResponse(Request $request): bool
    {
        return $request->is('api/*') || $request->expectsJson();
    }

    private function renderApiException(Request $request, Throwable $e): JsonResponse
    {
        // 业务异常：不记日志，是预期内的失败
        if ($e instanceof BusinessException) {
            return $this->apiError($e->getMessage(), $e->businessCode(), $e->httpStatus(), $e->errors());
        }

        // 表单校验失败：info 级别，属于客户端输入问题
        if ($e instanceof ValidationException) {
            Log::info('Validation failed', [
                'path' => $request->path(),
                'errors' => $e->errors(),
            ]);

            return $this->apiError(ApiCode::message(ApiCode::VALIDATION_FAILED), ApiCode::VALIDATION_FAILED, 422, $e->errors());
        }

        // JWT 相关异常（必须在 AuthenticationException 之前判断，
        // 因为 TokenExpired/TokenInvalid 都继承自 JWTException）
        if ($e instanceof TokenExpiredException) {
            return $this->apiError(ApiCode::message(ApiCode::TOKEN_EXPIRED), ApiCode::TOKEN_EXPIRED, 401);
        }

        if ($e instanceof TokenInvalidException) {
            return $this->apiError(ApiCode::message(ApiCode::UNAUTHENTICATED), ApiCode::UNAUTHENTICATED, 401);
        }

        if ($e instanceof JWTException) {
            Log::warning('JWT exception', [
                'path' => $request->path(),
                'exception' => get_class($e),
                'message' => $e->getMessage(),
            ]);

            return $this->apiError(ApiCode::message(ApiCode::UNAUTHENTICATED), ApiCode::UNAUTHENTICATED, 401);
        }

        if ($e instanceof AuthenticationException) {
            return $this->apiError(ApiCode::message(ApiCode::UNAUTHENTICATED), ApiCode::UNAUTHENTICATED, 401);
        }

        if ($e instanceof AuthorizationException) {
            return $this->apiError(ApiCode::message(ApiCode::FORBIDDEN), ApiCode::FORBIDDEN, 403);
        }

        if ($e instanceof ModelNotFoundException || $e instanceof NotFoundHttpException) {
            return $this->apiError(ApiCode::message(ApiCode::NOT_FOUND), ApiCode::NOT_FOUND, 404);
        }

        if ($e instanceof MethodNotAllowedHttpException) {
            return $this->apiError(ApiCode::message(ApiCode::METHOD_NOT_ALLOWED), ApiCode::METHOD_NOT_ALLOWED, 405);
        }

        // 500 及未预料的异常：记录完整堆栈便于排查
        Log::error('Unhandled exception', [
            'path' => $request->path(),
            'method' => $request->method(),
            'exception' => get_class($e),
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);

        $message = config('app.debug') ? $e->getMessage() : ApiCode::message(ApiCode::SERVER_ERROR);

        return $this->apiError($message, ApiCode::SERVER_ERROR, 500);
    }

    private function apiError(string $message, int $code, int $status, mixed $errors = null): JsonResponse
    {
        $payload = [
            'code' => $code,
            'message' => $message,
            'data' => null,
        ];

        if ($errors !== null) {
            $payload['errors'] = $errors;
        }

        return response()->json($payload, $status);
    }
}
