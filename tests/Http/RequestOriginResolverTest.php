<?php

declare(strict_types=1);

namespace Grotto\Tests\Http;

use Grotto\Http\RequestOriginResolver;
use Grotto\Tests\TestCase;
use GuzzleHttp\Psr7\ServerRequest;

final class RequestOriginResolverTest extends TestCase
{
    public function testConfiguredPublicBaseUrlWins(): void
    {
        $resolver = $this->resolver([
            'GROTTO_PUBLIC_BASE_URL' => 'https://public.example/',
            'GROTTO_TRUSTED_PROXIES' => '10.0.0.0/8',
        ]);
        $request = $this->request('http://internal.example/grotto/info', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'forwarded.example',
        ], '10.1.2.3');

        self::assertSame('https://public.example', $resolver->resolve($request));
    }

    public function testForwardedHeadersFromAnUntrustedPeerAreIgnored(): void
    {
        $resolver = $this->resolver();
        $request = $this->request('http://internal.example/grotto/info', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'grotto.example.com',
            'X-Forwarded-Port' => '8443',
        ], '203.0.113.10');

        self::assertSame('http://internal.example', $resolver->resolve($request));
    }

    public function testTrustedIpv4CidrUsesForwardedSchemeAndHost(): void
    {
        $resolver = $this->resolver([
            'GROTTO_TRUSTED_PROXIES' => '10.0.0.0/8',
        ]);
        $request = $this->request('http://internal.example/grotto/info', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'grotto.example.com',
        ], '10.2.3.4');

        self::assertSame('https://grotto.example.com', $resolver->resolve($request));
    }

    public function testTrustedProxyUsesForwardedPortAndOmitsDefaultPorts(): void
    {
        $resolver = $this->resolver([
            'GROTTO_TRUSTED_PROXIES' => '192.0.2.10',
        ]);

        $defaultPort = $this->request('http://internal.example/grotto/info', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'grotto.example.com:443',
        ], '192.0.2.10');
        $customPort = $this->request('http://internal.example/grotto/info', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'grotto.example.com',
            'X-Forwarded-Port' => '8443',
        ], '192.0.2.10');

        self::assertSame('https://grotto.example.com', $resolver->resolve($defaultPort));
        self::assertSame('https://grotto.example.com:8443', $resolver->resolve($customPort));
    }

    public function testTrustedIpv6CidrIsSupported(): void
    {
        $resolver = $this->resolver([
            'GROTTO_TRUSTED_PROXIES' => '2001:db8::/32',
        ]);
        $request = $this->request('http://internal.example/grotto/info', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => '[2001:db8::10]',
        ], '2001:db8::1');

        self::assertSame('https://[2001:db8::10]', $resolver->resolve($request));
    }

    public function testInvalidForwardedHostIsIgnored(): void
    {
        $resolver = $this->resolver([
            'GROTTO_TRUSTED_PROXIES' => '10.0.0.0/8',
        ]);
        $request = $this->request('http://internal.example/grotto/info', [
            'X-Forwarded-Proto' => 'https',
            'X-Forwarded-Host' => 'grotto.example.com/evil',
        ], '10.1.2.3');

        self::assertSame('https://internal.example', $resolver->resolve($request));
    }

    /**
     * @param array<string, string> $overrides
     */
    private function resolver(array $overrides = []): RequestOriginResolver
    {
        return new RequestOriginResolver($this->config(array_merge([
            'GROTTO_PUBLIC_BASE_URL' => '',
            'GROTTO_TRUSTED_PROXIES' => '',
        ], $overrides)));
    }

    /**
     * @param array<string, string> $headers
     */
    private function request(string $uri, array $headers = [], string $remoteAddress = '203.0.113.10'): ServerRequest
    {
        return new ServerRequest('GET', $uri, $headers, null, '1.1', [
            'REMOTE_ADDR' => $remoteAddress,
        ]);
    }
}
