# Caching

The [Cache module](https://github.com/laikait/laika-engine/tree/main/docs/cache) of `laikait/laika-engine` stores values between requests — anything slow to compute, query or fetch. It has four drivers, and three optional integrations built on it: [query results](#query-results), [whole responses](#responses) and [template fragments](#template-fragments).

```php
use Laika\Engine\Services\Cache;

$rates = Cache::remember('exchange-rates', 600, fn () => $api->fetchRates());
```

This is a **server-side** cache. It is unrelated to the browser caching of static files configured in [`lf-config/assets.php`](../01_getting-started/03_configuration.md#caching), which only sets `Cache-Control` headers.

## Choosing a Driver

Set in `lf-config/cache.php`:

```php
return [
    'driver'    => 'file',   // 'file' | 'array' | 'redis' | 'memcached'
    'prefix'    => 'laika',  // keys become "<prefix>:cache:<key>"
    'ttl'       => 3600,     // seconds when set() is given none; 0 = never expire
    'path'      => null,     // file driver; null = lf-storage/cache/data
    'serialize' => ['allowed_classes' => false],
    'query'     => ['enabled' => false, 'ttl' => 60],
];
```

| Driver | Storage | Good for | Notes |
|---|---|---|---|
| `file` | `lf-storage/cache/data/` | Default, single server | No extension, no server. Writes are atomic and `increment()` is locked, so concurrent requests lose nothing. |
| `array` | Process memory | Tests, per-request memos | Gone at the end of the request. Nothing is serialized. |
| `redis` | Redis, from [`lf-config/redis.php`](../01_getting-started/03_configuration.md#lf-configredisphp) | Several servers | Requires `ext-redis`. `flush()` removes only this cache's keys, never the queue's or sessions'. |
| `memcached` | Memcached, from [`lf-config/memcached.php`](../01_getting-started/03_configuration.md#lf-configmemcachedphp) | Several servers | Requires `ext-memcached`. See the warnings below. |

Redis and Memcached reuse the host, port and credentials already in their own config files — the same ones sessions and the queue read. `prefix` keeps the cache (`laika:cache:*`) from colliding with the queue (`laika:queue:*`) on a shared server.

> **Memcached never reports a dead server.** A wrong host or a stopped server looks exactly like an empty cache: every `set()` returns `false` and every read misses, forever, with no error. And Memcached cannot delete by prefix, so `flush()` and `php laika cache:clear` wipe **the whole server**, including anything else using it.

A backend that fails at runtime degrades to a miss rather than throwing — a cache outage makes pages slower, not broken. Only misconfiguration throws: an unknown driver name, or a missing extension.

## Reading and Writing

| Relay method (`Laika\Engine\Services\Cache`) | Helper | Does |
|---|---|---|
| `get(string $key, mixed $default = null): mixed` | `cache($key, $default)` | The value, or `$default` on a miss |
| `set(string $key, mixed $value, ?int $ttl = null): bool` | `cache_set($key, $value, $ttl)` | Store. `null` TTL uses the config default, `0` never expires |
| `has(string $key): bool` | `cache_has($key)` | Whether the key is present |
| `pop(string $key): bool` | `cache_pop($key)` | Remove. `true` even if it was already absent |
| `remember(string $key, ?int $ttl, callable $fn): mixed` | `cache_remember($key, $ttl, $fn)` | The value, or run `$fn`, store its result and return it |
| `forever(string $key, mixed $value): bool` | | `set()` with no expiry |
| `pull(string $key, mixed $default = null): mixed` | | Read and remove |
| `increment(string $key, int $by = 1): int\|false` | | Add; a missing key counts as 0. Keeps the key's existing expiry |
| `decrement(string $key, int $by = 1): int\|false` | | Subtract |
| `flush(): bool` | | Remove every entry this cache owns |
| `store(string $driver)` | | Use a driver other than the configured one, e.g. `Cache::store('array')` |
| `extend(string $name, callable $resolver): void` | | Register your own driver |

`cache` and `cache_remember` are also registered as [hooks](../08_hooks/01_basic.md).

**A stored `null`, `false`, `0` or `''` is a hit, not a miss.** `get()` returns `$default` only when the key is absent or expired, `has()` answers whether it is present whatever its value, and `remember()` runs its callback once even when the callback returns `null`.

### Keys and TTLs

Keys are any string — they are hashed before reaching the file system or Memcached, so slashes, colons and long keys are fine. There is no tagging: to invalidate a group of entries, put a version in their keys (`"products:v{$version}:{$id}"`) and change the version.

`increment()` does not renew the TTL, so a counter written with a 60-second window expires 60 seconds after it was *created*, however often it is incremented. It is atomic across processes with the `file` and `array` drivers only; on Redis and Memcached two simultaneous increments can land as one.

### Cached Objects

Values are serialized, and by default **no class is rebuilt** when reading one back: a cached object returns as an unusable `__PHP_Incomplete_Class`. A cache is shared, writable state — on Redis, possibly writable by anything else on that server — and rebuilding arbitrary classes from it is an object-injection risk. Cache arrays and scalars, or list the classes you cache on purpose:

```php
'serialize' => ['allowed_classes' => [App\Dto\Price::class]],
```

The `array` driver serializes nothing, so an object comes back as the very instance you stored.

## Query Results

Turn it on in `lf-config/cache.php`:

```php
'query' => ['enabled' => true, 'ttl' => 60],
```

Then opt a query in with `remember()`:

```php
$posts = (new Model())->table('posts')
    ->where(['status' => 'published'])
    ->remember(300)   // seconds; remember() alone uses 'ttl'
    ->get();
```

`get()` and `count()` are cached, and so are `first()`, `find()` and `pluck()`, which go through `get()`. `cursor()` never is — it exists for result sets too large to hold in memory. Without `remember()`, or with `enabled` off, the query runs as normal.

**Invalidation is automatic for writes the model makes.** `insert()`, `update()`, `delete()`, `increment()`, `decrement()` and `restore()` invalidate every cached query on that table and every cached query that **joined** it. The invalidation waits for the transaction to commit, and is dropped if it rolls back. A query that runs inside a transaction is never cached, since it can see uncommitted rows.

**Invalidation cannot see writes the model does not make:** raw SQL through `execute()`, another application, a cron script using PDO directly, or a table reached only inside a raw expression or subquery. After those, invalidate by hand:

```php
(new Model())->execute('UPDATE posts SET views = views + 1 WHERE id = ?', [$id]);
Model::forgetQueryCache('posts');               // or forgetQueryCache('posts', 'reporting')
```

## Responses

`CachePipeline` stores a route's whole response — body, status, content type and headers — so the controller does not run at all on a hit.

```php
use Laika\Engine\Pipeline\CachePipeline;

Url::get('/pricing', 'PageController@pricing')
    ->pipeline([Auth::class, CachePipeline::class . '|cache_ttl=300']);
```

**It must be the route's last pipeline.** Pipelines run in order and a hit returns immediately, so anything listed after it — an auth check, an IP allowlist — would be skipped for every cached request. For the same reason it refuses to cache when registered with `Url::globalPipeline()`: global pipelines run before a route's own authorization.

A response is stored only when it is safe to give to someone else. Each of these conditions exists because without it one visitor's page could be served to another:

| Not cached when | Why |
|---|---|
| The request is not `GET` | Not idempotent |
| The request has an `Authorization` header or a session cookie | The response may belong to that user |
| The controller generated a CSRF token | Tokens are single-use and bound to one browser. Any page using `lf_header()` does, so **pages using `lf_header()` are never cached** |
| The controller started a session or set a cookie | The output may be personal |
| The status is not 200, or the body is empty | |

Hidden `_csrf` fields that Laika adds to forms are *not* a problem: they are added after the pipeline, fresh on every request, cached or not.

Responses carry an `X-Laika-Cache: HIT` or `MISS` header. They expire on their TTL only — nothing invalidates them when data changes — so pick a TTL you can tolerate being stale for, and clear the cache after deploying changes to those pages.

## Template Fragments

Cache part of a Twig template:

{% raw %}
```twig
{% cache 'sidebar' %}
    {% for post in popular_posts() %} ... {% endfor %}
{% endcache %}

{% cache 'product-' ~ product.id 600 %}
    ...
{% endcache %}
```
{% endraw %}

The first argument is the key (any expression); the optional second is a TTL in seconds. On a hit the body does not run, so any query or function call inside it is skipped. Output is escaped exactly as without the tag, and tags may nest.

The key is the only thing that decides a hit. If the fragment depends on a variable — the product, the locale, the signed-in user — that variable **must** be part of the key, or every visitor sees whichever version was cached first.

A fragment that generates a CSRF token (a hand-built form field, `lf_header()`) is rendered but never stored.

## Long-Running Processes

Under PHP-FPM a process handles one request, and everything held in memory — loaded config files, options, the `array` cache driver — starts fresh each time. The queue worker is different: on a host without `pcntl` (every Windows host) it runs every job in one long-lived process.

The framework resets that state before each job, so a job sees current config and options rather than whatever the first job loaded. The reset runs `Laika\Engine\System\ProcessState::reset()`, which you can call yourself from any other long-running loop.

## Clearing the Cache

```bash
php laika cache:clear              # data cache and compiled Twig templates
php laika cache:clear --data       # data cache only
php laika cache:clear --templates  # compiled templates only
php laika cache:forget --key=exchange-rates
```

`cache:clear --templates` is the only command that clears compiled Twig. With `DEBUG` off, Twig does not recompile an edited template on its own, so run it after deploying template changes. Neither command touches the resource manifest from `php laika app:cache`.
