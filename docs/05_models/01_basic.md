# Models & Database

Laika's data layer is [`laikait/laika-model`](https://github.com/laikait/laika-model) — a PDO query builder and schema builder supporting MySQL, MariaDB, PostgreSQL, SQLite, SQL Server, Oracle, and Firebird. This page covers how it's wired into a Laika app; see the [laika-model README](https://github.com/laikait/laika-model) for the exhaustive API reference (joins, aggregates, transactions, chunking, casts, full schema builder, etc.).

## Connections

Connections are configured in [`lf-config/database.php`](../01_getting-started/03_configuration.md#lf-configdatabasephp) — each top-level key is a connection name:

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

Models resolve the `'default'` connection unless told otherwise. Connections are created lazily — nothing connects until a model or schema call actually needs it.

## Defining a Model

```bash
php laika model:make User --table=users --id=id --uid=uid
```

```php
namespace App\Model;

use Laika\Model\Model;

class UsersModel extends Model
{
    protected string $table          = 'users';
    protected string $id             = 'id';
    protected string $uid            = 'uid';
    protected string $connection     = 'default';
    protected bool   $softDelete     = false;
    protected string $deletedAtColumn = 'deleted_at';

    /** @var array<string,string> */
    protected array $casts = [
        'id'     => 'int',
        'active' => 'bool',
    ];
}
```

## Basic CRUD

```php
$users = new UsersModel();

// Select
$all    = $users->get();
$active = $users->where(['active' => 1])->get();
$one    = $users->where(['id' => 1])->first();

// Insert — returns the last inserted ID
$id = $users->insert(['name' => 'Alice', 'email' => 'alice@example.com']);

// Update — requires a WHERE clause
$users->where(['id' => $id])->update(['name' => 'Alice Smith']);

// Delete — requires a WHERE clause
$users->where(['id' => $id])->delete();
```

`update()` and `delete()` throw `InvalidArgumentException` if called without a `where()` — this is intentional, to prevent accidental full-table mutations.

## Defining a Schema

Schemas own table DDL and live in `lf-app/Schema`, one class per table, extending `Laika\Core\Abstracts\SchemaAbstract`:

```php
namespace App\Schema;

use Laika\Model\Schema\Schema;
use Laika\Model\Schema\Blueprint;
use Laika\Core\Abstracts\SchemaAbstract;

class UsersModelSchema extends SchemaAbstract
{
    protected string $table      = 'users';
    protected string $connection = 'default';

    public function up(): void
    {
        Schema::on($this->connection)->createIfNotExists($this->table, function (Blueprint $t) {
            $t->bigId('id');
            $t->string('uid');
            $t->string('email');
            $t->string('first_name');
            $t->string('last_name');
            $t->timestamp('deleted_at')->nullable()->default(null);

            $t->unique('email');
        });
    }
}
```

## Running Migrations

```bash
php laika app:migrate               # every discovered schema
php laika app:migrate --table=UsersModelSchema   # just one
```

`app:migrate` discovers every `App\Schema` class automatically, plus any schema classes shipped by installed packages (e.g. `laikait/laika-auth`'s `auth_tokens` table, `laikait/laika-queue`'s job tables) — no manual wiring needed.

## Security

- Every value is bound via PDO prepared statements — never interpolated into SQL.
- Every identifier (table/column name) is validated and quoted per-driver.
- `where()`/`having()` operators are checked against a strict allowlist.
- `update()`/`delete()` require a `where()` clause.

## Full Reference

The [laika-model README](https://github.com/laikait/laika-model) covers everything not repeated here:

- Multiple/named connections, connection management (`Connection::has()`, `close()`, `driver()`, ...)
- All `where` variants (`whereIn`, `whereNot`, `whereGroup`, `between`, ...), joins, aggregates, ordering & pagination
- Soft deletes, `increment()`/`decrement()`, chunking, transactions, raw queries, `debug()`
- Type casting reference table
- Full schema builder — column types/modifiers, indexes, foreign keys, altering/dropping/renaming tables, custom grammars
- Query log (`Laika\Model\Log`)
- Per-driver DSN/type-mapping/LIMIT-OFFSET reference tables
