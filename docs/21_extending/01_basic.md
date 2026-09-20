# Extending the Framework

Four ways to change what the framework does without editing `vendor/`:

| Need | Use |
|---|---|
| A storage or transport backend the framework doesn't ship | [Driver `extend()`](#custom-drivers) |
| A small method on a framework class | [Macros](#macros) |
| Different behaviour for one step of a framework class | [Subclassing](#subclassing) |
| A different implementation of a whole service | [Swap the relay](../07_services-and-relay/01_basic.md) |

Register all of them once, from a relay provider's `boot()` in `lf-app/Relay/`. Every provider there is discovered automatically:

```php
namespace App\Relay;

use Laika\Engine\Relay\RelayProvider;

class ExtensionsRelay extends RelayProvider
{
    public function register(): void
    {
    }

    public function boot(): void
    {
        // extend() and macro() calls go here
    }
}
```

## Custom Drivers

Each module that has drivers takes new ones by name. A resolver receives the relevant config array and returns an object that implements the module's interface. It is checked, so returning the wrong type fails straight away with a clear message. Registering a built-in name replaces that built-in.

| Module | Register | Select | Interface |
|---|---|---|---|
| Cache | `Cache::extend('name', fn (array $config) => ...)` | `lf-config/cache.php` → `'driver'` | `Laika\Engine\Cache\Contracts\CacheDriverInterface` |
| Session | `HandlerFactory::extend('name', fn (array $params) => ...)` | `SessionConfig::custom('name', $params)` | `Laika\Engine\Session\Contracts\SessionDriverInterface` |
| Queue | `Queue::extend('name', fn (array $config) => ...)` | `lf-config/queue.php` → `'driver'` | `Laika\Engine\Queue\Interfaces\QueueDriverInterface` |
| Failed jobs | `Queue::extendFailed('name', fn (array $config) => ...)` | `lf-config/queue.php` → `'failed_driver'` | `Laika\Engine\Queue\Interfaces\FailedJobProviderInterface` |
| Database | `DriverFactory::extend('name', fn (array $config) => ...)` | `lf-config/database.php` → `'driver'` | `Laika\Engine\Model\Drivers\DriverInterface` |
| Mail sending | `MailManager::extendMailer('name', fn (array $config) => ...)` | `MailManager::mailer(['driver' => 'name'] + config('mail'))` | `Laika\Engine\Mailman\Interfaces\MailerInterface` |
| Mail reading | `MailManager::extendReader('name', fn (array $config) => ...)` | `MailManager::reader(['protocol' => 'name', ...])` | `Laika\Engine\Mailman\Interfaces\MailReaderInterface` |

The classes live at `Laika\Engine\Services\Cache` (a relay), `Laika\Engine\Session\Handler\HandlerFactory`, `Laika\Engine\Worker\Queue`, `Laika\Engine\Model\Drivers\DriverFactory` and `Laika\Engine\Mailman\MailManager`.

For example, a mailer that writes to the log instead of sending, for development:

```php
use Laika\Engine\Mailman\MailManager;

MailManager::extendMailer('log', fn (array $config) => new LogMailer($config));
```

```php
MailManager::mailer(['driver' => 'log'] + config('mail'))
    ->to('ada@example.com')->subject('Hi')->body('...')->send();
```

`new Mailer(config('mail'))` keeps working. `MailManager` is only needed to reach a registered driver.

## Macros

A macro adds a method to a class at runtime. The closure is bound to the object it is called on, so `$this` and protected members work exactly as in a real method:

```php
use Laika\Engine\Model\Model;

Model::macro('active', function () {
    return $this->where(['status' => 'active']);
});

(new UsersModel)->active()->order('name')->get();
```

These classes take macros:

| Class | Typical use |
|---|---|
| `Laika\Engine\Model\Model` | Reusable query fragments |
| `Laika\Engine\Model\Schema\Blueprint` | Column presets for schemas |
| `Laika\Engine\Http\Request` | Request helpers |
| `Laika\Engine\Http\Response` | Response shortcuts |
| `Laika\Engine\Cache\Cache` | Cache helpers |
| `Laika\Engine\Route\Url` | Routing helpers |

- A macro added to `Model` is available on every model. One added to `UsersModel` is available only there and in its subclasses.
- A macro never replaces a real method. PHP only calls it when no method of that name exists.
- Relays forward macros too, so `Laika\Engine\Services\Request::myMacro()` works.
- `mixin($object)` registers every method of an object at once. Each of its methods returns the closure to register.
- `hasMacro('name')` checks, and `flushMacros()` clears a class's macros in tests.

## Subclassing

Most framework classes can be extended, and their internal steps are `protected`, so a subclass can replace just one step:

```php
use Laika\Engine\Model\Model;

class UsersModel extends Model
{
    protected string $table = 'users';

    // Runs on every fetched row
    protected function cast(array|object $row): array|object
    {
        $row = parent::cast($row);

        return $this->rowSet($row, 'name', ucfirst((string) $this->rowGet($row, 'name')));
    }
}
```

`Model` is organised into traits under `Laika\Engine\Model\Concerns` (`BuildsQueries`, `Paginates`, `SoftDeletes`, `CastsValues`, `CachesQueries`, `ManagesTransactions`), which makes each step easy to find. Its public API is unchanged.

A few classes stay `final` on purpose, and their docblocks say why:

- **Security boundaries:** `Route\Asset` (the static file server), `Http\ProxyTrust`, and the Shield detectors, rules and helpers. A subclass could weaken their checks.
- **Value objects:** for example `ResourceDefinition` and `Cache\Entry`.
- **Internal parts:** for example the SQL converter's parser, which are not extension points.

To change a security boundary, configure it (for example with `lf-config/assets.php`) rather than subclassing it.
