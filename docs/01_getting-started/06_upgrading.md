# Upgrading

What changed across the `laikait/*` packages in the laika-core 5.1 release line, and what to do about it. Upgrade notes for individual features also live on their own pages — they're linked below.

## Upgrading to laika-core 5.1

The skeleton's root `composer.json` must require core 5.1:

```json
"require": {
    "php": ">=8.1",
    "laikait/laika-core": "5.1.*"
}
```

Then:

```bash
composer update
php laika app:cache        # rebuild the resource manifest (app:sync, run by Composer, does too)
```

Core 5.1 pulls in these package versions:

| Package | Version | Notable change |
|---|---|---|
| `laikait/laika-session` | 5.1 | `Session::scope()` replaces the trailing `$for` argument |
| `laikait/laika-auth` | 2.1 | Guards take their config array in the constructor; token guards gained `connection` and `install` |
| `laikait/laika-queue` | 1.1 | `Worker::beforeJob()`; `install()` on the driver interface; `app:migrate` no longer creates the queue tables |
| `laikait/laika-route` | 2.x | Constructor/method injection for controllers, pipelines and filters; static files governed by `lf-config/assets.php`; UTF-8 routes |
| `laikait/laika-cli` | 3.x | No longer a Composer plugin |
| `laikait/laika-mailman` | 1.x | **New** — mail sending and reading |

## Checklist

