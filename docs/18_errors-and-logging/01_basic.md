# Errors & Logging

How Laika handles exceptions, how to return HTTP errors, where logs go, and the two tables the Core module keeps for you: the activity log and site options.

## The Error Handler

`Laika\Engine\Exceptions\Handler` is registered at boot, for web requests and the CLI alike:

| It catches | Effect |
|---|---|
| Uncaught exceptions | Logged (in DEBUG) and rendered |
| Warnings, notices, deprecations | **Turned into `ErrorException`** — unless suppressed with `@` or masked out of `error_reporting()` |
| Fatal errors | Rendered on shutdown |

Because warnings become exceptions, things PHP normally tolerates **stop the request**: an undefined array key, `setcookie()` after output, a deprecated call. Use `?? null` for optional keys, and `@` only for calls meant to fail softly.

### What the User Sees

| Request | `DEBUG` on | `DEBUG` off |
|---|---|---|
| HTML | The Whoops error page with the stack trace | A generic "Internal Server Error" page |
| JSON (see below) | `{"message": ..., "exception": "<message>"}` | `{"message": ..., "exception": null}` |

`HttpException` subclasses set the status code; anything else is a 500. The production HTML page always reads "Internal Server Error", even for a 404 or 403 — render your own view for those (see [Responses → Error Statuses](../02_routing/04_responses.md#error-statuses)).

The handler replies with **JSON** when the `Accept` header is exactly `application/json`, the `Content-Type` starts with `application/json`, or `X-Requested-With: XMLHttpRequest` is sent. `fetch()` sends none of these by default, and axios sends `Accept: application/json, text/plain, */*`, which doesn't count — add `X-Requested-With` or a JSON `Content-Type` to API calls.

JSON replies are sent with `Content-Type: application/json; charset=UTF-8`. (In laika-core 5.1.1 and earlier the JSON branch called a `Response` method that doesn't exist and ended in a fatal error; catch exceptions in API controllers yourself on those versions.)

## Throwing HTTP Errors

```php
use Laika\Engine\Exceptions\{HttpException, NotFoundHttpException, AuthenticationException, ValidationException};

throw new NotFoundHttpException();                                   // 404 "Page Not Found"
throw new AuthenticationException();                                 // 401 "Unauthenticated."
throw new HttpException(403, 'You cannot edit this order.');
throw new ValidationException(['email' => ['Already registered.']]); // 422, with errors
```

| Class | Status | Constructor |
|---|---|---|
| `HttpException` | any | `__construct(int $statusCode = 500, string $message = '', $code = 0)` |
| `NotFoundHttpException` | 404 | `__construct(string $message = 'Page Not Found')` |
| `AuthenticationException` | 401 | `__construct(string $message = 'Unauthenticated.')` |
| `ValidationException` | 422 | `__construct(array $errors, string $message = 'Validation Failed')`, plus `errors(): array` |

Other exceptions the framework throws — `CSRFException`, `AppKeyException`, `PathException`, `ExtensionException`, `ContextException`, `LocalException`, `LogException`, `OptionException`, `ResourceException`, `SchemaException`, and the Model module's `ModelException` — all render as a 500 unless you catch them. For example, catch `CSRFException` and answer 419.

## Logging

When `DEBUG` is on, the handler appends each exception (class, message, location, stack trace) to `lf-logs/{Y}-{Mon}-{d}-error.log`.

**When `DEBUG` is off, the framework logs nothing.** In production:

- configure PHP's `error_log` (`log_errors = On`, `error_log = /var/log/php/app.log`) — `error_log('...')` from your code goes there too;
- or write your own log lines with `File::append()`:

```php
\Laika\Engine\Services\File::append(
    sprintf("[%s] %s\n", date('c'), $message),
    APP_PATH . '/lf-storage/logs/app.log'
);
```

`lf-logs/` and `lf-storage/` are never served over HTTP.

## Activity Log

`Laika\Engine\Services\Activity` records an audit trail — who did what — in the `activities` table.

```php
use Laika\Engine\Services\Activity;

$changes = Activity::changelog($invoiceBefore);   // compares against the request input

Activity::author('staff', $staffId)
    ->log("Updated invoice #{$invoice['id']}")
    ->event('invoice.updated', $changes);

Activity::insert();   // write the queued events
```

**Nothing writes the log automatically** — `event()` only queues an entry, and queued entries are lost unless you call `Activity::insert()`. A global [filter](../04_filter/01_basic.md) is a good place for it:

```php
class WriteActivityLog implements FilterInterface
{
    public function terminate(callable $next, ?string $response, array &$params): ?string
    {
        \Laika\Engine\Services\Activity::insert();
        return $next($response);
    }
}
```

| Method | |
|---|---|
| `author(?string $type = null, ?int $id = null): static` | Who: `system` (default), `staff`, `client`, ... |
| `log(string $log): static` | A human-readable description |
| `event(string $event, array $changelog = []): void` | Queue the entry, then reset author and log |
| `events(?string $event = null): array` | Queued entries |
| `changelog(array $existing, ?array $inputs = null): array` | `['field' => ['old' => ..., 'new' => ...]]` for inputs that differ from `$existing` |
| `insert(?string $connection = null): int` | Write the queue; returns the row count |

Each row stores `author_type`, `author_id`, `event` (lowercased), `log`, `changes`, `from_ip` and `created_at`. The table is created on first `insert()`. Note that `changelog()` compares against the **sanitised** (HTML-encoded) request input, loosely.

## Options

`Laika\Engine\Services\Option` stores site-wide settings in the `options` table — handy for values an admin can change at runtime.

```php
use Laika\Engine\Services\Option;

Option::single('app_name');              // "Laika Framework" (a seeded default)
Option::insert('maintenance', false);    // stored as "false"
Option::update('data_limit', 50);        // stored as "50"

option_bool('maintenance');              // false
option_int('data_limit', 20);            // 50
```

| Method | |
|---|---|
| `single(string $key, ?string $default = null): ?string` | The stored value, or `$default` |
| `insert(string $key, mixed $value): bool` | Add; `false` if the key exists |
| `update(string $key, mixed $value): bool` | Change; `false` if the key doesn't exist |
| `install(?string $connection = null): void` | Create and seed the table |

- The table is created and seeded on first use. Seeded keys: `app_name`, `app_icon`, `app_logo`, `app_path`, `data_limit`, `date_format`, `time_format`, `datetime_format`, `time_zone`.
- Values are stored as text (`convert_to_string()`): read them with `option_bool()`, `option_int()` and `option_array()` — see [Helpers → Options](../15_helpers/01_basic.md#options).
- Lookups are cached for the rest of the request.
- For another connection: `new \Laika\Engine\Model\OptionModel('tenant_db')`.
- With `DEBUG` on, failures throw `OptionException`; in production reads return the default and writes return `false`.

## See Also

- [Deployment → Running With DEBUG Off](../13_deployment/01_basic.md#running-with-debug-off)
- [Responses](../02_routing/04_responses.md)
