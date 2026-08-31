# Routing

Routes live in `lf-routes/*.php` — plain PHP files, auto-loaded on boot by `Laika\Route\Url` (loads every file under `APP_PATH/lf-routes`). Just call `Url::...` at the top level of the file.

## HTTP Methods

```php
use Laika\Route\Url;

Url::get('/users', 'UserController@index');
Url::post('/users', 'UserController@store');
Url::put('/users/{id}', 'UserController@update');
Url::patch('/users/{id}', 'UserController@patch');
Url::delete('/users/{id}', 'UserController@destroy');
Url::options('/users', 'UserController@options');
```

A handler can be a `'Controller@method'` string, a `[Class::class, 'method']` array, or a closure:

```php
Url::get('/', 'HomeController@index');
Url::get('/', 'App\Controller\HomeController@index');
Url::get('/', [HomeController::class, 'index']);
Url::get('/', function () {
    return 'Home';
});
```

## Route Parameters

```php
Url::get('/users/{id}', 'UserController@show');
Url::get('/users/{id:[0-9]+}', 'UserController@show'); // regex-constrained
Url::get('/users/{id:\d+}', 'UserController@show');    // shorthand works too
```

Routes are tested in **registration order** — the first pattern that matches wins.
Register the more specific route before the generic one that would also match it:

```php
Url::get('/users/new', 'UserController@create'); // must come first
Url::get('/users/{id}', 'UserController@show');  // {id} would swallow "new"
```

## Non-ASCII Routes

Route URIs may contain any UTF-8 character:

```php
Url::get('/বাংলা', 'PageController@show')->name('bangla');
Url::get('/blog/{slug}', 'PostController@show'); // {slug} matches UTF-8 too
```

The request path is percent-decoded one segment at a time before matching, so
the encoded form a browser actually sends (`/%E0%A6%AC%E0%A6%BE...`) and the raw
form you write in the route file are the same route. Registered URIs are decoded
the same way, so an already-encoded URI in the route file works as well.

Parameter constraints are matched with the `u` flag, so `{slug:\w+}` and `.`
count characters rather than bytes.

Two request forms are always refused with a 404, because neither can be
expressed unambiguously as a path: an encoded separator (`%2F`, `%5C`) or a NUL
byte inside a segment, and a path that is not valid UTF-8.

## Named Routes

```php
Url::get('/users/{id}', 'UserController@show')->name('users.show');

$url = Url::url('users.show', ['id' => 5]); // "/users/5"
```

## Groups

```php
Url::group('admin', function () {
    Url::get('/dashboard', 'Admin\DashboardController@index');
});
```

Groups nest, and a nested group inherits its parent's pipelines/filters:

```php
Url::group('admin', function () {
    Url::group('billing', function () {
        Url::get('/invoices', 'Admin\Billing\InvoiceController@index');
    })->pipeline(['Permission|perm=billing.view']);
})->pipeline(['Auth']);
```

Chaining `->pipeline()`/`->filter()` on a group applies retroactively to every route already registered inside it:

```php
Url::group('api', function () {
    Url::post('/payments', 'PaymentController@store')->pipeline(['ApiKey']);
})->pipeline(['Cors'])->filter(['LogApi']);
```

## Pipelines & Filters

Attach pipeline per route:

```php
Url::get('/profile', 'ProfileController@show')
    ->pipeline(Authenticate::class)
    ->filter(LogResponse::class);

// Multiple, and with inline config args ("Class|key=value,key2=value2")
Url::get('/admin', 'AdminController@index')->pipeline(['Auth', 'Role|role=admin']);
```

Or globally, for every route in the application:

```php
Url::globalPipeline(['Csrf', 'Cors']);
Url::globalFilter(['LogResponse']);
```

See [Pipelines](../03_pipeline/01_basic.md) and [Filters](../04_filter/01_basic.md) for how to write these classes.

## Fallback / 404

```php
// Per group prefix
Url::fallback('admin', fn() => '<h1>Admin route not found</h1>');

// Default (no prefix)
Url::fallback(null, fn() => '<h1>Page not found</h1>');
```

Longest-prefix match; if nothing matches (no route, no registered fallback), the built-in `_404::show()` renders a minimal 404 page.

## Loading Routes From Elsewhere

To load route files from outside `lf-routes/` (e.g. a package or a submodule), register the directory with the resource loader:

```php
use Laika\Service\Resource;

Resource::register('routes', __DIR__ . '/routes'); // loads every *.php file as routes
```

## Dispatch

```php
Url::dispatch();
```

Called once, from `index.php`, after the framework boots. Lifecycle:

```
preDispatcher()
    → registerInitiators()   (headers + hook files)
    → match route / asset / fallback
    → run pipeline chain
    → run controller
    → run filter chain
```

## API Reference

| Class | Purpose |
|---|---|
| `Url` | Static facade — routes, groups, pipeline/filter chaining, dispatch, named URLs |
| `Handler` | Route registry — storage, group stack, fallback, naming |
| `Dispatcher` | Request lifecycle, matching, asset serving, fallback resolution |
| `Invoke` | Pipeline/filter chain execution, controller resolution |
| `Reflection` | Named-argument injection for controller methods |
| `Path` | URI normalization, pattern compiling, request matching |
| `_404` | Default 404 page |
| `PipelineInterface` | `handle(callable $next, array &$params): ?string` |
| `FilterInterface` | `terminate(callable $next, ?string $response, array &$params): ?string` |

See [Controllers](02_controllers.md) for writing the handlers routes point to.
