# Pipelines

A pipeline is middleware that runs **before** the controller — the place for authentication, authorization, rate limiting, or any check that may stop the request.

## Create via CLI

```bash
php laika pipeline:make Authenticate
```

Pipelines live in `lf-app/Pipeline/`, namespace `App\Pipeline`.

## Anatomy

```php
namespace App\Pipeline;

use Laika\Route\Contracts\PipelineInterface;

class Authenticate implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        // ... your check ...

        return $next();   // continue to the next pipeline, then the controller
    }
}
```

- `$next` — call it to continue. Whatever it returns is the response so far.
- `$params` — route parameters plus pipeline args, **passed by reference** through the whole chain: pipeline → controller → filters. Write to it to hand data forward.
- Return a **string** to stop the chain and respond with it.

## Attaching Pipelines

```php
use Laika\Route\Url;
use App\Pipeline\Authenticate;

Url::get('/dashboard', 'DashboardController@index')->pipeline(Authenticate::class);
Url::get('/dashboard', 'DashboardController@index')->pipeline(['Authenticate', 'VerifiedEmail']);
Url::get('/dashboard', 'DashboardController@index', ['Authenticate']); // third argument

// Every route in the application
Url::globalPipeline(['Authenticate']);
```

For groups, see [Routing → Middleware on Groups](../02_routing/01_basic.md#middleware-on-groups).

**Name resolution:** a short name (`'Authenticate'`, `'Admin\Role'`) resolves to `App\Pipeline\...`. A fully qualified class name (`Authenticate::class`, `\Laika\Shield\Pipeline\ShieldPipeline::class`) is used as-is.

## Return Behavior

| In `handle()` you... | Remaining pipelines | Controller | Filters | Response |
|---|---|---|---|---|
| `return $next();` | run | runs | run | the controller's |
| `return $next(false);` | **skipped** | **runs** | run | the controller's |
| `return 'text';` | skipped | skipped | run | `'text'` |
| `return null;` (no `$next`) | skipped | skipped | run | nothing is sent |

Code after `$next()` runs once everything later in the chain has finished, so a pipeline can also inspect or wrap the response:

```php
public function handle(callable $next, array &$params): ?string
{
    $start = microtime(true);
    $response = $next();
    error_log('took ' . round(microtime(true) - $start, 3) . 's');
    return $response;
}
```

## Stopping a Request

Set the status on the `Response` relay — a bare `http_response_code()` is overwritten when the response is sent:

```php
use Laika\Service\{Response, Redirect};
use Laika\Session\Session;

class Authenticate implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        if (!Session::has('user_id')) {
            Redirect::to('login');      // named route; sends Location and exits
        }

        return $next();
    }
}

class AdminOnly implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        if (Session::get('role') !== 'admin') {
            Response::setStatus(403);
            return 'Forbidden';          // stops here; filters still run
        }

        return $next();
    }
}
```

For APIs, set the content type too: `Response::setStatus(401)->setContentType('application/json'); return json_encode(['error' => 'Unauthenticated']);`

## Passing Config Args

Append `|key=value,key2=value2` to a pipeline name. The values arrive in `$params`:

```php
Url::get('/admin', 'AdminController@index')->pipeline(['Role|role=admin']);
Url::get('/reports', 'ReportController@index')->pipeline(['Throttle|limit=60,window=60']);
Url::get('/beta', 'BetaController@index')->pipeline(['Feature|beta']); // bare key = true
```

```php
class Role implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        if (Session::get('role') !== ($params['role'] ?? null)) {
            Response::setStatus(403);
            return 'Forbidden';
        }

        return $next();
    }
}
```

- Values are always **strings** (or `true` for a bare key). Cast numbers yourself.
- Don't put spaces after the commas — keys and values aren't trimmed, so `'A|x=1, y=2'` produces the key `" y"`.
- Values can't contain commas or `=`.
- Args are merged into `$params` when the pipeline runs, so later pipelines and the controller see them too — and they overwrite a route parameter with the same name.

## Passing Data to the Controller

Because `$params` is shared by reference, a pipeline can load something once and hand it to the controller by name:

```php
// Pipeline
public function handle(callable $next, array &$params): ?string
{
    $params['user'] = (new UsersModel())->find((int) Session::get('user_id'));
    return $next();
}

// Controller — $user is filled from $params by name
public function dashboard($user): string { /* ... */ }
```

## Dependencies

Pipelines are built through the service container, so type-hint what you need in the constructor:

```php
namespace App\Pipeline;

use Laika\Auth\AuthManager;
use Laika\Route\Contracts\PipelineInterface;
use Laika\Service\Redirect;

class Authenticate implements PipelineInterface
{
    public function __construct(private AuthManager $auth) {}

    public function handle(callable $next, array &$params): ?string
    {
        if ($this->auth->guard('web')->user() === null) {
            Redirect::to('login');   // a named route; sends Location and exits
        }

        return $next();
    }
}
```

> `Redirect::to()` takes a **route name** or an **absolute URL** — not a path. For a path, build the URL first: `Redirect::to(\Laika\Service\Url::build('login'))`.

The rules:

1. **Concrete classes need no registration.** They're auto-wired on demand, recursively.
2. **Interfaces must be bound** in a [relay provider](../07_services-and-relay/01_basic.md):

   ```php
   $this->registry->singleton(PaymentGateway::class, StripeGateway::class);
   ```

   An unbound interface throws, naming the parameter — unless the parameter is nullable or has a default, in which case you silently get `null` or the default.
3. **A `singleton()` bound under a class name** is shared by every pipeline, filter and controller in the request.
4. **Don't type-hint relays or core classes** (`Laika\Service\Config`, `Laika\Core\Http\Response`). Core services are bound under keys, not class names, so you'd get a fresh object or a proxy with no instance methods. Call relays statically, as above.

`handle()` keeps its fixed signature — the constructor is the injection point. A pipeline is instantiated only when the chain reaches it.

## Rules

- Implement `Laika\Route\Contracts\PipelineInterface` (any class with a matching `handle()` method is accepted).
- Signature: `handle(callable $next, array &$params): ?string`
- An unknown pipeline name throws `PipelineException` (status 500) when a request reaches it.

## CLI Reference

| Command | Description |
|---|---|
| `php laika pipeline:make <name>` | Create a pipeline class |
| `php laika pipeline:list` | List pipeline classes |
| `php laika pipeline:remove <name>` | Delete a pipeline class |
| `php laika pipeline:rename --old=<name> --new=<name>` | Rename a pipeline class |

## See Also

- [Filters](../04_filter/01_basic.md) — middleware after the controller
- [Security (Shield)](../10_security/01_basic.md) — the ready-made firewall pipeline
- [Authentication](../09_authentication/01_basic.md#protecting-routes)
