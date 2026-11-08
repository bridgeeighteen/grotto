# Grotto

[English](README.md) · **简体中文**

Grotto 是一个面向 **阿里云百炼**、**ModelScope（魔搭）** 和 **火山方舟** 的轻量 OpenAI 兼容网关。它提供稳定的 `/v1` 路由，转换各平台的请求与响应结构，应用实例级系统提示词策略，轮询异步媒体任务，以无缓冲方式代理对话 SSE，并在返回内联图片前移除图片元数据。

Grotto 采用 **自带密钥（BYOK）** 模式：调用方在每次请求中携带目标平台的 API 密钥，Grotto 将该密钥直接转发给平台。平台密钥不会从环境变量读取，不会写入磁盘或缓存，也不会出现在日志中。内置验证界面位于 `/ui`，支持英文和简体中文。

## 功能

- Slim 4 / PSR-7 / PSR-15 HTTP 接口，可配置中间件管道。
- 基于 `PlatformAdapterInterface` 的平台适配器与异步任务支持。
- OpenAI 形状的对话、图片、视频、音频和模型列表路由。
- 通过 `Authorization: Bearer <平台 API 密钥>` 按请求透传平台密钥。
- 百炼标准端点与工作空间端点解析。
- ModelScope 异步图片/视频任务轮询。
- 火山方舟视频任务轮询。
- 系统提示词注入，并保留显式提示词。
- 无缓冲对话流式输出。
- 清理内联 base64 JPEG、PNG、WebP 图片中的 EXIF / IPTC / XMP。
- 平台错误归一化，并移除凭据与主机名。
- 固定窗口速率限制（按密钥单向指纹）、CORS 和仅元数据请求日志。
- 英文与简体中文界面，可随时切换语言。
- PHPUnit 覆盖映射、安全辅助、HTTP 接口和带模拟上游的完整代理流程。

## 环境要求

- PHP 8.2+，需要 `curl`、`json`、`mbstring`、`openssl` 和 `sodium`。
- Composer 2。
- 调用方自己持有的平台 API 密钥。Grotto 服务器不需要配置上游密钥。

仓库包含 `composer.lock`。内置界面为纯 HTML/CSS/JS，无需 Node 构建步骤。

## 快速开始

```bash
composer install
cp .env.example .env
composer serve
```

打开 <http://127.0.0.1:8080/ui>。在调试区输入要调用平台的 API 密钥；该密钥只保留在当前页面内存中，并只随该页面的请求发送。

也可以直接在 Bearer 请求头中携带平台密钥调用 API：

```bash
export BAILIAN_API_KEY="sk-..."
curl http://127.0.0.1:8080/bailian/v1/chat/completions \
  -H "Authorization: Bearer $BAILIAN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "model": "qwen-plus",
    "messages": [{"role": "user", "content": "用一段话解释协议转换。"}],
    "stream": true
  }'
```

无需启动 HTTP 即可检查配置：

```bash
php bin/grotto check
```

服务器端没有需要生成或加密的密钥。密钥由调用方在请求时提供。

### 选择平台

请使用与所持密钥对应的路由：

| 密钥所属平台 | 路由前缀 |
|---|---|
| 阿里云百炼 | `/bailian/v1` |
| ModelScope（魔搭） | `/modelscope/v1` |
| 火山方舟 | `/volcengine/v1` |

Grotto 不会用服务器端副本校验密钥，而是由平台校验。无效或过期的密钥会以归一化的上游错误返回。

## 端点

| 路由 | 用途 |
|---|---|
| `GET /grotto/info` | 实例元数据、能力、提示词策略、图片清理状态、语言支持。 |
| `GET /grotto/health` | 进程与适配器就绪状态。不检查平台密钥，也不探测上游可达性。 |
| `POST /bailian/v1/chat/completions` | 百炼对话，支持流式或非流式。 |
| `GET /bailian/v1/models` | 百炼模型列表。 |
| `POST /bailian/v1/images/generations` | 百炼原生异步图片任务，归一化为 OpenAI 图片响应。 |
| `POST /bailian/v1/videos/generations` | 百炼异步视频任务，由 Grotto 归一化。 |
| `POST /bailian/v1/audio/speech` | 兼容音频路由；模型支持情况取决于上游。 |
| `POST /bailian/v1/audio/transcriptions` | multipart 语音转文本转发。 |
| `/bailian/custom/{region}/{workspaceId}/v1/…` | 工作空间级百炼域名。 |
| `/modelscope/v1/…` | 对话、模型、异步图片、异步视频；音频需显式启用。 |
| `/volcengine/v1/…` | 对话、图片、异步视频、模型、音频路由。 |

所有代理路由都需要 `Authorization: Bearer <平台 API 密钥>`。平台密钥会转发到所选平台，绝不会写入 Grotto 配置或存储。详细请求与响应结构见 [`docs/API.zh-CN.md`](docs/API.zh-CN.md) 和 [`docs/API.md`](docs/API.md)。

### 百炼工作空间路由

