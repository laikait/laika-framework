# Resources

A **resource** is a named directory whose PHP files the framework knows how to find — your models, controllers, jobs, pipelines, filters, schemas, commands, relay providers, routes and hooks are all resources. This is why you never register a class: dropping it in the right directory is enough.

`Laika\Service\Resource` is the registry that maps each name to its directories; `Laika\Service\Infra` is a friendlier reader on top of it.

Discovery is **lazy**. Registering a resource only records *where to look* — nothing is scanned until something asks, and every answer is memoised for the rest of the request.

## Built-in Resources

| Name | Path | Namespace | Contract |
|---|---|---|---|
| `models` | `lf-app/Model` | `App\Model` | — |
| `schemas` | `lf-app/Schema` | `App\Schema` | `Laika\Model\Contract\SchemaAbstract` |
| `controllers` | `lf-app/Controller` | `App\Controller` | — |
| `jobs` | `lf-app/Job` | `App\Job` | `Laika\Queue\Abstracts\Job` |
| `pipelines` | `lf-app/Pipeline` | `App\Pipeline` | `Laika\Route\Contracts\PipelineInterface` |
| `filters` | `lf-app/Filter` | `App\Filter` | `Laika\Route\Contracts\FilterInterface` |
| `commands` | `lf-app/Command` | `App\Command` | `Laika\Cli\Contracts\CommandInterface` |
| `relays` | `lf-app/Relay` | `App\Relay` | `Laika\Relay\RelayProvider` |
| `routes` | `lf-routes` | — (file paths) | — |
| `hooks` | `lf-hooks` | — (file paths) | — |

Packages add to these. In the 5.1 package set:

| Package | Contributes |
|---|---|
| laika-core | `functions` (its helper functions) and `hooks` (its template hooks) |
| laika-queue | `models` and `schemas` (the job tables) |
| laika-shield | `relays` (the `shield` relay) and `pipelines` (`ShieldPipeline`) |

`php laika resource:list` shows exactly what your install resolves. (An install whose `vendor/composer/installed.json` predates core 5.1 may still list models and schemas from laika-core, laika-auth and laika-session; `composer update` refreshes it.)

## Reading Resources

```php
use Laika\Service\Infra;

Infra::getModelClasses();       // ['users' => 'App\Model\UsersModel', ...]  keyed by table
Infra::getSchemaClasses();      // keyed by table
Infra::getControllerClasses();  // ['App\Controller\HomeController', ...]
Infra::getPipelineClasses();
Infra::getFilterClasses();
Infra::getQueueJobsClasses();
Infra::getTemplateNames();      // .twig/.html files under template/, grouped by directory

Infra::getRouteFiles();         // absolute file paths
Infra::getHookFiles();
Infra::getFunctionFiles();

Infra::get('policies');         // any class-map resource, e.g. one you declare
```

Or go straight to the registry:

```php
use Laika\Service\Resource;

Resource::getClasses('policies');       // class names, checked to exist and satisfy the contract
Resource::getFiles('routes');           // file paths
Resource::getResources('models');       // raw list, no validation
Resource::names();                      // every registered resource name
Resource::has('policies');
Resource::definitions('models');        // where they come from
```

## Adding Your Own Resource Type

Declare it in your **root `composer.json`**, under `extra.laika.resources` — the same block packages use. There is no config file and no framework class to edit.

```json
{
    "extra": {
        "laika": {
            "resources": {
                "policies": {
                    "path": "lf-app/Policy",
                    "namespace": "App\\Policy",
                    "contract": "App\\Contract\\PolicyInterface"
                }
            }
        }
    }
}
```

| Key | Meaning |
|---|---|
| `path` | Directory, relative to the project root (or absolute) |
| `namespace` | Base namespace matching that directory, PSR-4 style. **Omit it** to collect file paths instead of class names — that's what `routes` and `hooks` do |
| `contract` | Optional interface or base class. Every class in the directory must extend or implement it, or you get a named error pointing at the offender |

