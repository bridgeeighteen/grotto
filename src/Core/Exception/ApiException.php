<?php

declare(strict_types=1);

namespace Grotto\Core\Exception;

use RuntimeException;
use Throwable;

final class ApiException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorType,
        public readonly string $errorCode,
        string $message,
        public readonly ?string $param = null,
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * @return array{error: array{message: string, type: string, code: string, param: string|null}}
     */
    public function toArray(): array
    {
        return [
            'error' => [
                'message' => $this->getMessage(),
                'type' => $this->errorType,
                'code' => $this->errorCode,
                'param' => $this->param,
            ],
        ];
    }

    public static function invalidRequest(string $message, string $code = 'invalid_request', ?string $param = null): self
    {
        return new self(400, 'invalid_request_error', $code, $message, $param);
    }

    public static function notFound(string $message, string $code = 'not_found'): self
    {
        return new self(404, 'invalid_request_error', $code, $message);
    }

    public static function unsupported(string $message, string $code = 'unsupported_capability'): self
    {
        return new self(501, 'invalid_request_error', $code, $message);
    }

    public static function unauthorized(string $message, string $code = 'invalid_api_key'): self
    {
        return new self(401, 'authentication_error', $code, $message);
    }

    public static function serviceUnavailable(string $message, string $code = 'service_unavailable'): self
    {
        return new self(503, 'service_unavailable', $code, $message);
    }

    public static function upstream(string $message, string $code = 'upstream_error', int $status = 502): self
    {
        return new self($status, 'upstream_error', $code, $message);
    }
}
