(() => {
    "use strict";

    const CAPABILITIES = [
        { id: "chat", label: "Chat", column: "Chat", path: "chat/completions", method: "POST" },
        { id: "images", label: "Image generation", column: "Images", path: "images/generations", method: "POST" },
        { id: "videos", label: "Video generation", column: "Video", path: "videos/generations", method: "POST" },
        { id: "tts", label: "Text to speech", column: "TTS", path: "audio/speech", method: "POST" },
        { id: "stt", label: "Speech to text", column: "STT", path: "audio/transcriptions", method: "POST" },
        { id: "models", label: "Model list", column: "Models", path: "models", method: "GET" },
    ];

    const I18N = {
        en: {
            "meta.title": "Grotto · Gateway console",
            "meta.description": "Operational console for this Grotto gateway: instance status, capability matrix, and request tools.",
            "title.document": "{name} · Gateway console",
            "a11y.skip": "Skip to content",
            "a11y.noscript": "This console needs JavaScript. The API endpoints remain available without it.",
            "brand.home": "Grotto home",
            "brand.suffix": "console",
            "nav.sections": "Sections",
            "nav.run": "Run",
            "nav.build": "Build",
            "nav.system": "System",
            "nav.capabilities": "Capabilities",
            "nav.policy": "Policy",
            "status.checking": "Checking",
            "header.apiInfo": "API info",
            "header.search": "Search",
            "locale.aria.en": "Switch to Chinese",
            "locale.aria.zh": "Switch to English",
            "palette.open": "Open command palette",
            "palette.searchCommands": "Search commands",
            "palette.searchPlaceholder": "Search commands…",
            "palette.close": "Close command palette",
            "palette.title": "Command palette",
            "palette.empty": "No matching command.",
            "palette.navigate": "navigate",
            "palette.select": "select",
            "palette.count": "{count} command",
            "palette.countPlural": "{count} commands",
            "palette.clipboardDenied": "Clipboard access was denied.",
            "head.kicker": "Gateway console",
            "head.title": "Instance overview",
            "head.lede": "Live status, route support, and request tools for this deployment.",
            "head.refresh": "Refresh health",
            "head.healthJson": "Health JSON",
            "stat.summary": "Instance summary",
            "stat.platforms": "Platforms",
            "stat.loading": "Reading configuration",
            "stat.apiKey": "API key",
            "stat.sanitizer": "Image metadata",
            "stat.inlineBase64": "Inline base64",
            "stat.async": "Async budget",
            "stat.perTask": "Per media task",
            "stat.platformsEnabled": "{count} enabled",
            "stat.noneRegistered": "No platforms registered",
            "stat.apiKeyRequest": "Per request",
            "stat.apiKeyNote": "Caller supplies key",
            "stat.on": "On",
            "stat.off": "Off",
            "config.label": "Configuration",
            "playground.title": "Run a request",
            "playground.desc": "Send chat or image requests through this instance. Your platform API key stays in this browser tab.",
            "playground.noRequest": "No request sent",
            "playground.access": "Platform access",
            "playground.keyTitle": "Platform API key",
            "playground.keyNote": "Sent with each request. Never stored by Grotto.",
            "playground.keyLabel": "Platform API key",
            "playground.keyPlaceholder": "sk-…",
            "playground.keyHelp": "Kept in this page's memory only. Use the key for the selected platform.",
            "playground.show": "Show",
            "playground.hide": "Hide",
            "playground.clear": "Clear",
            "chat.title": "Chat completion",
            "chat.desc": "Streaming SSE or a single JSON response.",
            "chat.defaultMessage": "In one paragraph, explain how a protocol-translation gateway differs from a reverse proxy.",
            "chat.stream": "Stream response",
            "chat.send": "Send chat",
            "chat.sending": "Sending…",
            "chat.assistant": "Assistant",
            "chat.waitingUpstream": "Waiting for upstream",
            "chat.waitingToken": "Waiting for the first token…",
            "chat.metaRunning": "Chat · {platform} · running",
            "chat.metaComplete": "Chat · {platform} · complete · {elapsed}",
            "chat.metaFailed": "Chat · {platform} · failed",
            "chat.empty": "The upstream response contained no text content.",
            "chat.emptyStream": "The upstream stream completed without text content.",
            "chat.noResponse": "No response was rendered.",
            "chat.modelRequired": "Enter the upstream model ID for this request.",
            "chat.messageRequired": "Enter a message before sending the chat request.",
            "chat.completeAnnounce": "Chat response complete.",
            "chat.failedAnnounce": "Chat request failed: {message}",
            "chat.streamUnsupported": "This browser could not open the response stream.",
            "image.title": "Image generation",
            "image.desc": "Grotto polls upstream tasks before replying.",
            "image.defaultPrompt": "A quiet limestone grotto opening onto a cobalt-blue sea, soft daylight, architectural photograph.",
            "image.help": "Inline base64 output is metadata-stripped.",
            "image.generate": "Generate image",
            "image.generating": "Generating…",
            "image.output": "Output",
            "image.modelRequired": "Enter the upstream image model ID.",
            "image.promptRequired": "Enter a prompt before generating an image.",
            "image.metaPolling": "Images · {platform} · polling",
            "image.metaComplete": "Images · {platform} · complete · {elapsed}",
            "image.metaFailed": "Images · {platform} · failed",
            "image.noData": "The platform returned a successful response without image data.",
            "image.noRenderable": "The response contained no renderable image URLs or base64 image data.",
            "image.completeAnnounce": "Image generation complete. {count} image(s) returned.",
            "image.failedAnnounce": "Image request failed: {message}",
            "image.caption": "Output {index} · {source}",
            "image.sourceB64": "inline base64 · metadata stripped",
            "image.sourceB64Raw": "inline base64",
            "image.sourceData": "inline data URL · metadata stripped",
            "image.sourceDataRaw": "inline data URL",
            "image.sourceRemote": "remote URL · metadata not scrubbed",
            "image.count": "{count} image(s) · {elapsed}",
            "field.platform": "Platform",
            "field.model": "Model",
            "field.message": "Message",
            "field.prompt": "Prompt",
            "field.capability": "Capability",
            "field.region": "Region",
            "field.workspace": "Workspace ID",
            "common.loading": "Loading…",
            "common.unavailable": "Unavailable",
            "common.disabled": "Disabled",
            "workbench.title": "Build a request",
            "workbench.desc": "Select a platform and capability to generate an endpoint for this deployment.",
            "workbench.loadingPlatforms": "Loading platforms…",
            "workbench.loadingCapabilities": "Loading capabilities…",
            "workbench.workspaceRoute": "Bailian workspace route",
            "workbench.workspaceHint": "Use a workspace-scoped MaaS domain.",
            "workbench.noPlatforms": "No platforms enabled",
            "workbench.noCapabilities": "No declared capabilities",
            "workbench.noSupportedPlatform": "No supported platform",
            "code.tabsLabel": "Request example format",
            "code.copyAria": "Copy code example",
            "copy.label": "Copy",
            "copy.copied": "Copied",
            "copy.failed": "Failed",
            "copy.copiedAnnounce": "Copied to clipboard.",
            "copy.codeAria": "Copy code example",
            "copy.loading": "Loading request examples…",
            "code.loading": "Loading request examples…",
            "code.waitingCaption": "Waiting for instance configuration.",
            "code.selectPrompt": "Select a platform and capability to generate an example.",
            "code.noShape": "No request shape is available.",
            "caption.workspaceFill": "Fill in both the region and workspace ID before using this URL.",
            "caption.workspaceKey": "Use a Bailian API key that can access the selected workspace and region.",
            "caption.libre": "Minimal Libre WebUI completion plugin for chat; media routes remain direct capability calls.",
            "caption.chat": "Chat is forwarded to the platform's OpenAI-compatible endpoint; streaming stays as SSE.",
            "caption.images": "Grotto submits or forwards the image job, waits for any upstream task, and returns one image response.",
            "caption.videos": "Video generation is a Grotto extension: Grotto polls the platform task before returning a completed video object.",
            "caption.tts": "The request is JSON and the response is binary audio. Availability depends on the selected model.",
            "caption.stt": "Audio uploads are forwarded as multipart/form-data and are never written to request logs.",
            "caption.models": "Model lists are normalized to the OpenAI {object, data[]} shape.",
            "system.title": "System status",
            "system.desc": "Readiness reflects enabled adapters. Platform keys are supplied by callers per request, and upstream reachability is not probed.",
            "system.gateway": "Gateway",
            "system.readingHealth": "Reading health…",
            "system.versionUnknown": "Version —",
            "system.version": "Version {version}",
            "system.notChecked": "Not checked",
            "system.waitingHealth": "Waiting for health response.",
            "system.healthFootnote": "Ready means the adapter is enabled. Each request must include the target platform's API key.",
            "system.apiKey": "API key source",
            "system.rateLimit": "Rate limit",
            "system.async": "Async polling",
            "system.promptPolicy": "Prompt policy",
            "system.requestLogging": "Request logging",
            "system.metadataOnly": "Metadata only",
            "system.sanitizer": "Image sanitizer",
            "fact.auth": "Per request · not stored",
            "fact.rate": "{max} req / {window} s",
            "fact.async": "{timeout} s timeout · {interval} ms poll",
            "fact.prompt": "Inject · replace low-info defaults",
            "fact.promptOff": "Disabled",
            "fact.sanitizer": "EXIF / IPTC / XMP · {formats}",
            "health.operational": "Operational",
            "health.setupNeeded": "Setup needed",
            "health.ready": "Ready",
            "health.degraded": "Degraded",
            "health.gatewayReady": "Gateway ready for requests",
            "health.setupRequired": "Setup required",
            "health.checked": "Checked {time}",
            "health.checkedJustNow": "Checked just now",
            "health.noPlatforms": "No platforms are enabled.",
            "health.routes": "{count} routes",
            "health.refreshing": "Refreshing…",
            "health.unavailable": "Unavailable",
            "health.endpointUnavailable": "Health endpoint unavailable",
            "health.error": "Health error",
            "health.noResponse": "No response",
            "health.failed": "The gateway could not report its status. Check the service log.",
            "health.fetchFailed": "Health check failed: {message}",
            "health.httpError": "Health endpoint returned HTTP {status}.",
            "capabilities.title": "Capability matrix",
            "capabilities.desc": "Each column is an OpenAI-shaped route. Every request must include the target platform's API key.",
            "capabilities.metaLoading": "Reading platform registry",
            "capabilities.caption": "Capabilities available by enabled platform",
            "capabilities.platform": "Platform",
            "capabilities.loading": "Loading capabilities…",
            "capabilities.footnoteBefore": "ModelScope audio routes are disabled by default because audio support depends on the selected model. Enable them with",
            "capabilities.footnoteAfter": ".",
            "capabilities.meta": "{count} platforms · client keys per request",
            "capabilities.noPlatforms": "No platforms are enabled. Set GROTTO_PLATFORMS in the server environment.",
            "capabilities.enabled": "enabled",
            "capability.chat.column": "Chat",
            "capability.chat.label": "Chat",
            "capability.images.column": "Images",
            "capability.images.label": "Image generation",
            "capability.videos.column": "Video",
            "capability.videos.label": "Video generation",
            "capability.tts.column": "TTS",
            "capability.tts.label": "Text to speech",
            "capability.stt.column": "STT",
            "capability.stt.label": "Speech to text",
            "capability.models.column": "Models",
            "capability.models.label": "Model list",
            "capability.available": "{label}: available",
            "capability.notAvailable": "{label}: not available",
            "sr.available": "Available",
            "sr.notAvailable": "Not available",
            "policy.title": "Instance policy",
            "policy.descBefore": "Defaults reported by",
            "policy.descAfter": "for this deployment.",
            "policy.infoJson": "Info JSON",
            "policy.defaultPrompt": "Default system prompt",
            "policy.copyPromptAria": "Copy default system prompt",
            "policy.readingPrompt": "Reading configured prompt…",
            "policy.behavior": "Middleware behavior",
            "policy.promptTerm": "Prompt injection",
            "policy.promptDesc": "A missing system message is filled. Low-information defaults such as “You are a helpful assistant” are replaced. Explicit system prompts are preserved.",
            "policy.imageTerm": "Image metadata",
            "policy.imageDesc": "EXIF, IPTC, XMP, comments, and timestamp chunks are stripped from inline base64 images without re-encoding pixels. URL-only outputs cannot be scrubbed by the gateway.",
            "policy.logsTerm": "Request logs",
            "policy.logsDesc": "Method, path, status, platform, and duration are logged. Message content, prompts, image bytes, and API keys are not.",
            "policy.errorsTerm": "Upstream errors",
            "policy.errorsDesc": "Platform errors become OpenAI-shaped envelopes. Credentials, internal hosts, and request IDs are removed before a response leaves the gateway.",
            "policy.noPrompt": "No prompt policy was returned.",
            "policy.promptHidden": "Hidden by instance policy. The fingerprint and length are still published.",
            "policy.promptMeta": "{status} · {visibility} · {length} chars · sha256 {hash}",
            "policy.injectEnabled": "injection enabled",
            "policy.injectDisabled": "injection disabled",
            "policy.visible": "prompt visible",
            "policy.redacted": "prompt redacted",
            "footer.console": "gateway console",
            "footer.info": "Info JSON",
            "footer.health": "Health",
            "footer.top": "Top",
            "auth.keyRequired": "Enter the API key for the target platform.",
            "auth.keyTooLong": "The platform API key is too long.",
            "platform.missing": "No platform is selected.",
            "platform.capabilityMissing": "{platform} does not declare support for this capability.",
            "platform.metadataUnavailable": "Instance metadata is unavailable. Reload the page after the gateway starts.",
            "error.infoHttp": "Info endpoint returned HTTP {status}.",
            "error.metadataUnavailable": "Instance metadata could not be loaded. Check that the Grotto service is running.",
            "error.metadataUnavailableShort": "Instance metadata is unavailable.",
            "error.startAndReload": "Start the gateway and reload this page.",
            "error.promptUnreadable": "The configured prompt could not be read.",
            "error.metadataTitle": "Metadata unavailable",
            "error.loadFailed": "Could not load instance info: {message}",
            "error.gateway": "The gateway returned HTTP {status}. Check the server log for details.",
            "palette.section": "Section",
            "palette.action": "Action",
            "palette.copy": "Copy",
            "palette.newTab": "New tab",
            "palette.auth": "API key",
            "palette.cmd.run": "Run a request",
            "palette.cmd.build": "Build a request",
            "palette.cmd.system": "System status",
            "palette.cmd.capabilities": "Capability matrix",
            "palette.cmd.policy": "Instance policy",
            "palette.cmd.refresh": "Refresh health",
            "palette.cmd.copyBase": "Copy API base URL",
            "palette.cmd.openInfo": "Open info JSON",
            "palette.cmd.focusKey": "Focus platform key",
            "palette.baseCopied": "API base URL copied.",
            "state.idle": "Idle",
            "state.sent": "Sending",
            "state.complete": "Complete",
            "state.failed": "Failed",
            "state.generating": "Generating",
            "usage.tokens": "{count} tokens",
            "time.justNow": "just now",
        },
        "zh-CN": {
            "meta.title": "Grotto · 网关控制台",
            "meta.description": "Grotto 网关控制台：实例状态、能力矩阵与请求工具。",
            "title.document": "{name} · 网关控制台",
            "a11y.skip": "跳到主要内容",
            "a11y.noscript": "此控制台需要 JavaScript。API 端点仍可在无 JavaScript 时使用。",
            "brand.home": "Grotto 首页",
            "brand.suffix": "控制台",
            "nav.sections": "页面导航",
            "nav.run": "运行",
            "nav.build": "构建",
            "nav.system": "系统",
            "nav.capabilities": "能力",
            "nav.policy": "策略",
            "status.checking": "检查中",
            "header.apiInfo": "API 信息",
            "header.search": "搜索",
            "locale.aria.en": "切换到中文",
            "locale.aria.zh": "切换到英文",
            "palette.open": "打开命令面板",
            "palette.searchCommands": "搜索命令",
            "palette.searchPlaceholder": "搜索命令…",
            "palette.close": "关闭命令面板",
            "palette.title": "命令面板",
            "palette.empty": "没有匹配的命令。",
            "palette.navigate": "导航",
            "palette.select": "选择",
            "palette.count": "{count} 条命令",
            "palette.countPlural": "{count} 条命令",
            "palette.clipboardDenied": "剪贴板访问被拒绝。",
            "head.kicker": "网关控制台",
            "head.title": "实例概览",
            "head.lede": "查看此部署的实时状态、路由支持与请求工具。",
            "head.refresh": "刷新健康状态",
            "head.healthJson": "健康检查 JSON",
            "stat.summary": "实例摘要",
            "stat.platforms": "平台",
            "stat.loading": "正在读取配置",
            "stat.apiKey": "API 密钥",
            "stat.sanitizer": "图片元数据",
            "stat.inlineBase64": "仅内联 base64",
            "stat.async": "异步预算",
            "stat.perTask": "每个媒体任务",
            "stat.platformsEnabled": "{count} 个已启用",
            "stat.noneRegistered": "未注册平台",
            "stat.apiKeyRequest": "按请求提供",
            "stat.apiKeyNote": "由调用方提供密钥",
            "stat.on": "开",
            "stat.off": "关",
            "config.label": "配置",
            "playground.title": "运行请求",
            "playground.desc": "通过此实例发送对话或图片请求。平台 API 密钥只保留在当前浏览器标签页中。",
            "playground.noRequest": "尚未发送请求",
            "playground.access": "平台访问",
            "playground.keyTitle": "平台 API 密钥",
            "playground.keyNote": "随每次请求发送，Grotto 不会保存。",
            "playground.keyLabel": "平台 API 密钥",
            "playground.keyPlaceholder": "sk-…",
            "playground.keyHelp": "仅保留在当前页面内存中。请使用所选平台的密钥。",
            "playground.show": "显示",
            "playground.hide": "隐藏",
            "playground.clear": "清除",
            "chat.title": "对话补全",
            "chat.desc": "支持 SSE 流式响应或单次 JSON 响应。",
            "chat.defaultMessage": "用一段话说明协议转换网关与反向代理的区别。",
            "chat.stream": "流式响应",
            "chat.send": "发送对话",
            "chat.sending": "发送中…",
            "chat.assistant": "助手",
            "chat.waitingUpstream": "等待上游响应",
            "chat.waitingToken": "等待第一个 token…",
            "chat.metaRunning": "对话 · {platform} · 运行中",
            "chat.metaComplete": "对话 · {platform} · 完成 · {elapsed}",
            "chat.metaFailed": "对话 · {platform} · 失败",
            "chat.empty": "上游响应中没有文本内容。",
            "chat.emptyStream": "上游流已结束，但没有返回文本内容。",
            "chat.noResponse": "未渲染任何响应。",
            "chat.modelRequired": "请输入本次请求使用的上游模型 ID。",
            "chat.messageRequired": "发送对话前请输入消息。",
            "chat.completeAnnounce": "对话响应完成。",
            "chat.failedAnnounce": "对话请求失败：{message}",
            "chat.streamUnsupported": "当前浏览器无法打开响应流。",
            "image.title": "图片生成",
            "image.desc": "Grotto 会先轮询上游任务，再返回结果。",
            "image.defaultPrompt": "静谧的石灰岩洞窟通向钴蓝色大海，柔和日光，建筑摄影。",
            "image.help": "内联 base64 输出会自动清理元数据。",
            "image.generate": "生成图片",
            "image.generating": "生成中…",
            "image.output": "输出",
            "image.modelRequired": "请输入上游图片模型 ID。",
            "image.promptRequired": "生成图片前请输入提示词。",
            "image.metaPolling": "图片 · {platform} · 轮询中",
            "image.metaComplete": "图片 · {platform} · 完成 · {elapsed}",
            "image.metaFailed": "图片 · {platform} · 失败",
            "image.noData": "平台返回成功响应，但没有图片数据。",
            "image.noRenderable": "响应中没有可渲染的图片 URL 或 base64 数据。",
            "image.completeAnnounce": "图片生成完成，返回 {count} 张图片。",
            "image.failedAnnounce": "图片请求失败：{message}",
            "image.caption": "输出 {index} · {source}",
            "image.sourceB64": "内联 base64 · 已清理元数据",
            "image.sourceB64Raw": "内联 base64",
            "image.sourceData": "内联 data URL · 已清理元数据",
            "image.sourceDataRaw": "内联 data URL",
            "image.sourceRemote": "远程 URL · 元数据未清理",
            "image.count": "{count} 张图片 · {elapsed}",
            "field.platform": "平台",
            "field.model": "模型",
            "field.message": "消息",
            "field.prompt": "提示词",
            "field.capability": "能力",
            "field.region": "地域",
            "field.workspace": "工作空间 ID",
            "common.loading": "加载中…",
            "common.unavailable": "不可用",
            "common.disabled": "已禁用",
            "workbench.title": "构建请求",
            "workbench.desc": "选择平台与能力，生成本部署对应的端点。",
            "workbench.loadingPlatforms": "正在加载平台…",
            "workbench.loadingCapabilities": "正在加载能力…",
            "workbench.workspaceRoute": "百炼工作空间路由",
            "workbench.workspaceHint": "使用工作空间级 MaaS 域名。",
            "workbench.noPlatforms": "未启用平台",
            "workbench.noCapabilities": "未声明能力",
            "workbench.noSupportedPlatform": "无支持的平台",
            "code.tabsLabel": "请求示例格式",
            "code.copyAria": "复制代码示例",
            "copy.label": "复制",
            "copy.copied": "已复制",
            "copy.failed": "失败",
            "copy.copiedAnnounce": "已复制到剪贴板。",
            "copy.codeAria": "复制代码示例",
            "copy.loading": "正在加载请求示例…",
            "code.loading": "正在加载请求示例…",
            "code.waitingCaption": "正在等待实例配置。",
            "code.selectPrompt": "请选择平台与能力以生成示例。",
            "code.noShape": "没有可用的请求结构。",
            "caption.workspaceFill": "使用此 URL 前请填写地域和工作空间 ID。",
            "caption.workspaceKey": "请使用有权访问所选工作空间和地域的百炼 API 密钥。",
            "caption.libre": "用于对话的最小 Libre WebUI 补全插件；媒体路由仍直接调用对应能力端点。",
            "caption.chat": "对话请求转发到平台的 OpenAI 兼容端点，流式响应保持为 SSE。",
            "caption.images": "Grotto 提交或转发图片任务，等待上游任务完成后返回一个图片响应。",
            "caption.videos": "视频生成是 Grotto 的扩展能力：Grotto 会先轮询平台任务，再返回完成的视频对象。",
            "caption.tts": "请求为 JSON，响应为二进制音频。可用性取决于所选模型。",
            "caption.stt": "音频上传以 multipart/form-data 转发，不会写入请求日志。",
            "caption.models": "模型列表会归一化为 OpenAI 的 {object, data[]} 结构。",
            "system.title": "系统状态",
            "system.desc": "就绪状态反映已启用的适配器。平台密钥由调用方按请求提供，且不会探测上游可达性。",
            "system.gateway": "网关",
            "system.readingHealth": "正在读取健康状态…",
            "system.versionUnknown": "版本 —",
            "system.version": "版本 {version}",
            "system.notChecked": "尚未检查",
            "system.waitingHealth": "等待健康检查响应。",
            "system.healthFootnote": "“就绪”表示适配器已启用。每个请求都必须携带目标平台的 API 密钥。",
            "system.apiKey": "API 密钥来源",
            "system.rateLimit": "速率限制",
            "system.async": "异步轮询",
            "system.promptPolicy": "提示词策略",
            "system.requestLogging": "请求日志",
            "system.metadataOnly": "仅元数据",
            "system.sanitizer": "图片清理",
            "fact.auth": "按请求提供 · 不存储",
            "fact.rate": "{window} 秒内 {max} 次",
            "fact.async": "{timeout} 秒超时 · {interval} 毫秒轮询",
            "fact.prompt": "注入 · 替换低信息量默认提示词",
            "fact.promptOff": "已禁用",
            "fact.sanitizer": "EXIF / IPTC / XMP · {formats}",
            "health.operational": "运行正常",
            "health.setupNeeded": "需要配置",
            "health.ready": "就绪",
            "health.degraded": "降级",
            "health.gatewayReady": "网关已可接收请求",
            "health.setupRequired": "需要配置",
            "health.checked": "检查于 {time}",
            "health.checkedJustNow": "刚刚检查",
            "health.noPlatforms": "未启用任何平台。",
            "health.routes": "{count} 条路由",
            "health.refreshing": "刷新中…",
            "health.unavailable": "不可用",
            "health.endpointUnavailable": "健康检查端点不可用",
            "health.error": "健康检查错误",
            "health.noResponse": "无响应",
            "health.failed": "网关无法报告状态，请检查服务日志。",
            "health.fetchFailed": "健康检查失败：{message}",
            "health.httpError": "健康检查端点返回 HTTP {status}。",
            "capabilities.title": "能力矩阵",
            "capabilities.desc": "每一列代表一个 OpenAI 形状的路由。每个请求都必须携带目标平台的 API 密钥。",
            "capabilities.metaLoading": "正在读取平台注册表",
            "capabilities.caption": "按已启用平台列出的能力",
            "capabilities.platform": "平台",
            "capabilities.loading": "正在加载能力…",
            "capabilities.footnoteBefore": "ModelScope 音频路由默认关闭，因为音频支持取决于所选模型。启用方式：",
            "capabilities.footnoteAfter": "。",
            "capabilities.meta": "{count} 个平台 · 密钥由客户端提供",
            "capabilities.noPlatforms": "未启用任何平台。请设置服务器环境变量 GROTTO_PLATFORMS。",
            "capabilities.enabled": "已启用",
            "capability.chat.column": "对话",
            "capability.chat.label": "对话",
            "capability.images.column": "图片",
            "capability.images.label": "图片生成",
            "capability.videos.column": "视频",
            "capability.videos.label": "视频生成",
            "capability.tts.column": "语音合成",
            "capability.tts.label": "文本转语音",
            "capability.stt.column": "语音识别",
            "capability.stt.label": "语音转文本",
            "capability.models.column": "模型",
            "capability.models.label": "模型列表",
            "capability.available": "{label}：可用",
            "capability.notAvailable": "{label}：不可用",
            "sr.available": "可用",
            "sr.notAvailable": "不可用",
            "policy.title": "实例策略",
            "policy.descBefore": "此部署通过",
            "policy.descAfter": "报告的默认值。",
            "policy.infoJson": "信息 JSON",
            "policy.defaultPrompt": "默认系统提示词",
            "policy.copyPromptAria": "复制默认系统提示词",
            "policy.readingPrompt": "正在读取配置的提示词…",
            "policy.behavior": "中间件行为",
            "policy.promptTerm": "提示词注入",
            "policy.promptDesc": "缺少系统消息时会自动补充；诸如“You are a helpful assistant”的低信息量默认提示词会被替换；显式系统提示词保持不变。",
            "policy.imageTerm": "图片元数据",
            "policy.imageDesc": "从内联 base64 图片中移除 EXIF、IPTC、XMP、注释和时间戳块，不重新编码像素。仅返回 URL 的输出无法由网关清理。",
            "policy.logsTerm": "请求日志",
            "policy.logsDesc": "记录方法、路径、状态、平台和耗时，不记录消息内容、提示词、图片字节或 API 密钥。",
            "policy.errorsTerm": "上游错误",
            "policy.errorsDesc": "平台错误会转换为 OpenAI 形状的错误响应。凭据、内部主机名和请求 ID 会在响应离开网关前移除。",
            "policy.noPrompt": "未返回提示词策略。",
            "policy.promptHidden": "已按实例策略隐藏，但仍会发布指纹和长度。",
            "policy.promptMeta": "{status} · {visibility} · {length} 字符 · sha256 {hash}",
            "policy.injectEnabled": "注入已启用",
            "policy.injectDisabled": "注入已禁用",
            "policy.visible": "提示词可见",
            "policy.redacted": "提示词已隐藏",
            "footer.console": "网关控制台",
            "footer.info": "信息 JSON",
            "footer.health": "健康检查",
            "footer.top": "顶部",
            "auth.keyRequired": "请输入目标平台的 API 密钥。",
            "auth.keyTooLong": "平台 API 密钥过长。",
            "platform.missing": "未选择平台。",
            "platform.capabilityMissing": "{platform} 未声明支持此能力。",
            "platform.metadataUnavailable": "实例元数据不可用。请等待网关启动后刷新页面。",
            "error.infoHttp": "信息端点返回 HTTP {status}。",
            "error.metadataUnavailable": "无法加载实例元数据。请检查 Grotto 服务是否正在运行。",
            "error.metadataUnavailableShort": "实例元数据不可用。",
            "error.startAndReload": "启动网关后刷新此页面。",
            "error.promptUnreadable": "无法读取配置的提示词。",
            "error.metadataTitle": "元数据不可用",
            "error.loadFailed": "无法加载实例信息：{message}",
            "error.gateway": "网关返回 HTTP {status}。请检查服务器日志。",
            "palette.section": "分区",
            "palette.action": "操作",
            "palette.copy": "复制",
            "palette.newTab": "新标签页",
            "palette.auth": "API 密钥",
            "palette.cmd.run": "运行请求",
            "palette.cmd.build": "构建请求",
            "palette.cmd.system": "系统状态",
            "palette.cmd.capabilities": "能力矩阵",
            "palette.cmd.policy": "实例策略",
            "palette.cmd.refresh": "刷新健康状态",
            "palette.cmd.copyBase": "复制 API 基础 URL",
            "palette.cmd.openInfo": "打开信息 JSON",
            "palette.cmd.focusKey": "定位到平台密钥",
            "palette.baseCopied": "已复制 API 基础 URL。",
            "state.idle": "空闲",
            "state.sent": "发送中",
            "state.complete": "完成",
            "state.failed": "失败",
            "state.generating": "生成中",
            "usage.tokens": "{count} 个 token",
            "time.justNow": "刚刚",
        },
    };

    const LOCALE_KEY = "grotto.locale";
    const HEALTH_INTERVAL_MS = 30_000;

    const state = {
        info: null,
        infoError: null,
        health: null,
        healthError: null,
        token: "",
        locale: initialLocale(),
        codeTab: "curl",
        generatorCapability: "chat",
        paletteCommands: [],
        paletteIndex: 0,
        chatRunning: false,
        imageRunning: false,
        chatStatus: { name: "idle", key: "state.idle" },
        imageStatus: { name: "idle", key: "state.idle" },
        healthTimer: null,
        healthInFlight: false,
    };

    const dom = {};
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    function initialLocale() {
        try {
            const saved = window.localStorage.getItem(LOCALE_KEY);
            if (saved === "en" || saved === "zh-CN") {
                return saved;
            }
        } catch {
            // localStorage can be unavailable in hardened browser contexts.
        }

        const languages = Array.isArray(navigator.languages) && navigator.languages.length
            ? navigator.languages
            : [navigator.language || "en"];

        return languages.some((language) => String(language).toLowerCase().startsWith("zh")) ? "zh-CN" : "en";
    }

    function t(key, vars = {}) {
        const table = I18N[state.locale] || I18N.en;
        const template = table[key] ?? I18N.en[key] ?? key;

        return template.replace(/\{(\w+)\}/g, (match, name) => (
            Object.prototype.hasOwnProperty.call(vars, name) ? String(vars[name]) : match
        ));
    }

    function applyLocale() {
        document.documentElement.lang = state.locale;
        document.documentElement.dataset.locale = state.locale;

        for (const node of $$("[data-i18n]")) {
            node.textContent = t(node.dataset.i18n);
        }

        for (const node of $$("[data-i18n-placeholder]")) {
            node.placeholder = t(node.dataset.i18nPlaceholder);
        }

        for (const node of $$("[data-i18n-aria-label]")) {
            node.setAttribute("aria-label", t(node.dataset.i18nAriaLabel));
        }

        for (const node of $$("[data-i18n-content]")) {
            node.setAttribute("content", t(node.dataset.i18nContent));
        }

        if (dom.localeToggleLabel) {
            dom.localeToggleLabel.textContent = state.locale === "zh-CN" ? "English" : "简体中文";
        }

        if (dom.localeToggle) {
            dom.localeToggle.setAttribute(
                "aria-label",
                state.locale === "zh-CN" ? t("locale.aria.zh") : t("locale.aria.en")
            );
        }

        setChatState(state.chatStatus.name, state.chatStatus.key);
        setImageState(state.imageStatus.name, state.imageStatus.key);
        updateTokenVisibilityLabel();

        if (state.info) {
            applyInfo();
        } else {
            updateConnectionState();

            if (state.infoError) {
                showConfigAlert([t("error.metadataUnavailable")]);
                dom.matrixMeta.textContent = t("common.unavailable");
                dom.matrixBody.replaceChildren(rowMessage(t("error.metadataUnavailableShort"), 7));
                dom.codeOutput.textContent = t("error.metadataUnavailableShort");
                dom.codeCaption.textContent = t("error.startAndReload");
                dom.systemPrompt.textContent = t("error.promptUnreadable");
                dom.systemPromptMeta.textContent = "";
            }
        }

        if (state.health) {
            applyHealth();
        } else if (state.healthError) {
            applyHealthFailure(state.healthError);
        }

        dom.announcer.textContent = "";
    }

    function persistLocale() {
        try {
            window.localStorage.setItem(LOCALE_KEY, state.locale);
        } catch {
            // Ignore storage failures; the current page still switches language.
        }
    }

    function toggleLocale() {
        state.locale = state.locale === "zh-CN" ? "en" : "zh-CN";
        persistLocale();
        applyLocale();
        announcer(state.locale === "zh-CN" ? "界面已切换为中文。" : "Interface switched to English.");
    }

    document.addEventListener("DOMContentLoaded", init);

    async function init() {
        cacheElements();
        bindEvents();
        applyLocale();
        await Promise.allSettled([loadInfo(), loadHealth()]);
        state.healthTimer = window.setInterval(() => {
            if (!document.hidden) {
                loadHealth({ quiet: true });
            }
        }, HEALTH_INTERVAL_MS);
    }

    function cacheElements() {
        Object.assign(dom, {
            navStatus: $("#nav-status"),
            navStatusText: $("#nav-status .nav__status-text"),
            infoSummaryPlatforms: $("#summary-platforms"),
            infoSummaryPlatformsNote: $("#summary-platforms-note"),
            infoSummaryAuth: $("#summary-auth"),
            infoSummaryAuthNote: $("#summary-auth-note"),
            infoSummarySanitizer: $("#summary-sanitizer"),
            infoSummarySanitizerNote: $("#summary-sanitizer-note"),
            infoSummaryAsync: $("#summary-async"),
            configAlert: $("#config-alert"),
            configAlertList: $("#config-alert-list"),
            healthTitle: $("#health-title"),
            healthPill: $("#health-pill"),
            healthPillText: $("#health-pill .status-pill__text"),
            healthVersion: $("#health-version"),
            healthTime: $("#health-time"),
            healthList: $("#platform-health"),
            healthRefresh: $("#health-refresh"),
            factAuth: $("#fact-auth"),
            factRate: $("#fact-rate"),
            factAsync: $("#fact-async"),
            factPrompt: $("#fact-prompt"),
            factSanitizer: $("#fact-sanitizer"),
            matrixBody: $("#capability-body"),
            matrixMeta: $("#matrix-meta"),
            genPlatform: $("#gen-platform"),
            genCapability: $("#gen-capability"),
            genCustom: $("#gen-custom"),
            workspaceFields: $("#workspace-fields"),
            genRegion: $("#gen-region"),
            genWorkspace: $("#gen-workspace"),
            codeOutput: $("#code-output"),
            codeCaption: $("#code-caption"),
            codePanelBody: $("#code-panel-body"),
            copyCode: $("#copy-code"),
            tabButtons: $$('[data-code-tab]'),
            connectionTitle: $("#connection-title"),
            connectionNote: $("#connection-note"),
            tokenInput: $("#client-token"),
            tokenHelp: $("#token-help"),
            tokenToggle: $("#token-visibility"),
            tokenClear: $("#token-clear"),
            playgroundMeta: $("#playground-meta"),
            chatPlatform: $("#chat-platform"),
            chatModel: $("#chat-model"),
            chatMessage: $("#chat-message"),
            chatStream: $("#chat-stream"),
            chatSubmit: $("#chat-submit"),
            chatError: $("#chat-error"),
            chatResponse: $("#chat-response"),
            chatOutput: $("#chat-output"),
            chatResponseMeta: $("#chat-response-meta"),
            chatState: $("#chat-state"),
            chatForm: $("#chat-form"),
            imagePlatform: $("#image-platform"),
            imageModel: $("#image-model"),
            imagePrompt: $("#image-prompt"),
            imageSubmit: $("#image-submit"),
            imageError: $("#image-error"),
            imageResults: $("#image-results"),
            imageGrid: $("#image-grid"),
            imageResponseMeta: $("#image-response-meta"),
            imageState: $("#image-state"),
            imageForm: $("#image-form"),
            systemPrompt: $("#system-prompt"),
            systemPromptMeta: $("#system-prompt-meta"),
            copyPrompt: $("#copy-prompt"),
            footerVersion: $("#footer-version"),
            localeToggle: $("#locale-toggle"),
            localeToggleLabel: $("#locale-toggle-label"),
            palette: $("#palette"),
            paletteOpen: $("#palette-open"),
            paletteClose: $("#palette-close"),
            paletteInput: $("#palette-input"),
            paletteList: $("#palette-list"),
            paletteEmpty: $("#palette-empty"),
            paletteCount: $("#palette-count"),
            announcer: $("#sr-announcer"),
        });
    }

    function bindEvents() {
        dom.healthRefresh.addEventListener("click", () => loadHealth({ manual: true }));
        dom.localeToggle.addEventListener("click", toggleLocale);

        dom.genPlatform.addEventListener("change", () => {
            populateGeneratorCapabilities();
            updateCustomRouteVisibility();
            updateGeneratedCode();
        });
        dom.genCapability.addEventListener("change", () => {
            state.generatorCapability = dom.genCapability.value;
            updateGeneratedCode();
        });
        dom.genCustom.addEventListener("change", () => {
            updateCustomRouteVisibility();
            updateGeneratedCode();
        });
        dom.genRegion.addEventListener("input", updateGeneratedCode);
        dom.genWorkspace.addEventListener("input", updateGeneratedCode);

        for (const tab of dom.tabButtons) {
            tab.addEventListener("click", () => activateCodeTab(tab.dataset.codeTab));
            tab.addEventListener("keydown", handleCodeTabKeys);
        }

        dom.copyCode.addEventListener("click", () => {
            copyText(dom.codeOutput.textContent, dom.copyCode, t("copy.label"));
        });
        dom.copyPrompt.addEventListener("click", () => {
            copyText(dom.systemPrompt.textContent, dom.copyPrompt, t("copy.label"));
        });

        dom.tokenInput.addEventListener("input", onTokenInput);
        dom.tokenToggle.addEventListener("click", toggleTokenVisibility);
        dom.tokenClear.addEventListener("click", clearToken);

        dom.chatPlatform.addEventListener("change", () => {
            setDefaultModel(dom.chatPlatform, dom.chatModel, "chat");
            clearFormError("chat");
        });
        dom.imagePlatform.addEventListener("change", () => {
            setDefaultModel(dom.imagePlatform, dom.imageModel, "images");
            clearFormError("image");
        });
        dom.chatModel.addEventListener("input", () => {
            dom.chatModel.dataset.auto = "false";
        });
        dom.imageModel.addEventListener("input", () => {
            dom.imageModel.dataset.auto = "false";
        });
        dom.chatForm.addEventListener("submit", submitChat);
        dom.imageForm.addEventListener("submit", submitImage);

        for (const field of $$("input, textarea, select", dom.chatForm)) {
            field.addEventListener("input", () => clearFieldError(field));
        }
        for (const field of $$("input, textarea, select", dom.imageForm)) {
            field.addEventListener("input", () => clearFieldError(field));
        }

        dom.paletteOpen.addEventListener("click", openPalette);
        dom.paletteClose.addEventListener("click", closePalette);
        dom.paletteInput.addEventListener("input", () => {
            state.paletteIndex = 0;
            renderPaletteCommands();
        });
        dom.paletteInput.addEventListener("keydown", handlePaletteKeys);
        dom.palette.addEventListener("click", (event) => {
            if (event.target === dom.palette) {
                closePalette();
            }
        });

        document.addEventListener("keydown", (event) => {
            if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === "k") {
                event.preventDefault();
                if (dom.palette.open) {
                    closePalette();
                } else {
                    openPalette();
                }
            }
        });
    }

    async function loadInfo() {
        try {
            const response = await fetch("/grotto/info", {
                headers: { Accept: "application/json" },
                cache: "no-store",
            });

            if (!response.ok) {
                throw new Error(t("error.infoHttp", { status: response.status }));
            }

            state.info = await response.json();
            state.infoError = null;
            state.healthError = null;
            applyInfo();
        } catch (error) {
            state.infoError = error;
            showConfigAlert([t("error.metadataUnavailable")]);
            dom.matrixMeta.textContent = t("common.unavailable");
            dom.matrixBody.replaceChildren(rowMessage(t("error.metadataUnavailableShort"), 7));
            dom.codeOutput.textContent = t("error.metadataUnavailableShort");
            dom.codeCaption.textContent = t("error.startAndReload");
            dom.systemPrompt.textContent = t("error.promptUnreadable");
            dom.systemPromptMeta.textContent = "";
            dom.footerVersion.textContent = "v—";
            dom.healthTitle.textContent = t("error.metadataTitle");
            announcer(t("error.loadFailed", { message: error.message }));
        }
    }

    function applyInfo() {
        const info = state.info;
        const platforms = Array.isArray(info.platforms) ? info.platforms : [];
        const warnings = Array.isArray(info.configuration_warnings)
            ? info.configuration_warnings.slice()
            : [];

        showConfigAlert(warnings);

        dom.infoSummaryPlatforms.textContent = t("stat.platformsEnabled", { count: platforms.length });
        dom.infoSummaryPlatformsNote.textContent = platforms.length
            ? platforms.map((platform) => platformName(platform)).join(" · ")
            : t("stat.noneRegistered");

        dom.infoSummaryAuth.textContent = t("stat.apiKeyRequest");
        dom.infoSummaryAuthNote.textContent = t("stat.apiKeyNote");

        dom.infoSummarySanitizer.textContent = info.image_sanitization?.enabled ? t("stat.on") : t("stat.off");
        dom.infoSummarySanitizerNote.textContent = t("stat.inlineBase64");

        dom.infoSummaryAsync.textContent = `${info.async_tasks?.timeout_seconds ?? "—"} s`;
        dom.infoSummaryPlatformsNote.title = platforms.map((platform) => platform.base_url).join("\n");

        dom.factAuth.textContent = t("fact.auth");
        dom.factRate.textContent = info.rate_limit?.enabled
            ? t("fact.rate", {
                max: info.rate_limit.max_requests,
                window: info.rate_limit.window_seconds,
            })
            : t("common.disabled");
        dom.factAsync.textContent = t("fact.async", {
            timeout: info.async_tasks?.timeout_seconds ?? "—",
            interval: info.async_tasks?.poll_interval_ms ?? "—",
        });
        dom.factPrompt.textContent = info.default_system_prompt?.enabled
            ? t("fact.prompt")
            : t("fact.promptOff");
        dom.factSanitizer.textContent = info.image_sanitization?.enabled
            ? t("fact.sanitizer", { formats: (info.image_sanitization.formats || []).join(", ") })
            : t("common.disabled");

        dom.footerVersion.textContent = `v${info.version}`;
        document.title = t("title.document", { name: info.name });

        applyPrompt();
        renderCapabilityMatrix();
        populateGeneratorPlatforms();
        populatePlaygroundPlatforms();
        updateConnectionState();
        updateGeneratedCode();
        buildPaletteCommands();
    }

    function applyPrompt() {
        const prompt = state.info?.default_system_prompt;

        if (!prompt) {
            dom.systemPrompt.textContent = t("policy.noPrompt");
            dom.systemPromptMeta.textContent = "";
            dom.copyPrompt.disabled = true;
            return;
        }

        if (prompt.exposed && typeof prompt.content === "string" && prompt.content.length > 0) {
            dom.systemPrompt.textContent = prompt.content;
            dom.copyPrompt.disabled = false;
            dom.copyPrompt.hidden = false;
        } else {
            dom.systemPrompt.textContent = t("policy.promptHidden");
            dom.copyPrompt.disabled = true;
            dom.copyPrompt.hidden = true;
        }

        dom.systemPromptMeta.textContent = t("policy.promptMeta", {
            status: prompt.enabled ? t("policy.injectEnabled") : t("policy.injectDisabled"),
            visibility: prompt.exposed ? t("policy.visible") : t("policy.redacted"),
            length: prompt.length ?? 0,
            hash: shortHash(prompt.sha256),
        });
    }

    function renderCapabilityMatrix() {
        const platforms = state.info?.platforms || [];

        dom.matrixMeta.textContent = t("capabilities.meta", { count: platforms.length });
        dom.matrixBody.replaceChildren();

        if (!platforms.length) {
            dom.matrixBody.append(rowMessage(t("capabilities.noPlatforms"), 7));
            return;
        }

        for (const platform of platforms) {
            const row = document.createElement("tr");
            const heading = document.createElement("th");
            heading.scope = "row";
            heading.append(el("span", "matrix__platform-name", platformName(platform)));

            const status = el("span", "matrix__platform-status");
            status.dataset.state = "ready";
            const dot = el("span", "status-dot");
            dot.setAttribute("aria-hidden", "true");
            status.append(dot, document.createTextNode(t("capabilities.enabled")));
            heading.append(status);
            row.append(heading);

            for (const capability of CAPABILITIES) {
                const available = Boolean(platform.capabilities?.[capability.id]);
                const label = capabilityLabel(capability.id);
                const cell = document.createElement("td");
                cell.dataset.label = capabilityColumn(capability.id);
                cell.setAttribute("aria-label", t(available ? "capability.available" : "capability.notAvailable", {
                    label,
                }));

                if (available) {
                    const chip = el("span", "cap cap--yes");
                    chip.append(checkIcon());
                    chip.append(srOnly(t("sr.available")));
                    cell.append(chip);
                } else {
                    const chip = el("span", "cap cap--no");
                    chip.setAttribute("aria-hidden", "true");
                    chip.textContent = "—";
                    cell.append(chip, srOnly(t("sr.notAvailable")));
                }

                row.append(cell);
            }

            dom.matrixBody.append(row);
        }
    }

    function populateGeneratorPlatforms() {
        const platforms = state.info?.platforms || [];
        const previous = dom.genPlatform.value;
        dom.genPlatform.replaceChildren();

        if (!platforms.length) {
            dom.genPlatform.append(option("", t("workbench.noPlatforms"), true));
            dom.genPlatform.disabled = true;
            dom.genCapability.disabled = true;
            dom.genCapability.replaceChildren(option("", t("common.unavailable"), true));
            return;
        }

        for (const platform of platforms) {
            dom.genPlatform.append(option(platform.id, platformOptionLabel(platform)));
        }

        const selected = platforms.some((platform) => platform.id === previous)
            ? previous
            : platforms[0].id;

        dom.genPlatform.value = selected;
        dom.genPlatform.disabled = false;
        populateGeneratorCapabilities();
        updateCustomRouteVisibility();
    }

    function populateGeneratorCapabilities() {
        const platform = selectedPlatform(dom.genPlatform.value);
        const previous = state.generatorCapability;
        dom.genCapability.replaceChildren();

        if (!platform) {
            dom.genCapability.append(option("", t("common.unavailable"), true));
            dom.genCapability.disabled = true;
            return;
        }

        const supported = CAPABILITIES.filter((capability) => platform.capabilities?.[capability.id]);
        dom.genCapability.disabled = supported.length === 0;

        if (!supported.length) {
            dom.genCapability.append(option("", t("workbench.noCapabilities"), true));
            return;
        }

        for (const capability of supported) {
            dom.genCapability.append(option(capability.id, capabilityLabel(capability.id)));
        }

        state.generatorCapability = supported.some((capability) => capability.id === previous)
            ? previous
            : supported[0].id;
        dom.genCapability.value = state.generatorCapability;
    }

    function updateCustomRouteVisibility() {
        const platform = selectedPlatform(dom.genPlatform.value);
        const supportsCustom = platform?.custom_base_url_template;

        if (!supportsCustom) {
            dom.genCustom.checked = false;
            dom.genCustom.disabled = true;
            dom.workspaceFields.hidden = true;
            return;
        }

        dom.genCustom.disabled = false;
        dom.workspaceFields.hidden = !dom.genCustom.checked;
    }

    function populatePlaygroundPlatforms() {
        populateCapabilityPlatformSelect(dom.chatPlatform, "chat");
        populateCapabilityPlatformSelect(dom.imagePlatform, "images");
        setDefaultModel(dom.chatPlatform, dom.chatModel, "chat");
        setDefaultModel(dom.imagePlatform, dom.imageModel, "images");
    }

    function populateCapabilityPlatformSelect(select, capabilityId) {
        const platforms = (state.info?.platforms || []).filter((platform) => platform.capabilities?.[capabilityId]);
        const previous = select.value;
        select.replaceChildren();

        if (!platforms.length) {
            select.append(option("", t("workbench.noSupportedPlatform"), true));
            select.disabled = true;
            return;
        }

        for (const platform of platforms) {
            select.append(option(platform.id, platformOptionLabel(platform)));
        }

        const selected = platforms.some((platform) => platform.id === previous)
            ? previous
            : platforms[0].id;

        select.value = selected;
        select.disabled = false;
    }

    function setDefaultModel(select, input, capability) {
        const platform = selectedPlatform(select.value);
        const previousDefault = input.dataset.platformDefault || "";
        const current = input.value.trim();
        const nextDefault = platform?.default_models?.[capability] || "";

        if (!current || input.dataset.auto === "true" || current === previousDefault) {
            input.value = nextDefault;
            input.dataset.auto = "true";
        }

        input.dataset.platformDefault = nextDefault;
        input.placeholder = nextDefault || "your-model";
    }

    function updateGeneratedCode() {
        if (!state.info) {
            return;
        }

        const platform = selectedPlatform(dom.genPlatform.value);
        const capability = CAPABILITIES.find((entry) => entry.id === dom.genCapability.value);

        if (!platform || !capability) {
            dom.codeOutput.textContent = t("code.selectPrompt");
            dom.codeCaption.textContent = t("code.noShape");
            dom.copyCode.disabled = true;
            return;
        }

        const base = generatorBaseUrl(platform);
        const model = platform.default_models?.[capability.id] || "your-model";
        const context = {
            platform,
            capability,
            base,
            model,
            keyEnv: apiKeyEnvName(platform.id),
            hasWorkspaceWarning: dom.genCustom.checked && (!dom.genRegion.value.trim() || !dom.genWorkspace.value.trim()),
        };

        dom.copyCode.disabled = false;
        dom.codeOutput.textContent = state.codeTab === "curl"
            ? buildCurl(context)
            : state.codeTab === "python"
                ? buildPython(context)
                : buildLibrePlugin(context);
        dom.codeCaption.textContent = buildCaption(context);
        dom.codePanelBody.setAttribute("aria-labelledby", `tab-${state.codeTab}`);
    }

    function generatorBaseUrl(platform) {
        if (dom.genCustom.checked && platform.id === "bailian") {
            const region = dom.genRegion.value.trim() || "cn-beijing";
            const workspace = dom.genWorkspace.value.trim() || "your-workspace";
            let origin = window.location.origin;

            try {
                origin = new URL(platform.base_url).origin;
            } catch {
                // Keep the current origin when the reported base URL is somehow unparseable.
            }

            return `${origin}/bailian/custom/${encodeURIComponent(region)}/${encodeURIComponent(workspace)}/v1`;
        }

        return String(platform.base_url || "").replace(/\/+$/, "");
    }

    function buildCurl(context) {
        const { capability, base, model, keyEnv } = context;
        const token = `$${keyEnv}`;
        const url = `${base}/${capability.path}`;

        if (capability.id === "models") {
            return `curl "${url}" \\\n  -H "Authorization: Bearer ${token}" \\\n  -H "Accept: application/json"`;
        }

        if (capability.id === "stt") {
            return `curl "${url}" \\\n  -H "Authorization: Bearer ${token}" \\\n  -F "file=@sample.mp3" \\\n  -F "model=${model}"`;
        }

        if (capability.id === "tts") {
            const body = {
                model,
                voice: "alloy",
                input: "Read this sentence aloud.",
            };
            return `curl "${url}" \\\n  -H "Authorization: Bearer ${token}" \\\n  -H "Content-Type: application/json" \\\n  -d '${json(body)}' \\\n  --output speech.mp3`;
        }

        const body = requestBodyFor(capability.id, model);
        return `curl "${url}" \\\n  -H "Authorization: Bearer ${token}" \\\n  -H "Content-Type: application/json" \\\n  -d '${json(body)}'`;
    }

    function buildPython(context) {
        const { capability, base, model, keyEnv } = context;
        const baseLiteral = pyString(base);
        const modelLiteral = pyString(model);
        const keyLiteral = pyString(keyEnv);

        if (capability.id === "models") {
            return `import os
from openai import OpenAI

client = OpenAI(
    base_url=${baseLiteral},
    api_key=os.environ[${keyLiteral}],
)

for model in client.models.list():
    print(model.id)`;
        }

        if (capability.id === "videos") {
            return `import os
import requests

response = requests.post(
    f"${base}/videos/generations",
    headers={"Authorization": f"Bearer {os.environ['${keyEnv}']}"},
    json=${pythonObject(indentObject(requestBodyFor("videos", model), 4))},
    timeout=330,
)
response.raise_for_status()
print(response.json())`;
        }

        const header = `import os
from openai import OpenAI

client = OpenAI(
    base_url=${baseLiteral},
    api_key=os.environ[${keyLiteral}],
)
`;

        if (capability.id === "chat") {
            return `${header}
response = client.chat.completions.create(
    model=${modelLiteral},
    messages=[
        {"role": "user", "content": "Explain the difference between a gateway and a reverse proxy."}
    ],
)
print(response.choices[0].message.content)`;
        }

        if (capability.id === "images") {
            return `${header}
image = client.images.generate(
    model=${modelLiteral},
    prompt="A quiet limestone grotto opening onto a cobalt-blue sea.",
    size="1024x1024",
)
print(image.data[0].url or image.data[0].b64_json)`;
        }

        if (capability.id === "tts") {
            return `${header}
with client.audio.speech.with_streaming_response.create(
    model=${modelLiteral},
    voice="alloy",
    input="Read this sentence aloud.",
) as response:
    response.stream_to_file("speech.mp3")`;
        }

        if (capability.id === "stt") {
            return `${header}
with open("sample.mp3", "rb") as audio_file:
    transcript = client.audio.transcriptions.create(
        model=${modelLiteral},
        file=audio_file,
    )

print(transcript.text)`;
        }

        return t("code.noShape");
    }

    function buildLibrePlugin(context) {
        const { platform, base, keyEnv } = context;
        const plugin = {
            id: `grotto-${platform.id}${dom.genCustom.checked ? "-workspace" : ""}`,
            name: `Grotto · ${platformName(platform)}`,
            type: "completion",
            endpoint: `${base}/chat/completions`,
            base_url: base,
            api_path: "/chat/completions",
            api_mode: "chat_completions",
            auth: {
                header: "Authorization",
                prefix: "Bearer ",
                key_env: keyEnv,
            },
            model_map: [platform.default_models?.chat || "gateway-chat"],
        };

        return JSON.stringify(plugin, null, 2);
    }

    function buildCaption(context) {
        const { capability, hasWorkspaceWarning } = context;
        const notes = [];

        if (hasWorkspaceWarning) {
            notes.push(t("caption.workspaceFill"));
        }

        if (dom.genCustom.checked) {
            notes.push(t("caption.workspaceKey"));
        }

        if (state.codeTab === "libre") {
            notes.push(t("caption.libre"));
        } else {
            notes.push(t(`caption.${capability.id}`));
        }

        return notes.filter(Boolean).join(" ");
    }

    function requestBodyFor(capability, model) {
        if (capability === "chat") {
            return {
                model,
                messages: [
                    {
                        role: "user",
                        content: "Explain the difference between a gateway and a reverse proxy.",
                    },
                ],
                stream: false,
            };
        }

        if (capability === "images") {
            return {
                model,
                prompt: "A quiet limestone grotto opening onto a cobalt-blue sea, soft daylight.",
                size: "1024x1024",
            };
        }

        if (capability === "videos") {
            return {
                model,
                prompt: "A slow dolly through a limestone grotto toward a cobalt-blue sea.",
            };
        }

        return { model };
    }

    function activateCodeTab(tab) {
        setCodeTab(tab);
        const button = dom.tabButtons.find((entry) => entry.dataset.codeTab === tab);
        if (button) {
            button.focus({ preventScroll: true });
        }
    }

    function setCodeTab(tab) {
        state.codeTab = tab;

        for (const button of dom.tabButtons) {
            const active = button.dataset.codeTab === tab;
            button.setAttribute("aria-selected", active ? "true" : "false");
            button.tabIndex = active ? 0 : -1;
        }

        updateGeneratedCode();
    }

    function handleCodeTabKeys(event) {
        const tabs = dom.tabButtons.map((tab) => tab.dataset.codeTab);
        const current = tabs.indexOf(event.currentTarget.dataset.codeTab);
        let next = null;

        if (event.key === "ArrowRight") {
            next = tabs[(current + 1) % tabs.length];
        } else if (event.key === "ArrowLeft") {
            next = tabs[(current - 1 + tabs.length) % tabs.length];
        } else if (event.key === "Home") {
            next = tabs[0];
        } else if (event.key === "End") {
            next = tabs[tabs.length - 1];
        }

        if (next) {
            event.preventDefault();
            setCodeTab(next);
            dom.tabButtons.find((tab) => tab.dataset.codeTab === next)?.focus({ preventScroll: true });
        }
    }

    async function loadHealth(options = {}) {
        if (state.healthInFlight) {
            return;
        }

        state.healthInFlight = true;

        if (options.manual) {
            setButtonBusy(dom.healthRefresh, true, t("health.refreshing"), t("head.refresh"));
        }

        try {
            const response = await fetch("/grotto/health", {
                headers: { Accept: "application/json" },
                cache: "no-store",
            });

            if (!response.ok) {
                throw new Error(t("health.httpError", { status: response.status }));
            }

            state.health = await response.json();
            state.healthError = null;
            applyHealth();
        } catch (error) {
            state.healthError = error;
            applyHealthFailure(error);
        } finally {
            state.healthInFlight = false;
            setButtonBusy(dom.healthRefresh, false, t("health.refreshing"), t("head.refresh"));
        }
    }

    function applyHealth() {
        const health = state.health;
        const items = health?.platforms?.items || [];
        const ready = Boolean(health?.ready);
        const overallState = ready ? "ok" : "degraded";

        dom.navStatus.dataset.state = overallState;
        dom.navStatusText.textContent = ready ? t("health.operational") : t("health.setupNeeded");

        dom.healthPill.dataset.state = overallState;
        dom.healthPillText.textContent = ready ? t("health.ready") : t("health.degraded");
        dom.healthTitle.textContent = ready ? t("health.gatewayReady") : t("health.setupRequired");

        dom.healthVersion.textContent = t("system.version", { version: health?.version || "—" });
        dom.healthTime.textContent = health?.time
            ? t("health.checked", { time: formatTime(health.time) })
            : t("health.checkedJustNow");

        dom.healthList.replaceChildren();

        if (!items.length) {
            dom.healthList.append(el("li", "health-list__placeholder", t("health.noPlatforms")));
            return;
        }

        for (const platform of items) {
            const item = document.createElement("li");
            const name = el("span", "health-list__name");
            const dot = el("span", "status-dot");
            dot.dataset.state = platform.status;
            dot.setAttribute("aria-hidden", "true");
            name.append(dot, document.createTextNode(healthPlatformName(platform)));

            const capabilities = el("span", "health-list__caps");
            capabilities.textContent = t("health.routes", { count: (platform.capabilities || []).length });

            const status = el("span", "status-pill");
            status.dataset.state = platform.status;
            const statusDot = el("span", "status-dot");
            statusDot.setAttribute("aria-hidden", "true");
            status.append(statusDot, el("span", "status-pill__text", t("health.ready")));

            item.append(name, capabilities, status);
            dom.healthList.append(item);
        }

        if (state.info) {
            dom.infoSummaryPlatforms.textContent = t("stat.platformsEnabled", { count: items.length });
        }
    }

    function applyHealthFailure(error) {
        state.health = null;
        if (error) {
            state.healthError = error;
        }

        dom.navStatus.dataset.state = "error";
        dom.navStatusText.textContent = t("health.error");
        dom.healthPill.dataset.state = "error";
        dom.healthPillText.textContent = t("health.unavailable");
        dom.healthTitle.textContent = t("health.endpointUnavailable");
        dom.healthVersion.textContent = t("system.versionUnknown");
        dom.healthTime.textContent = t("health.noResponse");
        dom.healthList.replaceChildren(
            el("li", "health-list__placeholder", t("health.failed"))
        );
        announcer(t("health.fetchFailed", { message: error?.message || t("health.noResponse") }));
    }

    function showConfigAlert(messages) {
        if (!messages.length) {
            dom.configAlert.hidden = true;
            dom.configAlertList.replaceChildren();
            return;
        }

        dom.configAlert.hidden = false;
        dom.configAlertList.replaceChildren();

        for (const message of messages) {
            dom.configAlertList.append(el("li", "", message));
        }
    }

    function onTokenInput() {
        state.token = dom.tokenInput.value;
        clearFormError("chat");
        clearFormError("image");
    }

    function toggleTokenVisibility() {
        const showing = dom.tokenInput.type === "text";
        dom.tokenInput.type = showing ? "password" : "text";
        updateTokenVisibilityLabel();
    }

    function updateTokenVisibilityLabel() {
        const showing = dom.tokenInput.type === "text";
        dom.tokenToggle.setAttribute("aria-pressed", showing ? "true" : "false");
        dom.tokenToggle.textContent = t(showing ? "playground.hide" : "playground.show");
    }

    function clearToken() {
        state.token = "";
        dom.tokenInput.value = "";
        dom.tokenInput.type = "password";
        updateTokenVisibilityLabel();
        dom.tokenInput.focus();
        clearFormError("chat");
        clearFormError("image");
    }

    function updateConnectionState() {
        dom.connectionTitle.textContent = t("playground.keyTitle");
        dom.connectionNote.textContent = t("playground.keyNote");
        dom.tokenHelp.textContent = t("playground.keyHelp");
        dom.tokenInput.disabled = false;
        dom.tokenClear.disabled = false;
    }

    async function submitChat(event) {
        event.preventDefault();

        if (state.chatRunning) {
            return;
        }

        clearFormError("chat");
        const platform = selectedPlatform(dom.chatPlatform.value);
        const model = dom.chatModel.value.trim();
        const message = dom.chatMessage.value.trim();
        const authProblem = validateAuth();
        const platformProblem = validatePlatform(platform, "chat");

        if (platformProblem) {
            showFormError("chat", platformProblem);
            if (!platform) {
                dom.chatPlatform.focus();
            }
            return;
        }

        if (!model) {
            markFieldInvalid(dom.chatModel);
            showFormError("chat", t("chat.modelRequired"));
            dom.chatModel.focus();
            return;
        }

        if (!message) {
            markFieldInvalid(dom.chatMessage);
            showFormError("chat", t("chat.messageRequired"));
            dom.chatMessage.focus();
            return;
        }

        if (authProblem) {
            showFormError("chat", authProblem);
            dom.tokenInput.focus();
            return;
        }

        state.chatRunning = true;
        setChatState("running", "state.sent");
        setButtonBusy(dom.chatSubmit, true, t("chat.sending"), t("chat.send"));
        dom.chatResponse.hidden = false;
        dom.chatResponse.setAttribute("aria-live", "off");
        dom.chatResponseMeta.textContent = t("chat.waitingUpstream");
        dom.chatOutput.textContent = t("chat.waitingToken");
        dom.chatOutput.classList.add("is-placeholder");
        dom.playgroundMeta.textContent = t("chat.metaRunning", { platform: platformName(platform) });
        const started = performance.now();
        let received = "";
        let usage = null;

        try {
            const response = await fetch(proxyUrl(platform, "chat/completions"), {
                method: "POST",
                headers: requestHeaders(),
                body: JSON.stringify({
                    model,
                    messages: [{ role: "user", content: message }],
                    stream: dom.chatStream.checked,
                }),
            });

            if (!response.ok) {
                throw new Error(await errorFromResponse(response));
            }

            const contentType = response.headers.get("Content-Type") || "";

            if (dom.chatStream.checked && contentType.includes("text/event-stream")) {
                const result = await readChatStream(response, (delta) => {
                    received += delta;
                    dom.chatOutput.classList.remove("is-placeholder");
                    dom.chatOutput.textContent = received;
                });
                usage = result.usage;
            } else {
                const payload = await response.json();
                received = extractChatText(payload);
                usage = payload.usage || null;
                dom.chatOutput.classList.remove("is-placeholder");
                dom.chatOutput.textContent = received || t("chat.empty");
            }

            if (!received) {
                dom.chatOutput.textContent = t("chat.emptyStream");
            }

            const elapsed = elapsedLabel(started);
            dom.chatResponseMeta.textContent = usageSummary(usage, elapsed);
            dom.chatResponse.setAttribute("aria-live", "polite");
            dom.playgroundMeta.textContent = t("chat.metaComplete", {
                platform: platformName(platform),
                elapsed,
            });
            setChatState("success", "state.complete");
            announcer(t("chat.completeAnnounce"));
        } catch (error) {
            dom.chatResponse.setAttribute("aria-live", "polite");
            dom.chatOutput.classList.remove("is-placeholder");
            dom.chatOutput.textContent = received || t("chat.noResponse");

            if (!received) {
                dom.chatResponse.hidden = true;
            }

            showFormError("chat", error.message);
            setChatState("error", "state.failed");
            dom.playgroundMeta.textContent = t("chat.metaFailed", { platform: platformName(platform) });
            announcer(t("chat.failedAnnounce", { message: error.message }));
        } finally {
            state.chatRunning = false;
            setButtonBusy(dom.chatSubmit, false, t("chat.sending"), t("chat.send"));
        }
    }

    async function submitImage(event) {
        event.preventDefault();

        if (state.imageRunning) {
            return;
        }

        clearFormError("image");
        const platform = selectedPlatform(dom.imagePlatform.value);
        const model = dom.imageModel.value.trim();
        const prompt = dom.imagePrompt.value.trim();
        const authProblem = validateAuth();
        const platformProblem = validatePlatform(platform, "images");

        if (platformProblem) {
            showFormError("image", platformProblem);
            if (!platform) {
                dom.imagePlatform.focus();
            }
            return;
        }

        if (!model) {
            markFieldInvalid(dom.imageModel);
            showFormError("image", t("image.modelRequired"));
            dom.imageModel.focus();
            return;
        }

        if (!prompt) {
            markFieldInvalid(dom.imagePrompt);
            showFormError("image", t("image.promptRequired"));
            dom.imagePrompt.focus();
            return;
        }

        if (authProblem) {
            showFormError("image", authProblem);
            dom.tokenInput.focus();
            return;
        }

        state.imageRunning = true;
        setImageState("running", "state.generating");
        setButtonBusy(dom.imageSubmit, true, t("image.generating"), t("image.generate"));
        dom.imageResults.hidden = true;
        dom.imageGrid.replaceChildren();
        dom.playgroundMeta.textContent = t("image.metaPolling", { platform: platformName(platform) });
        const started = performance.now();

        try {
            const response = await fetch(proxyUrl(platform, "images/generations"), {
                method: "POST",
                headers: requestHeaders(),
                body: JSON.stringify({
                    model,
                    prompt,
                    size: "1024x1024",
                }),
            });

            if (!response.ok) {
                throw new Error(await errorFromResponse(response));
            }

            const payload = await response.json();
            const images = Array.isArray(payload.data) ? payload.data : [];

            if (!images.length) {
                throw new Error(t("image.noData"));
            }

            const sanitizedCount = Number(response.headers.get("X-Grotto-Image-Sanitized") || 0);
            dom.imageGrid.replaceChildren();
            let rendered = 0;

            images.forEach((image, index) => {
                let source = null;
                let sourceType = "";

                if (typeof image.b64_json === "string" && image.b64_json.length > 0) {
                    source = `data:image/png;base64,${image.b64_json}`;
                    sourceType = t(sanitizedCount > 0 ? "image.sourceB64" : "image.sourceB64Raw");
                } else if (typeof image.url === "string" && allowedImageUrl(image.url)) {
                    source = image.url;
                    sourceType = image.url.startsWith("data:image/")
                        ? t(sanitizedCount > 0 ? "image.sourceData" : "image.sourceDataRaw")
                        : t("image.sourceRemote");
                }

                if (!source) {
                    return;
                }

                const figure = document.createElement("figure");
                const img = document.createElement("img");
                img.src = source;
                img.alt = prompt;
                img.width = 1024;
                img.height = 1024;
                img.loading = "lazy";
                img.decoding = "async";
                img.referrerPolicy = "no-referrer";
                const caption = document.createElement("figcaption");
                caption.textContent = t("image.caption", { index: index + 1, source: sourceType });
                figure.append(img, caption);
                dom.imageGrid.append(figure);
                rendered++;
            });

            if (!rendered) {
                throw new Error(t("image.noRenderable"));
            }

            const elapsed = elapsedLabel(started);
            dom.imageResults.hidden = false;
            dom.imageResponseMeta.textContent = t("image.count", { count: rendered, elapsed });
            dom.playgroundMeta.textContent = t("image.metaComplete", {
                platform: platformName(platform),
                elapsed,
            });
            setImageState("success", "state.complete");
            announcer(t("image.completeAnnounce", { count: rendered }));
        } catch (error) {
            showFormError("image", error.message);
            setImageState("error", "state.failed");
            dom.playgroundMeta.textContent = t("image.metaFailed", { platform: platformName(platform) });
            announcer(t("image.failedAnnounce", { message: error.message }));
        } finally {
            state.imageRunning = false;
            setButtonBusy(dom.imageSubmit, false, t("image.generating"), t("image.generate"));
        }
    }

    async function readChatStream(response, onDelta) {
        if (!response.body) {
            throw new Error(t("chat.streamUnsupported"));
        }

        const reader = response.body.getReader();
        const decoder = new TextDecoder();
        let buffer = "";
        let usage = null;
        let done = false;

        while (!done) {
            const chunk = await reader.read();

            if (chunk.done) {
                break;
            }

            buffer += decoder.decode(chunk.value, { stream: true });
            buffer = buffer.replace(/\r\n/g, "\n");
            let boundary = buffer.indexOf("\n\n");

            while (boundary !== -1) {
                const frame = buffer.slice(0, boundary);
                buffer = buffer.slice(boundary + 2);
                const result = handleSseFrame(frame);

                if (result.done) {
                    done = true;
                    break;
                }

                if (result.delta) {
                    onDelta(result.delta);
                }

                if (result.usage) {
                    usage = result.usage;
                }

                boundary = buffer.indexOf("\n\n");
            }
        }

        if (buffer.trim() !== "") {
            const result = handleSseFrame(buffer);
            if (result.delta) {
                onDelta(result.delta);
            }
            if (result.usage) {
                usage = result.usage;
            }
        }

        return { usage };
    }

    function handleSseFrame(frame) {
        const dataLines = frame
            .split("\n")
            .filter((line) => line.startsWith("data:"))
            .map((line) => line.slice(5).trimStart());

        if (!dataLines.length) {
            return { done: false, delta: "", usage: null };
        }

        const data = dataLines.join("\n");

        if (data === "[DONE]") {
            return { done: true, delta: "", usage: null };
        }

        let payload;

        try {
            payload = JSON.parse(data);
        } catch {
            return { done: false, delta: "", usage: null };
        }

        const choice = payload.choices?.[0];
        const delta = extractText(choice?.delta?.content ?? choice?.message?.content ?? choice?.text);

        return {
            done: false,
            delta,
            usage: payload.usage || null,
        };
    }

    function extractChatText(payload) {
        const choice = payload.choices?.[0];
        return extractText(choice?.message?.content ?? choice?.delta?.content ?? choice?.text);
    }

    function extractText(content) {
        if (typeof content === "string") {
            return content;
        }

        if (Array.isArray(content)) {
            return content
                .filter((part) => part && typeof part === "object" && typeof part.text === "string")
                .map((part) => part.text)
                .join("");
        }

        return "";
    }

    function validateAuth() {
        const key = state.token.trim();

        if (!key) {
            return t("auth.keyRequired");
        }

        if (key.length > 8192) {
            return t("auth.keyTooLong");
        }

        return "";
    }

    function validatePlatform(platform, capability) {
        if (!state.info) {
            return t("platform.metadataUnavailable");
        }

        if (!platform) {
            return t("platform.missing");
        }

        if (!platform.capabilities?.[capability]) {
            return t("platform.capabilityMissing", { platform: platformName(platform) });
        }

        return "";
    }

    function requestHeaders() {
        const headers = {
            Accept: "application/json",
            "Content-Type": "application/json",
        };
        const key = state.token.trim();

        if (key) {
            headers.Authorization = `Bearer ${key}`;
        }

        return headers;
    }

    function proxyUrl(platform, path) {
        try {
            const base = new URL(platform.base_url);
            if (base.origin === window.location.origin) {
                return `${base.pathname.replace(/\/+$/, "")}/${path}`;
            }
        } catch {
            // Fall through to a relative URL when metadata is malformed.
        }

        return `/${platform.id}/v1/${path}`;
    }

    async function errorFromResponse(response) {
        let payload = null;

        try {
            payload = await response.json();
        } catch {
            // Some upstreams return HTML or plain text. Keep the surface stable.
        }

        const message = payload?.error?.message || payload?.message;
        const code = payload?.error?.code;

        if (message && code) {
            return `${message} (${code})`;
        }

        if (message) {
            return message;
        }

        return t("error.gateway", { status: response.status });
    }

    function showFormError(kind, message) {
        const target = kind === "chat" ? dom.chatError : dom.imageError;
        target.textContent = message;
        target.hidden = false;
    }

    function clearFormError(kind) {
        const target = kind === "chat" ? dom.chatError : dom.imageError;
        target.textContent = "";
        target.hidden = true;
    }

    function markFieldInvalid(field) {
        field.setAttribute("aria-invalid", "true");
        field.closest(".field")?.classList.add("is-invalid");
    }

    function clearFieldError(field) {
        if (field.getAttribute("aria-invalid") === "true") {
            field.removeAttribute("aria-invalid");
            field.closest(".field")?.classList.remove("is-invalid");
        }
    }

    function setChatState(stateName, labelKey) {
        state.chatStatus = { name: stateName, key: labelKey };
        dom.chatState.dataset.state = stateName;
        dom.chatState.textContent = t(labelKey);
    }

    function setImageState(stateName, labelKey) {
        state.imageStatus = { name: stateName, key: labelKey };
        dom.imageState.dataset.state = stateName;
        dom.imageState.textContent = t(labelKey);
    }

    function setButtonBusy(button, busy, busyLabel, idleLabel) {
        button.disabled = busy;
        button.setAttribute("aria-busy", busy ? "true" : "false");

        if (button === dom.healthRefresh) {
            const label = $(".btn__label", button);
            if (label) {
                label.textContent = busy ? busyLabel : idleLabel;
            }
        } else {
            button.textContent = busy ? busyLabel : idleLabel;
        }
    }

    function selectedPlatform(id) {
        return (state.info?.platforms || []).find((platform) => platform.id === id) || null;
    }

    function platformOptionLabel(platform) {
        return platformName(platform);
    }

    function platformName(platform) {
        if (!platform) {
            return "";
        }

        return state.locale === "zh-CN"
            ? (platform.short_label_zh || platform.label_zh || platform.short_label || platform.label || platform.id)
            : (platform.short_label || platform.label || platform.id);
    }

    function healthPlatformName(platform) {
        return state.locale === "zh-CN"
            ? (platform.label_zh || platform.label || platform.id)
            : (platform.label || platform.id);
    }

    function capabilityLabel(id) {
        return t(`capability.${id}.label`);
    }

    function capabilityColumn(id) {
        return t(`capability.${id}.column`);
    }

    function apiKeyEnvName(platformId) {
        return `${String(platformId).toUpperCase()}_API_KEY`;
    }

    function option(value, label, disabled = false) {
        const element = document.createElement("option");
        element.value = value;
        element.textContent = label;
        element.disabled = disabled;
        return element;
    }

    function rowMessage(message, columns) {
        const row = document.createElement("tr");
        const cell = document.createElement("td");
        cell.colSpan = columns;
        cell.className = "matrix__loading";
        cell.textContent = message;
        row.append(cell);
        return row;
    }

    function el(tag, className = "", text = null) {
        const node = document.createElement(tag);

        if (className) {
            node.className = className;
        }

        if (text !== null) {
            node.textContent = text;
        }

        return node;
    }

    function srOnly(text) {
        return el("span", "visually-hidden", text);
    }

    function checkIcon() {
        const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
        svg.setAttribute("viewBox", "0 0 20 20");
        svg.setAttribute("aria-hidden", "true");
        svg.setAttribute("focusable", "false");
        const path = document.createElementNS("http://www.w3.org/2000/svg", "path");
        path.setAttribute("d", "M4.5 10.5 8 14l7.5-8");
        path.setAttribute("fill", "none");
        path.setAttribute("stroke", "currentColor");
        path.setAttribute("stroke-width", "1.8");
        path.setAttribute("stroke-linecap", "round");
        path.setAttribute("stroke-linejoin", "round");
        svg.append(path);
        return svg;
    }

    function buildPaletteCommands() {
        const commands = [
            { label: t("palette.cmd.run"), hint: t("palette.section"), run: () => scrollToSection("playground") },
            { label: t("palette.cmd.build"), hint: t("palette.section"), run: () => scrollToSection("workbench") },
            { label: t("palette.cmd.system"), hint: t("palette.section"), run: () => scrollToSection("system") },
            { label: t("palette.cmd.capabilities"), hint: t("palette.section"), run: () => scrollToSection("capabilities") },
            { label: t("palette.cmd.policy"), hint: t("palette.section"), run: () => scrollToSection("policy") },
            { label: t("palette.cmd.refresh"), hint: t("palette.action"), run: () => loadHealth({ manual: true }) },
            {
                label: t("palette.cmd.copyBase"),
                hint: t("palette.copy"),
                run: () => copyPlainText(state.info?.api_base_url || window.location.origin, t("palette.baseCopied")),
            },
            {
                label: t("palette.cmd.openInfo"),
                hint: t("palette.newTab"),
                run: () => window.open("/grotto/info", "_blank", "noopener"),
            },
            { label: t("palette.cmd.focusKey"), hint: t("palette.auth"), run: () => focusToken() },
        ];

        state.paletteCommands = commands;
    }

    function openPalette() {
        buildPaletteCommands();
        dom.paletteInput.value = "";
        state.paletteIndex = 0;
        dom.palette.showModal();
        renderPaletteCommands();
        window.requestAnimationFrame(() => dom.paletteInput.focus());
    }

    function closePalette() {
        if (dom.palette.open) {
            dom.palette.close();
        }
    }

    function filteredCommands() {
        const query = dom.paletteInput.value.trim().toLowerCase();

        if (!query) {
            return state.paletteCommands;
        }

        return state.paletteCommands.filter((command) =>
            `${command.label} ${command.hint}`.toLowerCase().includes(query)
        );
    }

    function renderPaletteCommands() {
        const commands = filteredCommands();
        state.paletteIndex = Math.max(0, Math.min(state.paletteIndex, commands.length - 1));
        dom.paletteList.replaceChildren();
        dom.paletteEmpty.hidden = commands.length !== 0;
        dom.paletteCount.textContent = t(commands.length === 1 ? "palette.count" : "palette.countPlural", {
            count: commands.length,
        });

        commands.forEach((command, index) => {
            const item = document.createElement("li");
            const button = document.createElement("button");
            const active = index === state.paletteIndex;
            button.type = "button";
            button.className = "palette__command";
            button.id = `palette-command-${index}`;
            button.setAttribute("role", "option");
            button.setAttribute("aria-selected", active ? "true" : "false");
            button.tabIndex = -1;
            button.append(el("span", "", command.label), el("span", "palette__command-hint", command.hint));
            button.addEventListener("click", () => runPaletteCommand(command));
            button.addEventListener("pointermove", () => {
                if (state.paletteIndex !== index) {
                    state.paletteIndex = index;
                    renderPaletteSelection();
                }
            });
            item.append(button);
            dom.paletteList.append(item);
        });

        dom.paletteInput.setAttribute(
            "aria-activedescendant",
            commands.length ? `palette-command-${state.paletteIndex}` : ""
        );
    }

    function renderPaletteSelection() {
        const buttons = $$(".palette__command", dom.paletteList);
        buttons.forEach((button, index) => {
            button.setAttribute("aria-selected", index === state.paletteIndex ? "true" : "false");
        });

        if (buttons.length) {
            dom.paletteInput.setAttribute("aria-activedescendant", `palette-command-${state.paletteIndex}`);
            buttons[state.paletteIndex]?.scrollIntoView({ block: "nearest" });
        }
    }

    function handlePaletteKeys(event) {
        const commands = filteredCommands();

        if (event.key === "ArrowDown") {
            event.preventDefault();
            if (commands.length) {
                state.paletteIndex = (state.paletteIndex + 1) % commands.length;
                renderPaletteSelection();
            }
        } else if (event.key === "ArrowUp") {
            event.preventDefault();
            if (commands.length) {
                state.paletteIndex = (state.paletteIndex - 1 + commands.length) % commands.length;
                renderPaletteSelection();
            }
        } else if (event.key === "Enter") {
            event.preventDefault();
            const command = commands[state.paletteIndex];
            if (command) {
                runPaletteCommand(command);
            }
        } else if (event.key === "Escape") {
            event.preventDefault();
            closePalette();
        }
    }

    function runPaletteCommand(command) {
        closePalette();
        window.setTimeout(() => command.run(), 0);
    }

    function scrollToSection(id) {
        document.getElementById(id)?.scrollIntoView({ behavior: prefersReducedMotion() ? "auto" : "smooth" });
    }

    function focusToken() {
        scrollToSection("playground");
        window.setTimeout(() => dom.tokenInput.focus(), 180);
    }

    async function copyText(text, button, idleLabel) {
        const label = $(".copy-btn__label", button) || button;
        const original = label.textContent;

        try {
            await writeClipboard(text);
            label.textContent = t("copy.copied");
            button.dataset.copied = "true";
            announcer(t("copy.copiedAnnounce"));
        } catch {
            label.textContent = t("copy.failed");
            announcer(t("palette.clipboardDenied"));
        }

        window.setTimeout(() => {
            label.textContent = original || idleLabel;
            delete button.dataset.copied;
        }, 1700);
    }

    async function copyPlainText(text, successMessage) {
        try {
            await writeClipboard(text);
            announcer(successMessage);
        } catch {
            announcer(t("palette.clipboardDenied"));
        }
    }

    async function writeClipboard(text) {
        if (!text) {
            throw new Error("Nothing to copy.");
        }

        if (navigator.clipboard && window.isSecureContext) {
            await navigator.clipboard.writeText(text);
            return;
        }

        const area = document.createElement("textarea");
        area.value = text;
        area.setAttribute("readonly", "");
        area.className = "clipboard-fallback";
        document.body.append(area);
        area.select();
        const success = document.execCommand("copy");
        area.remove();

        if (!success) {
            throw new Error("Copy failed.");
        }
    }

    function announcer(message) {
        dom.announcer.textContent = "";
        window.setTimeout(() => {
            dom.announcer.textContent = message;
        }, 20);
    }

    function allowedImageUrl(value) {
        if (value.startsWith("data:image/")) {
            return true;
        }

        try {
            const url = new URL(value, window.location.href);
            return url.protocol === "https:" || url.protocol === "http:";
        } catch {
            return false;
        }
    }

    function usageSummary(usage, elapsed) {
        if (!usage || typeof usage !== "object") {
            return elapsed;
        }

        const prompt = Number(usage.prompt_tokens || 0);
        const completion = Number(usage.completion_tokens || 0);
        const total = Number(usage.total_tokens || prompt + completion);

        return total > 0 ? `${elapsed} · ${t("usage.tokens", { count: total })}` : elapsed;
    }

    function formatTime(value) {
        const date = new Date(value);

        if (Number.isNaN(date.getTime())) {
            return t("time.justNow");
        }

        return new Intl.DateTimeFormat(state.locale === "zh-CN" ? "zh-CN" : "en", {
            hour: "2-digit",
            minute: "2-digit",
            second: "2-digit",
        }).format(date);
    }

    function elapsedLabel(started) {
        const elapsed = performance.now() - started;
        return elapsed >= 1000 ? `${(elapsed / 1000).toFixed(1)} s` : `${Math.round(elapsed)} ms`;
    }

    function shortHash(value) {
        if (typeof value !== "string" || !value) {
            return "—";
        }

        return `${value.slice(0, 12)}…`;
    }

    function json(value) {
        return JSON.stringify(value, null, 2).replace(/'/g, "'\\''");
    }

    function pyString(value) {
        return JSON.stringify(String(value));
    }

    function indentObject(value, spaces) {
        const padding = " ".repeat(spaces);
        return JSON.stringify(value, null, 4)
            .split("\n")
            .map((line, index) => (index === 0 ? line : padding + line))
            .join("\n");
    }

    function pythonObject(value) {
        return value;
    }

    function prefersReducedMotion() {
        return window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    }
})();
