# Platform adapters

**English** · [简体中文](PLATFORMS.zh-CN.md)

A platform adapter answers two questions:

1. How does an OpenAI-shaped request become an upstream request?
2. How does the upstream response become an OpenAI-shaped response?

Key extraction, CORS, rate limiting, route selection, logging, prompt policy, and client-facing errors stay in the HTTP/core layers. Adapters only consume the key that the caller supplied for this request.

## Contract

```php
interface PlatformAdapterInterface
{
    public function id(): string;

    public function toUpstream(
        string $capability,
        array $openAiBody,
        RequestContext $ctx
    ): UpstreamRequest;

    public function toOpenAi(
        string $capability,
        ResponseInterface $upstreamResponse,
        RequestContext $ctx
    ): OpenAiResponse;

    public function supports(string $capability): bool;

    /** @return list<string> */
    public function capabilities(): array;

    public function needsAsyncPolling(
        string $capability,
        ResponseInterface $submission
    ): bool;

    public function resolveAsync(
        string $capability,
        ResponseInterface $submission,
        RequestContext $ctx
    ): OpenAiResponse;
}
```

`RequestContext` contains the platform, capability, request ID, client fingerprint, stream flag, route path, optional Bailian region/workspace, model, content type, multipart body, route args, and the **caller-supplied platform API key** for this request. Adapters should not depend on superglobals, configuration, or stored credentials.

`UpstreamRequest` carries method, absolute URI, headers, body (string or PSR stream), and stream flag. `OpenAiResponse` carries a status, a string or PSR stream body, and response headers.

## Standard routing

The adapter registry is defined in [`config/platforms.php`](../config/platforms.php). Each entry declares:

- `adapter`: class resolved through PHP-DI
- `route`: the first URL segment
- `base_url_env`, `default_base_url`
- `capabilities`
- optional capabilities, localized labels, and default model metadata

There is deliberately no `api_key_env`. Provider keys are never read from the environment.

The current adapters are:

| Platform | Route | Notes |
|---|---|---|
| Bailian | `/bailian` | Compatible chat/models/audio; native async image/video. |
| ModelScope | `/modelscope` | Compatible chat/models; async image/video; audio opt-in. |
| Volcengine Ark | `/volcengine` | Compatible chat/images/models; async video task API. |

Users select the route that matches the key they hold. A Bailian key goes to `/bailian`, a ModelScope key to `/modelscope`, and a Volcengine key to `/volcengine`.

## Adding a platform

1. Add a directory under `src/Platform/YourPlatform`.
2. Implement `PlatformAdapterInterface`, usually by extending `AbstractAdapter`.
3. Add the platform entry to `config/platforms.php`.
4. Add any endpoint resolver inside the platform directory.
5. Add any asynchronous task class extending `AbstractAsyncTask`.
6. Add unit tests for request mapping, endpoint validation, per-request key forwarding, and async terminal-state handling.
7. Add an HTTP integration test if the mapping has security-sensitive validation.

The Slim routes do not need to change if the platform uses a first-segment route. Add a new route group only when a platform needs an entirely different path prefix policy. Remember to register the route group in `src/Http/Route/ApiRoutes.php`; routes are not generated automatically from the registry.

The frontend capability matrix and generator read `/grotto/info`, so a registered platform appears automatically. Add `label_zh` and `short_label_zh` to the registry entry to localize the platform name in the Chinese UI.

## Async task rules

- Return a completed OpenAI-shaped result from `resolveAsync()`.
- Terminal states must be explicit. Treat unknown/missing state as pending.
- Use `UpstreamErrorMapper` for poll failures and map timeout to `504`.
- Never return an upstream task object as if it were a completed asset.
- Poll interval, total timeout, and max attempts come from `Config::async()`.
- Reuse the caller's key for polling: build poll headers from the same `RequestContext` key.
- Do not log prompt text, image bytes, or credentials in polling logs.

## Security rules

- Do not trust route segments when constructing hosts. Bailian validates both region and workspace ID and uses a fixed hostname template.
- Do not follow redirects. The shared Guzzle client disables redirects so bearer credentials are not forwarded to an unexpected host.
- Never return raw upstream error payloads. Use `UpstreamErrorMapper`.
- Read the platform key from `RequestContext` only. Never read it from `$_ENV`, `Config`, a database, a cache, or a file.
- Never log the key, echo it in errors, or persist it in rate-limit storage. The rate limiter receives a one-way fingerprint.
- Do not pass `X-Grotto-*` headers upstream unless a platform explicitly needs them.
- Preserve user-specified system prompts; prompt policy belongs to `SystemPromptInjector`.

## Testing checklist

- Request URI and method.
- Upstream authorization header matches the caller's platform key.
- A query with no bearer header returns `401 missing_api_key` without calling upstream.
- Async polling uses the same key as the initial request.
- Platform-specific async headers.
- Body mapping and parameter normalization.
- Terminal state detection.
- Final response shape.
- Error sanitization.
- Region/workspace or other URL injection rejection.
- If the platform supports streamed chat: `Accept: text/event-stream` and no buffering.
