<?php

namespace App\Exceptions\Api;

use App\Constants\ApiCode;
use RuntimeException;

class BusinessException extends RuntimeException
{
    public function __construct(
        string $message = 'Business error',
        protected int $businessCode = ApiCode::BAD_REQUEST,
        protected int $httpStatus = 400,
        protected mixed $errors = null
    ) {
        parent::__construct($message, $businessCode);
    }

    /**
     * 用 ApiCode 常量构造异常：自动填充前端展示文案与默认 HTTP 状态。
     *
     * 用法：throw BusinessException::fromCode(ApiCode::LOGIN_FAILED, $errors);
     */
    public static function fromCode(
        int $code,
        mixed $errors = null,
        ?int $httpStatus = null
    ): self {
        return new self(
            ApiCode::message($code),
            $code,
            $httpStatus ?? ApiCode::httpStatus($code),
            $errors
        );
    }

    public function businessCode(): int
    {
        return $this->businessCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public function errors(): mixed
    {
        return $this->errors;
    }
}
