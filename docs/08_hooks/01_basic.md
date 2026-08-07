# Hooks

Laika ships a WordPress-style action/filter hook system — `Laika\Core\Helper\Hook`, exposed through global helper functions and auto-loaded from `lf-hooks/*.hook.php`.

## Defining Hooks

Any `*.php` file under `lf-hooks/` is auto-loaded on boot. Register callbacks with `add_hook()`:

```php
// lf-hooks/example.hook.php

add_hook('hook.name', function () {
    return 'Hook Name';
});

add_hook('panel.template.path', function ($name) {
    return APP_PATH . "/lf-template/panel/{$name}";
});
```

## Actions vs Filters

Laika's hook system has two calling conventions, both backed by the same registry:

### `do_hook()` — fire-and-forget actions

No return value is collected; use this to run side effects (logging, enqueuing an asset, sending a notification).

```php
add_hook('enqueue_style', function (string $key, string $path) {
    // register a stylesheet
});

do_hook('enqueue_style', 'style', 'template/assets/css/style.css');
```

### `apply_hook()` — filters that transform a value

Each registered callback receives the current value and returns the next one — the final value is returned to the caller.

```php
add_hook('page.title', function (?string $title) {
    return $title ? "{$title} — My App" : 'My App';
});

$title = apply_hook('page.title', 'Dashboard'); // "Dashboard — My App"
```

This is also what powers the `|hook('name')` Twig filter — see [Templates](../06_templates/01_basic.md#built-in-twig-filters).

## Priority

Callbacks run in ascending priority order (lower runs first), default `10` — same convention for both `add_hook()` and the underlying `Hook::add()`.

```php
add_hook('page.title', fn ($t) => strtoupper($t), 5);  // runs first
add_hook('page.title', fn ($t) => "{$t}!", 20);         // runs second
```

## API Reference

| Global function | Equivalent | Signature |
|---|---|---|
| `add_hook()` | `Hook::add()` | `add_hook(string $filter, callable $callback, int $priority = 10): void` |
| `do_hook()` | `Hook::do()` | `do_hook(string $filter, mixed ...$args): void` |
| `apply_hook()` | `Hook::apply()` | `apply_hook(string $filter, mixed $value = null, mixed ...$args): mixed` |

`Laika\Core\Helper\Hook` can be used directly if you prefer the class form over the global functions — both call the same static registry.

## Extra Arguments

Both `do_hook()` and `apply_hook()` accept additional positional arguments, forwarded to every callback after the primary value:

```php
add_hook('order.placed', function (?string $result, int $orderId, float $total) {
    error_log("Order {$orderId} placed for {$total}");
    return $result;
});

apply_hook('order.placed', null, $orderId, $total);
```
