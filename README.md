# Grotto

**English** · [简体中文](README.zh-CN.md)

Grotto is a small OpenAI-compatible gateway for various platforms, currently support **Alibaba Cloud Bailian**, **ModelScope**, and **Volcengine Ark**. It exposes stable routes, translates platform-specific request and response shapes, applies an instance-level system prompt policy, polls asynchronous media jobs, proxies chat SSE without buffering, and removes image metadata before returning inline images.

Grotto is **bring-your-own-key**: the caller sends the API key for the target platform with every request, and Grotto forwards that key to the platform. Provider keys are never read from environment variables, written to disk, cached, or included in logs. The built-in verification UI is served at `/ui` and supports English and Simplified Chinese.

Grotto is a part of the Bridge Eighteen Community AI Lab.

## What is included

- Slim 4 / PSR-7 / PSR-15 HTTP surface with a configurable middleware pipeline.
- Platform adapters with a shared `PlatformAdapterInterface` and asynchronous task support.
- OpenAI-shaped chat, images, video, audio, and model-list routes.
- Per-request platform keys passed through as `Authorization: Bearer <platform-api-key>`.
- Bailian standard and workspace-scoped endpoint resolution.
- ModelScope async image/video task polling.
- Volcengine Ark video task polling.
- System prompt injection with explicit-prompt preservation.
- Unbuffered chat streaming.
- EXIF / IPTC / XMP stripping for inline base64 JPEG, PNG, and WebP images.
- Platform error normalization with credential and hostname redaction.
- Fixed-window rate limiting keyed by a one-way fingerprint of the caller's key, CORS, and metadata-only request logs.
- English and Simplified Chinese UI with a language switcher.
- PHPUnit coverage for mappings, security helpers, HTTP surface, and full proxy flow with a mocked upstream.

## Requirements

- PHP 8.2+ with `curl`, `json`, `mbstring`, `openssl`, and `sodium`.
- Composer 2.
- A platform API key held by the caller. The Grotto server does not need upstream keys.

The repository includes `composer.lock`. The built-in UI is plain HTML/CSS/JS and needs no Node build step.

## Quick start

```bash
composer install
cp .env.example .env
composer serve
```

Open <http://127.0.0.1:8080/ui>. Enter the API key for the platform you want to call in the playground; the key stays in that page's memory and is sent only with that page's requests.

Call the API directly by putting the platform key in the bearer header:

```bash
export BAILIAN_API_KEY="sk-..."
curl http://127.0.0.1:8080/bailian/v1/chat/completions \
  -H "Authorization: Bearer $BAILIAN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen-plus",
    "messages": [{"role": "user", "content": "Explain protocol translation in one paragraph."}],
    "stream": true
  }'
```

Check configuration without starting HTTP:

```bash
php bin/grotto check
```

There is no server-side key to generate or encrypt. The key is supplied by the caller at request time.

### Choosing the platform

Use the route that matches the key you are sending:

| Key belongs to | Route prefix |
|---|---|
| Alibaba Cloud Bailian | `/bailian/v1` |
| ModelScope | `/modelscope/v1` |
| Volcengine Ark | `/volcengine/v1` |

Grotto does not validate the key against a server-side copy. The platform validates it. Invalid or expired keys come back as a normalized upstream error.

## Endpoints

| Route | Purpose |
|---|---|
| `GET /grotto/info` | Instance metadata, capabilities, prompt policy, sanitizer state, language support. |
| `GET /grotto/health` | Process and adapter readiness. Platform keys are not checked, and upstream reachability is not probed. |
| `POST /bailian/v1/chat/completions` | Bailian chat, streaming or not. |
| `GET /bailian/v1/models` | Bailian model list. |
| `POST /bailian/v1/images/generations` | Bailian native async image task, normalized to OpenAI images. |
| `POST /bailian/v1/videos/generations` | Bailian async video task, normalized by Grotto. |
| `POST /bailian/v1/audio/speech` | Compatible audio route; model support depends on upstream. |
| `POST /bailian/v1/audio/transcriptions` | Multipart speech-to-text forwarding. |
| `/bailian/custom/{region}/{workspaceId}/v1/…` | Workspace-scoped Bailian domain. |
| `/modelscope/v1/…` | Chat, models, async images, async videos; audio opt-in. |
| `/volcengine/v1/…` | Chat, images, async videos, models, audio routes. |

All proxy routes require `Authorization: Bearer <platform-api-key>`. The platform key is forwarded to the selected platform and is never written to Grotto configuration or storage. Detailed request and response shapes are in [`docs/API.md`](docs/API.md) and [`docs/API.zh-CN.md`](docs/API.zh-CN.md).

### Bailian workspace routes

```text
http://127.0.0.1:8080/bailian/custom/cn-beijing/ws_abc123/v1/chat/completions
```

Grotto maps this to:

```text
https://ws_abc123.cn-beijing.maas.aliyuncs.com/compatible-mode/v1
```

