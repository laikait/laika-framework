# Filters

A filter is middleware that runs **after** the controller. It receives the response string and can log it, change it, or replace it.

## Create via CLI

```bash
php laika filter:make LogAccess
```

Filters live in `lf-app/Filter/`, namespace `App\Filter`.

## Anatomy

```php
namespace App\Filter;

use Laika\Engine\Route\Contracts\FilterInterface;

class LogAccess implements FilterInterface
{
    public function terminate(callable $next, ?string $response, array &$params): ?string
    {
        // ... your code ...

        return $next($response);   // pass the (possibly changed) response on
    }
}
```

- `$response` — the controller's return value (or a pipeline's short-circuit string). `null` when nothing was returned.
- `$params` — the same array the pipelines and controller received, by reference.
- Whatever the last filter returns is sent.

## Attaching Filters

```php
use Laika\Engine\Route\Url;

Url::get('/', 'HomeController@index')->filter(LogAccess::class);
Url::get('/dashboard', 'DashboardController@index')->filter(['LogAccess', 'Minify']);

// Every route in the application
Url::globalFilter(['LogAccess']);
```

Short names resolve to `App\Filter\...`; a fully qualified class name is used as-is.

## Return Behavior

| In `terminate()` you... | Remaining filters | Response sent |
|---|---|---|
| `return $next($response);` | run | the response, as passed along |
| `return $next($response, false);` | skipped | `$response` |
| `return $next('other');` | run | `'other'` replaces the response |
| `return $next('other', false);` | skipped | `'other'` |
| `return 'other';` (no `$next`) | skipped | `'other'` |

## Examples

**Log every response:**

```php
public function terminate(callable $next, ?string $response, array &$params): ?string
{
    error_log(sprintf('%s %s -> %d bytes',
        $_SERVER['REQUEST_METHOD'] ?? '-', $_SERVER['REQUEST_URI'] ?? '-', strlen((string) $response)));

    return $next($response);
}
```

**Add a header:**

```php
use Laika\Engine\Services\Response;

public function terminate(callable $next, ?string $response, array &$params): ?string
{
    Response::setHeader('X-Robots-Tag', 'noindex');
    return $next($response);
}
```

**Maintenance mode:**

```php
public function terminate(callable $next, ?string $response, array &$params): ?string
{
    if (option_bool('maintenance')) {
        \Laika\Engine\Services\Response::setStatus(503);
        return '<h1>Down for maintenance</h1>';
    }

    return $next($response);
}
```

## Passing Config Args

Same syntax as pipelines. The values arrive in `$params`, and are strings:

```php
Url::get('/reports', 'ReportController@index')->filter(['LogAccess|level=info']);
```

```php
public function terminate(callable $next, ?string $response, array &$params): ?string
{
    error_log('level=' . ($params['level'] ?? 'default'));
    return $next($response);
}
```

Filter args are merged when that filter runs, so only later filters see them — never the controller.

## Dependencies

Filters are built through the container, so type-hint dependencies in the constructor:

```php
use App\Support\AuditLog;

class LogAccess implements FilterInterface
{
    public function __construct(private AuditLog $log) {}

    public function terminate(callable $next, ?string $response, array &$params): ?string
    {
        $this->log->write($params);
        return $next($response);
    }
}
```

The rules are the same as for [pipelines](../03_pipeline/01_basic.md#dependencies): concrete classes auto-wire, interfaces must be bound in a relay provider, and relays should be called statically rather than injected.

## Order and Scope

- **Order:** global filters → group filters (`Handler::registerGroup()`) → route `->filter()` → group-chained `->filter()`.
- Filters form an onion: code after `$next($response)` runs in reverse order.
- Filters run on a controller's response and on a pipeline's short-circuit string. They do **not** run on 404 pages, fallbacks, or static files.

## Rules

- Implement `Laika\Engine\Route\Contracts\FilterInterface` (any class with a matching `terminate()` method is accepted).
- Signature: `terminate(callable $next, ?string $response, array &$params): ?string`
- An unknown filter name throws `FilterException` (status 500).

## CLI Reference

| Command | Description |
|---|---|
| `php laika filter:make <name>` | Create a filter class |
| `php laika filter:list` | List filter classes |
| `php laika filter:remove <name>` | Delete a filter class |
| `php laika filter:rename --old=<name> --new=<name>` | Rename a filter class |

## See Also

- [Pipelines](../03_pipeline/01_basic.md) — middleware before the controller
- [Responses](../02_routing/04_responses.md)
