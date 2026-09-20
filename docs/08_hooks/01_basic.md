# Hooks

Laika has a WordPress-style hook system: named extension points that callbacks attach to. It's also how you run code on every request — every file in `lf-hooks/` is loaded at boot.

## Hook Files

Every `*.php` file under `lf-hooks/`, in any subdirectory, is loaded during bootstrap — after the container and helpers are ready, before any route file or controller runs. Name them however you like:

```
lf-hooks/
├── example.php
├── session.php     # Init::file();
├── cors.php        # CORS::origins([...]);
└── theme/
    └── titles.php  # add_hook('page_title', ...);
```

This makes `lf-hooks/` the place for **per-request setup** as well as hook callbacks: the session driver, CORS origins, the timezone, extra database connections. See [Request Lifecycle](../01_getting-started/05_request-lifecycle.md).

## Registering Callbacks

```php
// lf-hooks/example.php
add_hook('hook.name', function () {
    return 'Hook Name';
});
```

## Actions vs Filters

Both use the same registry; they differ in how they're fired.

### `do_hook()` — actions

Runs every callback with the given arguments. Return values are ignored — use this for side effects.

```php
add_hook('order.placed', function (array $order) {
    error_log("Order {$order['id']} placed");
});

do_hook('order.placed', $order);
```

### `apply_hook()` — filters

Passes a value through every callback; each one receives the current value (plus any extra arguments) and returns the next. The final value is returned.

```php
add_hook('page.title', function (?string $title) {
    return $title ? "{$title} — My App" : 'My App';
});

$title = apply_hook('page.title', 'Dashboard'); // "Dashboard — My App"
```

Extra arguments are passed to every callback after the value:

```php
add_hook('price.display', fn ($price, $currency) => "{$currency} {$price}");

apply_hook('price.display', '9.99', 'USD'); // "USD 9.99"
```

## Priority

Callbacks run in **ascending** priority order (lower first); the default is `10`. Callbacks with equal priority run in the order they were added.

```php
add_hook('page.title', fn ($t) => strtoupper($t), 5);  // runs first
add_hook('page.title', fn ($t) => "{$t}!", 20);        // runs second
```

## Built-in Hooks

The Core module registers its template helpers as hooks at **priority 1000**, so your callbacks (at 10) run before them:

`app_host`, `app_name`, `asset`, `cache`, `cache_remember`, `local`, `csrf_field`, `alert_set`, `alert_get`, `page_title`, `page_number`, `request_header`, `request_input`, `request_inputs`, `request_is`, `context_add`, `context`, `enqueue_meta`, `enqueue_style`, `enqueue_script`, `print_metas`, `print_styles`, `print_scripts`, `lf_header`, `lf_footer`, `time_zones`.

Because `apply_hook()` chains, a callback you add to one of these sees the value **before** the core helper does. For example, to add a suffix to every page title:

```php
add_hook('page_title', fn (string $title) => "{$title} · Beta");

page_title('Home');                 // "Home | Laika Framework" — the function alone
apply_hook('page_title', 'Home');   // "Home · Beta | Laika Framework" — your hook, then core's
```

## Hooks in Twig

The `hook` filter calls `apply_hook()`. The **hook name is the piped value**, and filter arguments are passed on:

{% raw %}
```twig
<title>{{ 'page_title'|hook('Dashboard') }}</title>
{{ 'lf_header'|hook }}
<p>{{ 'local'|hook('greeting', user.name) }}</p>
{{ 'my.sidebar'|hook }}
```
{% endraw %}

Hooks like `lf_header` and `csrf_field` **echo** their markup while the template renders and return nothing; others (`page_title`, `asset`) return a string that Twig prints.

A hook of your own that returns HTML is escaped by Twig — add `|raw` if you trust it: {% raw %}`{{ 'my.sidebar'|hook|raw }}`{% endraw %}.

## API Reference

| Global function | Relay method (`Laika\Engine\Services\Hook`) | Signature |
|---|---|---|
| `add_hook()` | `Hook::add()` | `add_hook(string $filter, callable $callback, int $priority = 10): void` |
| `do_hook()` | `Hook::do()` | `do_hook(string $filter, mixed ...$args): void` |
| `apply_hook()` | `Hook::apply()` | `apply_hook(string $filter, mixed $value = null, mixed ...$args): mixed` |

`Laika\Engine\Helper\Hook` holds the registry in static properties, so the functions, the relay and the class are interchangeable.

## See Also

- [Request Lifecycle](../01_getting-started/05_request-lifecycle.md) — when hook files load
- [Helper Functions](../15_helpers/01_basic.md) — what each built-in hook does
- [Templates](../06_templates/01_basic.md)