```text
http://127.0.0.1:8080/bailian/custom/cn-beijing/ws_abc123/v1/chat/completions
```

Grotto 会映射为：

```text
https://ws_abc123.cn-beijing.maas.aliyuncs.com/compatible-mode/v1
```

请使用有权访问该工作空间和地域的百炼密钥。由于 Grotto 不存储密钥，无法预先校验密钥所属地域。`BAILIAN_ALLOWED_REGIONS` 可限制允许的地域值；地域和工作空间 ID 会在构造 URL 前校验。

## 核心行为

### API 密钥流程

1. 客户端发送 `Authorization: Bearer <目标平台密钥>`。
2. 中间件将密钥提取到请求对象，并计算单向指纹用于速率限制。
3. 适配器将密钥放入发往平台的 `Authorization` 请求头。
4. 请求结束后密钥即被丢弃。

原始密钥不会保存在 `Config`、请求日志、速率限制器、服务器元数据或 `/grotto/info` 中。Web 控制台只在页面内存中保留密钥，不写入 `localStorage`、`sessionStorage` 或 Cookie。

### 系统提示词策略

仅对对话请求生效：

1. 如果没有 `system` 消息，Grotto 会添加配置的默认提示词。
2. 如果所有 `system` 消息都是低信息量默认值（例如 “You are a helpful assistant”），则替换为配置的提示词。
3. 如果存在显式的非默认 `system` 消息，Grotto 不做修改。

低信息量提示词列表位于 [`config/grotto.php`](config/grotto.php)。`/grotto/info` 会根据 `GROTTO_EXPOSE_SYSTEM_PROMPT` 返回提示词内容或 SHA-256 指纹。

### 图片与元数据

对于内联 `b64_json` 值和 `data:image/...` URL，Grotto 会移除：

- JPEG APP1（EXIF / XMP）、APP13（IPTC）、注释段及其他非必要 APP 元数据。
- PNG `eXIf`、`tEXt`、`zTXt`、`iTXt` 和 `tIME` 块。
- WebP `EXIF` 和 `XMP ` 块。

不会解码或重新编码像素数据。响应会携带 `X-Grotto-Image-Sanitized`，表示清理的图片数量。

**远程图片 URL 无法由 Grotto 清理。** 网关必须下载并重新上传图片才能做到，这会改变响应，不在当前设计范围内。`/grotto/info` 会明确报告这一限制。

### 异步媒体

百炼和火山方舟视频任务、ModelScope 图片/视频任务会在同一个客户端请求内提交并轮询。轮询请求复用同一个调用方密钥。客户端收到的是完成结果，而不是上游任务对象。

轮询配置：

```dotenv
GROTTO_ASYNC_POLL_INTERVAL_MS=1000
GROTTO_ASYNC_TIMEOUT=300
GROTTO_ASYNC_MAX_ATTEMPTS=600
```

请将 `GROTTO_ASYNC_TIMEOUT` 加上 `GROTTO_UPSTREAM_TIMEOUT` 后仍低于 Web 服务器/反向代理的请求超时。任务超时会返回 `504 <platform>_task_timeout`。

### 错误

上游错误会转换为：

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

原始上游细节会经过 URL、IP、令牌、邮箱和请求 ID 脱敏后写入应用日志，不会原样返回客户端。

## 配置

复制 `.env.example` 后按需修改。Grotto 配置中不包含平台凭据。主要配置项：

| 变量 | 默认值 | 说明 |
|---|---|---|
| `GROTTO_PUBLIC_BASE_URL` | 请求来源 | 反向代理后请使用外部 HTTPS 地址。 |
| `GROTTO_TRUSTED_PROXIES` | 空 | 仅信任这些代理 IP/CIDR 的 `X-Forwarded-*` 头。 |
| `GROTTO_PLATFORMS` | 全部 | 逗号分隔的平台 ID。 |
| `GROTTO_SYSTEM_PROMPT` | 内置策略 | 实例提示词。 |
| `GROTTO_EXPOSE_SYSTEM_PROMPT` | `true` | 为 false 时 `/grotto/info` 仅返回哈希。 |
| `GROTTO_SANITIZE_IMAGES` | `true` | 是否清理内联图片元数据。 |
| `GROTTO_CORS_ORIGINS` | `*` | 生产环境请使用明确来源。 |
| `GROTTO_RATE_LIMIT_*` | 60 次 / 60 秒 | 按密钥指纹或 IP 的固定窗口限流。 |
| `GROTTO_UPSTREAM_TIMEOUT` | `120` | 秒。 |
| `GROTTO_UPSTREAM_VERIFY_SSL` | `true` | 除本地测试外不要关闭。 |
| `GROTTO_ASYNC_*` | 1 秒 / 300 秒 | 媒体任务轮询。 |
| `BAILIAN_BASE_URL` | DashScope | 上游端点，不是凭据。 |
| `MODELSCOPE_BASE_URL` | ModelScope 推理端点 | 上游端点，不是凭据。 |
| `VOLCENGINE_BASE_URL` | 方舟 | 上游端点，不是凭据。 |

