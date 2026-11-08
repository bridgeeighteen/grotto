# Deployment guide

**English** · [简体中文](DEPLOYMENT.zh-CN.md)

This guide covers a production deployment of Grotto behind **Nginx** or **Apache 2.4**, with PHP-FPM. The PHP built-in server used by `composer serve` is for local development only.

The repository includes ready-to-copy examples:

- [`deploy/nginx/grotto.conf`](../deploy/nginx/grotto.conf)
- [`deploy/apache/grotto.conf`](../deploy/apache/grotto.conf)
- [`deploy/php/grotto.ini`](../deploy/php/grotto.ini)
- [`deploy/logrotate/grotto`](../deploy/logrotate/grotto)

Replace `grotto.example.com`, `/var/www/grotto`, the PHP-FPM socket path, and the TLS certificate paths before use.

## 1. Architecture and assumptions

```text
client
  │  HTTPS + Authorization: Bearer <platform-api-key>
  ▼
Nginx or Apache 2.4
  │  FastCGI
  ▼
PHP-FPM ──► Grotto ──► Bailian / ModelScope / Volcengine Ark
              │
              └── storage/logs, storage/cache
```

- The web server document root **must** be the repository's `public/` directory. Never expose `src/`, `config/`, `storage/`, `tests/`, `.env`, or `composer.json`.
- Mount Grotto at the origin root (`https://grotto.example.com/`). The UI and generated examples use root-absolute paths such as `/assets` and `/bailian/v1`.
- Grotto is bring-your-own-key. There are no provider credentials in `.env` or in the server configuration. Callers send the target platform key on every request as `Authorization: Bearer <key>`.
- Use HTTPS. The bearer token is a real platform credential.
- Keep one writable `storage/` directory. The default rate limiter is local to one host.

## 2. Requirements

| Component | Requirement |
|---|---|
| PHP | 8.2 or newer |
| PHP extensions | `curl`, `json`, `mbstring`, `openssl`, `sodium` |
| Composer | 2.x |
| Nginx | 1.18+ recommended |
| Apache | 2.4.13+ (for `CGIPassAuth`; the `.htaccess` fallback works on older 2.4 builds) |
| TLS | A certificate for the public hostname |

Verify PHP before deploying:

```bash
php -v
php -m | grep -E 'curl|json|mbstring|openssl|sodium'
```

Install the matching PHP-FPM package for your distribution, for example:

```bash
# Debian / Ubuntu
sudo apt install nginx php8.3-fpm php8.3-curl php8.3-mbstring php8.3-xml

# RHEL / Rocky / AlmaLinux
sudo dnf install nginx php-fpm php-cli php-curl php-mbstring php-openssl php-sodium
```

Package names vary by distribution. Enable the extensions your PHP build packages separately if needed.

## 3. Prepare the application

### 3.1 Place the code outside the document root

A common layout is:

```text
/var/www/grotto/
├── current -> releases/20261109
├── releases/
│   └── 20261109/
│       ├── public/          # document root
│       ├── src/
│       ├── storage/         # shared writable directory
│       └── .env             # never in public/
└── shared/
    ├── .env
    └── storage/
```

A simple non-symlinked layout also works:

```text
/var/www/grotto/
├── public/
├── src/
├── storage/
└── .env
```

Use the document root `/var/www/grotto/public` (or `/var/www/grotto/current/public` with releases).

### 3.2 Install dependencies

```bash
cd /var/www/grotto
composer install --no-dev --optimize-autoloader --classmap-authoritative
```

`--classmap-authoritative` makes autoloading fast in production. Run `composer dump-autoload --no-dev --classmap-authoritative` again after changing the class map or adding files.

### 3.3 Create `.env`

```bash
cp .env.example .env
chmod 640 .env
```

Set at least:

```dotenv
GROTTO_PUBLIC_BASE_URL=https://grotto.example.com
GROTTO_CORS_ORIGINS=https://your-ui.example.com
GROTTO_RATE_LIMIT_ENABLED=true
GROTTO_RATE_LIMIT_MAX=60
GROTTO_RATE_LIMIT_WINDOW=60
GROTTO_ASYNC_TIMEOUT=300
GROTTO_UPSTREAM_TIMEOUT=120
```

