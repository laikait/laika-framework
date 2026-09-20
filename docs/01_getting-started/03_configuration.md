# Configuration

All configuration lives in `lf-config/*.php`. There is no `.env` file. Each file returns a plain PHP array, and you read it with the `config()` helper or the `Laika\Engine\Services\Config` relay.

```php
config('app', 'name');                  // 'Laika Framework'
config('database', 'default');          // the whole 'default' connection array
config('redis');                        // the whole file
config('app', 'timezone', 'UTC');       // with a default for a missing key
```

## Reading Configuration

```php
config(string $name, ?string $key = null, mixed $default = null): mixed
```

- `$name` is the file name without `.php`. `$key` is a **top-level** key in that file.
- **There is no dot notation.** `config('app.name')` looks for a file called `app.name.php` and returns `null`. Write `config('app', 'name')`.
- File names and keys are lowercased, so `APP_NAME` and `app_name` are the same key.
- Every file in `lf-config/` is loaded together the first time any config is read, then cached for the rest of the process. (`providers.php` is the one file that is skipped.)

The same API is on the relay, plus a few extras:

```php
use Laika\Engine\Services\Config;

Config::get('app', 'name');
Config::has('mail', 'host');     // file and key exist?
Config::all();                   // every loaded file
```

> **Warning:** `Config::set()`, `Config::pop()` and `Config::create()` **rewrite the whole file** from the loaded array. Comments, `use` statements, constants and `::class` expressions are lost. Never call them on hand-written files like `auth.php` or `database.php`; keep them for files your application manages itself.

Every config file starts with a direct-access guard. Keep it in files you add:

```php
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');
```

## The Files at a Glance

