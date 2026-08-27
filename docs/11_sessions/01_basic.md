# Sessions

[`laikait/laika-session`](https://github.com/laikait/laika-session) replaces PHP's own session storage with a pluggable handler. Three classes, one job each:

| Class | Role |
|---|---|
| `Laika\Session\SessionConfig` | Picks the driver and holds options/cookie params |
| `Laika\Session\Session` | Reads and writes session data |
| `Laika\Session\SessionManager` | Starts, stops, and destroys the session |

The framework wraps the configuration side in the `Init` service (`Laika\Service\Init`), whose method names mirror `SessionConfig`'s — so `Init::redis()` is `SessionConfig::redis()` with the client already built from `lf-config/redis.php`.

## Configure Once, at Bootstrap

Pick **one** driver before the first `Session::` call. Any file in `lf-hooks/` works: [`lf-boot/app.php`](../../lf-boot/app.php) `require_once`s every hook file during bootstrap, before routing.

```php
// lf-hooks/session.php
use Laika\Service\Init;

Init::file(); // or model(), mysql(), redis(), memcached()
```

Calling a second driver method switches drivers. Miss it entirely and the first `Session::` call throws `SessionHandlerException` — *"No session driver configured."*

## Drivers

| Driver | Storage | Requires | Notes |
|---|---|---|---|
| `file` | Files on disk | — | Default choice. Single-server only. Files are `0600` and locked read→write. |
| `model` | `sessions` table via [laika-model](../05_models/01_basic.md) | `laikait/laika-model` | Uses the connection names in [`lf-config/database.php`](../01_getting-started/03_configuration.md#lf-configdatabasephp). |
| `mysql` | `sessions` table via raw PDO | `ext-pdo` | Same table as `model`, no ORM in the path. Switch between the two freely. |
| `redis` | Redis keys | `ext-redis` | Reads [`lf-config/redis.php`](../01_getting-started/03_configuration.md#lf-configredisphp). Redis expires keys itself. |
| `memcached` | Memcached items | `ext-memcached` | Reads [`lf-config/memcached.php`](../01_getting-started/03_configuration.md#lf-configmemcachedphp). Memcached expires items itself. |

Redis and Memcached inherit their `lifetime` from `gc_maxlifetime`, and their garbage collection is a no-op — the server does it.

### File

```php
use Laika\Service\Init;

Init::file([
    'path'   => APP_PATH . '/lf-storage/sessions', // optional
    'prefix' => 'LK',                              // optional, default 'LK'
]);
```

Without `path`, the driver uses `session_save_path()`, falling back to the system temp directory when that is empty. A `path` you name yourself **must already exist** — the driver validates it and throws `SessionHandlerException` rather than creating it, so create the directory as part of deployment. Files are written as `<prefix>_<session id>`.

Outside the framework, or when you'd rather not go through the container:

```php
use Laika\Session\SessionConfig;

SessionConfig::file(['prefix' => 'LK']);
```

### Model (database via laika-model)

Pass a connection **name** from [`lf-config/database.php`](../01_getting-started/03_configuration.md#lf-configdatabasephp) — `Init::model()` registers that connection for you before selecting the driver:

```php
use Laika\Service\Init;

Init::model('default');
Init::model('default', install: true); // create the table on first use
```

`install` defaults to `false`. Creating the table at request time costs a round trip on every page load and needs DDL privileges the runtime user should not hold — do it once, then turn it back off:

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
use Laika\Session\SessionConfig;

SessionConfig::model(['connection' => 'default', 'install' => false]);
```

### MySQL (raw PDO)

Same table, no laika-model dependency. `Init::mysql()` hands the driver the PDO instance from the named connection:

```php
use Laika\Service\Init;

Init::mysql('default', ['table' => 'sessions']);
```

Direct, with your own connection — the package never handles credentials:

```php
use Laika\Session\SessionConfig;

$pdo = new PDO('mysql:host=127.0.0.1;dbname=myapp;charset=utf8mb4', 'user', 'pass', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

SessionConfig::mysql($pdo, ['table' => 'sessions', 'install' => false]);
```

The table name is validated (`[A-Za-z0-9_]+`) because it is interpolated into the SQL; everything else is bound.

### Redis

```php
use Laika\Service\Init;

Init::redis(['prefix' => 'LK']); // client built from lf-config/redis.php
```

Direct, with a client you connected and authenticated yourself:

```php
use Laika\Session\SessionConfig;
use Laika\Core\Storage\Connection\RedisConnection;

SessionConfig::redis(RedisConnection::make(), ['prefix' => 'LK', 'lifetime' => 1440]);
```

`RedisConnection::make()` throws `ExtensionException` when `ext-redis` is missing or the server is unreachable.

### Memcached

```php
use Laika\Service\Init;

Init::memcached(['prefix' => 'LK']); // client built from lf-config/memcached.php
```

```php
use Laika\Session\SessionConfig;
use Laika\Core\Storage\Connection\MemcachedConnection;

SessionConfig::memcached(MemcachedConnection::make(), ['prefix' => 'LK']);
```

`addServer()` only registers a server, it does not connect — an unreachable Memcached surfaces later as an empty session, not as an exception here.

## Session Options & Cookies

Both merge over the defaults, so a partial call leaves the rest intact. Neither throws when no driver is selected — they are plain setters.

```php
use Laika\Session\SessionConfig;

SessionConfig::options([
    'name'           => 'MY_APP', // cookie name, default 'LFSESS'
    'gc_maxlifetime' => 3600,     // seconds, default 1440
]);

SessionConfig::cookies([
    'domain'   => '.example.com',
    'samesite' => 'Strict',
]);
```

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
| `path` | `/` |
| `secure` | *follows the connection* |
| `httponly` | `true` |
| `samesite` | `Strict` |

> **Don't hardcode `secure => true` in development.** `secure` is `true` over HTTPS and `false` over plain HTTP, decided per request. Forcing it on a plain-HTTP host means the browser never sends the cookie back, so every request silently gets a brand new session — no error, no warning. Set it explicitly only when TLS terminates somewhere the check can't see.

## Using the Session Facade

```php
use Laika\Session\Session;

// Set — one key at a time
Session::set('user_id', 42);
Session::set('token', 'abc123', 'AUTH'); // in the 'AUTH' namespace

// Get — returns null (or your default) if missing
$userId = Session::get('user_id');
$role   = Session::get('role', 'guest');
$token  = Session::get('token', null, 'AUTH');

// Has / Pop / Purge
if (Session::has('user_id')) { /* ... */ }
Session::pop('flash_message');   // remove one key
Session::purge();                // clear the whole 'APP' namespace
Session::purge('AUTH');          // clear one namespace

// Everything in one namespace
$all  = Session::getFor();       // the 'APP' namespace
$auth = Session::getFor('AUTH');

// Lifecycle
Session::regenerate();      // rotate session ID, drop old data
Session::regenerate(false); // rotate ID, keep old data
Session::id();
Session::name();
Session::destroy();         // destroy session + data + cookie (logout)
```

`Session::*` calls `SessionManager::start()` internally on first access — you don't need to call `start()` yourself.

## Namespacing

Every key is stored under a namespace (`$for`, default `'APP'`) to avoid collisions between unrelated parts of the app. It is the **third** argument of `get()`, after the default:

```php
Session::set('id', 42, 'USER');
Session::set('id', 99, 'CART');

Session::get('id', null, 'USER'); // 42
Session::get('id', null, 'CART'); // 99
```

The [session guard](../09_authentication/01_basic.md#session-guard) uses this: it stores the logged-in user as `Session::set("laika_auth_{$guard}", $user, $provider)`, so authentication needs a session driver configured like anything else.

## Manual Control

`SessionManager` runs the lifecycle. You rarely need it directly:

```php
use Laika\Session\SessionManager;

SessionManager::isConfigured(); // has a driver been selected?
SessionManager::isStarted();    // is the session active?
SessionManager::start();        // start explicitly
SessionManager::handler();      // the active driver instance
SessionManager::destroy();      // destroy the session and its cookie
```

## Full Bootstrap Example

```php
// lf-hooks/session.php
use Laika\Service\Init;
use Laika\Session\SessionConfig;

// 1. Driver — once, before first use
Init::model('default');

// 2. Options and cookies (optional)
SessionConfig::options(['name' => 'MY_APP', 'gc_maxlifetime' => 7200]);
SessionConfig::cookies(['domain' => '.example.com']);
```

```php
// Anywhere in the app
use Laika\Session\Session;

Session::set('user_id', 1);

if (Session::has('user_id')) {
    Session::regenerate(); // rotate session ID on privilege change
}

// On logout
Session::destroy();
```

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
| `Session::all()` | `Session::getFor('APP')` |

Behaviour changes worth knowing:

- **The database table is no longer created on every request.** Pass `install: true` once to create it.
- **Cookie `secure` follows the connection** instead of being hardcoded `true`.
- **Garbage collection no longer deletes live sessions.** The old database `gc()` ignored `$maxlifetime`.
- `Session::set()` takes a single key — there is no array form.
