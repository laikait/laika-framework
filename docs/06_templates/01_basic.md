# Templates

Views are rendered through `Laika\Core\App\Template`, a thin wrapper around [Twig](https://twig.symfony.com/). Templates live in `template/`, compiled Twig cache goes to `lf-cache/`.

## Create via CLI

```bash
php laika template:make admin/dashboard
# creates template/admin/dashboard.twig
```

## Rendering a View

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

`assign()` accepts either a key/value pair or an array of pairs:

```php
$tpl->assign('title', 'Dashboard');
$tpl->assign(['title' => 'Dashboard', 'user' => $user]);
```

## Sub-directories & Custom Paths

```php
new Template();              // template/
new Template('admin');       // template/admin/
new Template('/absolute/path/to/dir'); // any existing directory
```

## HTML instead of Twig

```php
$tpl = (new Template())->html();
return $tpl->view('static-page'); // resolves template/static-page.html
```

## Custom Twig Filters

```php
$tpl->addFilter('currency', fn (float $v) => number_format($v, 2) . ' BDT');
```

{% raw %}
```twig
{{ order.total|currency }}
```
{% endraw %}

## Variables Available in Every Template

`Template` assigns these automatically, before your own `assign()` calls:

| Variable | Description |
|---|---|
| `local` | Current locale (`Laika\Service\Local::get()`) |
| `page` | `{ number, next, previous }` — current pagination state |
| `input` | Access any request input by property/method: {% raw %}`{{ input.email }}`{% endraw %} |
| `errors` | Form validation errors (`Laika\Service\Request::errors()`) |
| `visitor` | Visitor info (IP, browser, user agent, ...) |
| `context` | App-wide context data (`Laika\Service\Context::get()`) |

## Built-in Twig Filters

| Filter | Equivalent to |
|---|---|
| `\|hook('name')` | `apply_hook('name')` — run a hook filter chain, see [Hooks](../08_hooks/01_basic.md) |
| `\|decode` | `htmlspecialchars_decode` |
| `\|slug(index)` | `Url::segment(index)` |
| `\|query('key')` | `Url::query('key')` |
| `\|named('route.name', {...})` | `named('route.name', [...])` — build a named route URL |
| `\|asset` | Resolve an asset path |
| `\|context('key')` | `context_get('key')` |

{% raw %}
```twig
<a href="{{ 'users.show'|named({'id': user.id}) }}">{{ user.name }}</a>
<link rel="stylesheet" href="{{ 'assets/css/style.css'|asset }}">
```
{% endraw %}

## Registering Assets

`template/loader.php` is auto-loaded per `Template` instance and is where you enqueue CSS/JS via hooks:

```php
// template/loader.php
do_hook('enqueue_style', 'style', 'template/assets/css/style.css');
do_hook('enqueue_script', 'app', 'template/assets/js/app.js');
```

Static files referenced from templates live under `template/assets/` (`css/`, `img/`, ...).

## CLI Reference

| Command | Description |
|---|---|
| `php laika template:make <name> [--ext=twig] [--path=path]` | Create a new template file |
| `php laika template:list` | List existing template files |
