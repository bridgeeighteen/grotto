# 平台适配器

[English](PLATFORMS.md) · **简体中文**

平台适配器回答两个问题：

1. OpenAI 形状的请求如何变成上游请求？
2. 上游响应如何变成 OpenAI 形状的响应？

密钥提取、CORS、速率限制、路由选择、日志、提示词策略和面向客户端的错误处理都位于 HTTP/Core 层。适配器只使用调用方为当前请求提供的密钥。

## 契约

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

`RequestContext` 包含平台、能力、请求 ID、客户端指纹、流式标志、路由路径、可选的百炼地域/工作空间、模型、内容类型、multipart 请求体、路由参数，以及当前请求的**调用方平台 API 密钥**。适配器不应依赖超全局变量、配置或存储的凭据。

`UpstreamRequest` 包含方法、绝对 URI、请求头、请求体（字符串或 PSR 流）和流式标志。`OpenAiResponse` 包含状态码、字符串或 PSR 流响应体，以及响应头。

## 标准路由

适配器注册表位于 [`config/platforms.php`](../config/platforms.php)。每个条目声明：

- `adapter`：通过 PHP-DI 解析的类
- `route`：第一段 URL 路径
- `base_url_env`、`default_base_url`
- `capabilities`
- 可选能力、本地化名称和默认模型元数据

这里刻意不再提供 `api_key_env`。平台密钥绝不从环境变量读取。

当前适配器：

| 平台 | 路由 | 说明 |
|---|---|---|
| 百炼 | `/bailian` | 兼容对话/模型/音频；原生异步图片/视频。 |
| ModelScope | `/modelscope` | 兼容对话/模型；异步图片/视频；音频需显式启用。 |
| 火山方舟 | `/volcengine` | 兼容对话/图片/模型；异步视频任务 API。 |

用户选择与所持密钥对应的路由：百炼密钥使用 `/bailian`，ModelScope 密钥使用 `/modelscope`，火山密钥使用 `/volcengine`。

## 添加平台

1. 在 `src/Platform/YourPlatform` 下创建目录。
2. 实现 `PlatformAdapterInterface`，通常继承 `AbstractAdapter`。
3. 在 `config/platforms.php` 中添加平台条目。
4. 在平台目录中添加所需的端点解析器。
5. 添加继承 `AbstractAsyncTask` 的异步任务类（如有）。
6. 为请求映射、端点校验、按请求密钥转发和异步终态检测添加单元测试。
7. 如果映射包含安全敏感校验，添加 HTTP 集成测试。

如果平台使用首段路由，无需修改 Slim 路由。只有当平台需要完全不同的路径前缀策略时才添加新路由组，并记得在 `src/Http/Route/ApiRoutes.php` 中注册；路由不会根据注册表自动生成。

前端能力矩阵和示例生成器读取 `/grotto/info`，因此注册的平台会自动出现。在注册表条目中添加 `label_zh` 和 `short_label_zh` 可让中文界面显示本地化名称。

## 异步任务规则

- `resolveAsync()` 必须返回完成的 OpenAI 形状结果。
- 终态必须明确。未知或缺失状态按 pending 处理。
- 轮询失败使用 `UpstreamErrorMapper`，超时映射为 `504`。
- 不得将上游任务对象当作已完成资产返回。
- 轮询间隔、总超时和最大尝试次数来自 `Config::async()`。
- 轮询时复用调用方密钥：使用同一个 `RequestContext` 密钥构造轮询请求头。
- 不在轮询日志中记录提示词、图片字节或凭据。

## 安全规则

- 构造主机时不要信任路由片段。百炼会校验地域和工作空间 ID，并使用固定主机名模板。
- 不跟随重定向。共享 Guzzle 客户端禁用重定向，避免 Bearer 凭据被转发到意外主机。
- 不返回原始上游错误负载。使用 `UpstreamErrorMapper`。
- 仅从 `RequestContext` 读取平台密钥。绝不从 `$_ENV`、`Config`、数据库、缓存或文件读取。
- 绝不记录密钥、在错误中回显密钥，或将密钥持久化到限流存储。限流器只接收单向指纹。
- 除非平台明确需要，否则不要向上游传递 `X-Grotto-*` 头。
- 保留用户显式指定的系统提示词；提示词策略属于 `SystemPromptInjector`。

## 测试清单

- 请求 URI 与方法。
- 上游 `Authorization` 头与调用方平台密钥一致。
- 没有 Bearer 头的请求返回 `401 missing_api_key`，且不调用上游。
- 异步轮询使用与初始请求相同的密钥。
- 平台特定异步头。
- 请求体映射与参数归一化。
- 终态检测。
- 最终响应结构。
- 错误脱敏。
- 地域/工作空间或其他 URL 注入拒绝。
- 如果平台支持流式对话：`Accept: text/event-stream` 且无缓冲。
