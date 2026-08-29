# Filters

A filter is middleware that runs **after** the controller — useful for logging, response shaping, or auditing once you already have a response.

## Location

`App\Filter` namespace, files in `lf-app/Filter`.

## Create via CLI

```bash
php laika filter:make LogAccess
```

## Sample

```php
namespace App\Filter;

use Laika\Route\Contracts\FilterInterface;

class LogAccess implements FilterInterface
{
    /**
     * @param callable $next
     * @param mixed $response
     * @param array $params
     */
    public function terminate(callable $next, ?string $response, array &$params): ?string
    {
        // Write Code From Here

        return $next($response);
    }
}
```

## Register

```php
Url::get('/', function () {
    // controller logic
})->filter(LogAccess::class);
```

## Multiple Filters

```php
Url::get('/dashboard', function () {
    // controller logic
})->filter([LogAccess::class, AuditFilter::class]);
```

## Dependencies

Type hint what the filter needs in its constructor and the container builds it:

```php
namespace App\Filter;

use Laika\Route\Contracts\FilterInterface;
use App\Service\LoggerService;

class LogAccess implements FilterInterface
{
    public function __construct(private LoggerService $log) {}

    public function terminate(callable $next, ?string $response, array &$params): ?string
    {
        $this->log->write($params);

        return $next($response);
    }
}
```

Concrete classes auto-wire with no registration; interface type hints must be bound in a [RelayProvider](../07_services-and-relay/01_basic.md). A `singleton()` binding is the same instance the pipelines and controller on that route received. See [Pipelines → Dependencies](../03_pipeline/01_basic.md) for the full rules.

`terminate()` keeps its fixed signature — the constructor is the injection point.

## Passing Config Args

Inline args are **route params, not constructor arguments** — they arrive in `$params`, and are unaffected by dependency injection:

```php
Url::get('/reports', 'ReportController@index')->filter(['LogAccess|level=info']);
```

```php
namespace App\Filter;

use Laika\Route\Contracts\FilterInterface;

class LogAccess implements FilterInterface
{
    public function terminate(callable $next, ?string $response, array &$params): ?string
    {
        error_log('level=' . ($params['level'] ?? 'default'));

        return $next($response);
    }
}
```

## Global Filters

Apply a filter to every route in the application:

```php
Url::globalFilter(['LogResponse']);
```

## Return Behavior

| Return value | Chain continues? | Response |
|---|---|---|
| `$next($response)` | Yes | Original response passed forward |
| `$next($response, false)` | No | Original response passed forward |
| `$next('anytext')` | Yes | Controller's response is replaced with `'anytext'` |
| `$next('anytext', false)` | No | Controller's response is replaced with `'anytext'` |

## Rules

- Implements `Laika\Route\Contracts\FilterInterface`.
- `terminate(callable $next, ?string $response, array &$params): ?string`
- `$params` — passed by reference, same array threaded through pipelines and the controller.
- Runs after the controller, in the order registered.

## CLI Reference

| Command | Description |
|---|---|
| `php laika filter:make <name>` | Create a filter class |
| `php laika filter:list` | List registered filter classes |
| `php laika filter:remove <name>` | Delete a filter class |
| `php laika filter:rename --old=<name> --new=<name>` | Rename a filter class |

See [Pipelines](../03_pipeline/01_basic.md) for pre-controller middleware.
