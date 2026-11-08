<?php

declare(strict_types=1);

namespace Grotto\Http;

use Grotto\Config\Config;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Resolves the external origin used in generated URLs.
 *
 * GROTTO_PUBLIC_BASE_URL always wins. Otherwise the request URI is used, and
 * X-Forwarded-* headers are honored only when the direct peer is listed in
 * GROTTO_TRUSTED_PROXIES. This prevents public clients from spoofing the
 * scheme or host shown in /grotto/info.
 */
final class RequestOriginResolver
{
    public function __construct(
        private readonly Config $config,
    ) {
    }

    public function resolve(ServerRequestInterface $request): string
    {
        $configured = $this->config->publicBaseUrl();

        if ($configured !== null) {
            return $configured;
        }

        $uri = $request->getUri();
        $scheme = strtolower($uri->getScheme());

        if ($scheme !== 'http' && $scheme !== 'https') {
            $scheme = 'http';
        }

        $host = $uri->getHost() !== '' ? $uri->getHost() : 'localhost';
        $port = $uri->getPort();

        if ($this->isTrustedProxy($request)) {
            $forwardedScheme = strtolower((string) $this->firstHeaderValue($request, 'X-Forwarded-Proto'));

            if ($forwardedScheme === 'http' || $forwardedScheme === 'https') {
                $scheme = $forwardedScheme;
                // The TLS terminator's port is not the backend port.
                $port = null;
            }

            $forwardedHost = $this->parseAuthority((string) $this->firstHeaderValue($request, 'X-Forwarded-Host'));

            if ($forwardedHost !== null) {
                [$host, $port] = $forwardedHost;
            }

            $forwardedPort = $this->parsePort($this->firstHeaderValue($request, 'X-Forwarded-Port'));

            if ($forwardedPort !== null) {
                $port = $forwardedPort;
            }
        }

        $authority = $this->formatHost($host);

        if ($port !== null && !$this->isDefaultPort($scheme, $port)) {
            $authority .= ':' . $port;
        }

        return $scheme . '://' . $authority;
    }

    private function isTrustedProxy(ServerRequestInterface $request): bool
    {
        $remoteAddress = $request->getServerParams()['REMOTE_ADDR'] ?? null;

        if (!is_string($remoteAddress) || $remoteAddress === '') {
            return false;
        }

        foreach ($this->config->trustedProxies() as $proxy) {
            if ($this->ipMatches($remoteAddress, $proxy)) {
                return true;
            }
        }

        return false;
    }

    private function firstHeaderValue(ServerRequestInterface $request, string $header): ?string
    {
        $value = $request->getHeaderLine($header);

        if ($value === '') {
            return null;
        }

        $first = trim(explode(',', $value, 2)[0]);

        return $first === '' ? null : $first;
    }

    /**
     * @return array{0: string, 1: int|null}|null
     */
    private function parseAuthority(string $value): ?array
    {
        if ($value === '' || preg_match('/[\x00-\x20\x7F\/\\\\@]/', $value) === 1) {
            return null;
        }

        if (preg_match(
            '/^(\[[0-9A-Fa-f:.]+\]|[A-Za-z0-9](?:[A-Za-z0-9.-]*[A-Za-z0-9])?)(?::([0-9]{1,5}))?$/',
            $value,
            $matches
        ) !== 1) {
            return null;
        }

        $port = isset($matches[2]) && $matches[2] !== '' ? (int) $matches[2] : null;

        if ($port !== null && ($port < 1 || $port > 65535)) {
            return null;
        }

        return [$matches[1], $port];
    }

    private function parsePort(?string $value): ?int
    {
        if ($value === null || $value === '' || !ctype_digit($value)) {
            return null;
        }

        $port = (int) $value;

        return $port >= 1 && $port <= 65535 ? $port : null;
    }

    private function formatHost(string $host): string
    {
        $host = trim($host);

        if ($host === '') {
            return 'localhost';
        }

        if (str_contains($host, ':') && !str_starts_with($host, '[')) {
            return '[' . $host . ']';
        }

        return $host;
    }

    private function isDefaultPort(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
    }

    private function ipMatches(string $address, string $rule): bool
    {
        $rule = trim($rule);

        if ($rule === '') {
            return false;
        }

        if (!str_contains($rule, '/')) {
            $addressPacked = @inet_pton($address);
            $rulePacked = @inet_pton($rule);

            return $addressPacked !== false
                && $rulePacked !== false
                && $addressPacked === $rulePacked;
        }

        [$subnet, $prefix] = explode('/', $rule, 2);

        if (!ctype_digit($prefix)) {
            return false;
        }

        $addressPacked = @inet_pton($address);
        $subnetPacked = @inet_pton($subnet);

        if (
            $addressPacked === false
            || $subnetPacked === false
            || strlen($addressPacked) !== strlen($subnetPacked)
        ) {
            return false;
        }

        $bits = (int) $prefix;
        $maxBits = strlen($addressPacked) * 8;

        if ($bits < 0 || $bits > $maxBits) {
            return false;
        }

        $wholeBytes = intdiv($bits, 8);
        $remainingBits = $bits % 8;

        if (
            $wholeBytes > 0
            && substr($addressPacked, 0, $wholeBytes) !== substr($subnetPacked, 0, $wholeBytes)
        ) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($addressPacked[$wholeBytes]) & $mask)
            === (ord($subnetPacked[$wholeBytes]) & $mask);
    }
}