A bare string is shorthand for `{"path": ...}`. Subdirectories become namespace segments: `lf-app/Policy/Admin/AuditPolicy.php` resolves to `App\Policy\Admin\AuditPolicy`.

The root manifest is read straight from disk, so an edit takes effect immediately — **no `composer install` or `dump-autoload` needed** (unless the [compiled manifest](#caching-in-production) is in use). Check it with:

```bash
php laika resource:list --name=policies
```

A name your application declares **replaces the framework default** for that name — declaring `models` with a different path moves your app's models; it doesn't add a second location. It never hides what packages contribute: `models` stays the union of your directory and the packages' models.

## Declaring Resources From a Package

Package authors declare resources in their own `composer.json`. Relay providers are declared the same way, as a directory of `RelayProvider` classes:

```json
{
    "extra": {
        "laika": {
            "resources": {
                "relays":  { "path": "src/Relay",  "namespace": "Acme\\Blog\\Relay" },
                "models":  { "path": "src/Model",  "namespace": "Acme\\Blog\\Model" },
                "schemas": { "path": "src/Schema", "namespace": "Acme\\Blog\\Schema" },
                "routes":  "routes"
            }
        }
    }
}
```

Paths are relative to the package root. **That is the entire integration** — no bootstrap file, no `files` autoload entry, no code. The framework reads `extra.laika.resources` from every installed package via `vendor/composer/installed.json`.

Because that data comes from `installed.json`, it's a snapshot taken at install time. If you edit a package's `composer.json` in place inside `vendor/`, run `composer install` (or `composer update`) so the snapshot catches up — `composer dump-autoload` alone does **not** refresh it.

For a package that isn't installed by Composer, `Resource::package()` reads a manifest directly:

```php
Resource::package(__DIR__ . '/../composer.json');
```

## Registering at Runtime

For anything dynamic, register a directory directly — from a hook file. This is the escape hatch, not the normal path:

```php
use Laika\Service\Resource;

Resource::register('routes', APP_PATH . '/modules/blog/routes');
Resource::register('policies', APP_PATH . '/modules/blog/Policy', 'Blog\\Policy', PolicyInterface::class);
```

```php
Resource::register(string $name, string $path, ?string $base_namespace = null, ?string $contract = null): void
```

Registering the same directory twice is a no-op. A directory that doesn't exist is recorded rather than fatal — it resolves to nothing, and `resource:list` marks it `MISSING`.

## CLI

```bash
php laika resource:list                  # every resource: source, namespace, path, entry count
php laika resource:list --name=models    # just one
php laika app:cache                      # compile to lf-storage/cache/resources.php
php laika app:clear                      # delete the compiled manifest
```

`resource:list` also validates: it reports any class that won't load or doesn't satisfy its contract, and exits non-zero if it finds one. It's a useful thing to run in CI.

## Caching in Production

`php laika app:cache` writes every resolved resource to `lf-storage/cache/resources.php`. When `DEBUG` is `false` and that file exists, the framework loads it and skips discovery entirely — no `installed.json` parsing, no directory walking.

- `php laika app:sync` always rebuilds the manifest, and it runs on every `composer install`/`update`/`dump-autoload` — so a manifest normally exists.
- The manifest is a snapshot. **Re-run `php laika app:cache` after adding, moving or renaming a component**, or with `DEBUG` off the new class won't be seen.
- With `DEBUG` on, the manifest is ignored, so development never needs it cleared.

## Errors You Might See

| Message | Cause |
|---|---|
| `Resource [models] expected class [App\Model\Foo]` | File name and class name disagree, or the namespace doesn't match the directory |
| `[App\Job\Foo] is not a child class of [...]` | The class doesn't satisfy the `contract` declared for its resource |
| `Resource [routes] holds file paths, not class names` | `getClasses()` on a resource with no `namespace` — use `getFiles()` |
| `Invalid Resource Name [...]` | Names must start with a letter and contain only letters, digits or underscores |
| `Invalid Resource Class Base Namespace [...]` | Use backslash-separated PSR-4 segments (`App\Model`), not slashes |