Important values:

| Variable | Why it matters in production |
|---|---|
| `GROTTO_PUBLIC_BASE_URL` | External HTTPS origin used in `/grotto/info` and generated examples. Set it when TLS terminates at a proxy. |
| `GROTTO_CORS_ORIGINS` | Use explicit origins instead of `*`. |
| `GROTTO_RATE_LIMIT_STORAGE` | Must be writable by PHP-FPM. Use a shared store for multiple hosts (the file limiter is local). |
| `GROTTO_ASYNC_TIMEOUT` | Keep it plus `GROTTO_UPSTREAM_TIMEOUT` below web-server / proxy read timeouts. |
| `GROTTO_TRUSTED_PROXIES` | Only needed when another proxy connects to Grotto. See [section 7](#7-tls-and-forwarded-headers). |
| `GROTTO_LOG_LEVEL` | Use `info` or `warning` in production. |
| `GROTTO_UI_ENABLED` | Set `false` if the verification UI should not be public. |

Grotto never reads provider keys from `.env`. Do not add `BAILIAN_API_KEY`, `MODELSCOPE_API_KEY`, or similar variables.

### 3.4 Set ownership and permissions

The examples below assume PHP-FPM runs as `www-data`. Adjust for your distribution (`apache` on RHEL, or a dedicated `grotto` pool user).

```bash
sudo chown -R root:www-data /var/www/grotto
sudo find /var/www/grotto -type d -exec chmod 750 {} \;
sudo find /var/www/grotto -type f -exec chmod 640 {} \;
sudo chmod 640 /var/www/grotto/.env

# Runtime writes: logs and the file rate limiter.
sudo chown -R www-data:www-data /var/www/grotto/storage
sudo find /var/www/grotto/storage -type d -exec chmod 770 {} \;
sudo find /var/www/grotto/storage -type f -exec chmod 660 {} \;
```

Do not use `chmod -R 777`. The application only needs to write `storage/logs/` and `storage/cache/`.

On SELinux systems, allow the web server to read the application and write storage:

```bash
sudo semanage fcontext -a -t httpd_sys_content_t '/var/www/grotto(/.*)?'
sudo semanage fcontext -a -t httpd_sys_rw_content_t '/var/www/grotto/storage(/.*)?'
sudo restorecon -Rv /var/www/grotto
```

### 3.5 Validate configuration

```bash
sudo -u www-data php /var/www/grotto/bin/grotto check
```

This does not make an HTTP request and does not check upstream availability. It prints the public base URL, trusted proxy list, storage writability, and enabled platforms. `Storage: NOT WRITABLE` must be fixed before starting traffic.

## 4. Nginx + PHP-FPM

### 4.1 Install and start the services

```bash
sudo systemctl enable --now php8.3-fpm
sudo systemctl enable --now nginx
```

Confirm the FPM socket exists:

```bash
ls -l /run/php/php8.3-fpm.sock
```

### 4.2 Install the site configuration

```bash
sudo cp /var/www/grotto/deploy/nginx/grotto.conf /etc/nginx/sites-available/grotto.conf
sudo ln -s /etc/nginx/sites-available/grotto.conf /etc/nginx/sites-enabled/grotto.conf
sudo nginx -t
sudo systemctl reload nginx
```

Remove or adjust the default site if it also claims the same `server_name`:

```bash
sudo rm /etc/nginx/sites-enabled/default
```

### 4.3 What the configuration does

| Directive | Purpose |
|---|---|
| `root /var/www/grotto/public;` | Only `public/` is web-accessible. |
| `try_files $uri /index.php$is_args$args;` | Sends every non-file request to the Slim front controller. |
| `location = /index.php` | Only the front controller may execute. `router.php` and other PHP files are not reachable. |
| `fastcgi_param HTTP_AUTHORIZATION $http_authorization;` | Forwards the caller's platform key to PHP. |
| `fastcgi_param HTTPS $https if_not_empty;` | Lets Slim see the correct scheme when Nginx terminates TLS. |
| `fastcgi_read_timeout 480s;` | Covers 300s async polling plus a 120s upstream request. |
| `X-Accel-Buffering: no` (sent by Grotto) | Nginx disables FastCGI buffering for SSE responses only. |
| `client_max_body_size 32m;` | Matches the PHP upload/post limits. |
| `gzip_types ...` | Compresses JSON and UI assets. `text/event-stream` is intentionally absent. |

If your Nginx is 1.25.1 or newer, you can replace:

```nginx
listen 443 ssl http2;
```

with:

```nginx
listen 443 ssl;
http2 on;
```

Both forms are supported by current Nginx. The older `listen ... http2` form may produce a deprecation warning on very new versions.

### 4.4 Verify

```bash
curl -fsS https://grotto.example.com/grotto/health
```

The response should contain `"status":"ok"` and `"ready":true`. Then open:

```text
https://grotto.example.com/ui
```

To verify a proxied request:

```bash
export BAILIAN_API_KEY="sk-..."
curl -fsS https://grotto.example.com/bailian/v1/models \
  -H "Authorization: Bearer $BAILIAN_API_KEY"
```

## 5. Apache 2.4 + PHP-FPM

### 5.1 Enable modules

Debian / Ubuntu:

```bash
sudo a2enmod rewrite headers proxy proxy_fcgi setenvif deflate ssl
sudo systemctl restart apache2
```

RHEL family:

```bash
sudo dnf install httpd mod_ssl php-fpm
sudo systemctl enable --now php-fpm
sudo systemctl enable --now httpd
```

The required modules (`mod_rewrite`, `mod_headers`, `mod_proxy`, `mod_proxy_fcgi`, `mod_setenvif`, `mod_deflate`, `mod_ssl`) are usually available as packages or built in.

### 5.2 Install the virtual host

```bash
sudo cp /var/www/grotto/deploy/apache/grotto.conf /etc/apache2/sites-available/grotto.conf
sudo a2ensite grotto
sudo apachectl configtest
sudo systemctl reload apache2
```

For RHEL, copy the file to `/etc/httpd/conf.d/grotto.conf` and run:

```bash
sudo apachectl configtest
sudo systemctl reload httpd
```

### 5.3 What the configuration does

| Directive | Purpose |
|---|---|
| `DocumentRoot /var/www/grotto/public` | Only `public/` is exposed. |
| `CGIPassAuth On` | Passes `Authorization` to FastCGI/CGI. Requires Apache 2.4.13+. |
| `SetEnvIf Authorization ...` | Compatibility fallback for FastCGI stacks that do not use `CGIPassAuth`. On Apache < 2.4.13, remove `CGIPassAuth On` and keep this line. |
| `ProxyPassMatch ... flushpackets=on` | Sends PHP-FPM responses, including SSE chunks, without proxy buffering. |
| `ProxyTimeout 480` | Covers 300s async polling plus a 120s upstream request. |
| `RewriteRule ... index.php` | Front controller routing when `AllowOverride None` is used. |
| `SetEnvIf Request_URI ... no-gzip=1` | Disables `mod_deflate` for chat SSE. |
| `LimitRequestBody 33554432` | Caps request bodies at 32 MiB. Match PHP's `post_max_size`. |

The example sets `AllowOverride None` and keeps the rewrite rules in the virtual host, which is faster than reading `.htaccess` on every request.

If your host uses `mod_php` instead of PHP-FPM, omit `ProxyPassMatch` and `ProxyTimeout` and use the distribution's PHP module. The rewrite, `no-gzip`, and dotfile rules still apply. PHP-FPM is recommended because it isolates workers and makes streaming timeouts easier to control.

### 5.4 Shared hosting / `.htaccess`

If you cannot edit the virtual host, the repository's [`public/.htaccess`](../public/.htaccess) contains the same front-controller rules. It forwards `Authorization` with `SetEnvIf` and `mod_rewrite`, which only need `FileInfo`; `CGIPassAuth` is used in the virtual-host example instead. It requires:

- `mod_rewrite` enabled.
- `AllowOverride All`, or at least `FileInfo`, `Options`, and `Indexes` (for `RewriteRule`, `Options`, and `DirectoryIndex`).
- `mod_setenvif` for the `Authorization` fallback.

If Apache returns `401 missing_api_key`, confirm that `Authorization` reaches PHP. In order of preference:

1. Use the virtual-host configuration with `CGIPassAuth On`.
2. Add `SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1` to `.htaccess` (already included).
3. Ask the host to enable `CGIPassAuth` or pass the header to PHP-FPM.

### 5.5 Apache behind Nginx

If Nginx terminates TLS and proxies to Apache, keep Apache on a private listener and configure Nginx like this:

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
    proxy_buffering off; # required for chat SSE
}
```

Set `GROTTO_PUBLIC_BASE_URL` on the Grotto instance, or list the Nginx address in `GROTTO_TRUSTED_PROXIES`. The snippet disables `proxy_buffering` for the whole proxied location; that is acceptable for a dedicated API virtual host. If the same virtual host serves other large responses, move the chat route to a separate `location` and disable buffering only there.

## 6. PHP, OPcache, and PHP-FPM tuning

### 6.1 PHP settings

Install [`deploy/php/grotto.ini`](../deploy/php/grotto.ini) as a PHP configuration drop-in:

```bash
# Debian / Ubuntu
sudo cp /var/www/grotto/deploy/php/grotto.ini /etc/php/8.3/fpm/conf.d/99-grotto.ini

