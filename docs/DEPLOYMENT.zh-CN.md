# 部署指南

[English](DEPLOYMENT.md) · **简体中文**

本文介绍如何将 Grotto 部署在 **Nginx** 或 **Apache 2.4** + PHP-FPM 的生产环境中。`composer serve` 使用的 PHP 内置服务器仅适合本地开发。

仓库已经提供可直接复制修改的示例：

- [`deploy/nginx/grotto.conf`](../deploy/nginx/grotto.conf)
- [`deploy/apache/grotto.conf`](../deploy/apache/grotto.conf)
- [`deploy/php/grotto.ini`](../deploy/php/grotto.ini)
- [`deploy/logrotate/grotto`](../deploy/logrotate/grotto)

使用前请替换 `grotto.example.com`、`/var/www/grotto`、PHP-FPM socket 路径和 TLS 证书路径。

## 1. 架构与前提

```text
客户端
  │  HTTPS + Authorization: Bearer <平台 API Key>
  ▼
Nginx 或 Apache 2.4
  │  FastCGI
  ▼
PHP-FPM ──► Grotto ──► 百炼 / ModelScope / 火山方舟
              │
              └── storage/logs、storage/cache
```

- Web 服务器的文档根目录**必须**指向仓库的 `public/`。绝不能直接暴露 `src/`、`config/`、`storage/`、`tests/`、`.env` 或 `composer.json`。
- Grotto 必须挂载在域名根路径（`https://grotto.example.com/`）。验证界面和生成的示例使用 `/assets`、`/bailian/v1` 这样的根绝对路径。
- Grotto 是 bring-your-own-key 网关。`.env` 和服务器配置里没有平台密钥。调用方必须在每次请求中通过 `Authorization: Bearer <key>` 发送目标平台的密钥。
- 请使用 HTTPS。Bearer token 就是真实的平台凭据。
- 保留一个可写的 `storage/` 目录。默认文件限流器只对单台主机生效。

## 2. 环境要求

| 组件 | 要求 |
|---|---|
| PHP | 8.2 或更高 |
| PHP 扩展 | `curl`、`json`、`mbstring`、`openssl`、`sodium` |
| Composer | 2.x |
| Nginx | 建议 1.18+ |
| Apache | 2.4.13+（`CGIPassAuth` 需要；更早的 2.4 可用 `.htaccess` 回退方案） |
| TLS | 公网主机名对应的证书 |

部署前先确认 PHP：

```bash
php -v
php -m | grep -E 'curl|json|mbstring|openssl|sodium'
```

安装发行版对应的 PHP-FPM 包，例如：

```bash
# Debian / Ubuntu
sudo apt install nginx php8.3-fpm php8.3-curl php8.3-mbstring php8.3-xml

# RHEL / Rocky / AlmaLinux
sudo dnf install nginx php-fpm php-cli php-curl php-mbstring php-openssl php-sodium
```

不同发行版的包名可能不同。如果 PHP 扩展被拆分成独立包，请单独安装。

## 3. 准备应用

### 3.1 把代码放在文档根目录之外

一种常见布局：

```text
/var/www/grotto/
├── current -> releases/20261109
├── releases/
│   └── 20261109/
│       ├── public/          # 文档根目录
│       ├── src/
│       ├── storage/         # 共享的可写目录
│       └── .env             # 绝不能放进 public/
└── shared/
    ├── .env
    └── storage/
```

不使用软链接的简单布局也可以：

```text
/var/www/grotto/
├── public/
├── src/
├── storage/
└── .env
```

文档根目录使用 `/var/www/grotto/public`（发布目录布局则为 `/var/www/grotto/current/public`）。

### 3.2 安装依赖

