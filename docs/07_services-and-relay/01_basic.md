# Services & Relay

Laika's service container is [`laikait/laika-relay`](https://github.com/laikait/laika-relay) — a lightweight DI container (`RelayRegistry`) plus a static-proxy base class (`Relay`) for exposing bound services as clean facades (`Auth::check()`, `Session::get()`, ...). This page covers the app-level convention; see the [laika-relay README](https://github.com/laikait/laika-relay) for the full container API (`singleton()`/`bind()`/`instance()`, auto-wiring, provider lifecycle, testing).

Two directories work together:

| Directory | Namespace | Role |
|---|---|---|
| `lf-app/Relay/` | `App\Relay` | `RelayProvider` classes — **bind** a service into the container |
| `lf-app/Service/` | `App\Service` | `Relay` proxy classes — the **facade** your app code actually calls |

## Create via CLI

```bash
php laika service:make --name=Mailer --class=App\\Model\\MailerModel
```

This generates both halves at once:

- `lf-app/Service/Mailer.php` — the facade you'll `use` in controllers
- `lf-app/Relay/Mailer.php` — the provider that binds it into the container

## The Relay Provider (binding)

```php
namespace App\Relay;

use Laika\Relay\RelayProvider;
use App\Model\MailerModel;

class Mailer extends RelayProvider
{
    public function register(): void
    {
        // Only bind() / singleton() / instance() here — other providers
        // may not have registered their services yet.
        $this->registry->singleton('mailer.accessor', MailerModel::class, []);
    }

    public function boot(): void
    {
        // Called after every provider has registered. Safe to make() here.
    }
}
```

## The Service Facade (usage)

```php
namespace App\Service;

use Laika\Relay\Relay;

/**
 * @method static int example()
 */
class Mailer extends Relay
{
    protected static function getRelayAccessor(): string
    {
        return 'mailer.accessor';
    }
}
```

```php
use App\Service\Mailer;

Mailer::send($to, $subject, $body);
```

Document every proxied method with a `@method static` tag on the facade class — that's what gives you IDE autocomplete on an otherwise-magic `__callStatic` call.

## `register()` vs `boot()`

| | `register()` | `boot()` |
|---|---|---|
| Purpose | Promise a service exists | Use services that others promised |
| When called | Before other providers boot | After **all** providers have registered |
| Call `make()`? | ⚠️ Risky — others may not be ready | ✅ Safe |

## Binding Lifetimes

| Method | Instances | Built | Cached |
|---|---|---|---|
| `instance()` | 1 (yours, pre-built) | Before registration | Yes — immediately |
| `singleton()` | 1 | On first `make()` | Yes — after first use |
| `bind()` | N (fresh every call) | On every `make()` | Never |

Prefer `singleton()` for most app services — it's lazy, `instance()` isn't.

## Accessing the Container Directly

You don't strictly need a `Service` facade — the registry is reachable anywhere:

```php
use Laika\Relay\Relay;

$mailer = Relay::getRegistry()->make('mailer.accessor');
```

## Listing Registered Relays

```bash
php laika relay:list
```

## CLI Reference

| Command | Description |
|---|---|
| `php laika service:make --name=<ServiceClass> --class=<RelayClass>` | Create a service facade + its provider |
| `php laika service:remove <name>` | Delete a service and its provider |
| `php laika relay:list` | List registered Relay classes |

For method chaining, swapping instances at runtime, and mocking a `Relay` in tests, see the [laika-relay README](https://github.com/laikait/laika-relay#method-chaining).
