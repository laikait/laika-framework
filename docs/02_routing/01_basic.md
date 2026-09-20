# Routing

Routes live in `lf-routes/*.php` — plain PHP files that call `Laika\Engine\Route\Url` at the top level. Every file in `lf-routes/` (subdirectories included) is loaded when a request is dispatched, so adding a file is all it takes.

```php
// lf-routes/web.php
use Laika\Engine\Route\Url;

Url::get('/', 'HomeController@index')->name('home');
Url::get('/users/{id}', 'UserController@show')->name('users.show');
Url::post('/users', 'UserController@store');
```

> **Two classes called `Url`.** Route files use **`Laika\Engine\Route\Url`** (the router). `Laika\Engine\Services\Url` is a different class — a relay for building and inspecting URLs (`Url::base()`, `Url::segment()`). If a route call fails with "undefined method", check the `use` line.

## HTTP Methods

```php
Url::get('/users', 'UserController@index');
Url::post('/users', 'UserController@store');
Url::put('/users/{id}', 'UserController@update');
Url::patch('/users/{id}', 'UserController@patch');
Url::delete('/users/{id}', 'UserController@destroy');
Url::options('/users', 'UserController@options');
```

Every method has the same signature:

```php
Url::get(string $uri, mixed $controller, string|array $pipelines = []): Url
```

There is no `any()`, `match()`, `head()` or `redirect()`. HTML forms can send `PUT`, `PATCH` and `DELETE` with a hidden `_method` field — see [Requests](03_requests.md#method-spoofing).

Registering the same method and URI twice silently replaces the first handler. Trailing slashes don't matter: `/users/` and `/users` are the same route.

## Handlers

| Form | Resolves to |
|---|---|
| `'UserController@show'` | `App\Controller\UserController::show()` |
| `'Admin\UserController@show'` | `App\Controller\Admin\UserController::show()` |
| `'\Acme\Blog\PostController@show'` | Leading `\` = fully qualified, no prefix added |
| `'InvokableController'` | `App\Controller\InvokableController::__invoke()` |
| `[UserController::class, 'show']` | Needs a `use` import in the route file |
| `function () { ... }` | A closure |

> **A string handler always gets `App\Controller\` prepended unless it starts with `\`.** `'App\Controller\HomeController@index'` becomes `App\Controller\App\Controller\HomeController` and fails. Write `'HomeController@index'` or `'\App\Controller\HomeController@index'`.

What a handler may return, and how the response is sent, is covered in [Responses](04_responses.md). Short version: return a **string** (or `null`). Arrays and objects are a `TypeError`.

## Route Parameters

```php
Url::get('/users/{id}', 'UserController@show');
Url::get('/users/{id:[0-9]+}', 'UserController@show');   // regex constraint
Url::get('/posts/{year:[0-9]{4}}', ...);                  // ✗ see below
Url::get('/files/{path:.+}', 'FileController@show');      // catch-all, slashes included
```

- `{name}` matches one path segment (`[^/]+`). `{name:regex}` uses your regex instead.
- Parameter names match `[a-zA-Z_][a-zA-Z0-9_]*`.
- **A constraint can't contain `}`**, so quantifiers like `{4}` break the route. Repeat the class instead: `{year:[0-9][0-9][0-9][0-9]}`.
- **There are no optional parameters.** Register two routes.
- Values reach the controller as **strings**. Type controller parameters as `string` (or leave them untyped) and cast — see [Controllers](02_controllers.md#route-parameters).

Routes are tested in **registration order** — the first pattern that matches wins. Register the more specific route first:

```php
Url::get('/users/new', 'UserController@create'); // must come first
Url::get('/users/{id}', 'UserController@show');  // {id} would swallow "new"
```

### Non-ASCII Routes

Route URIs may contain any UTF-8 character:

```php
Url::get('/বাংলা', 'PageController@show')->name('bangla');
Url::get('/blog/{slug}', 'PostController@show'); // {slug} matches UTF-8 too
```

The request path is percent-decoded one segment at a time before matching, so the encoded form a browser sends (`/%E0%A6%AC...`) and the raw form in the route file are the same route. Constraints are matched with the `u` flag, so `{slug:\w+}` counts characters, not bytes.

Two request forms are always refused with a 404: an encoded separator (`%2F`, `%5C`) or NUL byte inside a segment, and a path that isn't valid UTF-8.

### URLs With a File Extension

A path with a file extension is served from disk **only when a real, servable file is there**. Nothing else is special about it: `/sitemap.xml`, `/feed.json` and `/users/john.doe` are ordinary routes as long as no file of that name exists.

```php
Url::get('/sitemap.xml', 'FeedController@sitemap');   // works
Url::get('/users/{name}', 'UserController@show');     // matches /users/john.doe
```

A file that *does* exist always wins over a route for the same path, and it is judged by [`lf-config/assets.php`](../01_getting-started/03_configuration.md#lf-configassetsphp) exactly as before — a file that config refuses is refused here too, never handed to a controller. So a route can never be used to reach `template/home.twig` or `lf-config/app.php`.

## Named Routes

```php
Url::get('/users/{id}', 'UserController@show')->name('users.show');
```

| Call | Returns |
|---|---|
| `Url::url('users.show', ['id' => 5])` | `/users/5` — path only |
| `named('users.show', ['id' => 5])` | `https://example.com/users/5` — absolute, includes the sub-directory |
| {% raw %}`{{ 'users.show'\|named({'id': 5}) }}`{% endraw %} | The same, in Twig |