# RHEL family
sudo cp /var/www/grotto/deploy/php/grotto.ini /etc/php.d/99-grotto.ini

sudo systemctl reload php8.3-fpm   # or php-fpm on RHEL
```

Key points:

- `display_errors=Off` keeps stack traces out of API responses.
- `output_buffering=Off` and `zlib.output_compression=Off` prevent SSE from arriving in one burst.
- `max_execution_time=480` must exceed `GROTTO_ASYNC_TIMEOUT` plus the upstream request timeout.
- `opcache.validate_timestamps=0` is fast, but requires a PHP-FPM reload after every deployment. If you deploy frequently without reloads, leave it at `1`.
- `memory_limit`, `post_max_size`, and `upload_max_filesize` must be at least as large as the web-server body limit.

### 6.2 PHP-FPM pool

A dedicated pool makes limits and permissions clearer:

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

Size `pm.max_children` from available RAM and from the number of concurrent media jobs. A media request can occupy a worker for `GROTTO_ASYNC_TIMEOUT` plus the initial upstream request; if workers run out, clients see `502 Bad Gateway` or `504 Gateway Timeout`. For heavy media workloads, increase workers or move asynchronous jobs to a separate queue-based service.

### 6.3 Streaming checks

Nginx:

Grotto sends `X-Accel-Buffering: no` on SSE responses, so the sample relies on Nginx honoring that header and keeps normal FastCGI buffering for JSON. Keep `text/event-stream` out of `gzip_types`; if you use a wildcard gzip rule, add `gzip off;` to the streaming location. If an outer proxy strips `X-Accel-Buffering`, add a dedicated location for the chat route with `fastcgi_buffering off;`.

Apache:

```apache
ProxyPassMatch ... flushpackets=on
SetEnvIf Request_URI ".../chat/completions" no-gzip=1
```

Test with `curl -N`; the client should print chunks as they arrive:

```bash
curl -N https://grotto.example.com/bailian/v1/chat/completions \
  -H "Authorization: Bearer $BAILIAN_API_KEY" \
  -H "Content-Type: application/json" \
  -d '{"model":"qwen-plus","stream":true,"messages":[{"role":"user","content":"Count slowly to five."}]}'