Use a Bailian key that has access to that workspace and region. Grotto cannot verify the key's region ahead of time because it does not store the key. `BAILIAN_ALLOWED_REGIONS` optionally restricts accepted region values, and the region and workspace IDs are validated before a URL is built.

## Core behaviour

### API key flow

1. The client sends `Authorization: Bearer <key-for-the-target-platform>`.
2. Middleware extracts the key into the request object and computes a one-way fingerprint for rate limiting.
3. The adapter forwards the key to the platform in its own `Authorization` header.
4. The key is dropped when the request ends.

The raw key is not stored in `Config`, request logs, the rate limiter, the UI's server metadata, or `/grotto/info`. The web console keeps it only in page memory; it is not written to `localStorage`, `sessionStorage`, or cookies.

### System prompt policy

For chat requests only:

1. If there is no `system` message, Grotto prepends the configured default prompt.
2. If every `system` message is a low-information default such as “You are a helpful assistant”, they are replaced with the configured prompt.
3. If an explicit, non-default `system` message exists, Grotto leaves it untouched.

The low-information list lives in [`config/grotto.php`](config/grotto.php). `/grotto/info` exposes the prompt or a SHA-256 fingerprint depending on `GROTTO_EXPOSE_SYSTEM_PROMPT`.

### Images and metadata

For inline `b64_json` values and `data:image/...` URLs, Grotto removes:

- JPEG APP1 (EXIF / XMP), APP13 (IPTC), comment segments, and other non-essential APP metadata.
- PNG `eXIf`, `tEXt`, `zTXt`, `iTXt`, and `tIME` chunks.
- WebP `EXIF` and `XMP ` chunks.

Pixel data is never decoded or re-encoded. The response carries `X-Grotto-Image-Sanitized` with the number of cleaned images.

**Remote image URLs cannot be scrubbed by Grotto.** The gateway would have to fetch and re-upload an image to do that, which changes the response and is outside this design. `/grotto/info` reports this scope explicitly.

### Asynchronous media

Bailian and Volcengine video jobs, and ModelScope image/video jobs, are submitted and then polled by Grotto inside the same client request. The same caller key is used for the polling requests. The client receives a completed result, not an upstream task object.

Polling controls:

```dotenv
GROTTO_ASYNC_POLL_INTERVAL_MS=1000
GROTTO_ASYNC_TIMEOUT=300
GROTTO_ASYNC_MAX_ATTEMPTS=600
```

Keep `GROTTO_ASYNC_TIMEOUT` plus `GROTTO_UPSTREAM_TIMEOUT` below your web server / reverse proxy request timeout. If a job exceeds the limit, Grotto returns `504` with `<platform>_task_timeout`.

### Errors

Upstream errors become:

```json
{
  "error": {
    "message": "Bailian rejected the API key supplied for this request.",
    "type": "upstream_error",
    "code": "bailian_invalid_api_key",
    "param": null
  }
}
```

Raw upstream details go to the application log after URL, IP, token, email, and request-ID redaction. They are not returned to the client.

## Configuration

Copy `.env.example` and edit it. Grotto configuration does not contain provider credentials. The most important values:

| Variable | Default | Notes |
|---|---|---|
| `GROTTO_PUBLIC_BASE_URL` | request origin | Use the external HTTPS origin behind a proxy. |
| `GROTTO_TRUSTED_PROXIES` | empty | Trust `X-Forwarded-*` only from these proxy IPs/CIDRs. |
| `GROTTO_PLATFORMS` | all | Comma-separated platform IDs. |
| `GROTTO_SYSTEM_PROMPT` | built-in policy | Instance prompt. |
| `GROTTO_EXPOSE_SYSTEM_PROMPT` | `true` | If false, `/grotto/info` only exposes a hash. |
| `GROTTO_SANITIZE_IMAGES` | `true` | Toggle inline metadata stripping. |
| `GROTTO_CORS_ORIGINS` | `*` | Use explicit origins in production. |
| `GROTTO_RATE_LIMIT_*` | 60 / 60 s | Fixed-window limiter per key fingerprint or IP. |
| `GROTTO_UPSTREAM_TIMEOUT` | `120` | Seconds. |
| `GROTTO_UPSTREAM_VERIFY_SSL` | `true` | Do not disable outside local testing. |
| `GROTTO_ASYNC_*` | 1 s / 300 s | Media task polling. |
| `BAILIAN_BASE_URL` | DashScope | Upstream endpoint, not a credential. |
| `MODELSCOPE_BASE_URL` | ModelScope inference | Upstream endpoint, not a credential. |
| `VOLCENGINE_BASE_URL` | Ark | Upstream endpoint, not a credential. |

Full configuration is documented by `.env.example`; non-secret defaults live in `config/`.

## Languages

The web console supports English and Simplified Chinese. The language switcher is in the header and persists the choice in `localStorage`; the browser language is used on first visit. Chinese documentation:

- [README.zh-CN.md](README.zh-CN.md)
- [docs/API.zh-CN.md](docs/API.zh-CN.md)
- [docs/PLATFORMS.zh-CN.md](docs/PLATFORMS.zh-CN.md)
- [docs/DEPLOYMENT.zh-CN.md](docs/DEPLOYMENT.zh-CN.md)

## Libre WebUI

Libre WebUI discovers models and sends chat through an OpenAI-compatible plugin. Point `key_env` at the client-side environment variable that holds your own platform key. This variable belongs to Libre WebUI, not to Grotto:

```json
{
  "id": "grotto-bailian",
  "name": "Grotto · Bailian",
  "type": "completion",
  "endpoint": "https://grotto.example.com/bailian/v1/chat/completions",
  "base_url": "https://grotto.example.com/bailian/v1",
  "api_path": "/chat/completions",
  "api_mode": "chat_completions",
  "auth": {
    "header": "Authorization",
    "prefix": "Bearer ",
    "key_env": "BAILIAN_API_KEY"
  },
  "model_map": ["qwen-plus"]
}
```

Repeat with `/modelscope/v1` or `/volcengine/v1` for the other platforms. The plugin above covers chat and model discovery. Image, video, TTS, and STT capabilities should point at the corresponding Grotto capability endpoints directly rather than reusing the chat endpoint.

## Security notes

- Grotto never stores platform API keys. The key exists only in request memory while it is forwarded.
- There is no separate Grotto gateway password. Anyone who can reach the gateway can send their own platform key, so restrict access at the network or reverse-proxy layer if the instance is not meant to be public.
- Request logs contain method, path, platform, status, and duration only; bodies, headers, prompts, and keys are not logged.
- The rate limiter uses a one-way fingerprint of the key, not the key itself.
- The upstream HTTP client does not follow redirects, so credentials are not forwarded to a redirected host.
- CORS with credentials cannot be combined with a wildcard origin; Grotto disables credentials and warns.
- The file rate limiter is atomic on one host but not shared across hosts. Use a network limiter if you scale horizontally.
- The health endpoint reports adapter readiness, not live upstream availability or key validity.
- Serve Grotto over HTTPS. Callers are sending secrets in the `Authorization` header, and intermediary proxies must not log headers.

## Deployment notes

For complete Nginx and Apache + PHP-FPM instructions, including TLS, streaming, OPcache, permissions, and troubleshooting, see [`docs/DEPLOYMENT.md`](docs/DEPLOYMENT.md) ([简体中文](docs/DEPLOYMENT.zh-CN.md)). Ready-to-copy examples live in [`deploy/`](deploy/).

- Point the web server document root at `public/`; do not serve `src/`, `.env`, or `storage/` directly.
- Mount the app at the origin root. The UI and generated examples use root-absolute `/assets` and platform paths.
- Set `GROTTO_PUBLIC_BASE_URL` to the external HTTPS origin when TLS terminates at a reverse proxy. Alternatively, trust `X-Forwarded-*` headers only from addresses listed in `GROTTO_TRUSTED_PROXIES`.
- Disable proxy buffering for `/v1/chat/completions` streaming, and set the proxy read timeout above `GROTTO_ASYNC_TIMEOUT` plus `GROTTO_UPSTREAM_TIMEOUT` for media jobs.
- Use PHP-FPM workers sized for long-running media waits, or expose a separate asynchronous task API if your workload cannot hold workers.
- Keep one writable `storage/` directory. The default rate limiter is local to one host.
- Rotate provider keys at the platform; Grotto has no server-side key copy to rotate. Restart is not required for a key change.

## Development

```bash
composer test
composer lint
```

The integration suite uses Guzzle's mock handler and exercises the full Slim middleware stack, including system prompt injection, SSE pass-through, async image polling, sanitizer output, per-request key forwarding, missing-key rejection, and upstream error redaction.

```bash
composer serve   # http://127.0.0.1:8080
```

## Project layout

```text
src/Http        Slim controllers, routes, middleware, OpenAI errors
src/Platform    Adapter contract, shared DTOs, Bailian/ModelScope/Ark adapters
src/Core        Prompt policy, normalizers, streaming, image sanitizer, errors
src/Config      Environment-backed configuration (no provider keys)
src/Support     Logger, HTTP client factory, rate limiter
config/         Platform registry, middleware order, container definitions
deploy/         Nginx, Apache, PHP, and logrotate production examples
frontend/       Plain HTML/CSS/JS verification workbench (English and Chinese)
tests/          Unit and HTTP integration tests
```

To add a platform, see [`docs/PLATFORMS.md`](docs/PLATFORMS.md) or [`docs/PLATFORMS.zh-CN.md`](docs/PLATFORMS.zh-CN.md). The UI capability matrix automatically reflects the new platform through `/grotto/info`.

## License
AGPL-3.0-or-later. See [`LICENSE`](LICENSE).