```bash
cd /var/www/grotto
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

`--classmap-authoritative` 可以加快生产环境自动加载。新增类或修改类映射后，需要重新执行：

```bash
composer dump-autoload --no-dev --classmap-authoritative
```

### 3.3 创建 `.env`

```bash
cp .env.example .env
chmod 640 .env
```

至少设置：

```dotenv
GROTTO_PUBLIC_BASE_URL=https://grotto.example.com
GROTTO_CORS_ORIGINS=https://your-ui.example.com
GROTTO_RATE_LIMIT_ENABLED=true
GROTTO_RATE_LIMIT_MAX=60
GROTTO_RATE_LIMIT_WINDOW=60
GROTTO_ASYNC_TIMEOUT=300
GROTTO_UPSTREAM_TIMEOUT=120
```

重要配置：

| 变量 | 生产环境中的作用 |
|---|---|
| `GROTTO_PUBLIC_BASE_URL` | `/grotto/info` 和生成示例使用的对外 HTTPS 地址。TLS 在代理层终止时必须设置。 |
| `GROTTO_CORS_ORIGINS` | 使用明确来源，不要使用 `*`。 |
| `GROTTO_RATE_LIMIT_STORAGE` | 必须对 PHP-FPM 用户可写。多主机部署应换用共享存储；文件限流器只对单机有效。 |
| `GROTTO_ASYNC_TIMEOUT` | 加上 `GROTTO_UPSTREAM_TIMEOUT` 后必须小于 Web 服务器/代理的读取超时。 |
| `GROTTO_TRUSTED_PROXIES` | 仅当还有另一层代理连接 Grotto 时需要。见[第 7 节](#7-tls-与转发头)。 |
| `GROTTO_LOG_LEVEL` | 生产环境建议 `info` 或 `warning`。 |
| `GROTTO_UI_ENABLED` | 验证界面不应公开时设为 `false`。 |

Grotto 绝不会从 `.env` 读取平台密钥。不要添加 `BAILIAN_API_KEY`、`MODELSCOPE_API_KEY` 之类的变量。

### 3.4 设置所有者和权限

以下示例假设 PHP-FPM 以 `www-data` 运行。RHEL 通常是 `apache`，也可以使用独立的 `grotto` 池用户，请按实际情况调整。

```bash
sudo chown -R root:www-data /var/www/grotto
sudo find /var/www/grotto -type d -exec chmod 750 {} \;
sudo find /var/www/grotto -type f -exec chmod 640 {} \;
sudo chmod 640 /var/www/grotto/.env

# 运行时写入：日志和文件限流器。
sudo chown -R www-data:www-data /var/www/grotto/storage
sudo find /var/www/grotto/storage -type d -exec chmod 770 {} \;
sudo find /var/www/grotto/storage -type f -exec chmod 660 {} \;
```

不要使用 `chmod -R 777`。应用只需要写 `storage/logs/` 和 `storage/cache/`。

如果系统启用 SELinux，需要允许 Web 服务器读取应用并写入 storage：

```bash
sudo semanage fcontext -a -t httpd_sys_content_t '/var/www/grotto(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/grotto/storage(/.*)?'
sudo restorecon -Rv /var/www/grotto
```

### 3.5 验证配置

```bash
sudo -u www-data php /var/www/grotto/bin/grotto check
```

该命令不会发起 HTTP 请求，也不会探测上游可用性。它会输出对外基础地址、可信代理列表、storage 可写状态和启用的平台。如果显示 `Storage: NOT WRITABLE`，必须先修复权限再开始接收流量。

## 4. Nginx + PHP-FPM

### 4.1 安装并启动服务

```bash
sudo systemctl enable --now php8.3-fpm
sudo systemctl enable --now nginx
```

确认 FPM socket 存在：

```bash
ls -l /run/php/php8.3-fpm.sock
```

### 4.2 安装站点配置

```bash
sudo cp /var/www/grotto/deploy/nginx/grotto.conf /etc/nginx/sites-available/grotto.conf
sudo ln -s /etc/nginx/sites-available/grotto.conf /etc/nginx/sites-enabled/grotto.conf
sudo nginx -t
sudo systemctl reload nginx
```

如果默认站点也占用了同一个 `server_name`，请删除或调整：

```bash
sudo rm /etc/nginx/sites-enabled/default
```

### 4.3 配置说明

| 指令 | 作用 |
|---|---|
| `root /var/www/grotto/public;` | 只有 `public/` 可被 Web 访问。 |
| `try_files $uri /index.php$is_args$args;` | 所有非静态文件请求进入 Slim 前端控制器。 |
| `location = /index.php` | 只允许前端控制器执行；`router.php` 和其他 PHP 文件不可执行。 |
| `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` | 把调用方的平台密钥传给 PHP。 |
| `fastcgi_param HTTPS $https if_not_empty;` | Nginx 终止 TLS 时让 Slim 获得正确的 scheme。 |
| `fastcgi_read_timeout 480s;` | 覆盖 300 秒异步轮询加 120 秒上游请求。 |
| `X-Accel-Buffering: no`（Grotto 发送） | Nginx 仅对 SSE 响应关闭 FastCGI 缓冲。 |
| `client_max_body_size 32m;` | 与 PHP 的上传/请求体限制保持一致。 |
| `gzip_types ...` | 压缩 JSON 和界面资源；刻意不包含 `text/event-stream`。 |

如果 Nginx 版本为 1.25.1 或更高，可以把：

```nginx
listen 443 ssl http2;
```

改为：

```nginx
listen 443 ssl;
http2 on;
```

两种写法都受当前 Nginx 支持；旧写法在很新的版本上可能产生弃用警告。

### 4.4 验证

```bash
curl -fsS https://grotto.example.com/grotto/health
```

响应中应包含 `"status":"ok"` 和 `"ready":true`。然后打开：

```text
https://grotto.example.com/ui
```

验证代理请求：

```bash
export BAILIAN_API_KEY="sk-..."
curl -fsS https://grotto.example.com/bailian/v1/models \
  -H "Authorization: Bearer $BAILIAN_API_KEY"
