# Configuration

Configuration lives entirely in `lf-config/*.php` — there's no `.env` file. Every file returns a plain PHP array and is read through the framework's `config()` helper (e.g. `config('database')`, `config('auth')`).

Each file starts with a direct-access guard and should keep it:

```php
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');
```

## `lf-config/app.php`

App-level metadata.

```php
return [
    'name'          => 'Laika Framework',
    'url'           => 'https://laikaframework.com',
    'documentation' => 'https://docs.laikaframework.com',
];
```

## `lf-config/database.php`

Each **top-level key is a connection name**. `Laika\Model\Model` and `Laika\Model\Schema\Schema` resolve connections by this name (`'default'` is used when a model doesn't override it). You can register as many named connections as you need.

```php
return [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'test',
        'username' => 'root',
        'password' => '',
    ],

    // Optional — a second connection, e.g. a read replica or another database
    'read' => [
        'driver'   => 'mysql',
        'host'     => '127.0.0.1',
        'database' => 'test',
        'username' => 'root',
        'password' => '',
    ],
];
```

See [Models & Database](../05_models/01_basic.md) for the full query builder and schema builder reference, and the [laika-model README](https://github.com/laikait/laika-model) for every supported driver (PostgreSQL, SQLite, SQL Server, Oracle, Firebird).

## `lf-config/mail.php`

SMTP/sendmail settings, keyed by `driver` (`sendmail`, `smtp`, `mail`, `qmail`). Only `driver` is required; everything else is commented out with sane defaults until you need SMTP:

```php
return [
    'driver' => 'smtp',
    'host'     => 'smtp.example.com',
    'username' => 'user@example.com',
    'password' => 'secret',
    'port'     => 587,
    'secure'   => 'ssl',
];
```

## `lf-config/redis.php`

```php
return [
    'host'     => '127.0.0.1',
    'port'     => 6379,
    'prefix'   => 'lf',
    'password' => '',
];
```

Used by the Redis session driver and the queue's `redis` driver — both read this file as-is; there's no separate Redis connection to configure per feature.

## `lf-config/memcached.php`

```php
return [
    'host'     => '127.0.0.1',
    'port'     => 11211,
    'prefix'   => 'cbm',
    'username' => '',
    'password' => '',
];
```

## `lf-config/queue.php`

```php
return [
    'driver'        => 'json',    // 'database' | 'redis' | 'json'
    'connection'    => 'default', // used when driver/failed_driver is 'database'
    'failed_driver' => null,      // 'database' | 'json' — defaults per 'driver', see below
];
```

See [Queue](../12_queue/01_basic.md) for the full driver breakdown and how to run `php worker`.

## `lf-config/auth.php`

Guards, keyed directly by guard name — **not** wrapped in a `'guards'` key. Each entry needs a `driver` (`session`, `cookie`, or `token`) and a `provider` (a model class for `token`, an arbitrary string for `session`/`cookie`).

```php
use App\Model\UsersModel;
use App\Model\StaffsModel;

return [
    'web'      => ['driver' => 'session', 'provider' => 'web'],
    'remember' => ['driver' => 'cookie',  'provider' => 'remember'],
    'admin'    => ['driver' => 'token',   'provider' => StaffsModel::class],
    'user'     => ['driver' => 'token',   'provider' => UsersModel::class],
];
```

See [Authentication](../09_authentication/01_basic.md) for guard usage.

## Shield (firewall) config

Not part of `lf-config/` by default — `laikait/laika-shield` reads its own config array via `Laika\Shield\ShieldConfig` (dot-notation `add()`/`get()`/`has()`), typically loaded from a file you publish yourself. See [Security (Shield)](../10_security/01_basic.md).

## CORS

Set via `Laika\Service\CORS` static setters (merged with framework defaults: `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Content-Security-Policy`).

## Hooks & Routes

`lf-hooks/` and `lf-routes/` aren't config files in the return-an-array sense — they're plain PHP files auto-loaded on boot. See [Hooks](../08_hooks/01_basic.md) and [Routing](../02_routing/01_basic.md).

## Language files (if used)

Managed via `Laika\Service\Local` — `Local::set('en')` / `Local::get()` / `Local::path(...$additional)`. Files live in `lf-lang/` (e.g. `en.local.php`).
