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

## Sub-directories & Custom Paths

```php
new Template();              // template/
new Template('admin');       // template/admin/
new Template('admin/reports'); // template/admin/reports/
new Template('/absolute/path/to/dir'); // any absolute path, used as-is
```

The rule is simple: **an absolute path is used as given, anything else is a sub-directory of `template/`.** Separators are normalised, so `'admin/reports'`, `'admin\reports'` and `'admin\reports\'` are the same directory on every platform. A sub-directory may not contain `..` — that throws `PathException`.

Note that a leading slash makes a path absolute, including on Windows: `new Template('/admin')` means the filesystem root, not `template/admin`. Drop the leading slash for a sub-directory.

The second argument overrides the cache directory the same way, relative to `lf-storage/cache/template/`.

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

`template/loader.php` is auto-loaded for **every** `Template` instance — including sub-directory ones — and is where you enqueue CSS/JS via hooks:

```php
// template/loader.php
do_hook('enqueue_style', 'style', 'template/assets/css/style.css');
do_hook('enqueue_script', 'app', 'template/assets/js/app.js');
```

The root `loader.php` is generated on first run if it is missing. A sub-directory may add its own `template/<sub>/loader.php`, which loads *after* the root one — it is never generated for you.

Static files referenced from templates live under `template/assets/` (`css/`, `img/`, ...).

## CLI Reference

| Command | Description |
|---|---|
| `php laika template:make <name> [--ext=twig] [--path=path]` | Create a new template file |
| `php laika template:list` | List existing template files |
