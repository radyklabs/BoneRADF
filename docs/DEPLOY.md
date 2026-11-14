# BoneRADF — Deployment Guide

Status: Planned recipes (not implemented yet). Every snippet is a documented
contract, marked `planned:` where it describes future behavior. Locked
decisions D1–D8 apply (`app/docs/PRD.md` §1).

---

## 1. Why deploy is easy — zero dependencies

BoneRADF has **no runtime dependencies**: no Composer packages, no Node
build, no vendor/ directory. A deploy is:

1. Copy the project folder to the server.
2. Point PHP-FPM at it (one pool + one nginx vhost).
3. Write `.env` with secrets.

No `composer install`. No `npm ci`. No build step. The framework boots from
its own PSR-4 autoloader (locked D1). One install = one microservice
(locked D2) — scale by copying the folder, not by unpacking a toolchain.

---

## 2. Target environments

| Environment | Status |
|-------------|--------|
| PHP 8.1+ with PHP-FPM | **Primary target** |
| nginx + PHP-FPM | Preferred web stack |
| Apache + mod_php | Supported (snippet below) |
| PHP built-in server (`php -S`) | **Dev only** — never production |
| `bone serve` (planned) | Dev convenience wrapper over `php -S` |

Required PHP extensions (planned baseline): `pdo` (+ one driver per DSN),
`openssl` (JWT/cookies), `json` (bundled). Optional: `ext-redis` — cache and
rate-limit may use it only if present, never required.

---

## 3. Directory layout for deploy

```
/var/www/my-service/          — app root (project root = app/)
  public/                     — WEBROOT (only this is served)
    index.php                 — front controller
    .htaccess                 — Apache rewrite (planned)
  src/                        — framework + app code (NOT web-accessible)
  config/                     — typed config (NOT web-accessible)
  modules/                    — modules (NOT web-accessible)
  logs/                       — structured logs (outside webroot)
  .env                        — secrets, chmod 600
  bin/bone                    — CLI
```

Rule: `public/` is the webroot. `src/`, `config/`, `modules/`, `logs/`,
`.env` must never be directly URL-reachable. nginx: only `root = public`.
Apache: `DocumentRoot` points at `public/` AND deny rules guard the rest.

---

## 4. nginx + PHP-FPM (preferred, planned)

```nginx
server {
    listen 443 ssl http2;
    server_name api.example.com;

    root /var/www/my-service/public;
    index index.php;

    ssl_certificate     /etc/ssl/certs/api.example.com.pem;
    ssl_certificate_key /etc/ssl/private/api.example.com.key;

    # Never serve dotfiles (.env, .git)
    location ~ /\. { deny all; }

    location / {
        try_files $uri /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    # Deny everything outside public/ (defence in depth; root already limits it)
    location ~ ^/(src|config|modules|logs|bin)/ { deny all; }

    access_log /var/log/nginx/my-service.access.log;
    error_log  /var/log/nginx/my-service.error.log;
}
```

Dev-only alternative (no FPM, no nginx):

```sh
php -S 0.0.0.0:8080 -t public      # or: bone serve (planned)
```

---

## 5. Apache (mod_php, planned)

```apache
<VirtualHost *:443>
    ServerName api.example.com
    DocumentRoot /var/www/my-service/public

    <Directory /var/www/my-service/public>
        AllowOverride All
        Require all granted
    </Directory>

    # Deny direct access to everything else
    <DirectoryMatch "^/var/www/my-service/(src|config|modules|logs|bin)">
        Require all denied
    </DirectoryMatch>

    ErrorLog  ${APACHE_LOG_DIR}/my-service.error.log
    CustomLog ${APACHE_LOG_DIR}/my-service.access.log combined
</VirtualHost>
```

`public/.htaccess` (planned) — front-controller rewrite:

```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^ index.php [L]
```

Note: Apache `AllowOverride All` is only needed for this `.htaccess`;
prefer in-vhost config when you control the server.

---

## 6. PHP-FPM pool notes (planned)

Pool file: `/etc/php/8.1/fpm/pool.d/my-service.conf`

```ini
[my-service]
user = www-data
group = www-data
listen = /run/php/php8.1-fpm-my-service.sock
pm = dynamic
pm.max_children = 10
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3

; Logging — never display errors in prod
php_admin_value[display_errors] = Off
php_admin_value[error_log] = /var/www/my-service/logs/php-fpm.log
php_admin_flag[log_errors] = On
```

