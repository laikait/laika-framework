# Request Lifecycle

What happens between a request arriving at `public/index.php` and a response leaving it. Knowing this order answers most "why doesn't my X run?" questions — when hooks run, when routes are loaded, which middleware sees what.

```
public/index.php
 └─ lf-boot/app.php                         BOOT
     ├─ APP_PATH, DS, lf-inc/const.php
     ├─ vendor/autoload.php
     │   └─ laika-engine helpers/loader.php
     │       ├─ path constants, DEBUG default
     │       ├─ container + CoreProviders + relay providers (register)
     │       ├─ Relay::setRegistry(), router resolver
     │       └─ providers boot → timezone UTC, error handler
     ├─ function files  (laika-engine helpers)
     └─ hook files      (lf-hooks/**, then laika-engine's)
 └─ Url::dispatch()                         DISPATCH
     ├─ CORS + security headers   (OPTIONS preflight → 204, exit)
     ├─ normalise request path    (invalid → 404)
     ├─ has a file extension?  → serve static file or 404, done
     ├─ load lf-routes/**
     ├─ match route             (none → fallback or 404 page, done)
     ├─ pipelines → controller
     ├─ filters
     └─ render response by Content-Type
```

## 1. Boot

`public/index.php` requires `lf-boot/app.php`, which runs the same way for web requests, `php laika` and `php worker` — apart from step 3, which is skipped under the CLI:

1. **Constants.** Defines `APP_PATH` (the project root) and `DS`, then requires `lf-inc/const.php` for `DEBUG`, `MEMORY_LIMIT` and `CLI_MEMORY_LIMIT`.
2. **Autoloader.** Requiring `vendor/autoload.php` runs laika-engine's `helpers/loader.php`, which:
   - defines `STORAGE_PATH`, `TEMPLATE_PATH`, `TEMPLATE_CACHE_PATH`, `CONFIG_PATH`, `LANG_PATH` (and `DEBUG = true` if you didn't define it);
   - creates the service container and registers `Laika\Engine\Relay\CoreProviders` (every `Laika\Engine\Services\*` relay);
   - calls `register()` on every relay provider: packages first, then yours in `lf-app/Relay/`, so yours can override a binding;
   - hands the container to the relays and to the router, so controllers, pipelines and filters get constructor injection;
   - calls `boot()` on every provider. The core provider **sets PHP's timezone to UTC** and **registers the error handler**.
3. **Static files.** `Dispatcher::dispatchAsset()` runs here, before the step below. A request that resolves to a real file is answered and the request ends — a static file needs nothing that follows. See [Dispatch](#2-dispatch) for what the response carries.

   > Because this runs first, **hook files have not loaded yet when a static file is served.** A hook that calls `MimeType::register()` or configures CORS does not affect asset responses. Nothing in the default app does either.

4. **Functions and hooks.** Requires laika-engine's helper functions (`config()`, `named()`, `asset()`, ...), then every hook file: your `lf-hooks/**/*.php` first, then the Core module's own hook file.

Consequences:

- **Hook files are the place for per-request setup** — session driver, CORS, timezone, extra DB connections. They run before any route file or controller.
- **Relay static calls don't work inside a provider's `register()`** — the registry isn't handed to the relays until every provider has registered. Use `boot()`.
- **Warnings are exceptions** from this point on. An undefined array key, or a `setcookie()` after output, ends the request. See [Errors & Logging](../18_errors-and-logging/01_basic.md).

## 2. Dispatch

`Url::dispatch()` (`Laika\Engine\Route\Dispatcher`) handles the rest:

1. **CORS and security headers.** `CORS::handle()` sends `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Content-Security-Policy`, `X-Powered-By` and, for allowed origins, the `Access-Control-*` headers. An `OPTIONS` preflight is answered with `204` and the request ends here — no route runs.
2. **Path normalisation.** The path is percent-decoded segment by segment, the install sub-directory is stripped, and trailing slashes are trimmed. An encoded separator (`%2F`, `%5C`), a NUL byte, or invalid UTF-8 gets a 404.
3. **Static files.** Already answered during boot in most cases (step 3 above); `Laika\Engine\Route\Asset` resolves the path on disk. If a real file is there it is checked against [`lf-config/assets.php`](03_configuration.md#lf-configassetsphp) and either served — with `ETag`, `Last-Modified`, `Cache-Control` and byte-range support — or refused. **A file that exists is never handed to a route, whether it was served or refused.** If no file is there, dispatch continues to the router, so `/sitemap.xml` and `/users/john.doe` match routes normally.
4. **Route files.** Every file in the `routes` resource (`lf-routes/**` by default) is `require_once`d.
5. **Matching.** Routes for the request method are tested in registration order; the first match wins. With no match, the longest-prefix [fallback](../02_routing/01_basic.md#fallback--404) runs, or the built-in 404 page is returned with status 404.
6. **Pipelines, then the controller.** Global pipelines, then the route's, then the controller. A pipeline can stop the chain by returning a string. See [Pipelines](../03_pipeline/01_basic.md).
7. **Filters.** Global filters, then the route's, each receiving the response string. See [Filters](../04_filter/01_basic.md).
8. **Rendering.** The final string is rendered according to `Laika\Engine\Services\Response::getContentType()`:

   | Content type | Renderer |
   |---|---|
   | `application/json...` | Decodes your string and re-encodes it as JSON |
   | `text/plain...` | Sent as text |
   | anything else (default `text/html`) | Sent as HTML; a hidden `_csrf` field is added to every `<form>` |

   The status comes from `Response::getStatus()`. An empty result (`null` or `''`) sends nothing at all.

## 3. Errors

Any uncaught exception or fatal error goes to `Laika\Engine\Exceptions\Handler`: logged to `lf-logs/` when `DEBUG` is on, then rendered as a Whoops page (`DEBUG` on) or a generic 500 page (`DEBUG` off). `HttpException` subclasses set their own status. See [Errors & Logging](../18_errors-and-logging/01_basic.md).

## The CLI and the Worker

`php laika` and `php worker` run the same boot (so your hook files, relay providers and helper functions are all available) but never call `Url::dispatch()`. Route files are only loaded by commands that need them, such as `route:list`.

## Where to Put Things

| You want to... | Put it in |
|---|---|
| Run code on every request, before routing | A file in `lf-hooks/` |
| Bind a service into the container | A provider in `lf-app/Relay/` |
| Check something before a controller runs | A [pipeline](../03_pipeline/01_basic.md) |
| Change or log the response after the controller | A [filter](../04_filter/01_basic.md) |
| Handle a request | A [controller](../02_routing/02_controllers.md) |

## See Also

- [Project Structure](02_project-structure.md)
- [Routing](../02_routing/01_basic.md) · [Controllers](../02_routing/02_controllers.md) · [Responses](../02_routing/04_responses.md)
- [Services & Relay](../07_services-and-relay/01_basic.md)
