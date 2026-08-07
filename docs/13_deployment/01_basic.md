# Deployment

## Entry Points

All web traffic must be routed through `index.php` — it loads `lf-boot/app.php` (constants, autoloader, hooks) and calls `Url::dispatch()`. Everything else in the project root should be unreachable directly.

### Apache

`.htaccess` ships with the rewrite rule already in place:

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

Point your VirtualHost's document root at the project root (not a `public/` subdirectory — Laika serves `index.php` directly from the root).

### nginx

Use the bundled sample as a `location` block include:

```nginx
location / {
    if ($http_authorization != "") {
        set $auth_header $http_authorization;
    }

    try_files $uri /index.php$is_args$args;
}
```

Add your own `fastcgi_pass`/PHP-FPM config around it — `nginx.conf` in the project root only covers the rewrite behavior.

## Pre-Deploy Checklist

- [ ] `composer install --no-dev --prefer-dist --optimize-autoloader` — production install, no dev dependencies
- [ ] `php laika fix:secret` — ensure `lf-storage/keys/app.key` exists (already runs on `composer install` via `post-autoload-dump`, but confirm)
- [ ] Set `DEBUG` to `false` in `lf-inc/const.php` before going live — it's `true` by default for local development
- [ ] Review `MEMORY_LIMIT` / `CLI_MEMORY_LIMIT` in `lf-inc/const.php` for your workload
- [ ] Point `lf-config/database.php` (and `redis.php`/`memcached.php`/`mail.php` if used) at production credentials — never commit real credentials
- [ ] `php laika app:migrate` — create/update tables (your own `App\Schema` classes plus any installed package schemas, e.g. `laika-auth`'s `auth_tokens`, `laika-queue`'s job tables)
- [ ] `php laika app:sync` — ensure `uploads/` exists and `.htaccess` is in place
- [ ] Set cookie `secure`/`samesite` appropriately for HTTPS in [session](../11_sessions/01_basic.md) and [auth](../09_authentication/01_basic.md) config
- [ ] Ensure `lf-cache/`, `lf-logs/`, `lf-storage/`, and `uploads/` are writable by the web server user, and **not** publicly reachable over HTTP
- [ ] Confirm Composer plugin trust (`laikait/laika-cli`, `laikait/laika-queue`) is set in `composer.json` — see [Installation](../01_getting-started/01_installation.md#composer-plugin-trust-prompt)

## Queue Worker

The `worker` executable (see [Queue](../12_queue/01_basic.md)) is a long-running process, not a cron job — keep it alive with a process supervisor:

```ini
; /etc/supervisor/conf.d/laika-queue-worker.conf
[program:laika-queue-worker]
command=php /path/to/project/worker default
directory=/path/to/project
autostart=true
autorestart=true
numprocs=2
stopsignal=TERM
```

For more than one queue, add a separate `program` block (or `numprocs` group) per queue name. The worker self-restarts on memory pressure — supervisor/systemd just needs to bring it back up when it exits.

## What Not to Deploy

- `.git/`, `docs/` *(optional — harmless but unnecessary on the server)*
- `lf-storage/keys/app.key` should be **generated on the server**, not copied from your dev machine, unless you specifically need the same key across environments
- Anything under `lf-cache/` — regenerate it on deploy, don't ship stale compiled Twig templates

## CI

The framework and each `laikait/*` package run PHP 8.1–8.5 compatibility checks and lint on every push via GitHub Actions (`.github/workflows/test.yml`), and build/publish a release artifact on every `v*.*.*` tag (`.github/workflows/release.yml`). See each package's own workflow files for the exact matrix.
