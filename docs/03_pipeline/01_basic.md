# Pipelines

A pipeline is middleware that runs **before** the controller — the natural place for authentication, authorization, or request validation that can short-circuit the response.

## Location

`App\Pipeline` namespace, files in `lf-app/Pipeline`.

## Create via CLI

```bash
php laika pipeline:make Authenticate
```

## Sample

```php
namespace App\Pipeline;

use Laika\Route\Contracts\PipelineInterface;

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

## Dependencies

Type hint what the pipeline needs in its constructor. The container builds it — nothing else changes, and the route API is untouched.

```php
namespace App\Pipeline;

use Laika\Route\Contracts\PipelineInterface;
use App\Service\AuthService;
use Laika\Service\Config;

class Authenticate implements PipelineInterface
{
    public function __construct(
        private AuthService $auth,
        private Config $config,
    ) {}

    public function handle(callable $next, array &$params): ?string
    {
        if (!$this->auth->check()) {
            return redirect($this->config->get('app.login_url'));
        }

        return $next();
    }
}
```

Three rules:

1. **Concrete classes need no registration.** They are auto-wired on demand, recursively — a dependency's own dependencies resolve too.
2. **Interface type hints must be bound** in a [RelayProvider](../07_services-and-relay/01_basic.md), because an interface cannot be auto-wired:

   ```php
   $this->registry->singleton(PaymentGateway::class, StripeGateway::class);
   ```

   An unbound one throws at the boundary naming the parameter, rather than injecting `null`.
3. **A `singleton()` binding is shared** across every pipeline, filter and controller in the request. The pipeline object itself is always built fresh.

`handle()` keeps its fixed signature — the constructor is the injection point. Filters work the same way, see [Filters](../04_filter/01_basic.md).

## Passing Config Args

Pipelines can be referenced by short class name with inline `key=value` config, available in `$params`. These are **route params, not constructor arguments** — the two channels are independent, so a pipeline can use both:

```php
Url::get('/admin', 'AdminController@index')->pipeline(['Role|role=admin']);
Url::get('/reports', 'ReportController@index')->pipeline(['Throttle|limit=60,window=60']);
```

```php
namespace App\Pipeline;

use Laika\Route\Contracts\PipelineInterface;

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
Url::globalPipeline(['CSRF', 'CORS']);
```

## Return Behavior

| Return value | Chain continues? | Controller runs? | Output |
|---|---|---|---|
| `$next()` | Yes | Yes (if last pipeline) | Controller's return value |
| `$next(false)` | No | No | Controller's return value |
| `'anytext'` | No | No | Ignores the controller, returns the string itself |

## Rules

- Implements `Laika\Route\Contracts\PipelineInterface`.
- `handle(callable $next, array &$params): ?string`
- `$params` — route params + pipeline config args, merged and passed **by reference** through the whole chain (pipeline → controller → filter). Mutate it to pass data forward.

## CLI Reference

| Command | Description |
|---|---|
| `php laika pipeline:make <name>` | Create a pipeline class |
| `php laika pipeline:list` | List registered pipeline classes |
| `php laika pipeline:remove <name>` | Delete a pipeline class |
| `php laika pipeline:rename <old> <new>` | Rename a pipeline class |

See [Filters](../04_filter/01_basic.md) for post-controller middleware, and [Routing](../02_routing/01_basic.md#pipelines--filters) for how attachment interacts with route groups.
