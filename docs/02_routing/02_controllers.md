# Controllers

Controllers live in `lf-app/Controller`, namespace `App\Controller`. A controller is a plain class — no base class to extend.

```bash
php laika make:controller UserController --method=index
```

## Basic Controller

```php
namespace App\Controller;

class UserController
{
    public function show($id)
    {
        return "User {$id}";
    }
}
```

Route parameters and pipeline/filter config args are resolved into method arguments via `Laika\Route\Reflection` — named-argument style injection, so parameter order in your method signature doesn't have to match the route definition.

```php
// lf-routes/web.php
Url::get('/users/{id}', 'UserController@show');

// UserController.php — $id is matched by name, not position
public function show($id) { /* ... */ }
```

## Returning a View

Use `Laika\Core\App\Template` to render a Twig template from `template/`:

```php
namespace App\Controller;

use Laika\Core\App\Template;

class HomeController
{
    public function index()
    {
        $tpl = new Template();

        $tpl->assign('title', 'Home');
        $tpl->assign('welcome', 'Welcome to Laika PHP MVC Framework!');
        $tpl->assign('provider', ['docurl' => 'https://laikait.com/docs']);

        return $tpl->view('home'); // resolves template/home.twig
    }
}
```

See [Templates](../06_templates/01_basic.md) for the full templating reference.

## Returning Raw Output

A controller can also just return a string (or nothing, if it writes output directly) — the router doesn't require a `Template` instance:

```php
public function health()
{
    return json_encode(['status' => 'ok']);
}
```

## Using Models

```php
namespace App\Controller;

use App\Model\UsersModel;

class UserController
{
    public function show($id)
    {
        $users = new UsersModel();
        $user  = $users->where(['id' => $id])->firstOrFail();

        return $user['first_name'];
    }
}
```

See [Models & Database](../05_models/01_basic.md) for the query builder.

## Using Services

Call into app-level services registered through the [service container](../07_services-and-relay/01_basic.md):

```php
namespace App\Controller;

use Laika\Service\Session;

class DashboardController
{
    public function index()
    {
        $userId = Session::get('user_id');
        // ...
    }
}
```

## Pairing With Middleware

Pipelines and filters wrap the controller call — see [Routing](01_basic.md#pipelines--filters), [Pipelines](../03_pipeline/01_basic.md), and [Filters](../04_filter/01_basic.md).

## CLI Reference

| Command | Description |
|---|---|
| `php laika make:controller <name> [--method=index]` | Create a controller class |
| `php laika list:controller` | List registered controller classes |
| `php laika remove:controller <name>` | Delete a controller class |
| `php laika rename:controller --old=<name> --new=<name>` | Rename a controller class and its file |
