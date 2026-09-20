# Requests

`Laika\Engine\Services\Request` gives you the current request's input, files, headers and method, and validates input. It's parsed once per request.

```php
use Laika\Engine\Services\Request;

$email = Request::input('email');
$data  = Request::only(['name', 'email']);
$token = Request::header('Authorization');

if (Request::isPost() && Request::has('title')) {
    // ...
}
```

## Reading Input

| Method | Returns |
|---|---|
| `input(string $key, mixed $default = null): mixed` | One value. Looks in the JSON body, then POST, then the query string. |
| `inputs(): array` | Query string, POST and JSON body merged (later sources win) |
| `only(array $keys): array` | Just those keys; missing ones are `null` |
| `has(string $key): bool` | Whether any source has the key |
| `body(): array` | The decoded JSON body |
| `raw(): string` | The raw request body, exactly as sent |

A JSON body is decoded automatically when `Content-Type` starts with `application/json`; invalid JSON adds an error under the key `body` (see `Request::errors()`).

The global helpers do the same: `request_input('email')` (default `''`), `request_inputs()`, `request_header('Accept')`, `request_is('post')`.

### Input Is HTML-Encoded by Default

**Every string input is passed through `htmlspecialchars()` before you see it.** `Tom & Jerry` arrives as `Tom &amp; Jerry`, and `O'Neil` as `O&apos;Neil`. Values are also trimmed and stripped of NUL bytes.

That makes values safe to echo, but it means they're **stored encoded** if you save them as-is, and their length includes the entities. Options:

- Decode for display in Twig with the `decode` filter: {% raw %}`{{ post.body|decode }}`{% endraw %}.
- Read the untouched body with `Request::raw()` (and `json_decode()` it yourself).
- Swap in a request that doesn't encode, early — in a hook file, before anything reads the request:

  ```php
  // lf-hooks/request.php
  use Laika\Engine\Services\Request;
  use Laika\Engine\Sanitizer\NullSanitizer;

  Request::swap(new \Laika\Engine\Http\Request(new NullSanitizer()));
  ```

  Twig auto-escapes output, so templates stay safe; you're then responsible for escaping anywhere else you echo input.

| Sanitizer (`Laika\Engine\Sanitizer\...`) | Does |
|---|---|
| `InputSanitizer` (default) | Trim, remove NUL bytes, `htmlspecialchars()` |
| `StripTagsSanitizer` | The same, with `strip_tags()` first (keeps the tags you allow) |
| `NullSanitizer` | Nothing — values arrive exactly as sent |

## Method

| Method | Returns |
|---|---|
| `method(): string` | The uppercase method, after spoofing |
| `isGet()`, `isPost()`, `isPut()`, `isPatch()`, `isDelete(): bool` | Method checks |
| `isAjax(): bool` | `X-Requested-With: XMLHttpRequest` |

### Method Spoofing

HTML forms can only send GET and POST. Add a `_method` field to send `PUT`, `PATCH` or `DELETE`; the router then matches the spoofed method:

```html
<form method="post" action="/users/5">
    <input type="hidden" name="_method" value="DELETE">
    <button>Delete</button>
</form>
```

```php
Url::delete('/users/{id}', 'UserController@destroy');
```

## Headers

| Method | Returns |
|---|---|
| `header(string $name): ?string` | One header, case-insensitive |
| `headers(): array` | All request headers |

`header('Authorization')` also works behind Apache + PHP-FPM, which doesn't pass the header to PHP: it falls back to `REDIRECT_HTTP_AUTHORIZATION` (set by the shipped `.htaccess`), then `PHP_AUTH_USER`/`PHP_AUTH_PW` (rebuilt as a `Basic` value), then `PHP_AUTH_DIGEST`.

Reading a bearer token:

```php
$token = preg_replace('/^Bearer\s+/i', '', Request::header('Authorization') ?? '');
```

## Uploaded Files

`Request::file(?string $key = null): ?array` returns one `$_FILES` entry (or all of them). Files are never sanitized. Store them with `Upload`:

