<?php

declare(strict_types=1);

namespace Grotto\Http\Middleware;

use Grotto\Core\Exception\ApiException;
use Grotto\Core\UpstreamErrorMapper;
use Grotto\Http\Error\OpenAiErrorResponder;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Slim\Exception\HttpMethodNotAllowedException;
use Slim\Exception\HttpNotFoundException;
use Throwable;

/**
 * Sits inside the observability/CORS stack and outside routing, so rendered
 * errors still pass back through CORS and request logging.
 */
final class JsonErrorMiddleware implements MiddlewareInterface
{
    public function __construct(
        private readonly OpenAiErrorResponder $errors,
        private readonly LoggerInterface $logger,
        private readonly UpstreamErrorMapper $redactor,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (ApiException $exception) {
            if ($exception->status >= 500) {
                $this->logger->warning('Gateway error response', [
                    'request_id' => $request->getAttribute('grotto.request_id'),
                    'status' => $exception->status,
                    'code' => $exception->errorCode,
                ]);
            }

            return $this->errors->fromApiException($exception);
        } catch (HttpNotFoundException $exception) {
            return $this->errors->fromApiException(ApiException::notFound(
                'The requested Grotto endpoint does not exist.',
                'endpoint_not_found'
            ));
        } catch (HttpMethodNotAllowedException $exception) {
            $response = $this->errors->fromApiException(new ApiException(
                405,
                'invalid_request_error',
                'method_not_allowed',
                'This endpoint does not accept the request method.'
            ));

            return $response->withHeader('Allow', implode(', ', $exception->getAllowedMethods()));
        } catch (Throwable $exception) {
            $this->logger->error('Unhandled gateway exception', [
                'request_id' => $request->getAttribute('grotto.request_id'),
                'exception' => $exception::class,
                'file' => basename($exception->getFile()),
                'line' => $exception->getLine(),
                'detail' => $this->redactor->redact($exception->getMessage()),
            ]);

            return $this->errors->fromApiException(new ApiException(
                500,
                'server_error',
                'internal_error',
                'Grotto encountered an internal error. The request was not completed.'
            ));
        }
    }
}
