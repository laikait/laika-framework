# CSRF & CORS

## CSRF

Laika's CSRF tokens are stateless, signed with the app key, and **single-use**.

### What Happens Automatically

When a controller returns HTML, the router inserts a hidden field into every `<form>` that doesn't already have one:

```html
<input type="hidden" name="_csrf" value="…">
```

**Nothing validates the token automatically.** Checking it is up to you — normally with a global pipeline.

### Validating Tokens

```bash
php laika pipeline:make VerifyCsrf
```

```php
namespace App\Pipeline;

use Laika\Engine\Exceptions\CSRFException;
use Laika\Engine\Route\Contracts\PipelineInterface;
use Laika\Engine\Services\{CSRF, Request, Response};

class VerifyCsrf implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        if (Request::isGet() || Request::method() === 'HEAD') {
            return $next();
        }

        try {
            CSRF::validate(CSRF::fromRequest());   // X-Csrf-Token header, else the _csrf field
        } catch (CSRFException $e) {
            Response::setStatus(419);
            return 'Page expired — reload and try again.';
        }

        return $next();
    }
}
```

```php
// lf-routes/web.php
Url::globalPipeline(['VerifyCsrf']);
```

Leave API routes that authenticate with bearer tokens out of CSRF checks — for example by attaching `VerifyCsrf` to the web routes with `Handler::registerGroup()` instead of globally.

### How Tokens Work

- A token is `payload.signature`. The payload holds a random id, issue and expiry times, and (by default) a fingerprint of the client's OS and user agent. The signature is HMAC-SHA256 with the [app key](03_encryption-and-tokens.md#app-key) — no server-side storage.
- On successful validation the token id is **burned**: recorded in an `_xct` cookie that keeps the last 20 ids. Presenting the same token again fails with "CSRF Token Already Used".
- `validate()` throws `CSRFException` for a malformed, badly signed, expired, fingerprint-mismatched or already-used token.

| `Laika\Engine\Services\CSRF` method | |
|---|---|
| `generate(): string` | A new token |
| `validate(?string $token): bool` | `true`, or throws `CSRFException` |
| `fromRequest(string $header = 'X-Csrf-Token'): ?string` | The token from that header, or the `_csrf` form field |
| `field(): string` | A hidden `<input>` with a fresh token (the `csrf_field()` helper echoes one) |
| `setTtl(int $ttl): void` | Lifetime in seconds, default 3600 |
| `bindFingerprint(bool $bind = true): void` | Bind tokens to OS + user agent (default on) |

### AJAX Requests

`lf_header()` prints a JS constant `TOKEN` with a fresh token. Send it in the `X-Csrf-Token` header:

```js
fetch('/orders', {
    method: 'POST',
    headers: { 'X-Csrf-Token': TOKEN, 'Content-Type': 'application/json' },
    body: JSON.stringify(order),
});
```

Because tokens are single-use, `TOKEN` works for **one** request. For pages that send several, return a new token with each response (e.g. `Response::setHeader('X-Csrf-Token', CSRF::generate())`) and use that for the next request.

> The burn list lives in a cookie on the client, so a client that deletes it can replay a token until it expires. Keep `setTtl()` short for sensitive actions.

## CORS

`CORS::handle()` runs at the start of **every** request, before routing. It always sends a set of security headers, adds `Access-Control-*` headers for allowed origins, and answers `OPTIONS` preflights with `204` — ending the request there.

Configure it in a hook file, which runs before `handle()`:

```php
// lf-hooks/cors.php
use Laika\Engine\Services\CORS;

CORS::origins(['https://app.example.com', 'https://admin.example.com']);
CORS::credentials(true);
CORS::expose(['X-Total-Count']);
```

**Each setter replaces its whole list** — `CORS::headers(['X-Api-Key'])` removes `Content-Type` and the other defaults. Repeat the defaults you want to keep.

| Method | Default |
|---|---|
| `origins(array $origins): void` | `['*']` |
| `methods(array $methods): void` | `GET, POST, PUT, PATCH, DELETE, OPTIONS` |
| `headers(array $headers): void` | `Content-Type, Authorization, X-Requested-With, Accept` |
| `expose(array $headers): void` | none |
| `credentials(bool $allow = true): void` | `false` |
| `maxAge(int $seconds): void` | 86400 |
| `securityHeaders(array $headers): void` | See below — replaces the whole set |

> **Never combine `credentials(true)` with the default `['*']` origins.** With credentials on, the request's `Origin` is echoed back with `Access-Control-Allow-Credentials: true` — so every website could make authenticated requests to your app. List explicit origins.

### Security Headers

Sent on every response:

| Header | Value |
|---|---|
| `X-Content-Type-Options` | `nosniff` |
| `Referrer-Policy` | `strict-origin-when-cross-origin` |
| `X-Frame-Options` | `sameorigin` |
| `Content-Security-Policy` | `frame-ancestors 'self'` |
| `X-Powered-By` | `Laika Framework` |

To drop `X-Powered-By` or add a stricter CSP, pass the full set:

```php
CORS::securityHeaders([
    'X-Content-Type-Options'    => 'nosniff',
    'Referrer-Policy'           => 'strict-origin-when-cross-origin',
    'X-Frame-Options'           => 'DENY',
    'Content-Security-Policy'   => "default-src 'self'; frame-ancestors 'none'",
    'Strict-Transport-Security' => 'max-age=31536000; includeSubDomains',
]);
```

## See Also

- [Security (Shield)](01_basic.md) — firewall, rate limiting, SQLi/XSS detection
- [Encryption & Tokens](03_encryption-and-tokens.md)
- [Pipelines](../03_pipeline/01_basic.md)