| File | Read by |
|---|---|
| [`app.php`](#lf-configappphp) | App name, trusted proxies (URL/IP/cookie detection) |
| [`assets.php`](#lf-configassetsphp) | The front controller, when it serves static files |
| [`database.php`](#lf-configdatabasephp) | Database connections |
| [`auth.php`](#lf-configauthphp) | Auth guards |
| [`mail.php`](#lf-configmailphp) | Your code, when it builds a `Laika\Engine\Mailman\Mailer` |
| [`redis.php`](#lf-configredisphp) | Redis session/queue drivers, `RedisStorage`, `RedisConnection` |
| [`memcached.php`](#lf-configmemcachedphp) | Memcached session driver, `MemcachedStorage` |
| [`queue.php`](#lf-configqueuephp) | The `worker` executable, `queue:*` commands, `Laika\Engine\Worker\Queue` |
| [`s3.php`](#lf-configs3php) | `S3Storage`, `S3Connection` |

Two more places hold settings that aren't arrays: [`lf-inc/const.php`](#lf-incconstphp) and your [hook files](#settings-that-belong-in-a-hook-file).

## `lf-config/app.php`

```php
return [
    'name'          => 'Laika Framework',
    'url'           => 'https://laikaframework.com',
    'documentation' => 'https://docs.laikaframework.com',

    // Proxies allowed to set X-Forwarded-* / CF-Visitor / X-Real-IP.
    'trusted_proxies' => [],
];
```

| Key | Used for |
|---|---|
| `name` | `app_name()`, `page_title()`, and the seeded `app_name` option |
| `url`, `documentation` | Informational only; nothing in the framework reads them |
| `trusted_proxies` | IPs or CIDR ranges of your load balancers/CDN, e.g. `['10.0.0.0/8', '192.168.1.5']` |

**`trusted_proxies` matters in production.** Proxy headers (`X-Forwarded-Proto`, `X-Forwarded-Host`, `X-Forwarded-For`, `CF-Visitor`, ...) are ignored unless the request's `REMOTE_ADDR` is listed here. Behind a load balancer, leave it empty and `Url::base()`, `Url::isHttps()`, `Visitor::ip()` and the cookie `Secure` flag all describe the proxy's hop instead of the client's. `'*'` trusts every peer — a last resort for platforms that don't expose a stable proxy address.

## `lf-config/assets.php`

Which static files the framework will hand out, and how long a browser may keep them. Every request reaches `public/index.php` — the rewrite rules do not let the web server serve a file directly — so `Laika\Engine\Route\Asset` is the only gatekeeper, and this file is what it reads.

```php
return [
    // Servable extensions. Anything not listed is a 404, whatever directory it
    // sits in. That is what keeps .twig sources, .env, lf-logs/*.log and
    // lf-storage/keys/app.key unreachable.
    'extensions' => [
        'css', 'js', 'map',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf',
        'mp3', 'wav', 'ogg', 'mp4', 'webm',
        'pdf', 'zip', 'txt', 'csv',
    ],

    // Refused everywhere, even if listed above.
    'blocked' => ['php', 'phar', 'phtml', 'phps', 'json'],

    // Roots written by users rather than by you. Markup served from here is
    // stored XSS, so these types are refused inside them.
    'untrusted' => [
        'roots'   => ['uploads'],
        'blocked' => ['html', 'htm', 'svg', 'xml'],
    ],

    // How long a browser may keep a served file.
    'cache' => [
        'default' => 3600,              // seconds, for anything not listed below
        'max_age' => [
            'woff2' => 31536000, 'woff' => 31536000, 'ttf' => 31536000, 'otf' => 31536000,
            'png'   => 2592000,  'jpg'  => 2592000,  'jpeg' => 2592000,
            'gif'   => 2592000,  'webp' => 2592000,  'ico'  => 2592000, 'svg' => 2592000,
            'css'   => 604800,   'js'   => 604800,
            'map'   => 0,               // 0 means revalidate every time
        ],
        'versioned_max_age' => 31536000, // for a URL carrying ?v=
    ],

    // null, 'x-sendfile' or 'x-accel-redirect'
    'sendfile' => null,
];
```

An extension in both `extensions` and `blocked` is refused — `blocked` always wins. `Content-Type` still comes from `Laika\Engine\Services\MimeType`; `MimeType::register()` only adds a content type, it does not make a type servable. Only this file decides that.

Three rules are enforced in code and no config can loosen them:

| Never served | |
|---|---|
| `lf-*`, `vendor/`, `docs/` | framework internals — the same list `nginx.conf` denies |
| any path with a dot segment | `.git/`, `.env`, `.htaccess` |
| anything outside `APP_PATH` | `realpath()` resolves `..` and symlinks before the check |

Everything else under the project root is servable if its extension passes, so a per-template asset directory such as `template/{name}/assets/css/app.css` works with no configuration. A rejection renders the same `404` page an unrouted URL renders, so a forbidden path looks the same as a missing one.

If this file is absent the framework falls back to built-in defaults: every type `MimeType` knows, minus `php`, `phar`, `phtml`, `phps`, `html`, `htm`, `svg`, `xml` and `json`.

### Caching

Every served file carries an `ETag` and a `Last-Modified`, and a request with `If-None-Match` or `If-Modified-Since` is answered `304` with no body. That happens at every `max_age`, including `0` — setting a type to `0` means "ask me every time", not "transfer it every time".

A request carrying a `v` query is treated as content-addressed and served `max-age={versioned_max_age}, immutable` — the browser then reuses its copy with no request at all, rather than revalidating. Set `versioned_max_age` to `0` to switch that off.

`asset()`, `enqueue_style()` and `enqueue_script()` put that `v` on the URLs they generate, derived from the file's modification time, so a file that changes gets a new URL by itself. The one way to get pinned to a stale copy is to pass an explicit `$version` to `enqueue_*` and then not change it when you edit the file.

### Handing the transfer to the web server

`sendfile` moves the bytes out of PHP once this file has decided the request is allowed:

| Value | Header sent | Needs |
|---|---|---|
| `null` | — | nothing; PHP reads the file (the default) |
| `'x-sendfile'` | `X-Sendfile: /abs/path` | Apache with `mod_xsendfile` |
| `'x-accel-redirect'` | `X-Accel-Redirect: /relative/path` | nginx with a matching `internal` location |

The authorisation checks above still run either way. See [Deployment](../13_deployment/01_basic.md) for the server config each one needs — turning this on without it serves nothing.

> A URL with a file extension is a static file **only when a real file is there**. `/sitemap.xml` and `/feed.json` can be ordinary routes — see [Routing](../02_routing/01_basic.md#urls-with-a-file-extension).

## `lf-config/database.php`

Each **top-level key is a connection name**. Models and schemas use `'default'` unless told otherwise.

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
];
```

`driver` accepts every Model driver: `mysql`, `mariadb`, `pgsql`, `sqlite`, `sqlsrv`, `oci`, `firebird`. See the [Model module docs](https://github.com/laikait/laika-engine/tree/main/docs/model) for per-driver keys (`charset`, `file` for SQLite, and so on).

Add a key per extra connection (for example `'analytics'`). It's registered under its own name the first time a model, schema, session driver, token guard or the queue uses it. See [Models → Multiple Connections](../05_models/01_basic.md#multiple-connections).

## `lf-config/auth.php`

Guards, keyed directly by guard name — **not** wrapped in a `'guards'` key.

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

Each entry needs a `driver` (`session`, `cookie` or `token`). Token guards need a `provider` that is a `Laika\Engine\Model\Model` subclass, and accept optional `connection` and `install` keys. See [Authentication](../09_authentication/01_basic.md).

## `lf-config/mail.php`

The skeleton ships with `sendmail` and every other key commented out:

```php
return [
    'driver' => 'sendmail', // smtp, sendmail, mail, qmail
];
```

Nothing in the framework reads this file on its own. You pass it to `Laika\Engine\Mailman\Mailer` yourself: `new Mailer(config('mail'))`.

The commented keys are the Mailer's own names; anything else is silently ignored. A working SMTP file:

```php
return [
    'driver'     => 'smtp',
    'host'       => 'smtp.example.com',
    'port'       => 587,
    'encryption' => 'tls',       // 'tls' = STARTTLS (587), 'ssl' = implicit TLS (465), '' = none
    'username'   => 'user@example.com',
    'password'   => 'secret',
    'from'       => 'no-reply@example.com',
    'from_name'  => 'Laika App',
];
```

> Older skeletons suggested `secure` and `from_email`. `Mailer` never read those — rename them to `encryption` and `from`.

Every key is listed in [Mail](../17_mail/01_basic.md#configuration).

## `lf-config/redis.php`

```php
return [
    'host'         => '127.0.0.1',
    'port'         => 6379,
    'prefix'       => 'laika',
    'expire'       => 0,       // default TTL for RedisStorage; 0 = none
    'database'     => 0,       // 0-15
    'timeout'      => 2.5,     // connect timeout, seconds
    'read_timeout' => 2.5,
    'username'     => '',      // Redis 6 ACL user
    'password'     => '',
];
```

One file serves every Redis feature — the session driver, the queue's `redis` driver, `RedisStorage` and `RedisConnection::make()`. There's no per-feature Redis connection. Requires `ext-redis`.

## `lf-config/memcached.php`

```php
return [
    'host'     => '127.0.0.1',
    'port'     => 11211,
    'prefix'   => 'laika',
    'expire'   => 0,           // seconds; 0 = never
    'username' => '',          // SASL; switches to the binary protocol
    'password' => '',
];
```

Used by the Memcached session driver, the `memcached` cache driver and `MemcachedStorage`. Requires `ext-memcached`.

## `lf-config/cache.php`

```php
return [
    'driver'    => 'file',   // 'file' | 'array' | 'redis' | 'memcached'
    'prefix'    => 'laika',  // keys become "<prefix>:cache:<key>"
    'ttl'       => 3600,     // seconds when set() is given none; 0 = never expire
    'path'      => null,     // file driver; null = lf-storage/cache/data
    'serialize' => ['allowed_classes' => false], // classes a cached value may rebuild
    'query'     => ['enabled' => false, 'ttl' => 60], // Model::remember()
];
```

The `redis` and `memcached` drivers connect with the settings in their own config files above. See [Caching](../20_cache/01_basic.md).

## `lf-config/queue.php`

```php
return [
    'driver'          => 'json',    // 'json' | 'database' | 'redis'
    'connection'      => 'default', // used when driver/failed_driver is 'database'
    'failed_driver'   => null,      // 'database' | 'json'; null = mirror 'driver', else 'json'
    'reserve_timeout' => 90,        // redis only: seconds before a reserved job is reclaimed
    'max_tries'       => 3,         // redis only: reclaims before a job goes to the dead set; 0 = no cap
];
```

See [Queue](../12_queue/01_basic.md) for what each driver needs and how to run the worker.

## `lf-config/s3.php`

Used by `Laika\Engine\Storage\S3Storage` for S3 and S3-compatible storage (MinIO, Cloudflare R2, DigitalOcean Spaces).

```php
return [
    'region'     => '',            // e.g. us-east-1
    'key'        => '',
    'secret'     => '',
    'bucket'     => '',
    'root'       => 'lf-storage',  // key prefix for every object
    'acl'        => 'public-read', // uploads are PUBLIC by default — use 'private' for private files
    'url'        => '',            // CDN/custom domain; empty = the bucket URL
    'version'    => 'latest',
    'endpoint'   => '',            // S3-compatible services; empty = AWS
    'path_style' => true,          // only applied when 'endpoint' is set
];
```

See [Files & Storage](../16_files-and-storage/01_basic.md#s3storage).

## `lf-inc/const.php`

Plain PHP constants, loaded before the autoloader:

```php
define('DEBUG', true);
define('MEMORY_LIMIT', '256M');
define('CLI_MEMORY_LIMIT', '256M');
```

| Constant | Effect |
|---|---|
| `DEBUG` | `true`: Whoops error pages, errors logged to `lf-logs/`, the resource manifest ignored. `false`: generic error page, **nothing logged**, the compiled manifest in `lf-storage/cache/resources.php` used. **Set it to `false` in production.** |
| `MEMORY_LIMIT` / `CLI_MEMORY_LIMIT` | Applied to `memory_limit` at boot: `MEMORY_LIMIT` for web requests, `CLI_MEMORY_LIMIT` for `php laika` and the worker. They can only lower the `php.ini` limit, never raise it. See [Deployment](../13_deployment/01_basic.md#memory-limits). |

If `DEBUG` isn't defined, the Core module defines it as `true`.

## Settings That Belong in a Hook File

Some settings are made with a method call rather than a config key. Put them in any file under `lf-hooks/` — those load during bootstrap, before routing. See [Hooks](../08_hooks/01_basic.md).

```php
// lf-hooks/app.php
use Laika\Engine\Services\{CORS, Date, Init, Local};

// Session driver — required before the first Session:: call
Init::file();

// CORS — each call REPLACES that list; defaults otherwise
CORS::origins(['https://app.example.com']);

// Timezone — the framework forces PHP's default timezone to UTC at boot
Date::setAppTimezone('Asia/Dhaka');

// Language — nothing loads lf-lang/ automatically
Local::set('en');
Local::load();
```

| Setting | Where it's documented |
|---|---|
| Session driver (`Init::file()`, `Init::redis()`, ...) | [Sessions](../11_sessions/01_basic.md) |
| CORS origins, methods, headers, security headers | [CSRF & CORS](../10_security/02_csrf-and-cors.md#cors) |
| Firewall rules (`ShieldConfig::add()`) | [Security (Shield)](../10_security/01_basic.md) |
| Timezone | [Utilities → Date](../19_utilities/01_basic.md#date) |
| Extra database connections | [Models](../05_models/01_basic.md#multiple-connections) |

## Language Files

Translations are static properties of a `LANG` class, one file per language, in `lf-lang/{lang}.local.php`:

```php
// lf-lang/en.local.php
class LANG
{
    public static string $greeting = 'Hello, %s!';
}
```

Select and load one in a hook file (`Local::set('en'); Local::load();`), then call `local('greeting', 'Ann')` in PHP or {% raw %}`{{ 'local'|hook('greeting', 'Ann') }}`{% endraw %} in Twig.

| `Laika\Engine\Services\Local` method | Does |
|---|---|
| `set(string $lang = 'en'): void` | Selects `xx` or `xx-yy`; anything else throws `LocalException` |
| `get(): string` | The selected language (`en` by default); templates get it as `local` |
| `setPath(string $path): void` | An absolute directory, or a sub-directory of `lf-lang` |
| `load(): void` | Requires the file, creating it with a sample `LANG` class if missing |

The file defines a global class with `require_once`, so one request can use only one language. `local()` throws `RuntimeException` if the `LANG` class or the property is missing.

## See Also

- [Project Structure](02_project-structure.md) — where each file lives
- [Request Lifecycle](05_request-lifecycle.md) — when config and hook files are loaded
- [Deployment](../13_deployment/01_basic.md) — production values
