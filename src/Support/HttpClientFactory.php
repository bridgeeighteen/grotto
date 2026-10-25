<?php

declare(strict_types=1);

namespace Grotto\Support;

use Grotto\Config\Config;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;

final class HttpClientFactory
{
    public static function create(Config $config): ClientInterface
    {
        $http = $config->http();

        return new Client([
            'timeout' => $http['timeout'],
            'connect_timeout' => $http['connect_timeout'],
            'verify' => $http['verify'],
            'http_errors' => false,
            'allow_redirects' => false,
            'cookies' => false,
            'headers' => [
                'User-Agent' => $http['user_agent'],
                'Accept' => 'application/json',
            ],
        ]);
    }
}
