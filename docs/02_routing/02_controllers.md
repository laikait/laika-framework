# Controllers

Controllers live in `lf-app/Controller/`, namespace `App\Controller`. A controller is a plain class — there's no base class to extend.

```bash
php laika controller:make UserController --method=index
```

## Basic Controller

```php
namespace App\Controller;

use App\Model\UsersModel;
use Laika\Core\App\Template;

class UserController
{
    public function show(string $id): string
    {
        $user = (new UsersModel())->find($id);

        $tpl = new Template();
        $tpl->assign('user', $user);
        return $tpl->view('users/show'); // template/users/show.twig
    }
}
```

```php
// lf-routes/web.php
Url::get('/users/{id}', 'UserController@show')->name('users.show');
```

## Return Values

**A controller method must return a string or `null`.** Whatever it returns is passed through the filters and then sent.

| Return | Result |
|---|---|
| `string` | Sent, rendered according to the response content type (HTML by default) |
| `null` / `''` | Nothing is sent — not even headers or a status set on `Response` |
| array, int, object | `TypeError` |

Returning JSON, setting a status, redirecting and downloading files are all covered in [Responses](04_responses.md):

```php
use Laika\Service\Response;

public function status(): string
{
    Response::setContentType('application/json');
    return json_encode(['status' => 'ok']);
}
```

> A bare `json_encode()` without `setContentType()` is sent as `text/html`. And a bare `http_response_code(404)` is overwritten when the response is sent — use `Response::setStatus(404)`.

## Route Parameters

Parameters are passed **by name**, so the order of your method's arguments doesn't matter:

```php
// Url::get('/posts/{year}/{slug}', 'PostController@show');
public function show(string $slug, string $year): string { /* ... */ }
```

Route values are always strings, and the router runs under `strict_types`, so **`int $id` throws a `TypeError`**. Type them `string` (or leave them untyped) and cast:

```php
public function show(string $id): string
{
    $user = (new UsersModel())->find((int) $id);
    // ...
}
```

Pipeline args (`'Role|role=admin'`) and anything a pipeline writes into `$params` are passed the same way, and override a route parameter of the same name. Filter args never reach the controller.

## Dependency Injection

Controllers are built through the service container, so both the **constructor** and **method** parameters can ask for services by type:

```php
namespace App\Controller;

use Laika\Auth\AuthManager;
use App\Service\Billing;

class InvoiceController
{
    public function __construct(private AuthManager $auth) {}

    public function show(string $id, Billing $billing): string
    {
        $user = $this->auth->guard('web')->user();
        // ...
    }
}
```

How each method parameter is filled, in order:

1. A route parameter (or pipeline value) with the **same name**.
2. A class or interface **type** — resolved from the container. Concrete classes are built automatically; interfaces must be [bound in a relay provider](../07_services-and-relay/01_basic.md).
3. The parameter's **default value**.
4. `null`, if the parameter is nullable.
5. Otherwise a `RuntimeException` ("Missing required parameter").

> **Don't type-hint relays or core classes.** The framework's own services are bound under keys (`'response'`, `'request'`, ...), not class names. Type-hinting `Laika\Core\Http\Response` builds a *new* `Response` — setting its status changes nothing. Type-hinting a relay (`Laika\Service\Request`) gives you a proxy object whose instance methods don't exist. Call relays statically instead: `Request::input('email')`, `Response::setStatus(201)`.

## Returning a View

```php
use Laika\Core\App\Template;

public function index(): string
{
    $tpl = new Template();
    $tpl->assign(['title' => 'Home', 'welcome' => 'Welcome to Laika!']);
    return $tpl->view('home'); // template/home.twig
}
```

See [Templates](../06_templates/01_basic.md).

## Reading Input

```php
use Laika\Service\{Request, Redirect};

public function store(): ?string
{
    if (!Request::validate(['email' => 'required|email', 'name' => 'required|max:100'])) {
        return $this->create(); // the form again; the template gets the `errors` variable
    }

    (new UsersModel())->insert(Request::only(['email', 'name']));

    Redirect::with('User created.', true)->to('users.index'); // exits
}
```

See [Requests](03_requests.md) for input, files, headers and validation.

## Using Models

```php
use App\Model\UsersModel;

$user = (new UsersModel())->where(['id' => $id])->firstOrFail();
```

See [Models & Database](../05_models/01_basic.md).

## Using the Session

```php
use Laika\Session\Session;

$userId = Session::get('user_id');
```

A session driver must be configured first — see [Sessions](../11_sessions/01_basic.md).

## Pairing With Middleware

Pipelines run before the controller and filters run after it — see [Pipelines](../03_pipeline/01_basic.md) and [Filters](../04_filter/01_basic.md).

## CLI Reference

| Command | Description |
|---|---|
| `php laika controller:make <name> [--method=index]` | Create a controller, or add a method to an existing one |
| `php laika controller:list` | List controller classes |
| `php laika controller:remove <name>` | Delete a controller |
| `php laika controller:rename --old=<name> --new=<name>` | Rename a controller class and its file |
