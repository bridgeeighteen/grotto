# AGENTS.md

Grotto is a PHP 8.2+ / Slim 4 OpenAI-compatible gateway in front of Alibaba Cloud Bailian, ModelScope, and Volcengine Ark. Callers supply the target platform's API key on every request; Grotto forwards it and never stores provider keys. The verification UI is plain HTML/CSS/JS served from `frontend/`; it supports English and Simplified Chinese, and there is no Node build step.

## Commands

- `composer install` — first-time setup (`vendor/` is gitignored).
- `composer test` — full PHPUnit suite. Single file: `vendor/bin/phpunit tests/Http/ProxyIntegrationTest.php`. Single test: `vendor/bin/phpunit --filter testName`.
- `composer lint` — syntax check only (`php -l`) over `src tests public`. It does **not** cover `config/` or `bin/grotto`; lint those manually.
- `composer serve` — dev server at <http://127.0.0.1:8080/ui>. Requires a local `.env` copied from `.env.example`.
- `php bin/grotto check` — validate configuration without HTTP. It is the only CLI command; there is no server-side key generation or encryption.
- No static analyzer, formatter, or CI workflow is configured; do not invent those commands.
- PHP needs `curl`, `json`, `mbstring`, `openssl`, and `sodium`. Verify `php -v` works before claiming tests ran.

## Architecture

- Entrypoint: `public/index.php` → `ApplicationFactory::create()` → routes in `src/Http/Route/ApiRoutes.php`.
- `config/platforms.php` is the adapter registry. Adapters live in `src/Platform/<Name>/`, extend `AbstractAdapter`, and handle protocol translation; async pollers extend `AbstractAsyncTask`. Each entry may include `label_zh` / `short_label_zh` for the Chinese UI. The registry declares upstream base URLs, not API keys.
- Adding a platform requires the adapter, its `config/platforms.php` entry, and a route group in `ApiRoutes.php`. The adapter's `id()` must match its registry key. `docs/PLATFORMS.md` implies routes are registered automatically; they are not.
- Supported paths are whitelisted in `PlatformCapability::fromPath()`. Adding or renaming an OpenAI route also requires updating that mapping.
- Register the Bailian workspace group before the generic `/bailian` group; otherwise `custom/{region}/...` is swallowed.
- Middleware order in `config/middleware.php` is outermost-first and `ApplicationFactory` reverses it for Slim. Keep CORS outside key extraction so preflights work, and rate limiting after extraction so it can key on a one-way fingerprint of the caller's platform key.
- Cross-platform behavior belongs in `src/Core` or the HTTP layer, not in adapters. Adapters must take the caller key from `RequestContext` and use platform endpoint resolvers; do not read `$_ENV`, `Config`, or storage for credentials, and do not build upstream hosts from raw route input.
- `/grotto/v1*` is reserved and always returns `418`. `/grotto/info` drives the UI capability matrix and examples.

## Invariants

- Never return raw upstream errors; route them through `UpstreamErrorMapper`. Never log raw prompts, response bodies, image bytes, or credentials.
- Treat the caller's `Authorization: Bearer <key>` as the target platform key. Forward it only to the selected platform; never read provider keys from environment variables, files, caches, or storage, and never persist the key.
- Keep Guzzle redirects disabled; credentials must not follow a redirect to another host.
- Bailian workspace routes must validate region/workspace through `BailianEndpointResolver` before constructing a hostname.
- Async media polling happens inline in the client request and must reuse the same caller key. `resolveAsync()` must return a completed OpenAI-shaped asset, never an upstream task object. Unknown states stay pending; timeouts map to `504`.
- Rate limiting may use a one-way key fingerprint, but must never receive or persist the raw key. Missing proxy keys fail locally with `401 missing_api_key`.
- System prompt injection applies to chat only and must preserve explicit non-default prompts. The low-information list is in `config/grotto.php`.
- Image sanitization only touches inline base64 / `data:image` values and never decodes or re-encodes pixels. Remote image URLs are intentionally left untouched.
- UI assets are allowlisted in `UiController::ASSETS` and read from `frontend/`. Adding an asset requires updating that map; `public/assets/` is for deployment-specific files.

## Configuration

- Defaults belong in `config/*.php`; environment parsing/overrides belong in `Config` and `Support/Env`. Read values via `Env::get/bool/int/list`, never `getenv()` directly, so tests stay isolated.
- `.env` is loaded immutably by `ApplicationFactory`, and `Env::override()` wins. Tests can pick up a local `.env`, so explicitly override anything an assertion depends on.
- `Env::override()` replaces the entire override map; the `TestCase` helpers merge `defaultEnvironment()` for you.
- Provider API keys are not configuration. Never add `*_API_KEY` variables, secret envelopes, key caches, or key-generation commands. The only credential is the per-request bearer token supplied by the caller.
- `Config` is immutable and caches platform metadata; construct a new `Config` after changing environment values.
- `storage/` must be writable for logs and the file rate limiter. The limiter is local to one host, not shared horizontally.

## Testing

- `tests/bootstrap.php` resets `Env`; `TestCase` defaults disable rate limiting and compress async timings. Use `$this->app($overrides)` or `ApplicationFactory::create($env, $definitions)`.
- Proxy tests must send `Authorization: Bearer <test-platform-key>` and assert that exact key reaches the mocked upstream, including async poll requests.
- Integration tests inject a Guzzle `MockHandler` as the `ClientInterface` container definition; never call real upstreams. Follow `tests/Http/ProxyIntegrationTest.php`.
- PHPUnit runs with `failOnWarning` and `failOnRisky`; keep tests warning-free.
- HTTP tests call `Slim\App::handle()` with `GuzzleHttp\Psr7\ServerRequest`; no web server or network is required.

Detailed API shapes are in `docs/API.md` and `docs/API.zh-CN.md`; adapter guidance is in `docs/PLATFORMS.md` and `docs/PLATFORMS.zh-CN.md` (apart from the route-registration note above). The UI translations live in the `I18N` object in `frontend/app.js`; add both English and Chinese strings when changing copy or documentation.
