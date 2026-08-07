# Pipelines

A pipeline is middleware that runs **before** the controller — the natural place for authentication, authorization, or request validation that can short-circuit the response.

## Location

`App\Pipeline` namespace, files in `lf-app/Pipeline`.

## Create via CLI

```bash
php laika make:pipeline Authenticate
```

## Sample

```php
namespace App\Pipeline;

use Laika\Route\Interfaces\PipelineInterface;

class Authenticate implements PipelineInterface
{
    /**
     * @param callable $next
     * @param array $params
     * @return ?string
     */
    public function handle(callable $next, array &$params): ?string
    {
        // Start Code From Here....

        return $next();
    }
}
```

## Register

```php
Url::get('/', function () {
    // controller logic
})->pipeline(Authenticate::class);
```

## Multiple Pipelines

```php
Url::get('/dashboard', function () {
    // controller logic
})->pipeline([Authenticate::class, VerifiedEmail::class]);
```

## Passing Config Args

Pipelines can be referenced by short class name with inline `key=value` config, available in `$params`:

```php
Url::get('/admin', 'AdminController@index')->pipeline(['Role|role=admin']);
Url::get('/reports', 'ReportController@index')->pipeline(['Throttle|limit=60,window=60']);
```

```php
namespace App\Pipeline;

use Laika\Route\Interfaces\PipelineInterface;

class Role implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        if (($_SESSION['role'] ?? null) !== ($params['role'] ?? null)) {
            http_response_code(403);
            return 'Forbidden'; // stops the chain, this string is the response
        }

        return $next();
    }
}
```

## Global Pipelines

Apply a pipeline to every route in the application:

```php
Url::globalPipeline(['Csrf', 'Cors']);
```

## Return Behavior

| Return value | Chain continues? | Controller runs? | Output |
|---|---|---|---|
| `$next()` | Yes | Yes (if last pipeline) | Controller's return value |
| `$next(false)` | No | No | Controller's return value |
| `'anytext'` | No | No | Ignores the controller, returns the string itself |

## Rules

- Implements `Laika\Route\Interfaces\PipelineInterface`.
- `handle(callable $next, array &$params): ?string`
- `$params` — route params + pipeline config args, merged and passed **by reference** through the whole chain (pipeline → controller → filter). Mutate it to pass data forward.

## CLI Reference

| Command | Description |
|---|---|
| `php laika make:pipeline <name>` | Create a pipeline class |
| `php laika list:pipeline` | List registered pipeline classes |
| `php laika remove:pipeline <name>` | Delete a pipeline class |
| `php laika rename:pipeline <old> <new>` | Rename a pipeline class |

See [Filters](../04_filter/01_basic.md) for post-controller middleware, and [Routing](../02_routing/01_basic.md#pipelines--filters) for how attachment interacts with route groups.
