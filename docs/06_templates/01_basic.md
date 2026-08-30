# Templates

Views are rendered through `Laika\Core\App\Template`, a thin wrapper around [Twig](https://twig.symfony.com/). Templates live in `template/`, compiled Twig cache goes to `lf-storage/cache/template/`.

`php laika app:sync` wipes `lf-storage/cache/` wholesale, so it doubles as the template cache clear.

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

## Sub-directories

The directory lives in the **view name**. `Template` takes no constructor arguments:

```php
$tpl = new Template();

$tpl->view('home');                 // template/home.twig
$tpl->view('admin/dashboard');      // template/admin/dashboard.twig
$tpl->view('admin/bootstrap/home'); // template/admin/bootstrap/home.twig
```

Everything up to the last slash is the directory, and it decides all three paths at once:

| For `view('admin/bootstrap/home')` | |
|---|---|
| Template directory | `template/admin/bootstrap/` |
| Cache directory | `lf-storage/cache/template/admin/bootstrap/` |
| Template file | `template/admin/bootstrap/home.twig` |

Both directories are created if they do not exist. One instance can render views from different sub-directories in turn — the engine is re-pointed on every `view()` call:

```php
$tpl = new Template();
$tpl->view('admin/bootstrap/home');
$tpl->view('home');                 // back to template/
```

A view name is always slash-separated — it is a Twig name, not a file path. Backslashes are normalised and a leading or trailing slash is ignored, so `'admin/reports/index'` and `'/admin/reports/index'` are the same view on every platform. A name may not contain `..` and may not be absolute (including a Windows drive prefix such as `C:/`) — both throw `PathException`.

### Sub-directories are isolated

The loader is pointed at the view's own directory and nothing else, so a template under `admin/bootstrap/` cannot `extends` or `include` a template at the root. To share layouts and partials, register a fallback directory that is searched after the view's own:

```php
$tpl = new Template();
$tpl->addPath(TEMPLATE_PATH . DS . 'shared');
$tpl->view('admin/bootstrap/home'); // may now extend 'layout.twig' from template/shared/
```

A path added this way survives the per-render re-point; one added directly through `engine()->getLoader()->addPath()` does not.

> **Upgrading:** `Template` used to take the sub-directory (and a cache sub-directory) as constructor arguments. `new Template('admin')` now raises `E_USER_DEPRECATED` and the argument is ignored — move it onto the view name. An absolute directory is no longer accepted; use `addPath()` instead.

## HTML instead of Twig

```php
$tpl = (new Template())->html();
return $tpl->view('static-page'); // resolves template/static-page.html

$tpl->twig();                     // back to .twig
```

Only `twig` and `html` are accepted. The older `extension()` setter is deprecated and now raises `E_USER_DEPRECATED`.

## Custom Twig Filters

```php
$tpl->addFilter('currency', fn (float $v) => number_format($v, 2) . ' BDT');
```

{% raw %}
```twig
{{ order.total|currency }}
```
{% endraw %}

## Reaching Twig Directly

`addFilter()` is the shortcut for the common case. For anything else — functions, globals, tests, extensions — `engine()` returns the underlying `Twig\Environment`:

```php
$tpl->engine()->addFunction(new \Twig\TwigFunction('csrf', 'csrf_field'));
$tpl->engine()->addGlobal('app_name', config('app.name'));
```

With `DEBUG` on, `Twig\Extension\DebugExtension` is registered automatically, so {% raw %}`{{ dump(user) }}`{% endraw %} works.

## Variables Available in Every Template

`Template` assigns these automatically. Your own `assign()` calls override them by name.

| Variable | Description |
|---|---|
| `local` | Current locale (`Laika\Service\Local::get()`) |
| `page` | `{ number, next, previous }` — current pagination state |
| `input` | Access any request input by property/method: {% raw %}`{{ input.email }}`{% endraw %} |
| `errors` | Form validation errors (`Laika\Service\Request::errors()`) |
| `visitor` | Visitor info (IP, browser, user agent, ...) |
| `context` | App-wide context data (`Laika\Service\Context::get()`) |

These resolve when `view()` runs, not when the instance is constructed, so it does not matter whether you validate or set context before or after `new Template()`:

```php
$tpl = new Template();
Request::validate($rules);       // errors land in {{ errors }}
context_add('user', $user);      // lands in {{ context.user }}
return $tpl->view('profile');
```

`input` reads any request input, including a JSON body. Scalars are cast to string; an array input needs the method form:

{% raw %}
```twig
{{ input.email }}      {# post, query or JSON body #}
{{ input.qty }}        {# JSON 5 renders as 5 #}
{{ input.tags(0) }}    {# first element of an array input #}
```
{% endraw %}

## Built-in Twig Filters

Most of these take their argument as the **piped value**, not as a filter argument — they read as functions written filter-style.

| Usage | Equivalent to |
|---|---|
| {% raw %}`{{ 'name'\|hook }}`{% endraw %} | `apply_hook('name')` — run a hook filter chain, see [Hooks](../08_hooks/01_basic.md) |
| {% raw %}`{{ value\|decode }}`{% endraw %} | `htmlspecialchars_decode($value)` |
| {% raw %}`{{ 1\|slug }}`{% endraw %} | `Url::segment(1)` — URL segment by index |
| {% raw %}`{{ 'key'\|query }}`{% endraw %} | `Url::query('key')` |
| {% raw %}`{{ 'route.name'\|named({...}) }}`{% endraw %} | `named('route.name', [...])` — build a named route URL |
| {% raw %}`{{ 'assets/css/app.css'\|asset }}`{% endraw %} | `asset(...)` — resolve an asset path |
| {% raw %}`{{ 'key'\|context }}`{% endraw %} | `context_get('key')` |

Only `decode` and `named` take the value you would expect on the left. The rest put the *name* or *index* on the left, which is why {% raw %}`{{ x|query('key') }}`{% endraw %} fails — that filter accepts one argument, and it is already the piped value.

{% raw %}
```twig
{{ 'lf_header' | hook }}
{{ 'user' | context }}
<a href="{{ 'users.show'|named({'id': user.id}) }}">{{ user.name }}</a>
<link rel="stylesheet" href="{{ 'assets/css/style.css'|asset }}">
```
{% endraw %}

## Registering Assets

`template/loader.php` is auto-loaded for **every** `Template` instance, whichever sub-directory the view comes from, and is where you enqueue CSS/JS via hooks:

```php
// template/loader.php
do_hook('enqueue_style', 'style', 'template/assets/css/style.css');
do_hook('enqueue_script', 'app', 'template/assets/js/app.js');
```

The root `loader.php` is generated on first run if it is missing. A sub-directory may add its own `template/<sub>/loader.php`, which loads *after* the root one when a view from that directory is rendered — it is never generated for you.

Static files referenced from templates live under `template/assets/` (`css/`, `img/`, ...).

## CLI Reference

| Command | Description |
|---|---|
| `php laika template:make <name> [--ext=twig] [--path=path]` | Create a new template file |
| `php laika template:list` | List existing template files |