```

## 5. Apache 2.4 + PHP-FPM

### 5.1 启用模块

Debian / Ubuntu：

```bash
sudo a2enmod rewrite headers proxy proxy_fcgi setenvif deflate ssl
sudo systemctl restart apache2
```

RHEL 系列：

```bash
sudo dnf install httpd mod_ssl php-fpm
sudo systemctl enable --now php-fpm
sudo systemctl enable --now httpd
```

需要的模块（`mod_rewrite`、`mod_headers`、`mod_proxy`、`mod_proxy_fcgi`、`mod_setenvif`、`mod_deflate`、`mod_ssl`）通常由发行版包提供。

### 5.2 安装虚拟主机

```bash
sudo cp /var/www/grotto/deploy/apache/grotto.conf /etc/apache2/sites-available/grotto.conf
sudo a2ensite grotto
sudo apachectl configtest
sudo systemctl reload apache2
```

RHEL 系列将文件复制到 `/etc/httpd/conf.d/grotto.conf`，然后执行：

```bash
sudo apachectl configtest
sudo systemctl reload httpd
```

### 5.3 配置说明

| 指令 | 作用 |
|---|---|
| `DocumentRoot /var/www/grotto/public` | 只暴露 `public/`。 |
| `CGIPassAuth On` | 把 `Authorization` 传给 FastCGI/CGI；需要 Apache 2.4.13+。 |
| `SetEnvIf Authorization ...` | FastCGI 环境不识别 `CGIPassAuth` 时的兼容回退。Apache < 2.4.13 时删除 `CGIPassAuth On`，保留此行。 |
| `ProxyPassMatch ... flushpackets=on` | PHP-FPM 响应（包括 SSE 分块）立即输出，不经过代理缓冲。 |
| `ProxyTimeout 480` | 覆盖 300 秒异步轮询加 120 秒上游请求。 |
| `RewriteRule ... index.php` | 在 `AllowOverride None` 时完成前端控制器路由。 |
| `SetEnvIf Request_URI ... no-gzip=1` | 对对话 SSE 关闭 `mod_deflate`。 |
| `LimitRequestBody 33554432` | 请求体上限 32 MiB，与 PHP 的 `post_max_size` 对齐。 |

示例使用 `AllowOverride None`，把重写规则放在虚拟主机里，避免每个请求都读取 `.htaccess`，性能更好。

如果主机使用 `mod_php` 而不是 PHP-FPM，请去掉 `ProxyPassMatch` 和 `ProxyTimeout`，改用发行版提供的 PHP 模块。重写、`no-gzip` 和点文件规则仍然适用。推荐使用 PHP-FPM，因为 worker 隔离更好，流式超时也更容易控制。

### 5.4 共享主机 / `.htaccess`

如果无法修改虚拟主机，仓库中的 [`public/.htaccess`](../public/.htaccess) 包含相同的前端控制器规则。其中 `Authorization` 转发通过 `SetEnvIf` 和 `mod_rewrite` 完成，只需要 `FileInfo`；`CGIPassAuth` 用在虚拟主机示例中。它需要：

- 启用 `mod_rewrite`。
- `AllowOverride All`，或至少允许 `FileInfo`、`Options` 和 `Indexes`（分别用于 `RewriteRule`、`Options` 和 `DirectoryIndex`）。
- `mod_setenvif` 提供 `Authorization` 回退。

如果 Apache 返回 `401 missing_api_key`，请确认 `Authorization` 到达了 PHP。按优先级处理：

1. 使用虚拟主机配置并启用 `CGIPassAuth On`。
2. 在 `.htaccess` 中加入 `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1`（仓库文件已包含）。
3. 请主机商启用 `CGIPassAuth` 或将请求头传给 PHP-FPM。

### 5.5 Nginx 反代 Apache

如果由 Nginx 终止 TLS 并反向代理到 Apache，请让 Apache 监听内网地址，并在 Nginx 中配置：

```nginx
location / {
    proxy_pass http://127.0.0.1:8080;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header X-Forwarded-Host $host;
    proxy_set_header X-Forwarded-Port $server_port;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_read_timeout 480s;
    proxy_buffering off; # 对话 SSE 必需
}
```

在 Grotto 实例上设置 `GROTTO_PUBLIC_BASE_URL`，或把 Nginx 地址加入 `GROTTO_TRUSTED_PROXIES`。示例对整段被代理路径关闭了 `proxy_buffering`，对专用 API 虚拟主机是可以接受的。如果同一虚拟主机还要承载其他大响应，请把对话路由拆到单独的 `location`，仅在那里关闭缓冲。

## 6. PHP、OPcache 与 PHP-FPM 调优

### 6.1 PHP 配置

将 [`deploy/php/grotto.ini`](../deploy/php/grotto.ini) 安装为 PHP 配置片段：

```bash
# Debian / Ubuntu
sudo cp /var/www/grotto/deploy/php/grotto.ini /etc/php/8.3/fpm/conf.d/99-grotto.ini

