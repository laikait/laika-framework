# Installation

## Requirements

| Requirement | Notes |
|---|---|
| PHP `>= 8.1` | 8.1 – 8.5 are tested in CI |
| Composer 2.x | `laikait/laika-engine` generates the `laika` and `worker` executables from a `post-autoload-dump` script |
| `ext-openssl` | Required. Encryption, the app key, CSRF tokens, JWTs, TLS for mail |
| `ext-mbstring` | Used by the validator (string lengths) and the input sanitizer |
| `ext-pdo` + a driver | `pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, `pdo_sqlsrv`, ... for your database |

Optional extensions, needed only by the feature that uses them:

| Extension | Needed by |
|---|---|
| `bcmath` | `Laika\Engine\Services\Math` |
| `gd` | `Laika\Engine\Services\Image`, and `processimage` on uploads |
| `zip` | `Laika\Engine\Helper\Zip` |
| `fileinfo` | MIME detection on uploads, `File::mime()`, `MimeType::fromContent()` |
| `redis` | Redis session driver, Redis queue driver, `RedisStorage` |
| `memcached` | Memcached session driver, `MemcachedStorage` |
| `pcntl`, `posix` | Graceful shutdown and per-job timeouts in the queue worker (Linux/macOS only) |

## Install via Composer (recommended)

```bash
composer create-project laikait/laika-framework myproject
cd myproject
```

This pulls in the skeleton and `laikait/laika-engine`, the single package that holds the whole framework: core, routing, models, the relay container, sessions, auth, the Shield firewall, queue, cache, mail and the CLI.

Composer then runs the project's scripts:

- **`post-create-project-cmd`** writes the `laika` and `worker` executables.
- **`post-autoload-dump`** rewrites those executables if they changed, then runs `php laika app:sync`, which creates `lf-storage/` and `uploads/`, writes `.htaccess` if it's missing, generates the app secret key (`lf-storage/keys/app.key`) if there isn't a valid one, and compiles the resource manifest.

## Install via Git

```bash
git clone https://github.com/laikait/laika-framework.git myproject
cd myproject
composer install
```

## The `laika` and `worker` Executables

`laikait/laika-engine` generates a `laika` and a `worker` executable in your project root. Both are thin proxies into the copy installed in `vendor/`, so they always match the version this project has.

They are written by a `post-autoload-dump` script, so they appear on the first `composer install` and are rewritten whenever their content changes. Delete one and it comes back on the next Composer run. Both are git-ignored. A project not created from the skeleton needs to wire the scripts itself:

```json
"scripts": {
    "post-autoload-dump": [
        "@php laika app:sync"
    ]
}
```

One entry does everything: `app:sync` writes both executables before the rest of its work.

It works on a first install, before the root `laika` file exists — Composer resolves `@php <name>` against the filesystem and, failing that, looks it up on `PATH`, to which it has already added `vendor/bin`. `laikait/laika-engine` ships `laika` and `worker` as Composer binaries, so the first run goes through `vendor/bin/laika` and every later run uses the root file directly.

While the CLI and the queue shipped as separate packages, each had its own Composer script handler. `Laika\Engine\Cli\ScriptHandler::generate` and `Laika\Engine\Queue\ScriptHandler::generate` both still exist and call the same generator, so an older `composer.json` keeps working with no edit.

> **No `allow-plugins` entry is needed.** `laika-engine` is an ordinary library, not a Composer plugin, so an older project can drop `laikait/laika-cli` and `laikait/laika-queue` from `config.allow-plugins`.

## Run the Development Server

```bash
php laika app:start
php laika app:start --host=0.0.0.0 --port=8080
```

Starts PHP's built-in server on `127.0.0.1:8000`, moving to the next free port if 8000 is busy.

Every request goes through `public/index.php`, as behind Apache or nginx, so static files follow [`lf-config/assets.php`](03_configuration.md#lf-configassetsphp) and project files such as `lf-storage/keys/app.key` aren't served.

> **Development only.** PHP's built-in server handles one request at a time and isn't hardened — never expose it to a network you don't trust. (laika-cli 3.0.9 and earlier started it without a router script, serving every file in the project directory as-is.) In production use Apache or nginx, see [Deployment](../13_deployment/01_basic.md).

## Verify

Visit `http://127.0.0.1:8000` — you should see the default landing page, served by `App\Controller\HomeController` through the `/` route in [`lf-routes/web.php`](../../lf-routes/web.php).

If you see an error page instead, check:

- `lf-storage/keys/app.key` exists — run `php laika secret:fix` if not.
- The web server user can write `lf-storage/` and `lf-logs/`.
- `DEBUG` is `true` in `lf-inc/const.php` while you develop, so errors show the full Whoops page and are logged to `lf-logs/`.

## Next Steps

- [Project Structure](02_project-structure.md) — what every directory does
- [Configuration](03_configuration.md) — database, mail, redis, auth config
- [Request Lifecycle](05_request-lifecycle.md) — how a request travels through the framework
- [CLI Reference](04_cli.md) — scaffold controllers, models and more