1. **Session scopes.** Search for `Session::set($key, $value, 'SCOPE')`, `Session::get($key, $default, 'SCOPE')`, `Session::purge('SCOPE')` and `Session::getFor(...)`. PHP ignores the extra argument, so these now **silently read and write the `APP` scope**. Rewrite them as `Session::scope('SCOPE')->set(...)`. See [Sessions → Upgrading To v5.1](../11_sessions/01_basic.md#upgrading-to-v51).
2. **Tables that `app:migrate` no longer creates.** `options` and `activities` install themselves on first use, and `auth_tokens` only with the token guard's `'install' => true`. The database user needs `CREATE` rights once — or create them ahead of time, see [Deployment → Database Tables](../13_deployment/01_basic.md#database-tables).
3. **Redirects.** `Redirect::back()` and `Redirect::to()` now return `void` (they always exited), so remove any chaining after them. `back()` only follows referers on the same host; anything else goes to `/`. 303 is now an allowed status.
4. **Templates.** `new Template('admin')` ignores its argument — move the directory onto the view name: `view('admin/dashboard')`. `extension()` is deprecated and throws under the error handler; use `html()`/`twig()`. See [Templates](../06_templates/01_basic.md#sub-directories).
5. **`@`-suppressed warnings.** The error handler now honours `@` and `error_reporting()`, so deliberately suppressed calls (`@mkdir`, `@fopen`) no longer throw.
6. **Composer plugins.** laika-cli and laika-queue are ordinary libraries since laika-cli 3.0 — drop them from `config.allow-plugins`, and make sure `post-autoload-dump` runs `Laika\Cli\ScriptHandler::generate`, `Laika\Queue\ScriptHandler::generate` and `@php laika app:sync` (see [Installation](01_installation.md#the-laika-and-worker-executables)).
7. **Resource commands.** The manifest is built with `php laika app:cache` and removed with `php laika app:clear`. (Older docs mentioned `resource:cache`/`resource:clear`, which don't exist.)

## Fixes After laika-core 5.1.1

Bug fixes in the package releases that follow core 5.1.1, model 4.0.6, route 2.0.3, shield 2.0.3, cli 3.0.9, queue 1.0.7 and auth 2.1.0.

| Package | Fix | What to do |
|---|---|---|
| laika-core | `min`, `max`, `between` and `size` compare a numeric string by value only. Before, they also checked its length, so `between:18,120` rejected `"25"`. | Remove `callback` workarounds for numeric ranges. Rules like `size:5` on all-digit strings (ZIP codes, PINs) now compare the value — use `regex` for their length. |
| laika-core, laika-model, laika-queue | A connection other than `default` is registered under its own name — by models, `Init::db()`/`Init::model()`, `Queue::driver()` and the worker. Before, it overwrote `default`. | Nothing. A hook-file `Connection::add(..., 'analytics')` workaround is harmless and can go. |
| laika-core | JSON error responses no longer end in a fatal error, and carry a JSON `Content-Type`. | None |
| laika-core | `Upload::multiple()` no longer stores a file that failed validation. | None |
| laika-route | A visible (non-hidden) `_csrf` input answers with the intended 415 JSON instead of a fatal error. | None |
| laika-shield | The `Laika\Shield\Service\ShieldConfig` relay resolves to the shared configuration. | None |
| laika-cli | The `model:make` schema stub's `deleted_at` defaults to `NULL`, and its `seed()` only seeds an empty table. | Fix schemas generated earlier: add `->default(null)` to `deleted_at` (and clear it in existing rows), and guard `seed()`. |
| laika-core | `MEMORY_LIMIT` and `CLI_MEMORY_LIMIT` are applied at boot, to every request and command. Before, only the queue worker applied `CLI_MEMORY_LIMIT`. | Make sure `MEMORY_LIMIT` fits your heaviest page — it now caps web requests below `php.ini`. A hook-file `MemoryManager::apply()` can go. |
| laika-core | `Runner` async runs work on Windows (they threw a `TypeError`), honour `cwd()` and `env()` everywhere, and `AsyncJob::stop()` no longer needs `ext-pcntl`. | None |
| laika-model | A schema's `$connection` property is honoured, including by `app:migrate`. | Schemas that declare a connection other than `default` now migrate there — check before running `app:migrate`. Constructor overrides that only set the connection can go. |
| laika-queue | 1.1.0: the schemas default to `queue.connection`. 1.1.1: the package no longer declares them to the resource loader, so **`app:migrate` no longer creates the queue tables**. | New installs using the `database` driver or failed-job store: create them with `QueueModelSchema`/`FailedJobModelSchema` `->up()` — see [Queue → Choosing a Driver](../12_queue/01_basic.md#choosing-a-driver). Existing tables are unaffected. |
| laika-queue | 1.1.0: `QueueDriverInterface` gained `install(): void`, and `Worker::beforeJob()` was added. | Custom drivers must add `install()` (an empty body is fine). See [Queue → Custom Drivers](../12_queue/02_drivers-and-operations.md#custom-drivers). |
| laika-queue | `DatabaseFailedJobProvider` given an invalid connection name throws `DriverException`. Before, it failed with a class-not-found error. | None |
| laika-auth | `issueToken()` also returns a `refresh_token` (stored hashed), and the new `refreshToken()` exchanges it for a new pair, revoking the old token. | Tokens issued earlier can't be refreshed. |
| laika-auth | The README and package description no longer advertise OAuth, and `ext-curl` is no longer required. | None |
| laika-cli | `app:start` routes every request through `index.php`, so it no longer serves project files such as `lf-storage/keys/app.key`. `controller:make` validates `--method`; `filter:rename` and `pipeline:rename` validate the new name; `filter:make`, `pipeline:remove`, `model:rename` and `template:make --ext` validate their input. | None |
| Skeleton | `lf-config/mail.php` uses the Mailer's key names. | In existing projects, rename `secure` → `encryption` and `from_email` → `from`. |

## laika-core 5.1.1

Bug fixes only; no code changes needed.

- `new OptionModel('name')` now honours the connection, with separate caches per connection, and the `options` table installs and seeds itself.
- A stored `''` or `'0'` option no longer counts as missing.
- `activities` is created on the connection passed to `Activity::insert()`, not always `default`.
- `Redirect::back()` works on non-standard ports (`localhost:8000`) and collapses `//evil.com`-style paths.

## laika-core 5.1.0 — Other Behaviour Changes

- `Request::header('Authorization')` falls back to `REDIRECT_HTTP_AUTHORIZATION` and `PHP_AUTH_*`, so bearer tokens survive Apache + PHP-FPM.
- The `Init` session helpers set the cookie's `Secure` flag behind trusted proxies.
- `OptionModel` and `Activity` accept a connection name in their constructors.

## laika-session 5.0 (from 4.x)

Configuration moved from `SessionManager` to `SessionConfig`, and the `Init` helpers were renamed (`Init::fileSession()` → `Init::file()`, `Init::dbSession()` → `Init::model()`). See [Sessions → Upgrading From v4](../11_sessions/01_basic.md#upgrading-from-v4).

## See Also

- [laika-core upgrade notes](https://github.com/laikait/laika-core/blob/main/docs/13_upgrading.md)
- [Configuration](03_configuration.md)