opcache — recommended prod settings (`php.ini` or pool override):

```ini
opcache.enable = 1
opcache.enable_cli = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 16
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0    ; prod: deploy = touch/restart FPM
```

With `validate_timestamps=0`, a code deploy requires
`systemctl reload php8.1-fpm` (planned operational note — without it old
opcache serves stale code).

---

## 7. `.env` + secrets

- One `.env` **per service** — every install has its own app secret and
  JWT keys (compromise does not cross services; `app/docs/SECURITY.md` §9).
- File permissions: `chmod 600 .env`, owner = FPM user. Never committed to
  VCS (`.gitignore` must list `.env`).
- Never logged; log redaction covers `SECRET|TOKEN|PASSWORD|KEY`.
- Rotation runbook: see `app/docs/SECURITY.md` §9 (introduce new secret as
  secondary verifier → deploy → age out old → drop).

```ini
# .env (planned example)
APP_ENV=prod
APP_SECRET=change-me-32-bytes-minimum
JWT_ALG=HS256
JWT_SECRET=change-me-too
DB_DSN=mysql:host=db;dbname=app;charset=utf8mb4
DB_USER=app
DB_PASS=change-me
```

---

## 8. Docker (one recipe, planned)

Multi-stage, opcache on, non-root. `php:8.1-cli` base (zero-dep means no
composer stage is needed):

```dockerfile
# ---- build stage (no deps to install; kept for future dev-time tools) ----
FROM php:8.1-cli AS build
WORKDIR /app
COPY . .

# ---- runtime ----
FROM php:8.1-cli
RUN docker-php-ext-install pdo_mysql \
 && { \
      echo 'opcache.enable=1'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'display_errors=Off'; \
    } > /usr/local/etc/php/conf.d/boneradf.ini

WORKDIR /app
COPY --from=build /app /app

RUN useradd -r -u 1000 app \
 && chown -R app:app /app
USER app

EXPOSE 8080
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

Production variant: replace CMD with `php-fpm` (use `php:8.1-fpm` base) and
put nginx in front; logs go to stdout/stderr (see §9). Secrets injected as
env vars at run time — never baked into the image.

---

## 9. Health check + logging

### Health endpoint (convention, planned)

```php
// GET /health  →  200
{ "status": "ok", "version": "1.0.0", "request_id": "..." }
```

Register it unauthenticated and outside rate limits; use it for LB checks,
Docker `HEALTHCHECK`, and deploy smoke tests.

### Where logs go

| Mode | Destination |
|------|-------------|
| Bare metal / VM | `logs/` dir (outside webroot), rotated |
| Container | stdout/stderr — the platform (Docker/K8s) rotates |

- Log format: structured JSON lines (timestamp, level, channel, context,
  `request_id`) — planned, `app/docs/PLAN.md` P2.
- Rotation (bare metal): `logrotate` daily, keep 14, compress, `copytruncate`
  on `logs/*.log`. Container mode: no app-side rotation needed.
- Never log secrets (redaction rule, `app/docs/SECURITY.md` §9).

---

## 10. Upgrade path — copy-folder install

1. Back up `config/`, `.env`, `logs/` (the only state that is yours).
2. Fetch new framework version; replace the **kernel/framework dirs**
   (`src/Kernel`, `src/Router`, ... — everything except your `src/` app
   code and `config/`).
3. Keep your `src/` (routes, controllers, services), `config/`, `.env`,
   `public/index.php` if you customized it.
4. Diff `app/docs/CHANGELOG.md` for breaking changes before reloading.
5. `bone test` (planned) → run the suite.
6. Reload PHP-FPM (`systemctl reload php8.1-fpm`) so opcache picks up new
   files (required when `validate_timestamps=0`).
7. Smoke: `GET /health` returns 200.

There is no `composer update` — the framework folder *is* the dependency.
Keep one backup folder of the previous version for instant rollback
(rename folders; no database migration step is implied by a framework bump).

---

Everything above is a planned contract until the matching phase in
`app/docs/PLAN.md` (P5) is marked done. Verify against
`app/docs/REPORT.md` before citing any recipe as tested.

Last synced: 2026-11-14 — project root moved to app/; decisions from user Q&A locked.
