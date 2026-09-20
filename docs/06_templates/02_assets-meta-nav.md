# Assets, Meta & Navigation

Helpers for the parts of a page that aren't the view itself: stylesheets and scripts, meta tags, request-wide template data, menus and icons.

## Styles and Scripts

Queue assets anywhere — `template/loader.php`, a controller, a hook — and print them in the layout.

```php
// template/loader.php
enqueue_style('bootstrap', 'assets/css/bootstrap.min.css', '5.3.0');
enqueue_style('app', 'assets/css/app.css');
enqueue_script('app', 'assets/js/app.js', '1.0.0', defer: true);
```

{% raw %}
```twig
<head>
    {{ 'lf_header'|hook }}   {# metas + styles + the TOKEN and APP_URI JS constants #}
</head>
<body>
    ...
    {{ 'lf_footer'|hook }}   {# scripts #}
</body>
```
{% endraw %}

| Helper | Relay method (`Laika\Engine\Services\Asset`) |
|---|---|
| `enqueue_style(string $handle, string $src, string $version = '', string $media = 'all'): void` | `addStyle(...)` |
| `enqueue_script(string $handle, string $src, string $version = '', bool $defer = false): void` | `addScript(...)` |
| `print_styles(): void` / `print_scripts(): void` | `printStyles()` / `printScripts()` |
| `lf_header(): void` | Metas, styles, then `headerScripts()` |
| `lf_footer(): void` | Scripts |

- Relative sources resolve against `Url::base()`, and each tag gets `?v={version}`.
- **Leave `$version` out and it tracks the file.** An omitted version is the file's modification time, so editing the file changes its URL. Pass one explicitly and yours is used verbatim — and then it is on you to change it, because a versioned URL is served `Cache-Control: immutable` for a year. See [Caching](../01_getting-started/03_configuration.md#caching).
- A source with no file behind it falls back to `1.0.0`, and an external URL keeps whatever query it already had.
- A handle registered twice keeps its **first** registration.
- The same functions are registered as hooks, so `do_hook('enqueue_style', 'style', 'template/assets/css/style.css')` works too — that's what the generated `loader.php` uses.
- `headerScripts()` (inside `lf_header()`) prints two JS constants: `TOKEN`, a fresh CSRF token, and `APP_URI`, the base URL. CSRF tokens are single-use — see [CSRF & CORS](../10_security/02_csrf-and-cors.md#ajax-requests).

`asset(string $path): string` returns the absolute URL of a file: {% raw %}`<img src="{{ 'assets/img/logo.png'|asset }}">`{% endraw %} → `https://example.com/assets/img/logo.png?v=6a060148`.

The `?v=` is the file's modification time, which is what lets the response be cached immutably instead of revalidated on every page load. It is added only for a file that exists under the project root — a path that resolves to nothing gets no version rather than a made-up one, and a URL that already has a host is returned untouched.

## Meta Tags

```php
enqueue_meta('description', 'Invoices and payments for your team');
enqueue_meta('og:title', 'Laika Billing', 'property');
```

| Helper / method | |
|---|---|
| `enqueue_meta(string $name, string $content, string $type = 'name'): void` / `Meta::add(...)` | `<meta name="…">`, or `property="…"` for Open Graph. The same name replaces the earlier tag. |
| `print_metas(): void` / `Meta::print()` | Print them, HTML-escaped (also done by `lf_header()`) |

## Context — Data for Every Template

`Laika\Engine\Services\Context` is a request-wide key/value store. Anything set there — from a pipeline, a hook, a service — reaches every template as {% raw %}`{{ context }}`{% endraw %}.

```php
// in a pipeline
context_add('user', $user);
context_add('unread', $notifications->unreadCount());
```

{% raw %}
```twig
Hello {{ context.user.first_name }} — {{ 'unread'|context }} new messages
```
{% endraw %}

| Helper / method | |
|---|---|
| `context_add(string $key, mixed $value): void` / `Context::set(...)` | Store a value |
| `context_get(?string $key = null, mixed $default = null): mixed` / `Context::get(...)` | One value, or everything |
| `Context::has(string $key): bool` / `Context::pop(string $key): void` / `Context::clear(): void` | Manage keys |

Keys must match `\w+` and are lowercased; anything else throws `ContextException`.

## Page Title

`page_title(string $title): string` returns `"{$title} | {app name}"`. In Twig: {% raw %}`{{ 'page_title'|hook(title) }}`{% endraw %}. Hook callbacks you add to `page_title` run before the core one, so you can rewrite the title first.

## Navigation Menus

`Laika\Engine\Services\Nav` builds menus from **named routes** and marks the active item from the current URL.

```php
// e.g. in a pipeline or hook file
use Laika\Engine\Services\Nav;

Nav::add('Dashboard', 'dashboard')->icon('bi bi-speedometer');
Nav::add('Orders', 'orders.index')
    ->child('All orders', 'orders.index')->end()
    ->child('Refunds', 'orders.refunds', display: Session::get('role') === 'admin');
```

```php
// in the controller or template
echo Nav::render('navbar');
```

| `Nav` method | |
|---|---|
| `add(string $title, string $named, array $namedParams = [], bool $display = true): Item` | A top-level item |
| `configure(array $config): static` | Renderer settings (classes, markup) |
| `current(?string $url): static` | Override the URL used to detect the active item |
| `find(string $name): ?Item` / `extend(string $name, callable $callback): static` | Look up / extend a named item |
| `render(string $class = 'navbar'): string` | The menu HTML |
| `items(): array` / `flush(): static` | Raw items / start over |

An `Item` has `child()`/`end()` for nesting, `name()`, `icon()` (a CSS class) or `svg()` (inline SVG), `addClass()`, `setId()`, `attr()`, `target()`, `rel()` and `active()`. `match_url(string $named): bool` tells you whether the current URL is under a named route — handy for hand-built menus.

The full guide — active-state rules, conditional display, styling — is in the [Nav README](https://github.com/laikait/laika-engine/blob/main/src/Nav/README.MD).

## Icons

`Laika\Engine\Generator\Icon` returns inline SVG (Bootstrap Icons path data). Nothing is loaded from a CDN, and icons inherit the text colour.

```php
use Laika\Engine\Generator\Icon;

Icon::svg('trash', 20);    // <svg … width="20" height="20">…</svg>
Icon::trash(20);           // the same, through the magic method
Icon::has('rocket');       // false
Icon::names();             // every available name
```

`svg(string $name, int $size = 16): string` clamps the size to 8–128; an unknown name falls back to `info`. Available names include `arrow-left/right/up/down`, `plus`, `edit`, `trash`, `save`, `search`, `refresh`, `download`, `printer`, `check`, `cross`, `ban`, `info`, `warning`, `eye`, `user`, `staff`, `key`, `login`, `logout`, `dashboard`, `reports`, `orders`, `products`, `invoices`, `currency`, `card`, `database`, `settings`, `mail`, `calendar`, `folder`, `menu` and more.

Twig escapes filter output, so register the filter yourself and mark it raw:

```php
$tpl->addFilter('icon', [\Laika\Engine\Generator\Icon::class, 'svg']);
```

{% raw %}
```twig
<button>{{ 'trash'|icon(14)|raw }} Delete</button>
```
{% endraw %}

## See Also

- [Templates](01_basic.md)
- [Helper Functions](../15_helpers/01_basic.md) — every global helper and template hook
