# Installation

## Requirements

| Requirement | Notes |
|---|---|
| PHP `>= 8.1` | 8.1 – 8.5 are tested in CI |
| Composer 2.x | `laika-cli` and `laika-queue` generate their executables from a `post-autoload-dump` script |
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

## The `laika` and `worker` executables

`laikait/laika-cli` and `laikait/laika-queue` generate a `laika` and a `worker`
executable in your project root. Both are thin proxies into the copy installed in
`vendor/`, so they always match the version this project has.

They are written by a `post-autoload-dump` script, so they appear on the first
`composer install` and are rewritten whenever their content changes. Delete one and
it comes back on the next Composer run. A project not created from the skeleton needs
to wire the script itself:

```json
"scripts": {
    "post-autoload-dump": [
        "Laika\\Cli\\ScriptHandler::generate",
        "Laika\\Queue\\ScriptHandler::generate"
    ]
}
```

> **No `allow-plugins` entry is needed.** Both packages were Composer *plugins* before
> laika-cli 3.0 and required trusting in every consuming project. They are ordinary
> libraries now, so you can drop `laikait/laika-cli` and `laikait/laika-queue` from
> `config.allow-plugins` if an older project still lists them.

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