```php
use Laika\Engine\Services\{Request, Upload};

$path = Upload::init(Request::file('avatar'))->single(APP_PATH . '/uploads/avatars', 'user-42', [
    'maxsize'    => 2 * 1024 * 1024,
    'extensions' => ['jpg', 'png', 'webp'],
]);
```

See [Files & Storage → Upload](../16_files-and-storage/01_basic.md#upload).

## Validation

```php
$ok = Request::validate([
    'email'   => 'required|email|max:100',
    'title'   => 'required|string|between:3,120',
    'confirm' => 'required|match:password',
], [
    'email.required' => 'We need your email address.',
]);

if (!$ok) {
    $errors = Request::errors(); // ['email' => ['We need your email address.'], ...]
}
```

- `validate(array $rules, array $customMessages = []): bool` checks `Request::inputs()`.
- Custom messages are keyed `field.rule`.
- Errors accumulate across `validate()` calls and `addError()`/`addBulkError()`, so check `errors()` once at the end.
- **Templates receive the errors automatically** as the {% raw %}`{{ errors }}`{% endraw %} variable, and the old input as {% raw %}`{{ input }}`{% endraw %}.

A typical form round-trip:

```php
public function store(): ?string
{
    if (!Request::validate(['email' => 'required|email', 'name' => 'required|max:100'])) {
        return $this->create();            // re-render the form
    }

    (new UsersModel())->insert(Request::only(['email', 'name']));
    Redirect::with('Saved.', true)->to('users.index');
}
```

{% raw %}
```twig
<input name="email" value="{{ input.email }}">
{% for message in errors.email ?? [] %}
    <p class="error">{{ message }}</p>
{% endfor %}
```
{% endraw %}

### Validating Any Array

`Laika\Engine\Http\Validator` validates data that isn't the request — a CSV row, a JSON payload you decoded yourself:

```php
use Laika\Engine\Http\Validator;

$errors = Validator::make($row, ['name' => 'required|string|max:50']);
// [] when valid, otherwise ['name' => ['The [name] field is required.']]
```

### Rules

Rules are pipe-separated. Parameters follow a colon and are comma-separated: `between:2,10`, `in:draft,published`.

| Rule | Passes when the value… |
|---|---|
| `required` | …is not `null` and not `''` (an empty array passes) |
| `nullable` | Marker only — every rule except `required` already skips `null` and `''` |
| `bail` | Marker: stop checking this field after its first error |
| `email` / `url` | …passes `FILTER_VALIDATE_EMAIL` / `FILTER_VALIDATE_URL` |
| `numeric` | …is numeric |
| `integer` | …is an integer: `"42"` and `-7` pass, `"3.5"` and `"1e2"` don't |
| `float` | …passes `FILTER_VALIDATE_FLOAT` |
| `boolean` | …is `true`/`false`, `1`/`0`, `"true"`, `"false"`, `"yes"`, `"no"`, `"on"`, `"off"` |
| `array` / `string` | …is of that type |
| `date` / `date:FORMAT` | …parses with `strtotime()`, or matches the exact format |
| `time` / `time:FORMAT` | The same for times, e.g. `time:H:i` |
| `before:DATE` / `after:DATE` | …is strictly before / after the date |
| `min:N` / `max:N` | …is at least / at most `N`. Numbers **and numeric strings** compare by value, other strings by character count, arrays by item count. |
| `between:A,B` | …is inside the inclusive range, measured like `min` |
| `size:N` | …equals `N`, measured like `min` |
| `match:FIELD` | …is identical to another field |
| `in:A,B,…` / `not_in:A,B,…` | …is (or isn't) one of the values. Case-sensitive. |
| `alpha` / `alpha_num` / `alpha_dash` | …is Unicode letters / plus digits / plus `-` and `_` |
| `upper` / `lower` | …is only upper- / lower-case letters |
| `regex:PATTERN` | …matches the pattern |
| `ip` / `ipv4` / `ipv6` | …is a valid address |
| `uid` | …is an 8-4-4-4-12 hex UUID |
| `json` | …decodes as JSON |
| `callback:NAME[,ARGS…]` | …makes `NAME($value, $data, $args)` return `true`; a returned string becomes the error message |

An unknown rule name throws `InvalidArgumentException`.

> **Numeric strings compare by value.** Form values are strings, but a numeric one is treated as a number: `'age' => 'required|integer|between:18,120'` accepts `"25"`. The flip side is that an all-digit value is never length-checked — `size:5` compares the ZIP code `"01234"` with 5 and fails, and `min:8` accepts the PIN `"1234"`. Check the length of such values with a pattern: `'zip' => 'required|regex:/^[0-9]{5}$/'`.
>
> Because the rule string is split on `|` first, a `regex:` pattern can't contain `|` — use `callback` for alternations.
>
> In laika-core 5.1.1 and earlier, a numeric string was checked by value *and then* by length, so `between:18,120` rejected `"25"`.

### Throwing Validation Errors

In an API, throw `ValidationException` and let the error handler answer with 422 and the errors as JSON:

```php
use Laika\Engine\Exceptions\ValidationException;

if (!Request::validate($rules)) {
    throw new ValidationException(Request::errors());
}
```

See [Errors & Logging](../18_errors-and-logging/01_basic.md#throwing-http-errors) for when the handler replies with JSON.

## CSRF

Every HTML response automatically gets a hidden `_csrf` field in each `<form>`. Checking it is up to you — see [CSRF & CORS](../10_security/02_csrf-and-cors.md#csrf).

## Client Information

`Laika\Engine\Services\Visitor` describes who is making the request:

```php
use Laika\Engine\Services\Visitor;

Visitor::ip();          // proxy-aware, honours trusted_proxies
Visitor::browser();     // "Chrome 124.0.0.0"
Visitor::os();          // "Windows 10"
Visitor::deviceType();  // Bot | Tablet | Mobile | Desktop
Visitor::isBot();
Visitor::info();        // all of the above as an array
```

`ip()` only believes `X-Forwarded-For` when the request comes from a proxy listed in `trusted_proxies` ([Configuration](../01_getting-started/03_configuration.md#lf-configappphp)).

## The Current URL

`Laika\Engine\Services\Url` (not the router's `Laika\Engine\Route\Url`) inspects and builds URLs:

| Method | Returns |
|---|---|
| `current(): string` | The full current URL |
| `base(): string` | `scheme://host[:port]/sub-dir/`, with a trailing slash |
| `path(): string` | The request path relative to the base |
| `segment(int $index): ?string` / `segments(): array` | Path segments, **counted from 1** |
| `query(string $key, ?string $default = null): ?string` / `queries(): array` | Query-string values |
| `build(string $path, array $params = []): string` | An absolute URL under the base |
| `withQuery(array $params)` / `withoutQuery(array $keys): string` | The current URL with query values added / removed |
| `incrementQuery(?string $key = null)` / `decrementQuery(?string $key = null): string` | Next / previous page links (`?page`) |
| `host(): string` / `scheme(): string` / `isHttps(): bool` / `port(): int` | Connection details, proxy-aware |

`Laika\Engine\Services\Page` wraps the `?page=` value: `Page::number()`, `Page::next()`, `Page::previous()`.

## API Reference

| `Laika\Engine\Services\Request` method | |
|---|---|
| `input(string $key, mixed $default = null): mixed` | One value |
| `inputs(): array` | All input |
| `only(array $keys): array` | Selected keys |
| `has(string $key): bool` | Key exists |
| `body(): array` | Decoded JSON body |
| `raw(): string` | Raw body |
| `file(?string $key = null): ?array` | Uploaded file(s) |
| `header(string $name): ?string` / `headers(): array` | Headers |
| `method(): string` | HTTP method |
| `isGet()` / `isPost()` / `isPut()` / `isPatch()` / `isDelete()` / `isAjax(): bool` | Method checks |
| `validate(array $rules, array $customMessages = []): bool` | Validate input |
| `addError(string $key, string $error): void` / `addBulkError(array $errors): void` | Add errors |
| `errors(): array` | Collected errors |

## See Also

- [Responses](04_responses.md) — sending JSON, redirects, cookies, downloads
- [Templates](../06_templates/01_basic.md) — `input` and `errors` in views
- [Helper Functions](../15_helpers/01_basic.md)
