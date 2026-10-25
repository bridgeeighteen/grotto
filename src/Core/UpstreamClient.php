<?php

declare(strict_types=1);

namespace Grotto\Core;

use Grotto\Platform\UpstreamRequest;
use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

final class UpstreamClient
{
    public function __construct(
        private readonly ClientInterface $http,
    ) {
    }

    public function send(UpstreamRequest $request): ResponseInterface
    {
        $options = [
            'headers' => $request->headers,
            'stream' => $request->stream,
            'http_errors' => false,
        ];

        if ($request->body !== null) {
            $options['body'] = $request->body;
        }

        return $this->http->request($request->method, $request->uri, $options);
    }
}
