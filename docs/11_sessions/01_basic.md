# Sessions

[`laikait/laika-session`](https://github.com/laikait/laika-session) provides a static `Session` facade backed by a pluggable storage handler.

> **Version note:** the driver set documented here reflects the `laika-session` version pinned in this project (`v3.0.3`) — **file and PDO/database handlers only**. Check the [laika-session README](https://github.com/laikait/laika-session) / changelog before assuming Redis or Memcached support; if this project's lockfile hasn't been updated to a version that ships those handlers, configuring them will fail with a class-not-found error.

## Configure Once, at Bootstrap

Call one of these **before** any `Session::*` call — e.g. from a hook in `lf-hooks/`, or early in a custom bootstrap step. `SessionManager` is idempotent; calling it more than once is a no-op.

### File driver (default, no dependencies)

```php
use Laika\Session\SessionManager;

SessionManager::fileSessionConfig([
    'prefix' => 'LK', // optional, default 'LK'
]);
```

### Database (PDO) driver

Stores sessions in a `sessions` table, auto-created on first use. Pass a connection **name** from [`lf-config/database.php`](../01_getting-started/03_configuration.md#lf-configdatabasephp) — not a PDO instance:

```php
use Laika\Session\SessionManager;

SessionManager::dbSessionConfig('default');
```

## Session Options & Cookies

```php
use Laika\Session\SessionManager;

SessionManager::setOptions([
    'name'           => 'MY_APP', // cookie name, default 'LAIKA'
    'gc_maxlifetime' => 3600,     // seconds, default 1440
]);

SessionManager::setCookies([
    'domain'   => '.example.com',
    'secure'   => true,     // default true
    'httponly' => true,     // default true
    'samesite' => 'Strict', // default 'Strict'
]);
```

Both throw `SessionHandlerException` if called before `fileSessionConfig()`/`dbSessionConfig()`.

## Using the Session Facade

```php
use Laika\Session\Session;

// Set — single value or multiple at once
Session::set('user_id', 42);
Session::set(['user_id' => 42, 'role' => 'admin']);

// Get — returns null (or your default) if missing
$userId = Session::get('user_id');
$role   = Session::get('role', 'guest');

// Has / Pop / Purge
if (Session::has('user_id')) { /* ... */ }
Session::pop('flash_message');   // remove one key
Session::purge();                // clear the whole 'APP' namespace

// Everything
$all = Session::all(); // raw $_SESSION

// Lifecycle
Session::regenerate();      // rotate session ID, drop old data
Session::regenerate(false); // rotate ID, keep old data
Session::id();
Session::name();
Session::destroy();         // destroy session + all data (logout)
```

`Session::*` calls `SessionManager::start()` internally on first access — you don't need to call `start()` yourself.

## Namespacing

Every key is stored under a namespace (`$for`, default `'APP'`) to avoid collisions between unrelated parts of the app:

```php
Session::set('id', 42, 'USER');
Session::set('id', 99, 'CART');

Session::get('id', for: 'USER'); // 42
Session::get('id', for: 'CART'); // 99
```

## Full Bootstrap Example

```php
use Laika\Session\SessionManager;
use Laika\Session\Session;

// 1. Configure driver — once, before first use
SessionManager::dbSessionConfig('default');
SessionManager::setCookies(['domain' => '.example.com']);

// 2. Use anywhere
Session::set('user_id', 1);

if (Session::has('user_id')) {
    Session::regenerate(); // rotate session ID on privilege change
}

// On logout
Session::destroy();
```
