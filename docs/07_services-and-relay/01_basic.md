# Services & Relays

Laika's service container is The [Relay module](https://github.com/laikait/laika-engine/tree/main/docs/relay) of `laikait/laika-engine`. It has two parts:

- **`RelayRegistry`** — a small dependency-injection container. You bind services into it with `singleton()`, `bind()` or `instance()`.
- **Relays** — classes extending `Laika\Engine\Relay\Relay` that forward static calls to a bound service. `Laika\Engine\Services\Request::input('email')` is a relay call: it runs `input('email')` on the shared `Request` instance.

The framework's own services are all reachable this way — see the [relay list](02_relay-list.md). This page shows how to add your own.

| Directory | Namespace | Role |
|---|---|---|
| `lf-app/Relay/` | `App\Relay` | **Providers** (`RelayProvider`) — bind a service into the container |
| `lf-app/Service/` | `App\Service` | **Relays** (`Relay`) — the static class your code calls |

Both are discovered automatically; there is nothing to register.

## Quick Start

```bash
php laika service:make --name=Mailer --class=Laika\\Engine\\Mailman\\Mailer
```

This generates both halves:

- `lf-app/Relay/Mailer.php` — the provider, binding `Laika\Engine\Mailman\Mailer` as a singleton under the key `mailer.accessor`
- `lf-app/Service/Mailer.php` — the relay you call

`--class` is the concrete class to bind and must already exist. `--name` and `--class` accept letters, underscores and `\` only.

Customise the provider so the service is built with the right arguments:

```php
namespace App\Relay;

use Laika\Engine\Relay\RelayProvider;
use Laika\Engine\Mailman\Mailer;

class Mailer extends RelayProvider
{
    public function register(): void
    {
        // A closure factory receives the registry, then any $args
        $this->registry->singleton('mailer.accessor', fn () => new Mailer(config('mail')));
    }
}
```

Document the methods on the relay for IDE autocomplete:

```php
namespace App\Service;

use Laika\Engine\Relay\Relay;

/**
 * @method static \Laika\Engine\Mailman\Mailer to(string $address, string $name = '')
 * @method static bool send()
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

Mailer::to('ann@example.com')->subject('Hi')->text('Hello!')->send();
```

Method chaining works whenever the target method returns `$this`: the first call goes through the relay, the rest run on the returned object.

> **Singletons are shared.** A stateful service like `Mailer` keeps its recipients between calls in the same process. Call its `reset()` (or bind with `bind()` for a fresh instance per resolution) when that matters.

## Providers: `register()` and `boot()`

```php
namespace App\Relay;

use Laika\Engine\Relay\RelayProvider;

class Billing extends RelayProvider
{
    public function register(): void
    {
        // Only bind here.
        $this->registry->singleton('billing', \App\Support\Billing::class);
        $this->registry->singleton(\App\Contracts\PaymentGateway::class, \App\Support\StripeGateway::class);
    }

    public function boot(): void
    {
        // Every provider has registered; relays work; safe to make().
    }
}
```

| | `register()` | `boot()` |
|---|---|---|
| Purpose | Bind services | Use services |
| Runs | During boot, in provider order | After **every** provider has registered |
| `$this->registry->make()` | Risky — later providers haven't registered | Safe |
| Relay static calls (`Config::get()`, ...) | **Throw** — relays aren't connected yet | Work |

Provider order is: core providers, then providers from packages, then yours in `lf-app/Relay/`. Because yours register last, **an app provider can override a core binding** by binding the same key (`'response'`, `'request'`, ...).

## Binding Methods

`$this->registry` is a `Laika\Engine\Relay\RelayRegistry`:

| Method | Instances | Built |
|---|---|---|
| `singleton(string $key, Closure\|string $concrete, array $args = []): static` | 1, shared | On first `make()` |
| `bind(string $key, Closure\|string $concrete, array $args = []): static` | New on every `make()` | Every time |
| `instance(string $key, object $instance): static` | Your object | Already built |

`$concrete` is a class name (auto-wired, with `$args` for constructor parameters the container can't resolve) or a closure called as `$concrete($registry, ...$args)`. Prefer `singleton()` for most services — it's lazy.

Other registry methods: `make(string $key): object`, `has(string $key): bool`, `forgetInstance(string $key): static`, `bindings(): array`, `classes(): array`.

## Auto-Wiring

`make()` resolves a key in this order: an existing instance or resolved singleton → a singleton definition → a `bind()` definition → **auto-wiring**, if the key is a class name. Auto-wiring builds the class and fills each constructor parameter from:

1. a binding registered under the parameter's type (a class or interface name);
2. the class itself, auto-wired recursively;
3. `$args` by parameter name, then by position;
4. the default value, or `null` if nullable;
5. otherwise it throws `RelayException` naming the parameter.

This is what gives controllers, pipelines and filters **constructor injection** — the router builds them through `make()`:

```php
class InvoiceController
{
    public function __construct(private \App\Contracts\PaymentGateway $gateway) {}
}
```

An **interface** type must be bound (as in the `Billing` provider above) — interfaces can't be auto-wired.

> **Core services are bound by key, not by class.** `Request`, `Response`, `Config` and the rest live under keys like `'request'` and `'response'`. Type-hinting `Laika\Engine\Http\Response` therefore auto-wires a **new** `Response`, not the one the router sends; type-hinting the relay `Laika\Engine\Services\Response` gives a proxy object without instance methods. Call relays statically instead. If you want the shared instance injected, alias it in a provider: `$this->registry->singleton(\Laika\Engine\Http\Response::class, fn ($r) => $r->make('response'));`

## Using the Container Directly

```php
use Laika\Engine\Relay\Relay;

$billing = Relay::getRegistry()->make('billing');
```

## Testing With Relays

Every relay inherits these static helpers:

| Method | Does |
|---|---|
| `X::swap(object $instance): void` | Replace the bound instance, e.g. with a fake |
| `X::clearResolvedInstance(): void` | Forget the resolved instance; the next call builds a fresh one (and undoes a `swap()`) |
| `X::relayRoot(): object` | The real underlying instance |
| `Relay::swapRegistry(RelayRegistry $registry): void` | Replace the whole registry (tests only) |

```php
Mailer::swap(new FakeMailer());
// ... exercise code that calls Mailer::send() ...
Mailer::clearResolvedInstance();
```

Singletons keep state for the whole process. In a long-running worker, reset request-bound services between jobs with `X::clearResolvedInstance()` (or `Visitor::refresh()`).

## CLI Reference

| Command | Description |
|---|---|
| `php laika service:make --name=<Name> --class=<Concrete\Class>` | Create a relay and its provider |
| `php laika service:remove <name>` | Delete the relay and its provider |
| `php laika relay:list` | List every container binding: key → class |

## See Also

- [Relay List](02_relay-list.md) — every `Laika\Engine\Services\*` relay
- [Request Lifecycle](../01_getting-started/05_request-lifecycle.md#1-boot) — when providers run
