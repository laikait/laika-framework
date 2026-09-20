# Responses

A controller returns a **string**. The router passes it through the filters and sends it, using the status, content type and headers held by the `Laika\Engine\Services\Response` relay. This page covers everything you can send: HTML, JSON, plain text, redirects, cookies, downloads and error statuses.

## How a Response Is Sent

1. The controller (or a pipeline that stops the chain) returns a string.
2. Filters may change it.
3. The router picks a renderer from `Response::getContentType()`:

| Content type | Rendered as |
|---|---|
| `text/html` (the default) or anything unrecognised | HTML. A hidden `_csrf` field is added to every `<form>`. |
| `application/json...` | JSON — your string is decoded and re-encoded |
| `text/plain...` | Plain text |

4. The status from `Response::getStatus()` and the headers set with `Response::setHeader()` are sent with the body.

Two consequences:

- **Return `null` or `''` and nothing is sent** — no status, no headers. Use this when you've already sent output yourself (a download, `echo`).
- **Use `Response::setStatus()`, not `http_response_code()`**, which is overwritten when the response is sent.

## HTML

The default. Return a rendered template or any string:

```php
use Laika\Engine\App\Template;

public function index(): string
{
    $tpl = new Template();
    $tpl->assign('title', 'Home');
    return $tpl->view('home');
}
```

## JSON

Either set the content type and return a JSON string:

```php
use Laika\Engine\Services\Response;

public function status(): string
{
    Response::setContentType('application/json');
    return json_encode(['status' => 'ok']);
}
```

Or let `Response::json()` set the status, content type and body, and return the body:

```php
public function store(): string
{
    $id = (new UsersModel())->insert(Request::only(['email', 'name']));

    return Response::json(['id' => $id], 201)->getBody();
}
```

```php
Response::json(mixed $data, int $status = 200, bool $pretty = false): static
```

For a consistent API envelope, the `response()` helper builds `['status' => ..., 'message' => ..., 'data' => ...]`:

```php
return Response::json(response(true, 'User created', ['id' => $id]), 201)->getBody();
// {"status":true,"message":"User created","data":{"id":"7"}}
```

> Returning an **array** from a controller is a `TypeError` — always encode it.

## Plain Text

```php
Response::setContentType('text/plain');
return "pong";
```

## Status Codes and Headers

```php
use Laika\Engine\Services\Response;

Response::setStatus(201);
Response::setHeader('X-Total-Count', '42');
Response::setHeaders(['Cache-Control' => 'no-store', 'X-Frame-Options' => 'DENY']);

Response::setStatus(404)->setHeader('X-Reason', 'missing'); // setters chain
```

The `Response` relay is a singleton, so a status or header set in a pipeline is still there when the controller's output is sent.

| Method | Does |
|---|---|
| `setStatus(int $code): static` / `getStatus(): int` | 100–599; default 200 |
| `setContentType(string $type): static` / `getContentType(): string` | Default `text/html; charset=UTF-8` |
| `setHeader(string $name, string $value): static` | One header; the name is sanitised and title-cased, control characters are stripped from the value |
| `setHeaders(array $headers): static` | Several at once |
| `getHeader(string $name): ?string` / `getHeaders(): array` / `removeHeader(string $name): static` | Read and remove |
| `json(mixed $data, int $status = 200, bool $pretty = false): static` | JSON body + content type + status |
| `html(string $html, int $status = 200): static` / `text(string $text, int $status = 200): static` | HTML / text body + status |
| `setBody(mixed $body): static` / `getBody(): mixed` | The body |
| `noContent(): static` | 204, no body |
| `send(): void` | Emit status, headers and body yourself (rarely needed) |
| `statusCodes(): array` | Every status code with its reason phrase |

## Redirects

```php
use Laika\Engine\Services\Redirect;

Redirect::to('users.show', ['id' => 7]);           // a named route
Redirect::to('https://example.org/docs', code: 301); // an absolute URL
Redirect::back();                                    // the previous page, same host only
Redirect::with('Profile saved.', true)->to('profile'); // with a flash message
```

| Method | Does |
|---|---|
| `to(string $to, array $params = [], int $code = 302): void` | Redirect to a **route name** or an **absolute URL** |
| `back(int $code = 302): void` | Redirect to the `Referer`, only if it's on this host; otherwise to `/` |
| `with(string $message, bool $status): static` | Store a one-shot flash alert in the session |

