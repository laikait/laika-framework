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
| `php laika controller:make <name> [--method=index]` | Create a controller class |
| `php laika controller:list` | List registered controller classes |
| `php laika controller:remove <name>` | Delete a controller class |
| `php laika controller:rename --old=<name> --new=<name>` | Rename a controller class and its file |

## Models & Schemas

| Command | Description |
|---|---|
| `php laika model:make <name> [--table=table] [--id=id] [--uid=uid] [--connection=name]` | Create a model class (and matching schema) |
| `php laika model:list` | List registered model classes |
| `php laika model:remove <name>` | Delete a model class |
| `php laika model:rename <old> <new>` | Rename a model class, its table, and primary/UID column references |
| `php laika schema:list` | List registered schema classes discoverable by `app:migrate` |

## Routes

| Command | Description |
|---|---|
| `php laika route:make <name> [--method=get] [--file=web]` | Add a route, appended to `lf-routes/web.php` (or `--file=`) |
| `php laika route:list [--method=get]` | List registered routes, optionally filtered by HTTP method |

## Pipelines & Filters

| Command | Description |
|---|---|
| `php laika pipeline:make <name>` | Create a pipeline class in `App\Pipeline` |
| `php laika pipeline:list` | List registered pipeline classes |
| `php laika pipeline:remove <name>` | Delete a pipeline class |
| `php laika pipeline:rename <old> <new>` | Rename a pipeline class |
| `php laika filter:make <name>` | Create a filter class in `App\Filter` |
| `php laika filter:list` | List registered filter classes |
| `php laika filter:remove <name>` | Delete a filter class |
| `php laika filter:rename --old=<name> --new=<name>` | Rename a filter class |

## Services & Relay

| Command | Description |
|---|---|
| `php laika service:make --name=<ServiceClass> --class=<RelayClass>` | Create an `App\Service` static-proxy class bound to a Relay accessor |
| `php laika service:remove <name>` | Delete a service class |
| `php laika relay:list` | List registered Relay classes |

## Templates

| Command | Description |
|---|---|
| `php laika template:make <name> [--ext=twig] [--path=path]` | Create a new Twig template under `template/` |
| `php laika template:list` | List existing template files |

## Secrets

| Command | Description |
|---|---|
| `php laika secret:fix [--byte=32]` | Ensure `lf-storage/keys/app.key` exists and is valid; regenerates it only if missing or malformed. Byte range: 16–64. Runs automatically on `composer install`/`update` via the `post-autoload-dump` script |
| `php laika secret:generate [--byte=32]` | Force-generate a brand-new secret key, overwriting the existing one |

## Help

```bash
php laika help
```

Lists every registered command with its signature and description — the authoritative source if a package adds commands not listed here.

---

## Example workflow

```bash
# Scaffold a User resource
php laika model:make User --table=users --id=id --uid=uid
php laika controller:make UserController --method=index
php laika route:make users

# Register the schema and create the table
php laika app:migrate --table=UsersModelSchema

# Add middleware
php laika pipeline:make Authenticate
php laika filter:make LogAccess

# Verify what got registered
php laika route:list
php laika model:list
```
