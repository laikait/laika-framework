# Templates

Views are rendered through `Laika\Engine\App\Template`, a thin wrapper around [Twig 3](https://twig.symfony.com/). Templates live in `template/`; compiled Twig goes to `lf-storage/cache/template/`.

```php
use Laika\Engine\App\Template;

$tpl = new Template();
$tpl->assign('title', 'Orders');
$tpl->assign(['orders' => $orders, 'total' => $total]);

return $tpl->view('admin/orders'); // template/admin/orders.twig
```

`php laika app:sync` wipes `lf-storage/cache/`, so it doubles as "clear the template cache". With `DEBUG` on, Twig recompiles changed templates on its own.

## Create via CLI

```bash
php laika template:make dashboard                # template/dashboard.twig
php laika template:make dashboard --path=admin   # template/admin/dashboard.twig
php laika template:make about --ext=html         # template/about.html
```

The name must be letters and underscores only — put the directory in `--path`, not in the name.

## Rendering a View

```php
namespace App\Controller;

use Laika\Engine\App\Template;

class HomeController
{
    public function index(): string
    {
        $tpl = new Template();

        $tpl->assign('title', 'Home');
        $tpl->assign('welcome', 'Welcome to Laika!');

        return $tpl->view('home'); // resolves template/home.twig
    }
}
```

| Method | Does |
|---|---|
| `view(string $name): string` | Render a view and return the HTML |
| `assign(string\|array $key, mixed $value = null): void` | One variable, or several from an array |
| `addPath(string $path): static` | A fallback directory for `extends`/`include`, searched after the view's own |
| `addFilter(string $name, string\|callable $callable): void` | Register a Twig filter |
| `engine(): Environment` | The underlying `Twig\Environment` |
| `vars(): array` | The variables the next render will receive |
| `html(): static` / `twig(): static` | Render `.html` files instead of `.twig`, or switch back |

## Sub-directories

The directory is part of the **view name**. `Template` takes no constructor arguments:

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

Both directories are created if they don't exist. One instance can render views from different sub-directories in turn.

A view name is always slash-separated — it's a Twig name, not a file path. Backslashes are normalised and a leading or trailing slash is ignored. A name may not contain `..` and may not be absolute (including a Windows drive prefix such as `C:/`) — both throw `PathException`.

### Sharing Layouts Between Sub-directories

The loader is pointed at the view's own directory and nothing else, so a template in `admin/bootstrap/` can't `extends` or `include` a template at the root. Register a fallback directory for shared layouts and partials:

```php
$tpl = new Template();
$tpl->addPath(TEMPLATE_PATH . DS . 'layouts');
$tpl->view('admin/bootstrap/home'); // may now {% raw %}{% extends 'base.twig' %}{% endraw %} from template/layouts/
```

A path added with `addPath()` survives the per-render re-point; one added directly through `engine()->getLoader()->addPath()` does not.

> **Upgrading:** `Template` used to take the sub-directory as a constructor argument. The argument is now silently ignored — `new Template('admin')` renders from `template/`, not `template/admin/`. Move the directory onto the view name.

## HTML Instead of Twig

```php
$tpl = (new Template())->html();
return $tpl->view('static-page'); // template/static-page.html

$tpl->twig();                     // back to .twig
```

The older `extension()` setter is deprecated. It raises `E_USER_DEPRECATED`, which Laika's error handler turns into an exception — so calling it ends the request.

## Variables Available in Every Template

`Template` assigns these automatically. Your own `assign()` calls override them by name.

| Variable | Contents |
|---|---|
| `local` | The selected language (`Laika\Engine\Services\Local::get()`) |
| `page` | `{ number, next, previous }` — pagination for `?page=` |
| `input` | Request input: {% raw %}`{{ input.email }}`{% endraw %}; {% raw %}`{{ input.tags(0) }}`{% endraw %} for an array item. A missing key reads as `''`. |
| `errors` | Validation errors from `Request::validate()` |
| `visitor` | `Visitor::info()` — `ip`, `os`, `browser`, `device`, `language`, `agent`, `isBot` |
| `context` | Everything set with `context_add()` / `Context::set()` |

They're computed when `view()` runs, so it doesn't matter whether you validate or add context before or after `new Template()`:

```php
$tpl = new Template();
Request::validate($rules);       // errors land in the `errors` variable
context_add('user', $user);      // lands in `context.user`
return $tpl->view('profile');
```

{% raw %}
```twig
<input name="email" value="{{ input.email }}">
{% for message in errors.email ?? [] %}<p class="error">{{ message }}</p>{% endfor %}
```
{% endraw %}

## Built-in Twig Filters

Most of these take the **name** (hook name, query key, route name) as the piped value:

| Usage | Equivalent to |
|---|---|
| {% raw %}`{{ 'page_title'\|hook('Home') }}`{% endraw %} | `apply_hook('page_title', 'Home')` — see [Hooks](../08_hooks/01_basic.md#hooks-in-twig) |
| {% raw %}`{{ 'users.show'\|named({'id': user.id}) }}`{% endraw %} | `named('users.show', [...])` — absolute URL of a named route |
| {% raw %}`{{ 'assets/css/app.css'\|asset }}`{% endraw %} | `asset(...)` — absolute URL of a static file |
| {% raw %}`{{ 'search'\|query }}`{% endraw %} | `Url::query('search')` |
| {% raw %}`{{ 2\|slug }}`{% endraw %} | `Url::segment(2)` — URL segment, counted from 1 |
| {% raw %}`{{ 'user'\|context }}`{% endraw %} | `context_get('user')` |
| {% raw %}`{{ body\|decode }}`{% endraw %} | `htmlspecialchars_decode($body)` — undo input encoding for display |

{% raw %}`{{ x|query('key') }}`{% endraw %} doesn't work: the key goes on the left.

## A Complete Layout

{% raw %}
```twig
<!doctype html>
<html lang="{{ local }}">
<head>
    <meta charset="utf-8">
    <title>{{ 'page_title'|hook(title) }}</title>
    {{ 'lf_header'|hook }}        {# queued metas, styles, TOKEN/APP_URI JS constants #}
</head>
<body>
    {% set alert = 'alert_get'|hook %}
    {% if alert %}<div class="alert">{{ alert.message }}</div>{% endif %}

    <form method="post" action="{{ 'contact.send'|named }}">
        <input name="email" value="{{ input.email }}">
        <button>Send</button>
    </form>

    <a href="{{ page.next }}">Next page</a>
    {{ 'lf_footer'|hook }}        {# queued scripts #}
</body>
</html>
```
{% endraw %}

The router adds a hidden `_csrf` input to every `<form>` in an HTML response, so you don't need {% raw %}`{{ 'csrf_field'|hook }}`{% endraw %} unless you render forms some other way. See [CSRF & CORS](../10_security/02_csrf-and-cors.md#csrf).

Styles, scripts, meta tags, menus and icons are covered in [Assets, Meta & Navigation](02_assets-meta-nav.md).

## Custom Twig Filters and Functions

```php
$tpl->addFilter('currency', fn (float $v) => number_format($v, 2) . ' BDT');
```

{% raw %}
```twig
{{ order.total|currency }}
```
{% endraw %}

For anything else — functions, globals, tests, extensions — `engine()` returns the `Twig\Environment`:

```php
$tpl->engine()->addFunction(new \Twig\TwigFunction('app_name', 'app_name'));
$tpl->engine()->addGlobal('support_email', config('app', 'support_email'));
```

With `DEBUG` on, `Twig\Extension\DebugExtension` is registered, so {% raw %}`{{ dump(user) }}`{% endraw %} works.

To use the same filters in every view, create the template in one place — a small base controller method or a [relay](../07_services-and-relay/01_basic.md) — instead of repeating `addFilter()`.

## Caching Fragments

{% raw %}`{% cache key [ttl] %}...{% endcache %}`{% endraw %} stores the rendered HTML, so the body — including any query or function call inside it — is skipped on a hit:

{% raw %}
```twig
{% cache 'product-' ~ product.id 600 %}
    {{ product.description }}
{% endcache %}
```
{% endraw %}

Put every variable the fragment depends on into the key. See [Caching → Template Fragments](../20_cache/01_basic.md#template-fragments).

## `template/loader.php`

`template/loader.php` is loaded for **every** `Template` instance (it's created if missing), and is the place to enqueue styles and scripts:

```php
// template/loader.php
do_hook('enqueue_style', 'style', 'template/assets/css/style.css');
do_hook('enqueue_script', 'app', 'template/assets/js/app.js');
```

A sub-directory may add its own `template/<sub>/loader.php`, which loads *after* the root one when a view from that directory is rendered. Loader files use `require_once`, so each runs once per process.

Static files referenced from templates live under `template/assets/`. A sub-directory template may keep its own — `template/admin/assets/css/admin.css` is served just as well. Which files are servable is decided by extension in [`lf-config/assets.php`](../01_getting-started/03_configuration.md#lf-configassetsphp); `.twig` sources are never served.

## CLI Reference

| Command | Description |
|---|---|
| `php laika template:make <name> [--ext=twig\|html] [--path=<sub/dir>]` | Create a template file |
| `php laika template:list` | List template files |

## See Also

- [Assets, Meta & Navigation](02_assets-meta-nav.md)
- [Helper Functions](../15_helpers/01_basic.md)
- [Requests](../02_routing/03_requests.md) — where `input` and `errors` come from