# RHEL 系列
sudo cp /var/www/grotto/deploy/php/grotto.ini /etc/php.d/99-grotto.ini

sudo systemctl reload php8.3-fpm   # RHEL 上通常是 php-fpm
```

关键点：

- `display_errors=Off` 防止堆栈信息进入 API 响应。
- `output_buffering=Off` 和 `zlib.output_compression=Off` 防止 SSE 一次性到达。
- `max_execution_time=480` 必须大于 `GROTTO_ASYNC_TIMEOUT` 加上上游请求超时。
- `opcache.validate_timestamps=0` 性能更好，但每次发布代码后都必须 reload PHP-FPM。如果不想每次发布都 reload，可设为 `1`。
- `memory_limit`、`post_max_size`、`upload_max_filesize` 不能小于 Web 服务器的请求体限制。

### 6.2 PHP-FPM 池

独立的池更便于控制权限和资源：

```ini
[grotto]
user = www-data
group = www-data
listen = /run/php/php8.3-fpm.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 20
pm.start_servers = 4
pm.min_spare_servers = 2
pm.max_spare_servers = 8
pm.max_requests = 500

request_terminate_timeout = 480s
catch_workers_output = yes
```

根据可用内存和并发媒体任务数计算 `pm.max_children`。一个媒体请求会占用一个 worker，时间约为 `GROTTO_ASYNC_TIMEOUT` 加上初始上游请求；worker 用尽时，客户端会看到 `502 Bad Gateway` 或 `504 Gateway Timeout`。媒体负载较重时，应增加 worker，或把异步任务迁移到独立队列服务。

### 6.3 流式响应检查

Nginx：

Grotto 会在 SSE 响应中发送 `X-Accel-Buffering: no`，示例依赖 Nginx 识别该头，并让普通 JSON 响应保持默认 FastCGI 缓冲。不要把 `text/event-stream` 加入 `gzip_types`；如果使用通配 gzip 规则，请在流式 location 中设置 `gzip off;`。如果外层代理会移除 `X-Accel-Buffering`，请为对话路由单独增加一个 location 并设置 `fastcgi_buffering off;`。

Apache：

```apache
ProxyPassMatch ... flushpackets=on
SetEnvIf Request_URI ".../chat/completions" no-gzip=1
```

用 `curl -N` 验证；客户端应按到达顺序逐块打印：

```bash
curl -N https://grotto.example.com/bailian/v1/chat/completions \
  -H "Authorization: Bearer $BAILIAN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"model":"qwen-plus","stream":true,"messages":[{"role":"user","content":"慢慢数到五。"}]}'
