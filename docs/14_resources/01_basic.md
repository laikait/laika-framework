# Resources

A **resource** is a named directory whose PHP files the framework knows how to find — your models, controllers, jobs, pipelines, filters, schemas, routes and hooks are all resources. `Laika\Service\Resource` is the registry that maps each name to the directories that provide it; `Laika\Service\Infra` is the friendly reader on top of it.

Discovery is **lazy**. Registering a resource only records *where to look* — no directory is scanned until something actually asks for it, and every answer is memoised for the rest of the request.

## Reading resources

```php
use Laika\Service\Infra;

Infra::getModelClasses();       // ['users' => App\Model\UsersModel, ...]  keyed by table
Infra::getSchemaClasses();      // keyed by table
Infra::getControllerClasses();  // ['App\Controller\HomeController', ...]
Infra::getPipelineClasses();
Infra::getFilterClasses();
Infra::getQueueJobsClasses();

Infra::getRouteFiles();         // absolute file paths
Infra::getHookFiles();
Infra::getFunctionFiles();
```

Anything you declare yourself is read with `Infra::get()`:

```php
Infra::get('policies');         // ['App\Policy\PostPolicy', ...]
```

Or go straight to the registry:

```php
use Laika\Service\Resource;

Resource::getClasses('policies');       // class names, validated
Resource::getFiles('routes');           // file paths
Resource::getResources('models');       // raw list, no validation
Resource::names();                      // every registered resource name
Resource::has('policies');
Resource::definitions('models');        // where they come from
```

## Adding your own resource type

Declare it in your **root `composer.json`**, under `extra.laika.resources` — the same block a package uses. There is no config file and no framework class to edit.

The built-in types (`models`, `schemas`, `controllers`, `jobs`, `pipelines`, `filters`, `routes`, `hooks`) need no entry at all — they work out of the box, contracts included. You add a block only to introduce a new type or move an existing one.

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

Subdirectories are supported and become namespace segments: `lf-app/Policy/Admin/AuditPolicy.php` resolves to `App\Policy\Admin\AuditPolicy`.

The root manifest is read straight from disk, not from `vendor/composer/installed.json`, so an edit takes effect immediately — **no `composer install` or `dump-autoload` needed**. Confirm it with:

```bash
php laika resource:list --name=policies
```

A name your application declares **replaces the framework default** for that name — so declaring `models` with a different path moves your app's models, it does not add a second location. It never hides what vendor packages contribute, though: `models` stays the union of your directory and the models shipped by `laika-core`, `laika-auth` and `laika-session`.

## Declaring resources from a package

Package authors declare resources in their own `composer.json`. Relay providers are declared the same way, as a directory of `RelayProvider` classes:

```json
{
    "extra": {
        "laika": {
            "resources": {
                "relays":  { "path": "src/Relay",  "namespace": "Acme\\Blog\\Relay" },
                "models":  { "path": "src/Model",  "namespace": "Acme\\Blog\\Model" },
                "schemas": { "path": "src/Schema", "namespace": "Acme\\Blog\\Schema" }
            }
        }
    }
}
```

Paths are relative to the package root. **That is the entire integration** — no bootstrap file, no `files` autoload entry, no code. The framework reads `extra.laika.resources` from every installed package via `vendor/composer/installed.json`. It is the only such mechanism — the older `extra.laika.relays` key, a flat list of provider class names, was replaced by the `relays` resource above.

Because the data comes from `installed.json`, it is a snapshot taken at install time. If you edit a package's `composer.json` in place inside `vendor/`, run `composer install` (or `composer update`) so the snapshot catches up — `composer dump-autoload` alone does **not** refresh it.

For a package that is not installed by composer at all, `Resource::package()` reads a manifest directly:

```php
Resource::package(__DIR__ . '/../composer.json');
```

## Registering at runtime

For anything dynamic, register a directory directly. This is the escape hatch, not the normal path:

```php
use Laika\Service\Resource;

Resource::register('routes', __DIR__ . '/routes');
Resource::register('policies', __DIR__ . '/Policy', 'App\\Policy', PolicyInterface::class);
```

Registering the same directory twice is a no-op — resources never double up.

A directory that doesn't exist is recorded rather than fatal. It simply resolves to nothing, and `resource:list` marks it `MISSING`. This is deliberate: package bootstrappers run during composer's autoload, before the error handler exists, so throwing there would produce an uncatchable fatal instead of a readable message.

## CLI

```bash
php laika resource:list                  # every resource, its source, path and entry count
php laika resource:list --name=models    # just one
php laika resource:cache                 # compile to lf-storage/cache/resources.php
php laika resource:clear                 # remove the compiled manifest
```

`resource:list` also validates: it reports any class that won't load or doesn't satisfy its contract, and exits non-zero if it finds one. It's a useful thing to run in CI.

## Caching in production

`resource:cache` writes every resolved resource to `lf-storage/cache/resources.php`. When `DEBUG` is `false`, the framework loads that file and skips discovery entirely — no `installed.json` parsing, no config read, no directory walking.

```bash
php laika resource:cache
```

The manifest is a snapshot. Re-run the command after adding, moving or renaming a component, or the new file won't be seen. `php laika app:sync` rebuilds it automatically if one is already in place, and `app:sync` runs on `composer dump-autoload`.

With `DEBUG` set to `true` the manifest is ignored, so development never needs the cache cleared.

## Errors you might see

| Message | Cause |
|---|---|
| `Resource [models] expected class [App\Model\Foo]` | File name and class name disagree, or the namespace doesn't match the directory |
| `[App\Job\Foo] is not a child class of [...]` | The class doesn't satisfy the `contract` declared for its resource |
| `Resource [routes] holds file paths, not class names` | `getClasses()` on a resource with no `namespace` — use `getFiles()` |
| `Invalid Resource Name [...]` | Names must start with a letter and contain only letters, digits or underscores |
| `Invalid Resource Class Base Namespace [...]` | Use backslash-separated PSR-4 segments (`App\Model`), not slashes |
