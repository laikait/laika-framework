# Authentication

Multi-guard authentication is provided by [`laikait/laika-auth`](https://github.com/laikait/laika-auth) — session, cookie, and token guards, resolved by name through `AuthManager`.

## Configuration

Guards are defined in [`lf-config/auth.php`](../01_getting-started/03_configuration.md#lf-configauthphp), keyed directly by guard name (not wrapped in a `'guards'` key):

```php
use App\Model\UsersModel;
use App\Model\StaffsModel;

return [
    'web'      => ['driver' => 'session', 'provider' => 'web'],
    'remember' => ['driver' => 'cookie',  'provider' => 'remember'],
    'admin'    => ['driver' => 'token',   'provider' => StaffsModel::class],
    'user'     => ['driver' => 'token',   'provider' => UsersModel::class],
];
```

`provider` means different things per driver:

- **`session`/`cookie`** — an arbitrary string, used to namespace the session/cookie key
- **`token`** — a model class implementing `find($id)`, used to load the authenticated user by ID

## Resolving a Guard

```php
use Laika\Auth\AuthManager;

$auth  = new AuthManager(); // reads lf-config/auth.php via config('auth')
$guard = $auth->guard('web');
```

## Session Guard

```php
$guard = $auth->guard('web');

$guard->login(['id' => $user['id'], 'name' => $user['name']]); // stores in session
$user = $guard->user();  // null if not logged in
$guard->logout();
```

## Cookie Guard

Typically used for "remember me" tokens alongside a session guard:

```php
$guard = $auth->guard('remember');

$guard->remember($token, ttl: 2592000); // 30 days, default
$token = $guard->token(); // null if not set
$guard->forget();
```

## Token Guard

Issues, validates, and revokes bearer tokens against the `auth_tokens` table — run `php laika app:migrate` once after installing `laikait/laika-auth` to create it.

```php
$guard = $auth->guard('api');

// Issue — returns ['token' => <plain, send to client>, 'hashed' => <stored form>]
$issued = $guard->issueToken(userId: $user['id']);

// Validate — returns the user row (via the guard's provider model), or null
$user = $guard->validateToken($plainToken);

// Revoke a single token / all tokens for a user
$guard->revoke($plainToken);
$guard->revokeAllForUser($user['id']);
```

`validateToken()` accepts an optional `$ttl` (extends expiry on each successful check) and `$strict` (also requires matching browser/user-agent/IP recorded at issue time).

## Using It in a Pipeline

Wrap a route (or a whole group) in a pipeline that checks the guard before the controller runs — see [Pipelines](../03_pipeline/01_basic.md):

```php
namespace App\Pipeline;

use Laika\Auth\AuthManager;
use Laika\Route\Contracts\PipelineInterface;

class Authenticate implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        $token = (new AuthManager())->guard('api')->validateToken($params['token'] ?? null);

        if (!$token) {
            http_response_code(401);
            return 'Unauthorized';
        }

        return $next();
    }
}
```

## OAuth (Google / Facebook)

`laika-auth` ships an `'oauth'` guard concept and references `OauthGuard`, `GoogleOauthProvider`, and `FacebookOauthProvider` in its manager, but as of the version installed here those classes don't exist yet in the package (`AuthManager::guard()` also has no `'oauth'` case in its driver match). Treat OAuth login as **not yet available** — check the [laika-auth changelog](https://github.com/laikait/laika-auth/releases) before relying on it, and fall back to token/session guards in the meantime.

## Database

`laika-auth` ships one schema, auto-discovered by `php laika app:migrate` (see [Models & Database](../05_models/01_basic.md#running-migrations)):

| Table | Columns |
|---|---|
| `auth_tokens` | `id`, `user_id`, `guard`, `browser`, `ip`, `user_agent`, `token`, `refresh_token`, `expires_at`, `revoked_at`, `created_at` |
