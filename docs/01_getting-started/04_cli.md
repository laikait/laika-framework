# CLI Reference

Every Laika project ships with a `laika` executable, generated automatically by `laikait/laika-cli` the first time Composer builds the autoloader. Run it with `php laika <command>`, or `php laika help` for the built-in command list.

```bash
php laika help
```

## Global install (optional)

Prefer a single `laika` command available in every project?

```bash
composer global require laikait/laika-cli
```

Make sure Composer's global `vendor/bin` is on your `PATH`, then run `laika` (no `php` prefix) from inside any Laika project directory — it detects the project by walking up from your working directory until it finds `lf-boot/app.php`.

---

## Application

| Command | Description |
|---|---|
| `php laika app:start [--host=127.0.0.1] [--port=8000]` | Start the PHP built-in dev server (auto-picks the next free port if busy) |
| `php laika app:sync` | Clear cache junk files, ensure `uploads/` exists, regenerate `.htaccess` if missing |
| `php laika app:migrate [--table=name]` | Run `up()` (and seed `seed()`) on every discovered `App\Schema` (and package) schema class, or just one |

## Controllers

| Command | Description |
|---|---|
| `php laika make:controller <name> [--method=index]` | Create a controller class |
| `php laika list:controller` | List registered controller classes |
| `php laika remove:controller <name>` | Delete a controller class |
| `php laika rename:controller --old=<name> --new=<name>` | Rename a controller class and its file |

## Models & Schemas

| Command | Description |
|---|---|
| `php laika make:model <name> [--table=table] [--id=id] [--uid=uid] [--connection=name]` | Create a model class (and matching schema) |
| `php laika list:model` | List registered model classes |
| `php laika remove:model <name>` | Delete a model class |
| `php laika rename:model <old> <new>` | Rename a model class, its table, and primary/UID column references |
| `php laika list:schema` | List registered schema classes discoverable by `app:migrate` |

## Routes

| Command | Description |
|---|---|
| `php laika make:route <name>` | Scaffold a new route file under `lf-routes/` |
| `php laika list:route [--method=get]` | List registered routes, optionally filtered by HTTP method |

## Pipelines & Filters

| Command | Description |
|---|---|
| `php laika make:pipeline <name>` | Create a pipeline class in `App\Pipeline` |
| `php laika list:pipeline` | List registered pipeline classes |
| `php laika remove:pipeline <name>` | Delete a pipeline class |
| `php laika rename:pipeline <old> <new>` | Rename a pipeline class |
| `php laika make:filter <name>` | Create a filter class in `App\Filter` |
| `php laika list:filter` | List registered filter classes |
| `php laika remove:filter <name>` | Delete a filter class |
| `php laika rename:filter --old=<name> --new=<name>` | Rename a filter class |

## Services & Relay

| Command | Description |
|---|---|
| `php laika make:service --name=<ServiceClass> --class=<RelayClass>` | Create an `App\Service` static-proxy class bound to a Relay accessor |
| `php laika remove:service <name>` | Delete a service class |
| `php laika list:relay` | List registered Relay classes |

## Templates

| Command | Description |
|---|---|
| `php laika make:template <name> [--ext=twig] [--path=path]` | Create a new Twig template under `template/` |
| `php laika list:template` | List existing template files |

## Secrets

| Command | Description |
|---|---|
| `php laika fix:secret [--byte=32]` | Ensure `lf-storage/keys/app.key` exists and is valid; regenerates it only if missing or malformed. Byte range: 16–64. Runs automatically on `composer install`/`update` via the `post-autoload-dump` script |
| `php laika generate:secret [--byte=32]` | Force-generate a brand-new secret key, overwriting the existing one |

## Help

```bash
php laika help
```

Lists every registered command with its signature and description — the authoritative source if a package adds commands not listed here.

---

## Example workflow

```bash
# Scaffold a User resource
php laika make:model User --table=users --id=id --uid=uid
php laika make:controller UserController --method=index
php laika make:route users

# Register the schema and create the table
php laika app:migrate --table=UsersModelSchema

# Add middleware
php laika make:pipeline Authenticate
php laika make:filter LogAccess

# Verify what got registered
php laika list:route
php laika list:model
```
