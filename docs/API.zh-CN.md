# API 参考

[English](API.md) · **简体中文**

Grotto 为每个平台提供 OpenAI 形状的 API。将 `{platform}` 替换为 `bailian`、`modelscope` 或 `volcengine`。

```text
https://grotto.example.com/{platform}/v1
```

百炼还提供工作空间路由：

```text
https://grotto.example.com/bailian/custom/{region}/{workspaceId}/v1
```

除特别说明外，所有路由均接受并返回 UTF-8 JSON。

## 认证

在 OpenAI 风格的 Bearer 请求头中发送**目标平台**的 API 密钥：

```http
Authorization: Bearer <平台 API 密钥>
```

`/bailian` 使用百炼密钥，`/modelscope` 使用 ModelScope 密钥，`/volcengine` 使用火山方舟密钥。

Grotto 会将密钥转发给对应平台，但不会保存。这里没有 Grotto 客户端令牌，没有服务器端上游密钥，也没有密钥生成命令。密钥由平台校验；无效密钥会以归一化的上游错误返回。缺失或格式错误的 Bearer 头会在本地以 `401 missing_api_key` 拒绝。

异步图片/视频轮询会在同一请求内复用该密钥。密钥不会写入日志、磁盘或限流存储。

## 能力

| 能力 | 方法与路径 | 结果 |
|---|---|---|
| 对话 | `POST /chat/completions` | JSON 补全或 SSE 流 |
| 图片 | `POST /images/generations` | 异步任务完成后返回 OpenAI 图片 JSON |
| 视频 | `POST /videos/generations` | 异步轮询完成后返回视频对象 |
| TTS | `POST /audio/speech` | 平台返回的二进制音频 |
| STT | `POST /audio/transcriptions` | multipart 转发到平台 |
| 模型 | `GET /models` | 归一化的 `{object, data[]}` 模型列表 |

平台不支持的能力会在发起上游请求前返回 `501`。未知路径返回 `404`。`/grotto/v1` 及其子路径始终返回 `418`。

## 对话

```bash
curl "$BASE_URL/chat/completions" \
  -H "Authorization: Bearer $BAILIAN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen-plus",
    "messages": [
      {"role": "user", "content": "用三条要点总结请求生命周期。"}
    ],
    "stream": true
  }'
```

- 必须提供 `model`。
- `messages` 必须是非空数组；每项需要字符串 `role` 和 `content` 键。
- `system` 处理遵循实例提示词策略。
- `stream: true` 仅对话支持，并以 SSE 透传。Grotto 不会缓冲完整响应。
- 客户端的 `Accept` 头不会作为上游流式判断依据；以 JSON 的 `stream` 字段为准。

流式帧格式为平台 OpenAI 兼容的 `chat.completion.chunk`，以以下内容结束：

```text
data: [DONE]
```

## 图片

```json
{
  "model": "wanx2.1-t2i-turbo",
  "prompt": "静谧的石灰岩洞窟通向钴蓝色大海。",
  "size": "1024x1024",
  "n": 1
}
```

百炼图片使用原生异步图片 API。ModelScope 图片使用其任务 API。火山方舟图片使用 Ark 图片端点。轮询完成后客户端收到 OpenAI 形状响应：

```json
{
  "created": 1792340000,
  "data": [
    {"url": "https://..."}
  ]
}
```

内联 base64 输出会清理元数据。仅包含远程 URL 的响应无法清理。`X-Grotto-Image-Sanitized` 响应头表示清理了多少张内联图片。

## 视频

```json
{
  "model": "wan2.2-t2v-plus",
  "prompt": "镜头缓慢穿过石灰岩洞窟。",
  "image_url": "https://.../start-frame.png",
  "duration": 5
}
```

Grotto 返回完成对象，而不是上游待处理任务：

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

这是 Grotto 对 OpenAI 接口的扩展。OpenAI 官方 Python SDK 没有视频方法；生成的 Python 示例使用 `requests`。

平台请求映射：

- **百炼：** 原生 `video-synthesis` 端点，使用 `X-DashScope-Async: enable`；任务在 `/api/v1/tasks/{id}` 轮询。
- **ModelScope：** `/v1/videos/generations`，使用 `X-ModelScope-Async-Mode: true`；任务在 `/v1/tasks/{id}` 轮询。
- **火山方舟：** `/api/v3/contents/generations/tasks`；在同一路径加 `/{id}` 轮询。

## 音频

TTS 将 JSON 请求转发到 OpenAI 兼容的 `/audio/speech` 端点，并流式返回二进制响应。STT 转发原始 `multipart/form-data` 请求体，以保持原始 boundary 有效。

音频支持取决于模型和平台。除非 `MODELSCOPE_AUDIO_ENABLED=true`，否则 ModelScope 音频路由不会公开。百炼和火山方舟路由要求所选上游模型实现兼容音频路径。

## 模型

`GET /models` 会归一化为：

```json
{
  "object": "list",
  "data": [
    {"id": "qwen-plus", "object": "model", "created": 0, "owned_by": "grotto"}
  ]
}
```

## 百炼工作空间路由

```text
/bailian/custom/cn-beijing/ws_abc123/v1/chat/completions
```

会映射为：

```text
https://ws_abc123.cn-beijing.maas.aliyuncs.com/compatible-mode/v1/chat/completions
```

校验规则：

- 地域必须形如 `cn-beijing` 或 `ap-southeast-1`。
- 工作空间 ID 可包含字母、数字、`_` 和 `-`。
- `BAILIAN_ALLOWED_REGIONS` 可限制允许的地域。
- 密钥必须属于该工作空间和地域；由于密钥按请求提供，Grotto 无法预先校验。

## 错误

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

| 状态码 | 常见错误码 |
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

所有错误响应都携带 `X-Grotto-Request-Id`。限流响应包含 `Retry-After` 和 `X-RateLimit-*` 响应头。

## 元数据端点

`GET /grotto/info` 和 `GET /grotto/health` 是公开端点。它们只暴露配置元数据，不暴露凭据：

- 已启用平台与声明的能力
- API 密钥策略（`Authorization: Bearer`，按请求提供，不存储）
- 界面支持的语言
- 提示词内容（或在隐藏时返回指纹）
- 图片清理范围
- 限流与异步设置
- 可安全公开的配置警告

健康检查返回 HTTP `200`。只要至少启用一个适配器，就会报告 `ready: true`，即使服务器上没有密钥，因为密钥由调用方提供。它会明确报告 `upstream_reachability: not_probed`。