```

如果响应一次性出现，请逐层检查：Nginx `fastcgi_buffering`、外层 `proxy_buffering`、Apache `flushpackets`、`mod_deflate`、PHP `output_buffering` 和 `zlib.output_compression`。

## 7. TLS 与转发头

### 7.1 推荐：显式设置对外地址

```dotenv
GROTTO_PUBLIC_BASE_URL=https://grotto.example.com
```

该值优先级最高，在负载均衡或 CDN 终止 TLS 时最可预测。

### 7.2 可选：信任转发头

如果未设置 `GROTTO_PUBLIC_BASE_URL`，Grotto 会从请求推导对外地址。只有直接对端地址（`REMOTE_ADDR`）匹配以下列表时，才会使用 `X-Forwarded-Proto`、`X-Forwarded-Host` 和 `X-Forwarded-Port`：

```dotenv
GROTTO_TRUSTED_PROXIES=10.0.0.0/8,2001:db8::/32
```

- 支持单个 IPv4/IPv6 地址或 CIDR 范围。
- 默认为空；来自公网客户端的转发头一律忽略。
- 只填写**直接连接** Grotto 的代理地址，不要把客户端网段加进去。
- 边缘代理必须覆盖（而不是追加）不可信的入站 `X-Forwarded-*` 头。Grotto 使用每个头的第一个值。
- 如果存在多级代理，最后一级代理地址必须在列表中，并且整条链路应由你的基础设施规范化。

外层 Nginx 代理示例：

```nginx
proxy_set_header Host $host;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Port $server_port;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

Apache 的 `mod_remoteip` 也可以实现类似效果，但 `GROTTO_PUBLIC_BASE_URL` 更简单、更不容易出错。

### 7.3 HSTS

确认域名已完全通过 HTTPS 提供服务后，再启用 `Strict-Transport-Security`：

```nginx
add_header Strict-Transport-Security "max-age=31536000" always;
```

示例配置特意将 HSTS 保持为注释状态。

## 8. 日志、健康检查与轮转

Grotto 默认将只包含元数据的请求日志写入 `storage/logs/grotto.log`。不会记录提示词、响应体、请求头或 API Key。

Nginx 和 Apache 的访问日志默认不包含 `Authorization`。不要向访问日志格式中加入 `$http_authorization` 或 `%{Authorization}i`。

健康检查：

```bash
curl -fsS https://grotto.example.com/grotto/health
```

监控 HTTP 状态码和 `ready` 字段。该端点只报告进程和适配器就绪状态，**不会**检查上游可达性，也不会验证密钥。

安装日志轮转示例：

```bash
sudo cp /var/www/grotto/deploy/logrotate/grotto /etc/logrotate.d/grotto
sudo logrotate -d /etc/logrotate.d/grotto   # 试运行
```

示例使用 `copytruncate`，因为 Monolog 会持续持有日志文件。也可以正常轮转后 reload PHP-FPM。

## 9. 部署与升级

使用软链接发布目录时：

```bash
cd /var/www/grotto/releases
git clone --depth 1 <repository-url> 20261110
cd 20261110
composer install --no-dev --optimize-autoloader --classmap-authoritative
ln -s /var/www/grotto/shared/.env .env
rm -rf storage
ln -s /var/www/grotto/shared/storage storage
sudo -u www-data php bin/grotto check
```

然后切换软链接并 reload PHP-FPM：

```bash
ln -sfn /var/www/grotto/releases/20261110 /var/www/grotto/current
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx   # 仅当 Nginx 配置变化时
curl -fsS https://grotto.example.com/grotto/health
```

示例中 `opcache.validate_timestamps=0`，因此代码变更后必须 reload PHP-FPM 才会生效。发布失败时，把 `current` 指回上一个版本并再次 reload PHP-FPM。

## 10. 故障排查