```

If the whole response appears at once, check every layer: Nginx `fastcgi_buffering`, an outer `proxy_buffering`, Apache `flushpackets`, `mod_deflate`, PHP `output_buffering`, and `zlib.output_compression`.

## 7. TLS and forwarded headers

### 7.1 Preferred: set the public origin explicitly

```dotenv
GROTTO_PUBLIC_BASE_URL=https://grotto.example.com
```

This value always wins and is the most predictable option when TLS terminates at a load balancer or CDN.

### 7.2 Optional: trust forwarded headers

If `GROTTO_PUBLIC_BASE_URL` is unset, Grotto derives the origin from the request. It uses `X-Forwarded-Proto`, `X-Forwarded-Host`, and `X-Forwarded-Port` **only** when the direct peer address (`REMOTE_ADDR`) matches an entry in:

```dotenv
GROTTO_TRUSTED_PROXIES=10.0.0.0/8,2001:db8::/32
```

- Entries may be single IPv4/IPv6 addresses or CIDR ranges.
- The list is empty by default; forwarded headers from public clients are ignored.
- Configure only the addresses that connect **directly** to Grotto. Do not add client networks.
- The edge proxy must overwrite, not append to, untrusted incoming `X-Forwarded-*` headers. Grotto uses the first value in each header.
- If a proxy chain exists, the final proxy's address must be in the list and the chain must be normalized by your infrastructure.

Example outer Nginx proxy:

```nginx
proxy_set_header Host $host;
proxy_set_header X-Forwarded-Proto $scheme;
proxy_set_header X-Forwarded-Host $host;
proxy_set_header X-Forwarded-Port $server_port;
proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
```

Equivalent Apache `mod_remoteip` setups are possible, but `GROTTO_PUBLIC_BASE_URL` is simpler and less error-prone.

### 7.3 HSTS

Enable `Strict-Transport-Security` only after the hostname is fully served over HTTPS:

```nginx
add_header Strict-Transport-Security "max-age=31536000" always;
```

The sample configuration leaves HSTS commented out intentionally.

## 8. Logs, health checks, and rotation

Grotto writes metadata-only request logs to `storage/logs/grotto.log` by default. It does not log prompts, response bodies, headers, or API keys.

Nginx and Apache access logs do not include `Authorization` headers by default. Do not add `$http_authorization` or `%{Authorization}i` to access-log formats.

Health check:

```bash
curl -fsS https://grotto.example.com/grotto/health
```

Monitor the HTTP status and `ready` field. The endpoint reports process and adapter readiness; it does **not** check upstream reachability or validate keys.

Install the logrotate example:

```bash
sudo cp /var/www/grotto/deploy/logrotate/grotto /etc/logrotate.d/grotto
sudo logrotate -d /etc/logrotate.d/grotto   # dry run
```

The example uses `copytruncate` because Monolog keeps the file open. An alternative is to rotate normally and reload PHP-FPM.

## 9. Deploy and upgrade

For a symlinked release layout:

```bash
cd /var/www/grotto/releases
git clone --depth 1 <repository-url> 20261110
cd 20261110
composer install --no-dev --optimize-autoloader --classmap-authoritative
ln -s /var/www/grotto/shared/.env .env
ln -s /var/www/grotto/shared/storage storage
sudo -u www-data php bin/grotto check
```

Then switch the symlink and reload PHP-FPM:

```bash
ln -sfn /var/www/grotto/releases/20261110 /var/www/grotto/current
sudo systemctl reload php8.3-fpm
sudo nginx -t && sudo systemctl reload nginx   # only if the Nginx config changed
curl -fsS https://grotto.example.com/grotto/health
```

Because `opcache.validate_timestamps=0` is used in the example, the PHP-FPM reload is required for code changes to take effect. If a deployment fails, point `current` back to the previous release and reload PHP-FPM again.

## 10. Troubleshooting

| Symptom | Likely cause and fix |
|---|---|
| `401 missing_api_key` | `Authorization` is not reaching PHP. Check `fastcgi_param HTTP_AUTHORIZATION` (Nginx), `CGIPassAuth On` or `SetEnvIf` (Apache), and any outer proxy that strips the header. |
| `404` for every route | The document root is wrong or the front-controller rewrite is missing. Check `try_files` / `RewriteRule`. |
| `500` with `startup_failure` | Configuration or storage error. Read `storage/logs/grotto.log` and the PHP-FPM/Apache error log. Check `.env` syntax and permissions. |
| `502 Bad Gateway` | Nginx cannot reach the FPM socket, or PHP-FPM has no free workers. Check the socket path, permissions, and `pm.max_children`. |
| `504 Gateway Timeout` | A media job exceeded `GROTTO_ASYNC_TIMEOUT` or a proxy/web-server timeout. Keep `GROTTO_ASYNC_TIMEOUT` plus `GROTTO_UPSTREAM_TIMEOUT` below `fastcgi_read_timeout` / `ProxyTimeout`, or raise the limits together. |
| SSE arrives all at once | Buffering or compression is enabled. Check `fastcgi_buffering`, `proxy_buffering`, Apache `flushpackets`, `no-gzip`, `output_buffering`, and `zlib.output_compression`. |
| `/grotto/info` shows `http://` or an internal host | Set `GROTTO_PUBLIC_BASE_URL`, or configure `GROTTO_TRUSTED_PROXIES` and make the proxy send `X-Forwarded-Proto` / `X-Forwarded-Host`. |
| `413 Request Entity Too Large` | Raise `client_max_body_size` (Nginx) or `LimitRequestBody` (Apache), and `post_max_size` / `upload_max_filesize` in PHP. |
| Rate limit is shared by all clients | The file limiter is local to one host. API requests are keyed by the caller's key fingerprint, not by IP. Use a shared limiter for multiple hosts. |
| UI assets 404 | The UI is disabled, or the file is not in `UiController::ASSETS`. `public/assets/` is not the UI asset directory. |
| CORS errors | Set explicit `GROTTO_CORS_ORIGINS` and include the UI origin. Credentials cannot be combined with `*`. |
| `Permission denied` in logs | `storage/logs` and `storage/cache` are not writable by the PHP-FPM user. Fix ownership, not permissions on the whole tree. |
| Upstream TLS error | Keep `GROTTO_UPSTREAM_VERIFY_SSL=true`; fix the CA bundle / system time instead of disabling verification. |

