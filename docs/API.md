# API reference

**English** · [简体中文](API.zh-CN.md)

Grotto exposes an OpenAI-shaped API per platform. Replace `{platform}` with `bailian`, `modelscope`, or `volcengine`.

```text
https://grotto.example.com/{platform}/v1
```

Bailian workspace routes are also available:

```text
https://grotto.example.com/bailian/custom/{region}/{workspaceId}/v1
```

All routes accept and return UTF-8 JSON unless noted.

## Authentication

Send the API key **for the target platform** as an OpenAI-style bearer key:

```http
Authorization: Bearer <platform-api-key>
```

Use a Bailian key with `/bailian`, a ModelScope key with `/modelscope`, and a Volcengine Ark key with `/volcengine`.

Grotto forwards the key to that platform and does not store it. There is no Grotto client token, no server-side upstream key, and no key-generation command. The platform validates the key; an invalid key is returned as a normalized upstream error. Missing or malformed bearer headers are rejected locally with `401 missing_api_key`.

The same key is reused for asynchronous image/video polling within the request. It is never written to logs, disk, or the rate-limit store.

## Capabilities

| Capability | Method and path | Result |
|---|---|---|
| Chat | `POST /chat/completions` | JSON completion or SSE stream |
| Images | `POST /images/generations` | OpenAI image JSON after any async task completes |
| Videos | `POST /videos/generations` | Completed video object after async polling |
| TTS | `POST /audio/speech` | Binary audio from the platform |
| STT | `POST /audio/transcriptions` | Multipart forwarded to the platform |
| Models | `GET /models` | Normalized `{object, data[]}` model list |

Unsupported platform capabilities return `501` before any upstream request. Unknown paths return `404`. `/grotto/v1` and descendants always return `418`.

## Chat

```bash
curl "$BASE_URL/chat/completions" \
  -H "Authorization: Bearer $BAILIAN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen-plus",
    "messages": [
      {"role": "user", "content": "Summarize the request lifecycle in three bullets."}
    ],
    "stream": true
  }'
```

- `model` is required.
- `messages` must be a non-empty array; each item needs a string `role` and a `content` key.
- `system` handling follows the instance prompt policy.
- `stream: true` is supported for chat only and is passed through as SSE. Grotto does not buffer the complete response.
- The `Accept` header from the client is not forwarded as the upstream streaming decision; the JSON `stream` field is authoritative.

Streaming frame format is the platform's OpenAI-compatible `chat.completion.chunk` format, ending with:

```text
data: [DONE]
```

## Images

```json
{
  "model": "wanx2.1-t2i-turbo",
  "prompt": "A quiet limestone grotto opening onto a cobalt-blue sea.",
  "size": "1024x1024",
  "n": 1
}
```

Bailian images use the native async image API. ModelScope images use its task API. Volcengine images use the Ark image endpoint. The client gets an OpenAI-shaped response after polling:

```json
{
  "created": 1792340000,
  "data": [
    {"url": "https://..."}
  ]
}
```

Inline base64 output is metadata-stripped. Responses that only contain remote URLs cannot be scrubbed. The `X-Grotto-Image-Sanitized` header reports how many inline images were changed.

## Videos

```json
{
  "model": "wan2.2-t2v-plus",
  "prompt": "A slow dolly through a limestone grotto.",
  "image_url": "https://.../start-frame.png",
  "duration": 5
}
```

Grotto returns a completed object rather than an upstream pending task:

```json
{
  "id": "task-id",
  "object": "video",
  "created": 1792340000,
  "model": "wan2.2-t2v-plus",
  "status": "completed",
  "data": [{"url": "https://cdn.example/video.mp4"}]
}
```

This is a Grotto extension to the OpenAI surface. There is no official OpenAI Python SDK video method; the generated Python example uses `requests`.

Platform request mapping:

- **Bailian:** native `video-synthesis` endpoint with `X-DashScope-Async: enable`; task polled at `/api/v1/tasks/{id}`.
- **ModelScope:** `/v1/videos/generations` with `X-ModelScope-Async-Mode: true`; task polled at `/v1/tasks/{id}`.
- **Volcengine Ark:** `/api/v3/contents/generations/tasks`; task polled on the same path with `/{id}`.

## Audio

TTS forwards a JSON request to the OpenAI-compatible `/audio/speech` endpoint and streams the binary response. STT forwards the raw `multipart/form-data` body so the original boundary remains valid.

Audio support is model- and platform-dependent. ModelScope audio is hidden unless `MODELSCOPE_AUDIO_ENABLED=true`. Bailian and Volcengine routes require the selected upstream model to implement the compatible audio path.

## Models

`GET /models` is normalized:

```json
{
  "object": "list",
  "data": [
    {"id": "qwen-plus", "object": "model", "created": 0, "owned_by": "grotto"}
  ]
}
```

## Bailian workspace routes

```text
/bailian/custom/cn-beijing/ws_abc123/v1/chat/completions
```

becomes:

```text
https://ws_abc123.cn-beijing.maas.aliyuncs.com/compatible-mode/v1/chat/completions
```

Validation:

- Region must look like `cn-beijing` or `ap-southeast-1`.
- Workspace IDs may contain letters, numbers, `_`, and `-`.
- `BAILIAN_ALLOWED_REGIONS` can restrict accepted regions.
- The key must belong to the workspace and region; Grotto cannot pre-validate this because the key is supplied per request.

## Errors

```json
{
  "error": {
    "message": "The requested model is not available on ModelScope.",
    "type": "invalid_request_error",
    "code": "modelscope_model_not_found",
    "param": null
  }
}
```

| Status | Typical code |
|---|---|
| `400` | `invalid_json`, `missing_model`, `bailian_invalid_region` |
| `401` | `missing_api_key`, `invalid_api_key` |
| `404` | `endpoint_not_found`, `model_not_found` |
| `405` | `method_not_allowed` |
| `418` | `teapot` |
| `429` | `rate_limit_exceeded`, `<platform>_rate_limited`, `<platform>_quota_exceeded` |
| `501` | `<platform>_capability_not_supported` |
| `502` | `<platform>_invalid_api_key`, `<platform>_upstream_error` |
| `504` | `<platform>_task_timeout` |

All error responses carry `X-Grotto-Request-Id`. Rate-limited responses include `Retry-After` and the `X-RateLimit-*` headers.

## Metadata endpoints

`GET /grotto/info` and `GET /grotto/health` are public. They expose configuration metadata, not credentials:

- enabled platforms and declared capabilities
- API key policy (`Authorization: Bearer`, supplied per request, not stored)
- supported UI locales
- prompt content (or fingerprint if redacted)
- image-sanitizer scope
- rate-limit and async settings
- request-safe configuration warnings

Health returns HTTP `200`. It reports `ready: true` when at least one adapter is enabled, even when no keys exist on the server, because keys are supplied by callers. It explicitly reports `upstream_reachability: not_probed`.
