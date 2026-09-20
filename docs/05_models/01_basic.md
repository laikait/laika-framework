# Models & Database

Laika's data layer is The [Model module](https://github.com/laikait/laika-engine/tree/main/docs/model) of `laikait/laika-engine` — a PDO query builder and schema builder for MySQL, MariaDB, PostgreSQL, SQLite, SQL Server, Oracle and Firebird. This page covers setting up models, schemas and migrations; the [Query Builder](02_query-builder.md) page is the full method reference.

```php
use App\Model\UsersModel;

$users = new UsersModel();

$active = $users->where(['is_active' => 'yes'])->order('id', 'DESC')->limit(20)->get();
$one    = $users->find(5);
$id     = $users->insert(['email' => 'ann@example.com', 'first_name' => 'Ann']);
$users->where(['id' => $id])->update(['first_name' => 'Anne']);
```

## Connections

Connections are defined in [`lf-config/database.php`](../01_getting-started/03_configuration.md#lf-configdatabasephp), one per top-level key:

```php
return [
    'default' => [
        'driver'   => 'mysql',
        'host'     => 'localhost',
        'port'     => 3306,
        'database' => 'test',
        'username' => 'root',
        'password' => '',
    ],
];
```

A connection is registered from this file the first time a model or schema asks for it. Note that **creating a model opens the connection** — `new UsersModel()` connects in its constructor.

### Connection Keys

| Key | Drivers | Notes |
|---|---|---|
| `driver` | all | `mysql`/`mariadb`, `pgsql`/`postgres`, `sqlite`/`sqlite3`, `sqlsrv`, `oci`/`oracle`, `firebird`/`ibase` |
| `host`, `port` | all but SQLite | Default host `127.0.0.1`; default port per driver (3306, 5432, 1433, 1521, 3050) |
| `database` | all | Database name, or for SQLite the file path (or `:memory:`) |
| `username`, `password` | all but SQLite | |
| `charset` | mysql, oci, firebird | Default `utf8mb4` (mysql), `AL32UTF8` (oci), `UTF8` (firebird) |
| `timezone` | mysql | Session time zone, e.g. `'+00:00'` |
| `unix_socket` | mysql | Local socket instead of host/port |
| `sslmode` | pgsql | e.g. `require` |
| `encrypt`, `trust_server_certificate` | sqlsrv | Booleans |
| `service_name`, `tns` | oci | Instead of `database` |
| `options` | all | PDO attributes, overriding the defaults (`ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES => false`) |

Rows come back as **associative arrays** (`PDO::FETCH_ASSOC`).

### Multiple Connections

Add more keys to `database.php` and point a model at one with its `$connection` property or constructor argument:

```php
// lf-config/database.php
return [
    'default'   => [ /* ... */ ],
    'analytics' => ['driver' => 'pgsql', 'host' => 'reports.internal', 'database' => 'analytics', 'username' => 'app', 'password' => 'secret'],
];
```

```php
$rows = (new ReportModel('analytics'))->where(['year' => 2025])->get();
```

Each connection is registered under its own name the first time something uses it — a model, `Schema::on('analytics')`, the session `Init::model('analytics')` driver, a token guard with `'connection' => 'analytics'`, or `queue.connection`. A name missing from `database.php` throws.

> **Older packages** (laika-core 5.1.1, laika-model 4.0.6 and earlier) registered a second connection under the name `default` — overwriting the default connection — and then failed. On those versions, register extra connections yourself in a hook file: `Laika\Engine\Model\Connection::add(config('database', 'analytics'), 'analytics');`

## Defining a Model

```bash
php laika model:make UsersModel --table=users
```

This creates `lf-app/Model/UsersModel.php` **and** `lf-app/Schema/UsersModelSchema.php`.

```php
namespace App\Model;

use Laika\Engine\Model\Model;

class UsersModel extends Model
{
    protected string $table           = 'users';
    protected string $id              = 'id';        // primary key column
    protected string $uid             = 'uid';       // public id column, used by find('abc...')
    protected string $connection      = 'default';
    protected bool   $softDelete      = false;
    protected string $deletedAtColumn = 'deleted_at';

    /** @var array<string,string> */
    protected array $casts = [
        'id'          => 'int',
        'is_admin'    => 'bool',
        'preferences' => 'json',
    ];
}
```

| Property | Meaning |
|---|---|
| `$table` | Table name (required) |
| `$id` | Primary key column. `find(5)` looks it up. |
| `$uid` | Public identifier column. `find('3f2b…')` (any non-numeric value) looks it up. |
| `$connection` | Connection name from `database.php` |
| `$softDelete` | `delete()` sets `$deletedAtColumn` instead of deleting, and reads hide those rows |
| `$casts` | Convert columns when reading: `int`, `float`, `decimal` (string), `bool`, `json`/`array`, `serialize`, `string` |

Each model instance is a query builder. **The builder resets after every query**, so one instance can run many queries — but build one query at a time on it.

## Basic CRUD

```php
$users = new UsersModel();

// Read
$all   = $users->get();
$page  = $users->where(['is_active' => 'yes'])->order('id', 'DESC')->limit(20)->page(2)->get();
$user  = $users->find(5);                              // by id, or by uid for a non-numeric value
$user  = $users->where(['email' => $email])->first();  // null if not found
$user  = $users->where(['email' => $email])->firstOrFail(); // throws ModelException
$count = $users->where(['is_active' => 'yes'])->count();

// Create — returns the new id as a string
$id = $users->insert(['email' => 'ann@example.com', 'first_name' => 'Ann']);

// Several rows at once
$users->insert([
    ['email' => 'a@example.com', 'first_name' => 'A'],
    ['email' => 'b@example.com', 'first_name' => 'B'],
]);

// Update / delete — both return the affected row count
$users->where(['id' => $id])->update(['first_name' => 'Anne']);
$users->where(['id' => $id])->delete();
```

**Safety rails** — these throw `Laika\Engine\Model\Exceptions\ModelException` without a `where()`:

- `update()`, `delete()`, `increment()`, `decrement()`, `restore()` — to prevent accidental full-table changes
- `first()` and `firstOrFail()` — so `$users->order('id', 'DESC')->first()` fails; use `->limit(1)->get()[0] ?? null` for "the latest row"

`insert()` returns `''` on Oracle and Firebird, which have no auto-generated id to report.

## Soft Deletes

```php
class PostsModel extends Model
{
    protected string $table      = 'posts';
    protected bool   $softDelete = true;
}

$posts = new PostsModel();
$posts->where(['id' => 3])->delete();         // sets deleted_at = now
$posts->get();                                // trashed rows hidden
$posts->withTrash()->get();                   // include them
$posts->onlyTrashed()->get();                 // only them
$posts->where(['id' => 3])->restore();        // deleted_at = NULL
$posts->soft(false)->where(['id' => 3])->delete(); // really delete this once
```

> **The `deleted_at` column must default to `NULL`.** `Blueprint::timestamp()` adds `DEFAULT CURRENT_TIMESTAMP`, so `$t->timestamp('deleted_at')->nullable()` fills the column on every insert and **every row counts as deleted**. Use `$t->deleted()` (nullable, default null, indexed) — or add `->default(null)`. Schemas generated by laika-cli 3.0.9 and earlier have this problem; add `->default(null)` to their `deleted_at` column, and clear the column in rows already inserted.

## Schemas

A schema owns one table's DDL. Schemas live in `lf-app/Schema/` and extend `Laika\Engine\Model\Contract\SchemaAbstract`:

```php
namespace App\Schema;

use Laika\Engine\Model\Schema\Schema;
use Laika\Engine\Model\Schema\Blueprint;
use Laika\Engine\Model\Contract\SchemaAbstract;

class UsersModelSchema extends SchemaAbstract
{
    protected string $table = 'users';

    public function up(): void
    {
        Schema::on($this->connection)->createIfNotExists($this->table, function (Blueprint $t) {
            $t->bigId('id');
            $t->uid('uid');                          // UUID column + unique index
            $t->string('email');
            $t->string('first_name');
            $t->string('last_name')->nullable()->default(null);
            $t->enum('is_active', ['yes', 'no'])->default('no');
            $t->timestamps();                        // created_at, updated_at
            $t->deleted();                           // deleted_at, nullable, indexed

            $t->unique('email');
        });
    }

    public function seed(): void
    {
        // optional — runs after every schema's up(), on every app:migrate
    }
}
```

| Method | Called by |
|---|---|
| `up(): void` | Required. `php laika app:migrate` |
| `seed(): void` | Optional. `app:migrate`, after every schema's `up()` |
| `down(): void` | Optional; defaults to dropping `$table`. No CLI command calls it. |

- **Make `up()` and `seed()` safe to run repeatedly** — `app:migrate` runs them every time. Use `createIfNotExists()`, and check before inserting seed rows (`firstOrCreate()`).
- **A schema's `$connection` property picks its connection**, including under `app:migrate`: declare `protected string $connection = 'analytics';` and `Schema::on($this->connection)` in `up()` follows it. A name passed to the constructor overrides it: `(new ReportsSchema('staging'))->up()`. (laika-model 4.0.6 and earlier ignored the property and always used `'default'` unless you overrode the constructor.)

The full list of column types, modifiers, indexes and foreign keys is in [Query Builder → Schema Builder](02_query-builder.md#schema-builder).

## Running Migrations

```bash
php laika app:migrate                 # every discovered schema
php laika app:migrate --table=users   # one schema, by TABLE name
php laika schema:list                 # what app:migrate will run
```

`app:migrate` runs `up()` on every schema, then `seed()` on every schema, with foreign-key checks disabled around each call. It discovers every class in `lf-app/Schema/`, plus schemas that installed packages declare.

Some framework tables are **not** created by `app:migrate` — they install themselves on first use, which needs `CREATE` rights once:

| Table | Created by |
|---|---|
| `options`, `activities` | The Core module, the first time `Option`/`Activity` is used |
| `sessions` | `Init::model('default', install: true)` |
| `auth_tokens` | a token guard with `'install' => true` |
| `laika_queue_jobs`, `laika_failed_jobs` | nothing — create them yourself, see [Queue → Choosing a Driver](../12_queue/01_basic.md#choosing-a-driver) |

See [Deployment](../13_deployment/01_basic.md#database-tables) for creating them ahead of time.

## Security

- Every value is bound through PDO prepared statements — never interpolated into SQL.
- Every identifier (table, column) is validated against `/^[a-zA-Z_][a-zA-Z0-9_]*$/` and quoted per driver.
- `where()` and `having()` operators, join types and order directions are checked against allowlists.
- Raw SQL is only possible through `execute()` and `Schema\Expression` — keep user input out of both.

## CLI Reference

| Command | Description |
|---|---|
| `php laika model:make <name> [--table=] [--id=id] [--uid=uid] [--connection=default]` | Create a model and its schema |
| `php laika model:list` | List models, keyed by table |
| `php laika model:remove <name>` | Delete a model and its schema |
| `php laika model:rename --old=<name> --new=<name> [--table=] [--id=] [--uid=]` | Rename a model and its schema |
| `php laika schema:list` | List schemas, keyed by table |
| `php laika app:migrate [--table=<table>]` | Run schemas |

## See Also

- [Query Builder](02_query-builder.md) — every model, connection and schema method
- [Backup & Convert](03_backup-and-convert.md) — database backups and moving between engines
- [Model module docs](https://github.com/laikait/laika-engine/tree/main/docs/model) — per-driver type mapping and grammar details