## 11. Security checklist

- [ ] Document root is `public/`; `.env`, `src/`, `storage/`, and `config/` are not web-accessible.
- [ ] HTTPS is enforced and HSTS is enabled only after HTTPS works.
- [ ] `GROTTO_CORS_ORIGINS` is an explicit list, not `*`.
- [ ] `GROTTO_TRUSTED_PROXIES` is empty or contains only controlled proxy addresses.
- [ ] Nginx/Apache access logs do not record `Authorization`.
- [ ] `storage/` is writable by the PHP-FPM user only; `.env` is `640` or stricter.
- [ ] PHP `display_errors=Off`, `expose_php=Off`, and `opcache.validate_timestamps` is set intentionally.
- [ ] Request body limits are set consistently at the web server and PHP layers.
- [ ] Streaming is tested with `curl -N`; media timeouts are tested with a real long-running job.
- [ ] `/grotto/health` is monitored, and logs are rotated.
- [ ] Platform keys are rotated at the platform; Grotto stores no key copy.
- [ ] If the gateway should not be public, restrict it at the network or reverse-proxy layer. Grotto has no separate gateway password.

## 12. Reference files

| File | Use |
|---|---|
| [`deploy/nginx/grotto.conf`](../deploy/nginx/grotto.conf) | Complete Nginx server block with TLS, FastCGI, streaming, and gzip settings. |
| [`deploy/apache/grotto.conf`](../deploy/apache/grotto.conf) | Complete Apache virtual host with PHP-FPM, `Authorization`, streaming, and compression settings. |
| [`deploy/php/grotto.ini`](../deploy/php/grotto.ini) | Production PHP/OPcache settings. |
| [`deploy/logrotate/grotto`](../deploy/logrotate/grotto) | Log rotation for `storage/logs`. |
| [`public/.htaccess`](../public/.htaccess) | Shared-hosting front controller and Authorization fallback. |

For API shapes and platform-specific behavior, see [`API.md`](API.md) and [`PLATFORMS.md`](PLATFORMS.md).
