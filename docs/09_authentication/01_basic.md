# Authentication

[`laikait/laika-auth`](https://github.com/laikait/laika-auth) provides three kinds of guard, resolved by name through `Laika\Auth\AuthManager`:

| Driver | Stores | Use for |
|---|---|---|
| `session` | The logged-in user's array in the session | Web logins |
| `cookie` | An opaque token in a cookie | "Remember me" |
| `token` | Hashed bearer tokens in the `auth_tokens` table | APIs, mobile apps |

Guards don't check passwords or protect routes by themselves — you verify credentials (with `Vault::verifyPassword()`) and call the guard from a controller or [pipeline](../03_pipeline/01_basic.md).

## Configuration

Guards are defined in [`lf-config/auth.php`](../01_getting-started/03_configuration.md#lf-configauthphp), keyed directly by guard name (not wrapped in a `'guards'` key):

```php
use App\Model\UsersModel;
use App\Model\StaffsModel;

return [
    'web'      => ['driver' => 'session', 'provider' => 'web'],
    'remember' => ['driver' => 'cookie',  'provider' => 'remember'],
    'admin'    => ['driver' => 'token',   'provider' => StaffsModel::class],
    'user'     => [
        'driver'     => 'token',
        'provider'   => UsersModel::class,
        'connection' => 'default', // where auth_tokens lives
        'install'    => false,     // true creates auth_tokens on first use — development only
    ],
];
```

`driver` is required: a missing or unknown one throws `InvalidArgumentException("Unknown auth driver […]")` when the guard is resolved.

What `provider` means depends on the driver:

| Driver | `provider` |
|---|---|
| `session` | Optional. The **session scope** the user is stored in (`web` → the `WEB` scope). Without it, the `APP` scope. |
| `cookie` | Optional, and currently unused — the cookie name comes from the guard name. |
| `token` | Required. A `Laika\Model\Model` subclass; `validateToken()` loads the user with its `find()`. Anything else throws `AuthException`. |

When given, `provider` must be a non-empty string for every driver, or resolving the guard throws `AuthException`.

**Set `connection` explicitly on token guards.** Without it, tokens are read and written on the *default* connection, but `install` creates the table on the connection *named* `default` — two different connections if you changed the default with `Connection::setDefault()`.

## Resolving a Guard

```php
use Laika\Auth\AuthManager;

$auth  = new AuthManager();        // reads config('auth')
$guard = $auth->guard('web');
```

`AuthManager::__construct(?array $config = null)` reads `config('auth')` unless you pass an array. `guard(string $name)` returns a `SessionGuard`, `CookieGuard` or `TokenGuard`, cached per manager; an unknown guard name throws `InvalidArgumentException("Guard [api] not configured.")`.

`AuthManager` has no constructor dependencies, so you can inject it into controllers and pipelines: `public function __construct(private AuthManager $auth) {}`.

## Session Guard

The session guard needs a [session driver](../11_sessions/01_basic.md) configured.

```php
$guard = $auth->guard('web');

$guard->login(['id' => $user['id'], 'email' => $user['email']]);
$user = $guard->user();   // the array you stored, or null
$guard->logout();
```

| Method | |
|---|---|
| `login(array $user): void` | Store the user array |
| `user(): ?array` | The stored array, or `null` |
| `logout(): void` | Remove it |

The user is stored as `Session::scope($provider ?? 'APP')->set("laika_auth_{$guard}", $user)`. There's no `check()` or `id()` — use `user() !== null` and `user()['id']`.

### A Login Controller

```php
namespace App\Controller;

use App\Model\UsersModel;
use Laika\Auth\AuthManager;
use Laika\Core\App\Template;
use Laika\Service\{Request, Redirect, Vault};
use Laika\Session\Session;

class LoginController
{
    public function __construct(private AuthManager $auth) {}

    public function show(): string
    {
        return (new Template())->view('auth/login');
    }

    public function login(): ?string
    {
        if (!Request::validate(['email' => 'required|email', 'password' => 'required'])) {
            return $this->show();
        }

        $user = (new UsersModel())->where(['email' => Request::input('email')])->first();

        if (!$user || !Vault::verifyPassword(Request::input('password'), $user['password'])) {
            Request::addError('email', 'These credentials do not match our records.');
            return $this->show();
        }

        Session::regenerate();   // new session id on login
        $this->auth->guard('web')->login(['id' => $user['id'], 'email' => $user['email']]);

        Redirect::to('dashboard');
    }

    public function logout(): void
    {
        $this->auth->guard('web')->logout();
        Session::regenerate();
        Redirect::to('login');
    }
}
```

> **Input is HTML-encoded.** `Request::input('password')` returns the encoded value (`&` → `&amp;`). Hash and verify through the same path — both with `Request::input()`, or both with a raw source — or passwords containing `& < > " '` won't match. See [Requests](../02_routing/03_requests.md#input-is-html-encoded-by-default).

## Cookie Guard

Stores a token in a cookie named `laika_remember_{guard}`, typically for "remember me":

```php
$guard = $auth->guard('remember');

$guard->remember($token);            // 30 days by default
$guard->remember($token, ttl: 86400);
$token = $guard->token();            // null if not set
$guard->forget();
```

| Method | |
|---|---|
| `remember(string $token, int $ttl = 2592000): void` | Set the cookie (httponly, SameSite=Strict, `secure` over HTTPS) |
| `token(): ?string` | Read it, exactly as stored; `null` if absent |
| `forget(): void` | Delete it |

The guard only stores the token — generating it (`Vault::token()`), saving a hash of it against the user, and checking it on return are up to you.

## Token Guard

Issues, validates and revokes bearer tokens stored in the `auth_tokens` table. Only a SHA-256 hash of each token is stored.

```php
$guard = $auth->guard('user');

// Issue — send 'token' and 'refresh_token' to the client once; neither can be recovered later
$issued = $guard->issueToken($user['id'], ttl: 3600);
// ['token' => 'b7e1…', 'hashed' => '9f2c…', 'refresh_token' => '4a0d…']

// Validate — returns the user row (from the provider model's find()), or null
$user = $guard->validateToken($plainToken);

// Refresh — revokes the old token and returns a new pair, or null
$issued = $guard->refreshToken($refreshToken, ttl: 3600);

// Revoke
$guard->revoke($plainToken);
$guard->revokeAllForUser($user['id']);
```

| Method | |
|---|---|
| `issueToken(int $userId, ?int $ttl = null): array` | New token. **Without `$ttl` the token never expires.** |
| `validateToken(?string $token, ?int $ttl = null, bool $strict = false): ?array` | The user, or `null` if the token is unknown, revoked, expired, or the user is gone |
| `refreshToken(string $refreshToken, ?int $ttl = null): ?array` | Revoke the token and issue a new pair (same shape as `issueToken()`), or `null` for an unknown, revoked or already-used refresh token. **Pass the `$ttl` you issue with** — `null` never expires. |
| `revoke(string $plainToken): bool` | Revoke one token |
| `revokeAllForUser(int $userId): bool` | Revoke every token of a user for this guard |

- **Sliding expiry:** for a token that has an expiry, each successful `validateToken()` pushes it to now + `$ttl`. **Pass the same `$ttl` you issued with** — without it the default is 3600 seconds, so validating a 30-day token without a `$ttl` cuts it to one hour. Tokens issued without a TTL are not affected.
- **`$strict`:** also require the browser, user agent and IP recorded when the token was issued. `refreshToken()` does not check them.
- Tokens are scoped to the guard: a token issued by `user` doesn't validate on `admin`. Guard names are compared lowercased for this, so guards named `User` and `user` share tokens.
- **Refresh tokens** are stored hashed and work once: `refreshToken()` revokes the old token, so a stolen refresh token that's already been used gets nothing. They **don't expire**: an expired token can still be refreshed, however old. A revoked one can't, so `revoke()` and `revokeAllForUser()` also end refreshing — revoke a user's tokens on logout and password change. Tokens issued by laika-auth 2.1.0 and earlier have no usable refresh token.
- A token is 48 hex characters, a refresh token 64.
- The provider model's rows must be arrays — the default. A connection configured with `PDO::FETCH_OBJ` breaks `validateToken()`.
- Database errors — a missing `auth_tokens` table, a lost connection — propagate from every method as the database's own exception.

### The `auth_tokens` Table

Not created by `php laika app:migrate`. Either set `'install' => true` on the guard once (development), or create it with a privileged connection:

```php
(new \Laika\Auth\Schema\AuthSchema('default'))->up();
```

| Column | Type |
|---|---|
| `id` | BIGINT auto-increment primary key |
| `user_id` | BIGINT UNSIGNED |
| `guard` | VARCHAR(50) |
| `browser`, `ip` | VARCHAR(50), nullable |
| `user_agent` | VARCHAR(255), nullable |
| `token` | VARCHAR(255), unique — the SHA-256 hash |
| `refresh_token` | VARCHAR(255), nullable — the SHA-256 hash |
| `expires_at`, `revoked_at` | TIMESTAMP, nullable |
| `created_at` | TIMESTAMP, default now |

Plus an index on `(user_id, guard)`. Leave `install` off in production: it runs on every request that resolves the guard, and needs DDL rights the runtime database user shouldn't have.

## Protecting Routes

A pipeline that requires a logged-in web user:

```php
namespace App\Pipeline;

use Laika\Auth\AuthManager;
use Laika\Route\Contracts\PipelineInterface;
use Laika\Service\Redirect;

class Authenticate implements PipelineInterface
{
    public function __construct(private AuthManager $auth) {}

    public function handle(callable $next, array &$params): ?string
    {
        $user = $this->auth->guard('web')->user();

        if ($user === null) {
            Redirect::to('login');
        }

        $params['user'] = $user;   // available to the controller as $user
        return $next();
    }
}
```

A pipeline that requires a valid bearer token:

```php
namespace App\Pipeline;

use Laika\Auth\AuthManager;
use Laika\Route\Contracts\PipelineInterface;
use Laika\Service\{Request, Response};

class ApiAuth implements PipelineInterface
{
    public function __construct(private AuthManager $auth) {}

    public function handle(callable $next, array &$params): ?string
    {
        $token = preg_replace('/^Bearer\s+/i', '', Request::header('Authorization') ?? '');
        $user  = $this->auth->guard('user')->validateToken($token ?: null);

        if ($user === null) {
            Response::setStatus(401)->setContentType('application/json');
            return json_encode(['message' => 'Unauthenticated.']);
        }

        $params['user'] = $user;
        return $next();
    }
}
```

```php
use Laika\Route\Handler;
use Laika\Service\Url;

Handler::registerGroup('api', function () {
    Url::get('/me', 'Api\MeController@show');
}, ['ApiAuth']);
```

Behind Apache with PHP-FPM, `Request::header('Authorization')` still works — see [Deployment](../13_deployment/01_basic.md#php-fpm).

## What laika-auth Doesn't Do

- **OAuth** — no OAuth guard, no Google/Facebook providers. Use a dedicated OAuth client library and log the resulting user in with the session guard.
- **Passwords** — hash and verify them with `Vault::hashPassword()` / `Vault::verifyPassword()`, see [Encryption & Tokens](../10_security/03_encryption-and-tokens.md).
- **Rate limiting** of login attempts, and **session regeneration** — call `Session::regenerate()` yourself after login, as the login controller above does.
- **Stateless JWTs** — the `Token` relay, also in [Encryption & Tokens](../10_security/03_encryption-and-tokens.md).

## Errors

| Exception | Thrown when |
|---|---|
| `InvalidArgumentException` | `guard()` with a name not in `lf-config/auth.php`, or a guard whose `driver` is missing or unknown |
| `Laika\Auth\Exceptions\AuthException` | A misconfigured guard: an empty guard name, a `provider` that isn't a non-empty string, or a token guard whose provider isn't a `Laika\Model\Model` subclass |
| Database exceptions | Any token guard method, when the database fails |

Validation failures don't throw — `user()`, `token()`, `validateToken()` and `refreshToken()` return `null`. To turn one into a 401 from a pipeline, throw `Laika\Core\Exceptions\AuthenticationException`; the framework's error handler renders it — see [Errors & Logging](../18_errors-and-logging/01_basic.md).

## See Also

- [Sessions](../11_sessions/01_basic.md) — the session guard's storage
- [Encryption & Tokens](../10_security/03_encryption-and-tokens.md) — password hashing, `Vault::token()`, stateless JWTs
- [Pipelines](../03_pipeline/01_basic.md)
