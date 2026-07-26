# Laika Framework — Routing Setup

Routes live in `lf-routes/*.php`, auto-loaded via `Url` (loads from `APP_PATH/lf-routes`).

## Basic Route

Used by `Laika\Route\Url`

```php
Url::get('/', 'HomeController@index');

Url::get('/', 'App\Controller\HomeController@index');

Url::get('/', function () {
    return 'Home';
});

Url::post('/users', [UserController::class, 'store']);
```

## Placeholders

```php
Url::get('/users/{id}', [UserController::class, 'show']);
Url::get('/users/{id:[0-9]+}', [UserController::class, 'show']); // typed pattern
```

## Groups

```php
// Url::group($prefix, $callable);

Url::group('admin', function () {
    Url::get('/dashboard', [AdminController::class, 'index']);
});
```

- Groups nest via internal `$groupStack` (prefixes accumulate).

## Pipeline / Filter

```php
Url::get('/profile', [ProfileController::class, 'show'])
    ->pipeline(Authenticate::class)
    ->filter(LogResponse::class);
```

- Dispatcher merges route + group pipelines/filters and runs them through `Invoke`.

## Controllers

```php
class UserController
{
    public function show($id)
    {
        return "User {$id}";
    }
}
```

- Method args are resolved via `Reflection` (named-arg/dependency injection support).

Controller with twig template engine

```php
namespace App\Controller;

use Laika\Core\App\Template;

class HomeController
{
    public function index()
    {
        $tpl = new Template();

        // Assign Data
        $tpl->assign('title', 'Home');
        // Assign Data
        $tpl->assign('welcome', 'Welcome to Laika PHP MVC Framework!');

        $tpl->assign('provider', ['docurl' => 'https://laikait.com/docs']);

        // load View File
        return $tpl->view('home'); // This will resolve template/home.twig
    }
}
```

## Fallback / 404

- Unmatched routes fall back via `uksort` on key length, then `_404::show()` returns a minimal 404 page.

## Route Matching Order

- Static/more-specific patterns are checked before generic ones (longest key first).

## Assign Routes From Repository
Call by file autoloader:

Used by `Laika\Service\Resource`

```php
Resource::register('routes', __DIR__ . '/routes_absolute_dir') // It will load all (*.php) as routes file
```

## Notes

- `Url` is a static facade over `Dispatcher`/`Handler`.
- Route files are plain PHP — just call `Url::...` at the top level of the file.