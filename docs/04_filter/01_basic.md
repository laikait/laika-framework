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

## Passing Config Args

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
