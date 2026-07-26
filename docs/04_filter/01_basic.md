# Creating a Filter

## Location
`App\Filter` namespace, files in `lf-app/Filter`.

## Create Filter Using CLI

```bash
php laika make:filter LogFilter
```

## Sample
```php
namespace App\Filter;

use Laika\Route\Interfaces\FilterInterface;

class LogFilter implements FilterInterface
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
})->filter(LogFilter::class);
```

## Multiple Filters
```php
Url::get('/dashboard', function () {
    // controller logic
})->filter([LogFilter::class, AuditFilter::class]);
```

## Return Behavior

| Return value | Chain continues? | Response |
|---|---|---|
| `$next($response)` | Yes | Original response passed forward |
| `$next($response, false)` | No | Original response passed forward |
| `$next('anytext')` | Yes | Controller's response is replaced with 'anytext' |
| `$next('anytext', false)` | No | Controller's response is replaced with 'anytext' |

## Rules
- Implements `Laika\Route\Interfaces\FilterInterface`.
- `terminate(callable $next, ?string $response, array &$params): ?string`
- `$params` passed by reference — mutate to pass data forward.
- Runs after the controller, in the order registered.