Names are unique across the whole app — registering one twice throws `RuntimeException`. Values are URL-encoded. A placeholder you don't pass is left in the URL as-is (`/users/{id}`), and extra params are ignored rather than added as a query string. `named()` accepts a query string on the name: `named('users.index?status=active')`.

Prefer `named()` for links: `Url::url()` doesn't add the base path, so it breaks when the app is installed in a sub-directory.

## Groups

`Url::group()` prefixes every route registered inside the callback. Groups nest, and prefixes stack:

```php
Url::group('admin', function () {
    Url::get('/dashboard', 'Admin\DashboardController@index');       // /admin/dashboard

    Url::group('billing', function () {
        Url::get('/invoices', 'Admin\Billing\InvoiceController@index'); // /admin/billing/invoices
    });
});
```

### Middleware on Groups

There are two ways to attach pipelines and filters to a group, and they behave differently.

**`Handler::registerGroup()` — recommended.** Pipelines and filters are passed up front, apply to every route registered inside, and are inherited by nested groups:

```php
use Laika\Engine\Route\Handler;

Handler::registerGroup('admin', function () {
    Url::get('/dashboard', 'Admin\DashboardController@index');

    Handler::registerGroup('billing', function () {
        Url::get('/invoices', 'Admin\Billing\InvoiceController@index');
    }, ['Permission|perm=billing.view']);

}, ['Authenticate'], ['LogAccess']);
// /admin/billing/invoices runs: Authenticate → Permission → controller → LogAccess
```

```php
Handler::registerGroup(string $prefix, callable $callback, string|array $pipelines = [], string|array $filters = []): void
```

**Chaining on `Url::group()`.** `Url::group(...)->pipeline([...])` / `->filter([...])` runs *after* the callback. It finds every route registered so far whose URI is `/prefix` or starts with `/prefix/`, and appends the middleware to them.

```php
Url::group('api', function () {
    Url::post('/payments', 'PaymentController@store');
})->pipeline(['ApiKey'])->filter(['LogApi']);
```

That works for a single, top-level group, with two caveats:

- **On a nested group it does nothing.** The chained call only knows its own prefix (`billing`), not the full `/admin/billing`, so it matches no routes. Use `Handler::registerGroup()` for nested groups.
- It matches by URI prefix, not by group — so it also catches matching routes registered outside the group, but not routes registered after it.

## Pipelines & Filters on a Route

```php
Url::get('/profile', 'ProfileController@show')
    ->pipeline(Authenticate::class)
    ->filter(LogResponse::class);

// Multiple, with inline args ("Class|key=value,key2=value2")
Url::get('/admin', 'AdminController@index')->pipeline(['Authenticate', 'Role|role=admin']);

// Or as the third argument
Url::get('/admin', 'AdminController@index', ['Authenticate']);
```