- Both `to()` and `back()` send the `Location` header and **exit** — nothing after them runs.
- Allowed codes are 301, 302 and 303; anything else throws `HttpException`.
- **`to()` does not take a path.** `Redirect::to('/login')` is treated as a route name and throws `RuntimeException` ("Named route '/login' not found."). For a path, build an absolute URL: `Redirect::to(Url::build('login'))` (with `Laika\Engine\Services\Url`).
- **Never pass user input to `to()`** (a `?next=` parameter, for instance) without checking its host — any value with a host is sent to the browser as-is, which makes an open redirect.

### Flash Messages

`Redirect::with()` and `alert_set()` store an alert in the session; `alert_get()` returns it once and removes it. Both need a [session driver](../11_sessions/01_basic.md) configured.

```php
Redirect::with('Invoice sent.', true)->to('invoices.index');
```

{% raw %}
```twig
{% set alert = 'alert_get'|hook %}
{% if alert %}
    <div class="alert {{ alert.status ? 'alert-success' : 'alert-danger' }}">{{ alert.message }}</div>
{% endif %}
```
{% endraw %}

## Error Statuses

For an HTML page, set the status and render your own view:

```php
if (!$user) {
    Response::setStatus(404);
    return (new Template())->view('errors/404');
}
```

Or throw an HTTP exception and let the error handler answer. With a JSON request (or `X-Requested-With: XMLHttpRequest`) it replies with the status and a JSON body:

```php
use Laika\Engine\Exceptions\{HttpException, NotFoundHttpException, ValidationException};

throw new NotFoundHttpException();                    // 404
throw new HttpException(403, 'You cannot edit this order.');
throw new ValidationException(Request::errors());     // 422 with {"errors": {...}}
```

See [Errors & Logging](../18_errors-and-logging/01_basic.md) for the details — including the fact that in production the HTML error page always reads "Internal Server Error".

## Cookies

```php
use Laika\Engine\Services\Cookie;

Cookie::set('theme', 'dark');                             // 7 days, httponly, SameSite=Strict
Cookie::ttl(3600)->path('/admin')->set('tab', 'users');   // options apply to this call only
Cookie::set('prefs', ['lang' => 'en']);                   // arrays are JSON-encoded

Cookie::get('prefs');                                     // ['lang' => 'en']
Cookie::pop('theme');                                     // delete
```

| Method | Does |
|---|---|
| `policy(string $policy): static` | SameSite: `strict` (default), `lax` or `none` (HTTPS only) |
| `ttl(int $ttl): static` | Lifetime in seconds, default 604800 (7 days) |
| `httponly(bool $httponly = true): static` | Hide from JavaScript (default on) |
| `path(string $path): static` | Cookie path, default `/` |
| `set(string $name, mixed $value): bool` | Write |
| `get(string $name, mixed $default = null): mixed` | Read — JSON-decoded when possible, so `"123"` comes back as `123` |
| `pop(string $name): void` | Expire. Use the same `path()` the cookie was set with. |

- `policy()`, `ttl()`, `httponly()` and `path()` configure the **next** `set()`/`pop()` only, then reset — chain them in one expression.
- `secure` is set automatically over HTTPS (proxy-aware). No `domain` is sent: cookies are host-only.
- Set cookies before any output — after output, PHP can't send headers, and the error handler turns that warning into an exception.

## File Downloads

```php
use Laika\Engine\Services\File;

public function invoice(string $id): ?string
{
    File::download(APP_PATH . "/lf-storage/invoices/{$id}.pdf", "invoice-{$id}.pdf");
    return null; // the file has been sent; send nothing else
}
```

`download()` sends `Content-Type`, a sanitised `Content-Disposition`, `Content-Length` and `nosniff`, then streams the file. It doesn't exit, so return `null`.

## URLs You Can Link To

| Call | Returns |
|---|---|
| `named('users.show', ['id' => 5])` | Absolute URL of a named route |
| `asset('assets/css/app.css')` | Absolute URL of a static file, with `?v={mtime}` for cache-busting |
| `Laika\Engine\Services\Url::build('search', ['q' => 'laika'])` | Absolute URL of any path |
| `Laika\Engine\Route\Url::url('users.show', ['id' => 5])` | Path only (`/users/5`) |

## See Also

- [Requests](03_requests.md) · [Controllers](02_controllers.md)
- [CSRF & CORS](../10_security/02_csrf-and-cors.md)
- [Errors & Logging](../18_errors-and-logging/01_basic.md)
