# Installation

## Requirements

| Requirement | Notes |
|---|---|
| PHP `>= 8.1` | 8.1 – 8.5 are tested in CI |
| Composer 2.2+ | Required for plugin auto-loading (`laika-cli`, `laika-queue`) |
| `ext-pdo` | Plus the PDO driver for your database (`pdo_mysql`, `pdo_pgsql`, `pdo_sqlite`, ...) |
| `ext-json`, `ext-mbstring` | Required by the framework core |
| `ext-pcntl`, `ext-posix` *(optional)* | Enables graceful shutdown/timeouts in the queue worker — Linux/macOS only |
| `ext-redis` *(optional)* | Only if you use the Redis session or queue driver |

## Install via Composer (recommended)

```bash
composer create-project laikait/laika-framework myproject
cd myproject
```

This pulls in the framework skeleton along with its core packages (`laika-core`, `laika-route`, `laika-model`, `laika-relay`, `laika-cli`, ...) and runs the post-install scripts that generate your app's secret key and sync local files.

## Install via Git

```bash
git clone https://github.com/laikait/laika-framework.git myproject
cd myproject
composer install
```

## Composer plugin trust prompt

`laika-cli` and `laika-queue` ship as Composer plugins (they generate the `laika` and `worker` executables on install). Composer 2.2+ requires plugins to be explicitly trusted. This is already configured in the framework's `composer.json`:

```json
"config": {
    "allow-plugins": {
        "laikait/laika-cli": true,
        "laikait/laika-queue": true
    }
}
```

If you see a trust prompt anyway (e.g. after adding another plugin package), see [CLI Reference](04_cli.md) or that package's README for the exact key to add.

## Run the development server

```bash
php laika app:start
```

Starts PHP's built-in server on `127.0.0.1:8000` (auto-selects the next free port if 8000 is busy). Options:

```bash
php laika app:start --host=0.0.0.0 --port=8080
```

## Verify

Visit `http://127.0.0.1:8000` — you should see the default Laika landing route (served by `App\Controller\HomeController`, see [`lf-routes/web.php`](../../lf-routes/web.php)).

## Next Steps

- [Project Structure](02_project-structure.md) — what every directory does
- [Configuration](03_configuration.md) — database, mail, redis, auth config
- [CLI Reference](04_cli.md) — scaffold controllers, models, and more
