# Creating a Pipeline

## Location
`App\Pipeline` namespace, files in `lf-app/Pipeline`.

## Create Pipeline Using CLI

```bash
php laika make:pipeline HomePipeline
```

## Sample
```php
namespace App\Pipeline;

use Laika\Route\Interfaces\PipelineInterface;

class HomePipeline implements PipelineInterface
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
})->pipeline(HomePipeline::class);
```

## Multiple Pipelines
```php
Url::get('/dashboard', function () {
    // controller logic
})->pipeline([HomePipeline::class, AuthPipeline::class]);
```

## Return Behavior

| Return value | Chain continues? | Controller runs? | Output |
|---|---|---|---|
| `$next()` | Yes | Yes (if last pipeline) | Controller's return value |
| `$next(false)` | No | No | Nothing / null |
| `'any text'` | No | No | The returned string itself |

## Rules
- Implements `Laika\Route\Interfaces\PipelineInterface`.
- `handle(callable $next, array &$params): ?string`
- `$params` passed by reference — mutate to pass data forward.