Globally, for every matched route:

```php
Url::globalPipeline([\Laika\Engine\Shield\Pipeline\ShieldPipeline::class]);
Url::globalFilter(['LogResponse']);
```

Call these in a route file or a hook file. Short names resolve to `App\Pipeline\` and `App\Filter\`. A pipeline that doesn't exist throws when a request reaches it.

**Order of execution:** global → group (`registerGroup`) → route third argument → `->pipeline()` → group-chained `->pipeline()`. Filters follow the same order. See [Pipelines](../03_pipeline/01_basic.md) and [Filters](../04_filter/01_basic.md).

## Fallback / 404

```php
// For everything under /admin
Url::fallback('admin', fn () => '<h1>Admin page not found</h1>');

// Default, for everything else
Url::fallback(null, function () {
    \Laika\Engine\Services\Response::setStatus(404);
    return (new \Laika\Engine\App\Template())->view('errors/404');
});
```

```php
Url::fallback(?string $group, callable $callback, string|array $pipelines = []): void
```

- The longest matching prefix wins. With no route and no fallback, the built-in 404 page is shown with status 404.
- The prefix is absolute — `Url::fallback()` inside a `Url::group()` does **not** inherit the group's prefix.
- The callback receives no arguments and must return a string. The output is always sent as HTML.
- **The status stays 200** unless you call `Response::setStatus(404)`.
- Global pipelines and filters don't run for fallbacks; pass pipelines as the third argument if you need them.

## Loading Routes From Elsewhere

To load route files from outside `lf-routes/` — a package or module — register the directory as a `routes` resource:

```php
// in a hook file
use Laika\Engine\Services\Resource;

Resource::register('routes', APP_PATH . '/modules/blog/routes');
```

Packages can declare it in `composer.json` instead. See [Resources](../14_resources/01_basic.md).

## Dispatch

`public/index.php` calls `Url::dispatch()` once, after the framework boots. The full sequence — CORS headers, static files, route loading, matching, pipelines, controller, filters, rendering — is described in [Request Lifecycle](../01_getting-started/05_request-lifecycle.md#2-dispatch).

## API Reference

### `Laika\Engine\Route\Url`

| Method | |
|---|---|
| `static get/post/put/patch/delete/options(string $uri, mixed $controller, string\|array $pipelines = []): self` | Register a route |
| `name(string $name): self` | Name the last route |
| `pipeline(string\|array $pipelines): self` | Append pipelines to the last route (or group, see above) |
| `filter(string\|array $filters): self` | Append filters to the last route (or group) |
| `static group(string $prefix, callable $callback): self` | Prefix routes registered inside |
| `static globalPipeline(string\|array $pipelines): void` | Pipelines for every route |
| `static globalFilter(string\|array $filters): void` | Filters for every route |
| `static fallback(?string $group, callable $callback, string\|array $pipelines = []): void` | Handler for unmatched URLs |
| `static url(string $name, array $params = []): string` | Path of a named route |
| `static dispatch(): void` | Handle the current request |

### Other Classes

| Class | Purpose |
|---|---|
| `Handler` | Route registry. `registerGroup()`, `getRoutes()`, `getNamedRoutes()`, `getGlobalPipelines()`, ... |
| `Dispatcher` | Request lifecycle: headers, static files, matching, fallback, rendering |
| `Invoke` | Runs pipeline/filter chains and resolves controllers |
| `Reflection` | Fills controller arguments by name and from the container |
| `Path` | Path normalisation, pattern compiling, matching |
| `_404` | The built-in 404 page |
| `Contracts\PipelineInterface` | `handle(callable $next, array &$params): ?string` |
| `Contracts\FilterInterface` | `terminate(callable $next, ?string $response, array &$params): ?string` |
| `Exceptions\ControllerException`, `PipelineException`, `FilterException` | Thrown with status 500 for an unresolvable controller/pipeline/filter |

## See Also

- [Controllers](02_controllers.md) · [Requests](03_requests.md) · [Responses](04_responses.md)
- [Pipelines](../03_pipeline/01_basic.md) · [Filters](../04_filter/01_basic.md)
