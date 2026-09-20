# Helper Functions

The Core module defines a set of global functions (in `helpers/functions/system.php`) that are available everywhere after boot — controllers, pipelines, hook files, jobs, commands. Many are also registered as [hooks](../08_hooks/01_basic.md) so templates can call them.

## Configuration & App

| Function | Returns |
|---|---|
| `config(string $name, ?string $key = null, mixed $default = null): mixed` | A config file or one top-level key — **no dot notation**. See [Configuration](../01_getting-started/03_configuration.md#reading-configuration). |
| `app_name(): string` | `config('app', 'name')`, default `Laika Framework` |
| `app_host(): string` | The base URL, `Url::base()` |
| `time_zones(): array` | Every timezone identifier |
| `repo_dir(string $name): string` | Absolute path of `vendor/{name}` (throws a `TypeError` if it doesn't exist) |
| `setPermission(string $path, int $mode = 0755): bool` | `chmod()`; on Windows it only toggles read-only |
| `setPermissionRecursive(string $path, int $dirMode = 0755, int $fileMode = 0644): bool` | The same, through a tree |

## URLs & Routing

| Function | Returns |
|---|---|
| `named(string $name, array $params = []): string` | Absolute URL of a named route. Accepts a query string: `named('users.index?status=active')`. Throws for an unknown name. |
| `asset(string $path): string` | Absolute URL under the base, with `?v={mtime}` when the file exists; a URL with a host is returned unchanged |
| `match_url(string $named): bool` | Whether the current URL starts with that route's URL — for highlighting menu items |
| `page_number(): int` | `?page=`, at least 1 |

## Request

| Function | Returns |
|---|---|
| `request_input(string $key, mixed $default = ''): mixed` | `Request::input()` — note the `''` default |
| `request_inputs(): array` | `Request::inputs()` |
| `request_header(string $key): ?string` | `Request::header()` |
| `request_is(string $method): bool` | `post`, `get`, `put`, `patch`, `delete` or `ajax` |

## Responses

| Function | Returns / does |
|---|---|
| `response(bool $status, int\|string $message, array $data = []): array` | `['status' => …, 'message' => …, 'data' => …]` — encode it for a JSON API |
| `alert_set(string $message, bool $status): void` | Store a one-shot flash alert in the session (`Redirect::with()` does the same) |
| `alert_get(): array` | `['message' => …, 'status' => …]` and remove it, or `[]` |

## Hooks

| Function | Does |
|---|---|
| `add_hook(string $filter, callable $callback, int $priority = 10): void` | Register a callback; lower priority runs first |
| `do_hook(string $filter, mixed ...$args): void` | Run every callback (action) |
| `apply_hook(string $filter, mixed $value = null, mixed ...$args): mixed` | Pass a value through every callback (filter) |

## Templates

| Function | Does |
|---|---|
| `page_title(string $title): string` | `"{$title} \| {app name}"` |
| `context_add(string $key, mixed $value): void` | Share a value with every template |
| `context_get(?string $key = null, mixed $default = null): mixed` | Read it back |
| `enqueue_style(string $handle, string $src, string $version = '', string $media = 'all'): void` | Queue a stylesheet (once per handle); an omitted version tracks the file |
| `enqueue_script(string $handle, string $src, string $version = '', bool $defer = false): void` | Queue a script; an omitted version tracks the file |
| `enqueue_meta(string $name, string $content, string $type = 'name'): void` | Queue a `<meta>` (`name` or `property`) |
| `print_styles()` / `print_scripts()` / `print_metas(): void` | Print the queued tags |
| `lf_header(): void` | Metas, styles and the `TOKEN`/`APP_URI` JS constants — before `</head>` |
| `lf_footer(): void` | Scripts — before `</body>` |
| `csrf_field(): void` | **Echoes** a hidden `_csrf` input |
| `local(string $property, ...$args): string` | A translated string from `lf-lang/`, through `sprintf()`; throws if missing |

See [Assets, Meta & Navigation](../06_templates/02_assets-meta-nav.md).

## Options

Site-wide settings stored in the `options` table (see [Errors & Logging → Options](../18_errors-and-logging/01_basic.md#options)):

| Function | Returns |
|---|---|
| `option(string $key, string\|int\|null $default = null): ?string` | The stored value, or `$default` when the key is missing. The whole `options` table is loaded with one query the first time any option is read |
| `option_bool(string $key): bool` | `true` only when the stored value is `true` (any case) |
| `option_int(string $key, int $default = 0): int` | The value as an integer, if numeric |
| `option_array(string $key, array $default = []): array` | The value JSON-decoded |
| `option_insert(string $key, mixed $value): bool` | Add a key; `false` if it exists |
| `option_update(string $key, mixed $value): bool` | Change a key; `false` if it doesn't exist |

> `option()` always returns a string or `null`: `option('limit', 0)` returns `'0'` for a missing key. Use `option_int()` for a number. A value changed with `option_update()` is returned by the next `option()` call in the same request. Store booleans as `true`/`false`; `option_bool()` doesn't accept `1`/`yes`/`on`.

## Cache

Thin wrappers over [`Laika\Engine\Services\Cache`](../20_cache/01_basic.md):

| Function | Returns |
|---|---|
| `cache(string $key, mixed $default = null): mixed` | The cached value, or `$default` on a miss. A stored `null` is returned as `null` |
| `cache_set(string $key, mixed $value, ?int $ttl = null): bool` | Store; `null` TTL uses `lf-config/cache.php`, `0` never expires |
| `cache_remember(string $key, ?int $ttl, callable $callback): mixed` | The cached value, or run the callback and store its result |
| `cache_has(string $key): bool` | Whether the key is present, whatever its value |
| `cache_pop(string $key): bool` | Remove a key |

## Data

| Function | Returns |
|---|---|
| `purify(array $data): array` | The array with every string trimmed, recursively |
| `convert_to_string(mixed $value): string` | `'true'`/`'false'` for booleans, `''` for null, JSON for arrays and objects |
| `slugify(string $name): string` | A lowercase ASCII slug. **Stops at the first dot** (`'v1.2 notes'` → `'v1'`). |

## Debugging

| Function | Does |
|---|---|
| `dd(mixed $data, bool $die = false): void` | `var_dump()` inside `<pre>` — **only stops when `$die` is `true`** |
| `show(mixed $data, bool $die = false): void` | `print_r()` inside `<pre>` |

With `DEBUG` on, {% raw %}`{{ dump(var) }}`{% endraw %} is available in Twig too.

## Template Hooks

These functions are also registered as hooks at priority **1000**, so templates can call them with the `hook` filter — {% raw %}`{{ 'page_title'|hook('Home') }}`{% endraw %} — and your own callbacks (priority 10) run before them:

`app_host`, `app_name`, `asset`, `local`, `csrf_field`, `alert_set`, `alert_get`, `page_title`, `page_number`, `request_header`, `request_input`, `request_inputs`, `request_is`, `context_add`, `context` (→ `context_get`), `enqueue_meta`, `enqueue_style`, `enqueue_script`, `print_metas`, `print_styles`, `print_scripts`, `lf_header`, `lf_footer`, `time_zones`.

## See Also

- [Relay List](../07_services-and-relay/02_relay-list.md) — the class-based API behind most helpers
- [Hooks](../08_hooks/01_basic.md)
