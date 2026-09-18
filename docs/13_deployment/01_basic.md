# Deployment

## Entry Points

All web traffic must be routed through `index.php` — it loads `lf-boot/app.php` and calls `Url::dispatch()`. The framework itself then decides which static files may be served (see [`lf-config/assets.php`](../01_getting-started/03_configuration.md#lf-configassetsphp)), so nothing else in the project root should be reachable directly.

Point the web server's document root at the **project root** — Laika has no `public/` subdirectory.

### Apache

`.htaccess` ships with the rewrite rule in place (and `php laika app:sync` regenerates it if it's missing):

```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{HTTP:Authorization} .
    RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
    RewriteRule ^ index.php [QSA,L]
</IfModule>
```

`mod_rewrite` must be enabled and `AllowOverride` must allow `FileInfo` for the directory.

### nginx

Do not hand-write this. `nginx.conf` in the project root is generated, and
`php laika nginx:server` prints a complete server block that includes it:

```bash
php laika nginx:make                                  # (re)generate nginx.conf
php laika nginx:server --domain=example.com --php=8.3 # full server block
```

`nginx.conf` holds the deny rules and the front-controller rewrite:

```nginx
location / {
    if ($http_authorization != "") {
        set $auth_header $http_authorization;
    }

    rewrite ^ /index.php last;
}
```

Every request is rewritten to `index.php`, including ones that map to a real file, so
the framework decides what may be served rather than nginx handing files out before PHP
runs. The cost is that static assets go through PHP instead of nginx's static path —
see [Static file caching](#static-file-caching) below for what that does and does not cost.

> The server block **must** define `location = /index.php`. That is what terminates the
> rewrite above — without it nginx loops and returns 500. It also means no PHP file
> other than the front controller can ever be executed directly. `php laika nginx:server`
> emits it for you; keep the `include` of `nginx.conf` above it.

### Static file caching

Static files are served by `Laika\Route\Asset`, not by the web server, so the cache
policy comes from [`lf-config/assets.php`](../01_getting-started/03_configuration.md#lf-configassetsphp)
rather than from an `expires` directive. Out of the box every response carries:

| Header | |
|---|---|
| `ETag`, `Last-Modified` | a conditional request is answered `304` with no body |
| `Cache-Control` | per-extension `max_age`; `immutable` for a URL carrying `?v=` |
| `Accept-Ranges: bytes` | `Range` requests get a `206`, so audio and video seek |

Do not add `expires` or `Cache-Control` directives in nginx or Apache for these paths —
they would fight the per-extension policy. Tune `lf-config/assets.php` instead.

URLs from `asset()` and `enqueue_*` carry `?v={mtime}`, so they are served `immutable` and
the browser reuses its copy without revalidating until the file itself changes.

A static file is answered during boot, **before the function and hook files load** — that
is what keeps it from paying for the framework it does not use. The trade-off is that a
hook cannot influence an asset response: `MimeType::register()` and the `CORS::` setters
have no effect there. CORS itself still runs, so preflights and cross-origin fonts are
unaffected.

The one thing PHP still does is move the bytes, which holds an FPM worker for the whole
transfer. If that matters — large media, many concurrent downloads — hand the transfer
to the server with the `sendfile` key, which keeps the authorisation decision in PHP and
the I/O out of it.

**nginx** (`'sendfile' => 'x-accel-redirect'`). The app sends the file's path relative to
the project root, so nginx needs an `internal` location covering the roots you serve from:

```nginx
location ~ ^/(assets|uploads|template)/ {
    internal;
    root /var/www/example.com;
}
```

`internal` is what makes this safe: the location is reachable only from an
`X-Accel-Redirect`, never from the outside, so requests still enter through `index.php`
and nginx moves the bytes only once PHP has named the file. Add any other servable root
to that regex, or the app will point nginx at a location it does not handle and the
response will be empty.

**Apache** (`'sendfile' => 'x-sendfile'`) needs `mod_xsendfile`:

```apache
XSendFile On
XSendFilePath /var/www/example.com
```

Without the matching server config either setting produces empty responses, so change it
on a staging host first.

### PHP-FPM

Every `laikait/*` package runs under PHP-FPM. `php laika nginx:server` already targets an FPM
socket (`unix:/var/run/php/php<version>-fpm.sock`, override with `--fastcgi=127.0.0.1:9000`).
A few things differ from Apache mod_php:

- **Apache + FPM (`proxy_fcgi`)** — Apache does not forward the `Authorization` header to FastCGI.
  Add `CGIPassAuth On` (Apache 2.4.13+) to the vhost or `.htaccess`. Without it, Laika's `Request`
  still recovers the header from `REDIRECT_HTTP_AUTHORIZATION` (set by the shipped rewrite rule) or
  from `PHP_AUTH_USER`/`PHP_AUTH_PW`.
- **Behind a load balancer or CDN** — set `trusted_proxies` in `lf-config/app.php`. Otherwise scheme,
  host, client IP and the cookie `Secure` flag are all detected from the proxy's hop, not the client's.
- **Pool user** — the FPM pool user (often `www-data`) must be able to write `lf-logs/`, `lf-storage/`
  and `uploads/`. Files that a CLI command creates as a different user (`php laika ...`, the queue
  worker) must stay writable by the pool user too.
- **`disable_functions`** — hardened pools often disable `exec`, `shell_exec`, `proc_open`, `passthru`
  and `system`. Database backups, the cron helper and `System\Command\Runner` depend on them, so run
  those from the CLI, not from a web request.
- **systemd `PrivateTmp`** — php-fpm often gets its own private `/tmp`, which is emptied whenever the
  service restarts. Keep the Shield rate-limit `storageDir` and the file-session `path` under
  `lf-storage/` rather than the system temp directory.

## Pre-Deploy Checklist

- [ ] `composer install --no-dev --prefer-dist --optimize-autoloader`. This also runs `php laika app:sync`, which creates `lf-storage/` and `uploads/`, generates the app key if missing, and compiles the resource manifest.
- [ ] Set `DEBUG` to `false` in `lf-inc/const.php` — read [Running With DEBUG Off](#running-with-debug-off) first.
- [ ] Point `lf-config/database.php` (and `redis.php`, `memcached.php`, `mail.php`, `s3.php` if used) at production credentials — never commit real credentials.
- [ ] Set `trusted_proxies` in `lf-config/app.php` if you're behind a load balancer or CDN.
- [ ] `php laika app:migrate` — your `App\Schema` classes. Then create the [framework tables](#database-tables) it doesn't cover, including the queue tables if you use the `database` queue driver.
- [ ] Configure a session driver in a hook file, and make sure its storage exists (e.g. the file-session `path`).
- [ ] Review CORS origins (and `credentials`) in your hook file — see [CSRF & CORS](../10_security/02_csrf-and-cors.md#cors).
- [ ] Decide on the firewall — `Url::globalPipeline(ShieldPipeline::class)`, see [Security (Shield)](../10_security/01_basic.md).
- [ ] Make `lf-storage/`, `lf-logs/` and `uploads/` writable by the web server user.
- [ ] Confirm `laika` and `worker` exist in the project root — the `post-autoload-dump` script regenerates them, see [Installation](../01_getting-started/01_installation.md#the-laika-and-worker-executables).
- [ ] Start the [queue worker](#queue-worker) under a process supervisor, if you use queues.
- [ ] Run `php laika cache:clear` after deploying, so compiled templates, cached fragments and cached responses match the new code. With the `memcached` cache driver this clears the whole Memcached server — see [Caching](../20_cache/01_basic.md#choosing-a-driver).

## Running With DEBUG Off

`DEBUG = false` changes four things:

| | `DEBUG = true` | `DEBUG = false` |
|---|---|---|
| Error page | Whoops, with the stack trace | A generic "Internal Server Error" page |
| Error log | `lf-logs/{Y}-{Mon}-{d}-error.log` | **Nothing is logged by the framework** |
| Resource discovery | Scans directories on every request | Uses `lf-storage/cache/resources.php` when it exists |
| Twig templates | Recompiled when the source changes | Compiled once; edits need `php laika cache:clear --templates` |

Two consequences:

- **Configure PHP's own error log** (`log_errors = On`, `error_log = /var/log/php/app.log`), or production errors leave no trace. You can also log from a filter or catch-and-log in your own code.
- **The resource manifest is a snapshot.** `composer install` writes it (via `app:sync`), and from then on new controllers, models, pipelines, routes or hook files are invisible until you run `php laika app:cache` (or `app:sync`) again. Make it the last step of every deploy.

## Database Tables

`php laika app:migrate` runs your own schemas. These framework tables are **not** created by it. Most install themselves on first use, which needs `CREATE TABLE` rights once; the queue tables never do:

| Table | Created |
|---|---|
| `options` | The first time `Option` / `option()` is used |
| `activities` | The first time `Activity::insert()` writes |
| `sessions` | `Init::model('default', install: true)` |
| `auth_tokens` | A token guard with `'install' => true` |
| `laika_queue_jobs`, `laika_failed_jobs` | Never automatically — only by the calls below |

If the runtime database user can't run DDL, create them once with a privileged connection — a [custom command](../01_getting-started/04_cli.md#writing-your-own-commands) is a good place:

```php
(new \Laika\Core\Schema\OptionSchema('default'))->up();
(new \Laika\Core\Schema\ActivitySchema('default'))->up();
(new \Laika\Session\Schema\SessionSchema('default'))->up();
(new \Laika\Auth\Schema\AuthSchema('default'))->up();
(new \Laika\Queue\Schema\QueueModelSchema())->up();      // database queue driver; follows queue.connection
(new \Laika\Queue\Schema\FailedJobModelSchema())->up();  // database failed-job store
```

## Timezone

The framework sets PHP's default timezone to **UTC** at boot. Store dates in UTC, and convert for display — or set your own zone in a hook file:

```php
// lf-hooks/timezone.php
\Laika\Service\Date::setAppTimezone('Asia/Dhaka');
```

For MySQL, add `'timezone' => '+00:00'` to the connection so the database session agrees with PHP.

## Memory Limits

`lf-inc/const.php` defines `MEMORY_LIMIT` and `CLI_MEMORY_LIMIT`. laika-core applies them to `memory_limit` at boot, through `Laika\Core\System\MemoryManager::apply()`:

| Process | Limit |
|---|---|
| Web requests | `MEMORY_LIMIT` |
| `php laika ...`, `php worker` | `CLI_MEMORY_LIMIT` |

They only ever **lower** the limit below `php.ini` (or set one when it's unlimited). To give a process more memory, raise `memory_limit` in `php.ini` or the FPM pool as well. The queue worker also exits gracefully at about 90% of its `memory_limit`, for the supervisor to restart.

> laika-core 5.1.1 and earlier applied `CLI_MEMORY_LIMIT` in the queue worker only; everything else used `php.ini`'s limit.

## Queue Worker

The `worker` executable (see [Queue](../12_queue/01_basic.md)) is a long-running process, not a cron job — keep it alive with a process supervisor. One process handles one queue.

**supervisor:**

```ini
; /etc/supervisor/conf.d/laika-worker.conf
[program:laika-worker-default]
command=php /var/www/myapp/worker default
directory=/var/www/myapp
user=www-data
autostart=true
autorestart=true
numprocs=1
stopsignal=TERM
stopwaitsecs=70
```

**systemd:**

```ini
# /etc/systemd/system/laika-worker@.service
[Unit]
Description=Laika queue worker (%i)
After=network.target

[Service]
User=www-data
WorkingDirectory=/var/www/myapp
ExecStart=/usr/bin/php /var/www/myapp/worker %i
Restart=always
KillSignal=SIGTERM
TimeoutStopSec=70

[Install]
WantedBy=multi-user.target
```

```bash
systemctl enable --now laika-worker@default laika-worker@emails
```

- Add a program (or a `@queue` instance) per queue name.
- Keep `numprocs=1` per queue with the `database` or `json` driver — several workers on the same queue can pick up the same job. The `redis` driver is safe to scale.
- The worker exits on memory pressure and on `SIGTERM` after finishing the current job; the supervisor brings it back. Restart workers after each deploy so they load the new code.
- Run the worker as the same user as PHP-FPM, so files it creates stay writable.

## What Not to Deploy

- `.git/`, `docs/` *(optional — harmless but unnecessary; the release zip includes `docs/`)*
- `lf-storage/keys/app.key` should be **generated on the server**, not copied from your dev machine — unless you need the same key across environments (for example, to read data encrypted elsewhere).
- Anything under `lf-storage/cache/` — `app:sync` rebuilds it; don't ship stale compiled templates or manifests.

## CI

The skeleton's GitHub Actions workflows:

- **`.github/workflows/test.yml`** — on every push to `main`/`beta`: `composer validate` and `php -l` on every PHP file, across PHP 8.1–8.5.
- **`.github/workflows/release.yml`** — on a `v*.*.*` tag: zips the skeleton (app, config, docs, templates) with a SHA-256 checksum and publishes a GitHub release.

Adding `php laika resource:list` to CI catches classes that don't load or don't satisfy their contract — it exits non-zero when it finds one.
