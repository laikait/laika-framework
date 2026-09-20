# Sessions

The [Session module](https://github.com/laikait/laika-engine/tree/main/docs/session) of `laikait/laika-engine` replaces PHP's own session storage with a pluggable handler. Three classes, one job each:

| Class | Role |
|---|---|
| `Laika\Engine\Session\SessionConfig` | Picks the driver and holds options/cookie params |
| `Laika\Engine\Session\Session` | Reads and writes session data |
| `Laika\Engine\Session\SessionManager` | Starts, stops, and destroys the session |

The framework wraps the configuration side in the `Init` relay (`Laika\Engine\Services\Init`), whose method names mirror `SessionConfig`'s — so `Init::redis()` is `SessionConfig::redis()` with the client already built from `lf-config/redis.php`. There is no `Session` relay: use `Laika\Engine\Session\Session` directly.

This page covers configuration and everyday use. Locking, garbage collection, failure modes and running on several servers are in [Session Drivers & Operations](02_drivers-and-operations.md).

## Configure Once, at Bootstrap

Pick **one** driver before the first `Session::` call. Any file in `lf-hooks/` works: [`lf-boot/app.php`](../../lf-boot/app.php) `require_once`s every hook file during bootstrap, before routing.

```php
// lf-hooks/session.php
use Laika\Engine\Services\Init;

Init::file(); // or model(), mysql(), redis(), memcached()
```

Calling a second driver method switches drivers — until the session starts. The driver is built on the first `Session::` call and kept for the rest of the request, so a later switch has no effect. Miss the call entirely and the first `Session::` call throws `SessionHandlerException` — *"No session driver configured."*

## Drivers

| Driver | Storage | Requires | Notes |
|---|---|---|---|
| `file` | Files on disk | — | The simplest choice. Single-server only. Files are `0600` and locked from read to write. |
| `model` | `sessions` table via [the Model module](../05_models/01_basic.md) | `laikait/laika-engine` | Uses the connection names in [`lf-config/database.php`](../01_getting-started/03_configuration.md#lf-configdatabasephp). The table is always named `sessions`. |
| `mysql` | A table via raw PDO | `ext-pdo` | No ORM in the path. Uses the same table as `model` when `table` is `sessions` (the default), so you can switch between the two. |
| `redis` | Redis keys | `ext-redis` | Reads [`lf-config/redis.php`](../01_getting-started/03_configuration.md#lf-configredisphp). Redis expires keys itself. |
| `memcached` | Memcached items | `ext-memcached` | Reads [`lf-config/memcached.php`](../01_getting-started/03_configuration.md#lf-configmemcachedphp). Memcached expires items itself. |

There is no default driver — you must pick one. Redis and Memcached expire a session after `gc_maxlifetime` seconds unless you pass a `lifetime` param, and their garbage collection is a no-op — the server does it.

### File

```php
use Laika\Engine\Services\Init;

Init::file([
    'path'   => APP_PATH . '/lf-storage/sessions', // optional
    'prefix' => 'LK',                              // optional, default 'LK'
]);
```

Without `path`, the driver uses `session_save_path()`, falling back to the system temp directory when that is empty. A `path` you name yourself **must already exist** — the driver validates it and throws `SessionHandlerException` rather than creating it, so create the directory as part of deployment. Files are written as `<PREFIX>_<session id>` — the prefix is uppercased (`'myapp'` becomes `MYAPP_…`), and the same goes for Redis and Memcached keys.

Outside the framework, or when you'd rather not go through the container:

```php
use Laika\Engine\Session\SessionConfig;

SessionConfig::file(['prefix' => 'LK']);
```

### Model (database via the Model module)

Pass a connection **name** from [`lf-config/database.php`](../01_getting-started/03_configuration.md#lf-configdatabasephp) — `Init::model()` registers that connection, under its own name, before selecting the driver. See [Models → Multiple Connections](../05_models/01_basic.md#multiple-connections).

```php
use Laika\Engine\Services\Init;

Init::model('default');
Init::model('default', install: true); // create the table on first use
```

`install` defaults to `false`. With it on, every new PHP process checks for the table on its first session, and the runtime user needs DDL privileges it should not hold — do it once, then turn it back off. `install: true` builds the table with `Laika\Engine\Session\Schema\SessionSchema`; to create it by hand instead, this MySQL is compatible with both the `model` and `mysql` drivers (`SessionSchema` itself makes `data` `NOT NULL` and names the index its own way):

```sql
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(128) NOT NULL,
    `data`          BLOB NULL,
    `last_activity` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `idx_last_activity` (`last_activity`)
);
```

The direct form takes an array instead of positional arguments:

```php
use Laika\Engine\Session\SessionConfig;

SessionConfig::model(['connection' => 'default', 'install' => false]);
```

`SessionConfig::model()` throws `SessionHandlerException` straight away if the Model module is not installed.

### MySQL (raw PDO)

Same table, without going through the Model module. `Init::mysql()` hands the driver the PDO instance from the named connection:

```php
use Laika\Engine\Services\Init;

Init::mysql('default', ['table' => 'sessions']);
```

Direct, with your own connection — the package never handles credentials:

```php
use Laika\Engine\Session\SessionConfig;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

SessionConfig::mysql($pdo, ['table' => 'sessions', 'install' => false]);
```

The table name is validated (`[A-Za-z0-9_]+`) because it is interpolated into the SQL; everything else is bound. Database errors are **not** caught: with `ERRMODE_EXCEPTION`, a database outage throws in the middle of the request — see [Failure Modes](02_drivers-and-operations.md#failure-modes).

### Redis

```php
use Laika\Engine\Services\Init;

Init::redis(['prefix' => 'LK']); // client built from lf-config/redis.php
```

Direct, with a client you connected and authenticated yourself:

```php
use Laika\Engine\Session\SessionConfig;
use Laika\Engine\Storage\Connection\RedisConnection;

SessionConfig::redis(RedisConnection::make(), ['prefix' => 'LK', 'lifetime' => 1440]);
```

`lifetime` is the key's time-to-live in seconds; without it, the driver uses `gc_maxlifetime`. `RedisConnection::make()` throws `ExtensionException` when `ext-redis` is missing or the server is unreachable. Once the session has started, Redis errors are swallowed: the user gets an empty session rather than an error page.

### Memcached

```php
use Laika\Engine\Services\Init;

Init::memcached(['prefix' => 'LK']); // client built from lf-config/memcached.php
```

```php
use Laika\Engine\Session\SessionConfig;
use Laika\Engine\Storage\Connection\MemcachedConnection;

SessionConfig::memcached(MemcachedConnection::make(), ['prefix' => 'LK']);
```

`addServer()` only registers a server, it does not connect — an unreachable Memcached surfaces later as an empty session, not as an exception here.

## Session Options & Cookies

Both merge over the defaults, so a partial call leaves the rest intact. Neither throws when no driver is selected — they are plain setters.

```php
use Laika\Engine\Session\SessionConfig;

SessionConfig::options([
    'name'           => 'MY_APP', // cookie name, default 'LFSESS'
    'gc_maxlifetime' => 3600,     // seconds, default 1440
]);

SessionConfig::cookies([
    'domain'   => '.example.com',
    'samesite' => 'Strict',
]);
```

`options()` accepts any [`session_start()` option](https://www.php.net/manual/en/session.configuration.php) without its `session.` prefix — `cookie_lifetime`, `lazy_write`, `sid_length` and so on — not only the six below.

| Option | Default |
|---|---|
| `name` | `LFSESS` |
| `use_only_cookies` | `true` |
| `use_strict_mode` | `true` |
| `gc_probability` | `1` |
| `gc_divisor` | `100` |
| `gc_maxlifetime` | `1440` |

| Cookie param | Default |
|---|---|
| `lifetime` | *not set* — the cookie ends when the browser closes |
| `path` | `/` |
| `domain` | *not set* — the current host only |
| `secure` | *follows the connection* |
| `httponly` | `true` |
| `samesite` | `Strict` |

To keep users signed in across browser restarts, set a cookie `lifetime` **and** a `gc_maxlifetime` at least as long — the cookie is useless once the stored session has been collected:

```php
SessionConfig::options(['gc_maxlifetime' => 604800]);   // 7 days
SessionConfig::cookies(['lifetime' => 604800]);
```

> **Don't hardcode `secure => true` in development.** `secure` is `true` over HTTPS and `false` over plain HTTP, decided per request. Forcing it on a plain-HTTP host means the browser never sends the cookie back, so every request silently gets a brand new session — no error, no warning. Set it explicitly only when TLS terminates somewhere the check can't see.
>
> The `Init::*` helpers already cover that case: when `Url::isHttps()` is true — it honours `trusted_proxies` in [`lf-config/app.php`](../01_getting-started/03_configuration.md#lf-configappphp) — they turn `secure` on. They never turn it off, and a later `SessionConfig::cookies()` call overrides them. Only direct `SessionConfig::*` calls behind a TLS-terminating proxy need `secure` set by hand.

## Using the Session Class

```php
use Laika\Engine\Session\Session;

// Set — one key at a time, in the 'APP' scope
Session::set('user_id', 42);

// Get — returns null (or your default) if missing
$userId = Session::get('user_id');
$role   = Session::get('role', 'guest');

// Has / Pop / Purge
if (Session::has('user_id')) { /* ... */ }
Session::pop('flash_message');   // remove one key
Session::purge();                // clear the whole 'APP' scope

// Everything in the 'APP' scope
$all = Session::all();

// Any other scope: the same methods on Session::scope()
Session::scope('AUTH')->set('token', 'abc123');
$token = Session::scope('AUTH')->get('token');
Session::scope('AUTH')->purge();
$auth  = Session::scope('AUTH')->all();

// Lifecycle
Session::regenerate();      // new ID; the record under the old ID is deleted
Session::regenerate(false); // new ID; the old record is left in storage
Session::id();
Session::name();
Session::destroy();         // destroy session + data + cookie (logout)
```

`Session::*` calls `SessionManager::start()` internally on first access — you don't need to call `start()` yourself. That includes `id()` and `name()`: asking for the id starts a session.

`regenerate()` never touches the data you are using — it moves it to a new ID. Call it after login and any change of privilege, so that an ID an attacker planted before login is worthless afterwards.

> **Starting a session has a cost beyond storage.** A response that started a session is never stored by the [response cache](../20_cache/01_basic.md#responses), and the file driver makes the user's other requests wait — see [Locking and Concurrency](02_drivers-and-operations.md#locking-and-concurrency). Don't touch the session on pages that don't need it.

**Flash messages** also live in the session: `alert_set()`, `alert_get()` and `Redirect::with()` store them under the `APP` key `alert`, so don't use that key yourself. See [Responses → Flash Messages](../02_routing/04_responses.md#flash-messages).

## Scopes

Every key is stored under a scope (default `'APP'`) to avoid collisions between unrelated parts of the app. The static `Session::` methods use `APP`; `Session::scope()` returns a `Scope` with the same methods for any other:

```php
Session::scope('USER')->set('id', 42);
Session::scope('CART')->set('id', 99);

Session::scope('USER')->get('id'); // 42
Session::scope('CART')->get('id'); // 99
```

Scope names are trimmed and uppercased, so `'auth'` and `'AUTH'` are the same scope. An empty name throws `InvalidArgumentException`.

The [session guard](../09_authentication/01_basic.md#session-guard) uses this: it stores the logged-in user as `Session::scope($provider)->set("laika_auth_{$guard}", $user)` (the `APP` scope when there is no provider), so authentication needs a session driver configured like anything else.

## Manual Control

`SessionManager` runs the lifecycle. You rarely need it directly:

```php
use Laika\Engine\Session\SessionManager;

SessionManager::isConfigured(); // has a driver been selected?
SessionManager::isStarted();    // is the session active?
SessionManager::start();        // start explicitly
SessionManager::handler();      // the active driver instance
SessionManager::destroy();      // destroy the session and its cookie
```

## Full Bootstrap Example

```php
// lf-hooks/session.php
use Laika\Engine\Services\Init;
use Laika\Engine\Session\SessionConfig;

// 1. Driver — once, before first use
Init::model('default');

// 2. Options and cookies (optional)
SessionConfig::options(['name' => 'MY_APP', 'gc_maxlifetime' => 7200]);
SessionConfig::cookies(['domain' => '.example.com']);
```

```php
// Anywhere in the app
use Laika\Engine\Session\Session;

Session::set('user_id', 1);

if (Session::has('user_id')) {
    Session::regenerate(); // rotate session ID on privilege change
}

// On logout
Session::destroy();
```

## API Reference

### `Laika\Engine\Session\Session` (static — the `APP` scope)

| Method | |
|---|---|
| `set(string $key, mixed $value): void` | One key at a time; there is no array form |
| `get(string $key, mixed $default = null): mixed` | |
| `has(string $key): bool` | `isset` semantics — **false for a key holding `null`** |
| `pop(string $key): void` | Remove one key |
| `purge(): void` | Clear the whole `APP` scope |
| `all(): array` | Everything in `APP` |
| `scope(string $name = 'APP'): Scope` | Any other scope; names are trimmed and uppercased |
| `regenerate(bool $deleteOldData = true): bool` | New session id; `true` deletes the record stored under the old one |
| `destroy(): bool` | Destroy data and expire the cookie |
| `id(): string` / `name(): string` | **Start the session** if it isn't active; `''` only if it fails to start |

`Laika\Engine\Session\Scope` has the same `set`/`get`/`has`/`pop`/`purge`/`all` methods, plus `name(): string`. `new Scope('cart')` is the same as `Session::scope('cart')`.

### `Laika\Engine\Session\SessionConfig` (static)

| Method | |
|---|---|
| `file(array $params = []): void` | File driver |
| `redis(Redis $client, array $params = []): void` | Redis driver |
| `memcached(Memcached $client, array $params = []): void` | Memcached driver |
| `mysql(PDO $pdo, array $params = []): void` | Raw PDO driver |
| `model(array $params = []): void` | Model driver |
| `options(array $options = []): array` | Merge options; returns the full set, so `options()` alone is a getter |
| `cookies(array $cookies = []): array` | Merge cookie params; returns the full set |
| `driver(): ?string` / `params(): array` / `isConfigured(): bool` | Inspect the selection |
| `reset(): void` | Forget the configuration (tests) |

Driver names are also available as constants: `SessionConfig::DRIVER_FILE`, `DRIVER_REDIS`, `DRIVER_MEMCACHED`, `DRIVER_MYSQL`, `DRIVER_MODEL`.

### `Laika\Engine\Services\Init` (relay — the framework's shortcuts)

| Method | |
|---|---|
| `file(array $params = []): void` | `SessionConfig::file()` |
| `model(?string $name = null, bool $install = false): void` | Registers the connection, then `SessionConfig::model()` |
| `mysql(?string $name = null, array $params = []): void` | Hands the named connection's PDO to `SessionConfig::mysql()` |
| `redis(array $params = []): void` | Client from `lf-config/redis.php` |
| `memcached(array $params = []): void` | Client from `lf-config/memcached.php` |
| `db(?string $name = null): void` | Register a database connection only |

Each session method also turns the cookie's `secure` flag on when `Url::isHttps()` is true.

### `Laika\Engine\Session\SessionManager` (static)

`start(): void`, `isConfigured(): bool`, `isStarted(): bool`, `handler(): SessionDriverInterface`, `destroy(): bool`, `reset(): void`.

`handler()` throws `SessionHandlerException` when no driver is configured. `isStarted()` stays `false` if `session_start()` failed. `reset()` forgets the started state and the built driver — for tests.

## Upgrading To v5.1

`laika-session` v5.1 drops the trailing `$for` parameter. Scopes other than `APP` go through `Session::scope()`:

| v5.0 | v5.1 |
|---|---|
| `Session::set($key, $value, 'X')` | `Session::scope('X')->set($key, $value)` |
| `Session::get($key, $default, 'X')` | `Session::scope('X')->get($key, $default)` |
| `Session::has($key, 'X')` | `Session::scope('X')->has($key)` |
| `Session::pop($key, 'X')` | `Session::scope('X')->pop($key)` |
| `Session::purge('X')` | `Session::scope('X')->purge()` |
| `Session::getFor('X')` | `Session::scope('X')->all()` |
| `Session::getFor()` | `Session::all()` |

Calls that never passed `$for` are unchanged, and stored data stays readable, so nobody is logged out by the upgrade. PHP ignores extra arguments to user functions, so a leftover v5 call doesn't error: it silently reads from and writes to `APP`. Search your code for them.

## Upgrading From v4

`laika-session` v5 moved every configuration call out of `SessionManager` and into `SessionConfig`, and `Init`'s session helpers were renamed to match.

| v4 | v5 |
|---|---|
| `SessionManager::fileSessionConfig([...])` | `SessionConfig::file([...])` |
| `SessionManager::dbSessionConfig('default')` | `SessionConfig::model(['connection' => 'default'])` |
| `SessionManager::setOptions([...])` | `SessionConfig::options([...])` |
| `SessionManager::setCookies([...])` | `SessionConfig::cookies([...])` |
| `SessionManager::isConfiguarded()` | `SessionManager::isConfigured()` |
| `Init::fileSession([...])` | `Init::file([...])` |
| `Init::dbSession('default')` | `Init::model('default')` |
| `Session::all()` | No direct equivalent — v4 returned every scope (all of `$_SESSION`); v5.1's `Session::all()` returns only `APP`. Read other scopes with `Session::scope('X')->all()`. |

Behaviour changes worth knowing:

- **The database table is no longer created on every request.** Pass `install: true` once to create it.
- **Cookie `secure` follows the connection** instead of being hardcoded `true`.
- **Garbage collection no longer deletes live sessions.** The old database `gc()` ignored `$maxlifetime`.
- `Session::set()` takes a single key — there is no array form.

## See Also

- [Session Drivers & Operations](02_drivers-and-operations.md) — locking, garbage collection, failure modes, several servers
- [Authentication → Session Guard](../09_authentication/01_basic.md#session-guard)
- [Responses → Flash Messages](../02_routing/04_responses.md#flash-messages)