完整配置说明见 `.env.example`；非敏感默认值位于 `config/`。

## 多语言

Web 控制台支持英文和简体中文。页头提供语言切换按钮，选择会保存在 `localStorage`；首次访问时使用浏览器语言。中文文档：

- [README.zh-CN.md](README.zh-CN.md)
- [docs/API.zh-CN.md](docs/API.zh-CN.md)
- [docs/PLATFORMS.zh-CN.md](docs/PLATFORMS.zh-CN.md)
- [docs/DEPLOYMENT.zh-CN.md](docs/DEPLOYMENT.zh-CN.md)

## Libre WebUI

Libre WebUI 通过 OpenAI 兼容插件发现模型并发送对话。将 `key_env` 指向 Libre WebUI 客户端侧保存你自己平台密钥的环境变量（该变量不属于 Grotto）：

```json
{
  "id": "grotto-bailian",
  "name": "Grotto · 百炼",
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

其他平台将路径替换为 `/modelscope/v1` 或 `/volcengine/v1`。上述插件覆盖对话与模型发现。图片、视频、TTS 和 STT 能力应直接指向对应的 Grotto 能力端点，而不是复用对话端点。

## 安全说明

- Grotto 绝不存储平台 API 密钥。密钥仅在转发期间存在于请求内存中。
- Grotto 没有独立的网关密码。任何能访问网关的人都可以发送自己的平台密钥；如果实例不应公开，请在网络或反向代理层限制访问。
- 请求日志仅包含方法、路径、平台、状态和耗时；不记录请求体、请求头、提示词或密钥。
- 速率限制器使用密钥的单向指纹，而不是密钥本身。
- 上游 HTTP 客户端不跟随重定向，避免凭据被转发到其他主机。
- 带凭据的 CORS 不能与通配符来源同时使用；Grotto 会禁用凭据并给出警告。
- 文件速率限制器在单机上具有原子性，但不会跨主机共享。水平扩展时请使用网络限流器。
- 健康检查端点报告适配器就绪状态，不报告上游实时可用性或密钥有效性。
- 请通过 HTTPS 提供服务。调用方会在 `Authorization` 头中发送密钥，中间代理不得记录请求头。

## 部署说明

完整的 Nginx、Apache + PHP-FPM、TLS、流式响应、OPcache、权限和故障排查步骤，请参阅 [`docs/DEPLOYMENT.zh-CN.md`](docs/DEPLOYMENT.zh-CN.md)（[English](docs/DEPLOYMENT.md)）。可直接复制的示例位于 [`deploy/`](deploy/)。

- 将 Web 服务器文档根目录指向 `public/`；不要直接提供 `src/`、`.env` 或 `storage/`。
- 将应用挂载在域名根路径。界面和生成的示例使用根绝对路径 `/assets` 与平台路径。
- TLS 在反向代理终止时，将 `GROTTO_PUBLIC_BASE_URL` 设置为外部 HTTPS 地址。也可以仅信任 `GROTTO_TRUSTED_PROXIES` 中列出的代理所发送的 `X-Forwarded-*` 头。
- 对 `/v1/chat/completions` 流式响应禁用代理缓冲；媒体任务的代理读取超时应高于 `GROTTO_ASYNC_TIMEOUT` 加 `GROTTO_UPSTREAM_TIMEOUT`。
- 为长时间媒体等待配置足够的 PHP-FPM 工作进程，或为无法长时间占用进程的工作负载提供独立异步任务 API。
- 保留一个可写的 `storage/` 目录。默认限流器仅限单机。
- 在平台侧轮换供应商密钥；Grotto 没有服务器端密钥副本需要轮换，也无需因密钥变更而重启。

## 开发

```bash
composer test
composer lint
```

集成测试使用 Guzzle MockHandler，覆盖完整 Slim 中间件栈，包括系统提示词注入、SSE 透传、异步图片轮询、图片清理输出、按请求密钥转发、缺失密钥拒绝和上游错误脱敏。

```bash
composer serve   # http://127.0.0.1:8080
```

## 项目结构

```text
src/Http        Slim 控制器、路由、中间件、OpenAI 错误
src/Platform    适配器契约、共享 DTO、百炼/ModelScope/方舟适配器
src/Core        提示词策略、归一化、流式、图片清理、错误
src/Config      环境驱动配置（不包含平台密钥）
src/Support     日志、HTTP 客户端工厂、速率限制
config/         平台注册表、中间件顺序、容器定义
deploy/         Nginx、Apache、PHP 和日志轮转生产示例
frontend/       纯 HTML/CSS/JS 验证工作台（英文与中文）
tests/          单元测试与 HTTP 集成测试
```

添加平台请参阅 [`docs/PLATFORMS.zh-CN.md`](docs/PLATFORMS.zh-CN.md) 或 [`docs/PLATFORMS.md`](docs/PLATFORMS.md)。界面能力矩阵会自动通过 `/grotto/info` 反映新平台。

## 许可证

根据 AGPL-3.0-or-later 许可证分发。AGPL-3.0 的完整副本请见 [LICENSE](LICENSE)。