| 现象 | 常见原因与处理 |
|---|---|
| `401 missing_api_key` | `Authorization` 没有到达 PHP。检查 Nginx 的 `fastcgi_param HTTP_AUTHORIZATION`、Apache 的 `CGIPassAuth On` 或 `SetEnvIf`，以及外层代理是否剥离了该头。 |
| 所有路由都 404 | 文档根目录错误，或缺少前端控制器重写。检查 `try_files` / `RewriteRule`。 |
| `500` 且返回 `startup_failure` | 配置或 storage 错误。查看 `storage/logs/grotto.log` 和 PHP-FPM/Apache 错误日志，检查 `.env` 语法与权限。 |
| `502 Bad Gateway` | Nginx 无法连接 FPM socket，或 PHP-FPM 没有空闲 worker。检查 socket 路径、权限和 `pm.max_children`。 |
| `504 Gateway Timeout` | 媒体任务超过 `GROTTO_ASYNC_TIMEOUT`，或代理/Web 服务器超时。保证 `GROTTO_ASYNC_TIMEOUT` 加 `GROTTO_UPSTREAM_TIMEOUT` 后仍小于 `fastcgi_read_timeout` / `ProxyTimeout`，或一起调大。 |
| SSE 一次性到达 | 存在缓冲或压缩。检查 `fastcgi_buffering`、`proxy_buffering`、Apache `flushpackets`、`no-gzip`、`output_buffering` 和 `zlib.output_compression`。 |
| `/grotto/info` 显示 `http://` 或内网主机名 | 设置 `GROTTO_PUBLIC_BASE_URL`，或配置 `GROTTO_TRUSTED_PROXIES` 并让代理发送 `X-Forwarded-Proto` / `X-Forwarded-Host`。 |
| `413 Request Entity Too Large` | 调大 Nginx 的 `client_max_body_size` 或 Apache 的 `LimitRequestBody`，同时调整 PHP 的 `post_max_size` / `upload_max_filesize`。 |
| 所有客户端共享同一个限流额度 | 文件限流器只对单机有效。API 请求按调用方密钥指纹分组，不按 IP 分组。多主机部署请使用共享限流器。 |
| 界面资源 404 | 界面被禁用，或文件不在 `UiController::ASSETS` 中。`public/assets/` 不是界面资源目录。 |
| CORS 错误 | 设置明确的 `GROTTO_CORS_ORIGINS` 并包含界面来源。带凭据的 CORS 不能与 `*` 同时使用。 |
| 日志中出现 `Permission denied` | `storage/logs` 和 `storage/cache` 对 PHP-FPM 用户不可写。修正所有者和权限，不要对整个目录树使用 777。 |
| 上游 TLS 错误 | 保持 `GROTTO_UPSTREAM_VERIFY_SSL=true`；修复 CA 证书包或系统时间，而不是关闭校验。 |

## 11. 安全检查清单

- [ ] 文档根目录是 `public/`；`.env`、`src/`、`storage/`、`config/` 不可通过 Web 访问。
- [ ] 已强制 HTTPS；确认 HTTPS 正常后才启用 HSTS。
- [ ] `GROTTO_CORS_ORIGINS` 是明确列表，不是 `*`。
- [ ] `GROTTO_TRUSTED_PROXIES` 为空，或只包含受控代理地址。
- [ ] Nginx/Apache 访问日志不记录 `Authorization`。
- [ ] `storage/` 只对 PHP-FPM 用户可写；`.env` 权限为 `640` 或更严格。
- [ ] PHP 已设置 `display_errors=Off`、`expose_php=Off`，并明确决定 `opcache.validate_timestamps` 的值。
- [ ] Web 服务器和 PHP 的请求体限制保持一致。
- [ ] 已用 `curl -N` 验证流式响应，并用真实长任务验证媒体超时。
- [ ] 已监控 `/grotto/health`，并配置日志轮转。
- [ ] 平台密钥在平台侧轮换；Grotto 不保存密钥副本。
- [ ] 如果网关不应公开，请在网络或反向代理层限制访问。Grotto 没有独立的网关密码。

## 12. 参考文件

| 文件 | 用途 |
|---|---|
| [`deploy/nginx/grotto.conf`](../deploy/nginx/grotto.conf) | 完整的 Nginx server 配置，包含 TLS、FastCGI、流式和 gzip。 |
| [`deploy/apache/grotto.conf`](../deploy/apache/grotto.conf) | 完整的 Apache 虚拟主机，包含 PHP-FPM、`Authorization`、流式和压缩。 |
| [`deploy/php/grotto.ini`](../deploy/php/grotto.ini) | 生产环境 PHP/OPcache 配置。 |
| [`deploy/logrotate/grotto`](../deploy/logrotate/grotto) | `storage/logs` 的日志轮转。 |
| [`public/.htaccess`](../public/.htaccess) | 共享主机的前端控制器和 `Authorization` 回退。 |

API 请求/响应结构和平台差异请参阅 [`API.zh-CN.md`](API.zh-CN.md) 和 [`PLATFORMS.zh-CN.md`](PLATFORMS.zh-CN.md)。
