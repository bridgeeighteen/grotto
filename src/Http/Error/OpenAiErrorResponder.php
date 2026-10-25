<?php

declare(strict_types=1);

namespace Grotto\Http\Error;

use Grotto\Core\Exception\ApiException;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;

final class OpenAiErrorResponder
{
    public function __construct(
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function fromApiException(ApiException $exception): ResponseInterface
    {
        $response = $this->fromArray(
            $exception->status,
            $exception->toArray()
        );

        if ($exception->status === 401) {
            $response = $response->withHeader('WWW-Authenticate', 'Bearer');
        }

        return $response;
    }

    public function create(
        int $status,
        string $message,
        string $type = 'invalid_request_error',
        string $code = 'error',
        ?string $param = null,
    ): ResponseInterface {
        return $this->fromArray($status, [
            'error' => [
                'message' => $message,
                'type' => $type,
                'code' => $code,
                'param' => $param,
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function fromArray(int $status, array $payload): ResponseInterface
    {
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($json === false) {
            $json = '{"error":{"message":"The gateway could not encode an error response.","type":"server_error","code":"encoding_error","param":null}}';
            $status = 500;
        }

        return $this->responseFactory->createResponse($status)
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($json));
    }
}
