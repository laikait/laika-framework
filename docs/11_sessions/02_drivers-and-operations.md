# Session Drivers & Operations

What each session driver does under load, how old sessions are cleaned up, how failures show up, and what to check before running on several servers. For configuring and using sessions, start with [Sessions](01_basic.md).

## Locking and Concurrency

One user's requests can run at the same time — a page and its AJAX calls, or two tabs. What happens then depends on the driver:

| Driver | Concurrent requests on one session |
|---|---|
| `file` | **Wait for each other.** The file is locked from the moment the session is read until it is written back at the end of the request. |
| `model`, `mysql`, `redis`, `memcached` | Run in parallel. Each writes the whole session at the end, so **the last request to finish wins** — a value one request set can be overwritten by another that read the session before it. |

With the file driver, one slow request — a report, an upload, a call to a slow API — holds up every other request from that user. Release the lock as soon as the request has finished changing the session:

```php
Session::set('export_started', true);
session_write_close();   // later Session::get() calls still read; writes are lost

$report = $service->buildHugeReport();
```

With the other drivers, keep values that parallel requests both change out of the session, or accept that one change may be lost.

## Garbage Collection

With the defaults (`gc_probability = 1`, `gc_divisor = 100`), about one session start in a hundred also deletes sessions idle for longer than `gc_maxlifetime`:

| Driver | Cleanup |
|---|---|
| `file` | Lists the directory and deletes `<PREFIX>_*` files older than `gc_maxlifetime` |
| `model`, `mysql` | One `DELETE … WHERE last_activity < ?`, using the `last_activity` index |
| `redis`, `memcached` | Nothing — each key carries its own expiry |

On a busy site, the file driver's directory listing runs inside some unlucky user's request. To move it out, set `gc_probability` to `0` and delete expired files from a scheduled job instead — matching the driver's prefix (`LK_*` by default), not PHP's usual `sess_*`.

## Keeping Idle Sessions Alive

A session's age is measured from its last **write**. PHP skips writing a session whose data didn't change (`lazy_write`, on by default) and instead asks the driver to refresh its timestamp — every driver supports this, so a user who only reads pages stays signed in. Only turn `lazy_write` off if you need every request to rewrite the session.

## Failure Modes

A storage problem shows up differently on each driver:

| Driver | When storage fails |
|---|---|
| `file` | A `path` that doesn't exist throws `SessionHandlerException` at start. A file that can't be opened reads as an empty session. |
| `mysql`, `model` | Database errors **propagate** — the request fails with the database exception. (Only the timestamp refresh swallows them.) |
| `redis` | Errors are swallowed once the session has started: reads return an empty session and writes are lost. The user appears logged out. |
| `memcached` | A server that is down never throws: every read is empty and every write fails, silently. |

"Every user is suddenly logged out" with Redis or Memcached almost always means the server is unreachable — check it before anything else.

Session ids are checked against `[A-Za-z0-9,-]`; a cookie with any other character is treated as having no session.

## Strict Mode

`use_strict_mode` is on by default: PHP asks the driver whether a session id from the cookie actually exists, and replaces an unknown one with a fresh id instead of adopting it. This stops an attacker from choosing a victim's session id in advance. Leave it on.

## Running on Several Servers

The file driver keeps sessions on one server's disk. Behind a load balancer, a user whose requests reach different servers loses their session on every switch. Use a shared store:

- `redis`, `model` or `mysql` — shared by every server.
- `memcached` — shared, but it may evict sessions when it runs out of memory, logging users out.
- Sticky sessions on the load balancer — only as a last resort.

With `model` or `mysql`, keep `date.timezone` the same on every server and in the CLI: `last_activity` is compared against the current time.

## File Driver Under PHP-FPM

Always give the file driver an explicit `path` in production:

```php
Init::file(['path' => APP_PATH . '/lf-storage/sessions']);
```

The default, `session_save_path()`, causes trouble on common setups:

- **Debian and Ubuntu** default to `/var/lib/php/sessions`, which can't be listed (mode `1733`) and is cleaned by a cron job that only deletes `sess_*` files. This driver's files are named `LK_*`, so neither the cron job nor the driver's own cleanup removes them — they pile up.
- **systemd `PrivateTmp`**: when the path falls back to the temp directory, php-fpm's private `/tmp` is wiped on every restart, logging everyone out.

Create the directory during deployment (the driver won't), make it writable by the FPM pool user, and keep it outside the web root. Files are created with mode `0600`, so a CLI script running as another user creates session files the web server can't read.

## Custom Drivers

Not supported. The five drivers are a closed list, and `SessionConfig` has no way to register another. Every driver implements `Laika\Engine\Session\Contracts\SessionDriverInterface` — PHP's `SessionHandlerInterface` and `SessionUpdateTimestampHandlerInterface`, plus `setup(): void`, which runs once per process before the first session starts.

## Exceptions

| Exception | Thrown when |
|---|---|
| `Laika\Engine\Session\Exceptions\SessionHandlerException` | No driver is configured and the session starts; a file `path` doesn't exist; `SessionConfig::model()` without the Model module; a `mysql` table name that isn't `[A-Za-z0-9_]+`; a driver built without its client |
| `InvalidArgumentException` | `Session::scope('')` — an empty scope name |
| `PDOException` and Model exceptions | The `mysql` and `model` drivers, on database errors |
| `Laika\Engine\Exceptions\ExtensionException` | `Init::redis()` without `ext-redis`, or when Redis is unreachable or rejects the credentials; `Init::memcached()` without `ext-memcached` (an unreachable Memcached never throws) |

## See Also

- [Sessions](01_basic.md)
- [Deployment](../13_deployment/01_basic.md)
- [Authentication → Session Guard](../09_authentication/01_basic.md#session-guard)
