# Query Builder

Every `Laika\Model\Model` subclass is a fluent query builder. Chain clauses, then finish with a terminal method (`get()`, `first()`, `count()`, `insert()`, `update()`, `delete()`, ...). The builder resets after each terminal call.

```php
use App\Model\UsersModel;

$rows = (new UsersModel())
    ->select(['id', 'email', 'first_name'])
    ->where(['is_active' => 'yes'])
    ->whereIn('role', ['admin', 'staff'])
    ->order('created_at', 'DESC')
    ->limit(20)->page(2)
    ->get();
```

## Selecting

| Method | |
|---|---|
| `select(array\|string\|Expression\|null $columns = null): Static` | Columns; `'a AS b'` and `'t.*'` are fine. Default `*`. |
| `distinct(): Static` | `SELECT DISTINCT` |
| `table(string $table): Static` | Query a different table with this model's connection |

For SQL expressions, wrap them in `Laika\Model\Schema\Expression` — plain strings are treated as identifiers and validated:

```php
use Laika\Model\Schema\Expression;

$orders->select([new Expression('SUM(total) AS revenue'), 'customer_id'])
       ->groupBy('customer_id')
       ->get();
```

## Where Clauses

`where()` takes an **array** of `column => value` pairs. The operator applies to every pair, and a multi-key array becomes one parenthesised group:

```php
$users->where(['is_active' => 'yes'])->get();                     // is_active = ?
$users->where(['is_active' => 'yes', 'role' => 'admin'])->get();  // (is_active = ? AND role = ?)
$users->where(['age' => 18], '>=')->get();                        // age >= ?
$users->where(['email' => '%@example.com'], 'LIKE')->get();
$users->where(['email' => $login, 'username' => $login], '=', 'OR')->first(); // (email = ? OR username = ?)
```

| Method | SQL |
|---|---|
| `where(array $where, string $operator = '=', string $compare = 'AND')` | `col op ?` — operators: `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE` |
| `whereNot(array $where, string $compare = 'AND')` | `col != ?` |
| `whereIn(string $column, array $values, string $compare = 'AND')` | `col IN (...)` |
| `whereNotIn(string $column, array $values, string $compare = 'AND')` | `col NOT IN (...)` |
| `isNull(string $column, string $compare = 'AND')` | `col IS NULL` |
| `notNull(string $column, string $compare = 'AND')` | `col IS NOT NULL` |
| `between(string $column, mixed $value1, mixed $value2, string $compare = 'AND')` | `col BETWEEN ? AND ?` |
| `whereGroup(callable $callback, string $compare = 'AND')` | `( ... )` built by the callback |

`$compare` joins the clause to the previous ones (`AND` or `OR`).

```php
// status = 'open' AND (priority = 'high' OR assignee_id IS NULL)
$tickets->where(['status' => 'open'])
        ->whereGroup(function ($q) {
            $q->where(['priority' => 'high'])->isNull('assignee_id', 'OR');
        })
        ->get();
```

## Joins, Grouping, Having

```php
$orders->select(['orders.id', 'users.email'])
       ->join('users', 'users.id', '=', 'orders.user_id', 'INNER')
       ->get();

$orders->select(['customer_id', new Expression('COUNT(*) AS n')])
       ->groupBy('customer_id')
       ->having('n', '>', 5)
       ->get();
```

| Method | |
|---|---|
| `join(string $table, string $first, string $operator, string $second, string $type = 'LEFT')` | Types: `LEFT`, `RIGHT`, `INNER` |
| `groupBy(string ...$columns)` | |
| `having(string $column, string $operator, mixed $value)` | Same operator allowlist as `where()` |

## Ordering and Pagination

| Method | |
|---|---|
| `order(string $column, string $direction = 'ASC')` | `ASC` or `DESC`. (It's `order()`, not `orderBy()`.) |
| `limit(int\|string $limit)` | |
| `page(int\|string $page = 1)` | Offset = `(page - 1) × limit`. Requires `limit()`; SQL Server and Oracle also need `order()`. |

A paginated list with the current `?page=`:

```php
$perPage = 20;
$page    = page_number();                                  // ?page=, at least 1

$rows  = $users->order('id', 'DESC')->limit($perPage)->page($page)->get();
$total = $users->count();
$pages = (int) ceil($total / $perPage);
```

In Twig, {% raw %}`{{ page.next }}`{% endraw %} and {% raw %}`{{ page.previous }}`{% endraw %} give the neighbouring page URLs.

## Reading

| Method | Returns |
|---|---|
| `get(): array` | All matching rows |
| `cursor(): \Generator` | Rows one at a time — for result sets too large for memory |
| `first(): array\|object\|null` | The first row, or `null`. **Requires a `where()`.** |
| `firstOrFail(): array\|object` | The first row, or throws `ModelException` |
| `find(int\|string $id): array\|object\|null` | By `$id` for numeric values, by `$uid` otherwise |
| `firstOrCreate(array $where, array $data = []): array\|object\|null` | Find, or insert `$where + $data` and return it (not atomic) |
| `count(): int` | Number of matching rows (ignores `limit`/`page`) |
| `exists(): bool` | `count() > 0` |
| `pluck(string $column): array` | One column's values |
| `chunk(int $size, callable $callback): void` | Rows in batches; return `false` from the callback to stop |

```php
foreach ($users->where(['is_active' => 'yes'])->cursor() as $user) {
    // one row in memory at a time
}

$users->chunk(500, function (array $rows) {
    foreach ($rows as $row) { /* ... */ }
});
```

### Caching Results

With `query.enabled` on in `lf-config/cache.php`, add `remember()` to cache a read:

| Method | Does |
|---|---|
| `remember(?int $ttl = null): static` | Cache this query's `get()` or `count()` (and so `first()`, `find()`, `pluck()`). No effect when query caching is off |
| `Model::forgetQueryCache(string $table, ?string $connection = null): void` | Invalidate every cached query on a table |

Writes through the model invalidate the table and every cached query that joined it, once the transaction commits. Raw `execute()` writes do not — call `forgetQueryCache()` after them. `cursor()` and reads inside a transaction are never cached. See [Caching → Query Results](../20_cache/01_basic.md#query-results).

## Writing

| Method | Returns |
|---|---|
| `insert(array $data): string\|false` | The new id (a string; `''` on Oracle/Firebird). Pass a list of rows for a multi-row insert. |
| `update(array $data): int` | Affected rows. Requires `where()`. |
| `delete(): int` | Affected rows. Requires `where()`. Soft-deletes when `$softDelete` is on. |
| `increment(string $column, int $number = 1): int` | Requires `where()`; can't target the primary key |
| `decrement(string $column, int $number = 1): int` | Same |
| `restore(): int` | Sets `$deletedAtColumn` to `NULL`. Requires `where()`. |

```php
$posts->where(['id' => 7])->increment('views');
```

## Soft-Delete Scopes

| Method | |
|---|---|
| `withTrash()` | Include soft-deleted rows |
| `onlyTrashed()` | Only soft-deleted rows |
| `withoutTrash()` | The default: hide them |
| `soft(bool $enable = true)` | Turn soft-delete behaviour on/off for this query |

## Transactions

```php
$orderId = $orders->transaction(function ($m) use ($cart) {
    $id = $m->insert(['user_id' => $cart->userId, 'total' => $cart->total]);
    (new OrderItemsModel())->insert($cart->itemsFor($id));
    return $id;
});
```

`transaction(callable $callback): mixed` passes the model to the callback and returns its result. Any exception rolls back and is re-thrown unchanged. Nested calls on the same connection use savepoints.

## Raw SQL and Debugging

| Method | |
|---|---|
| `execute(string $sql, ?array $bindings = null): \PDOStatement` | Run raw SQL with bound values |
| `debug(): string` | The SQL the current chain would run, values inlined — for inspection only; doesn't reset the chain |
| `pdo(): \PDO` / `driver(): string` | The connection and its canonical driver name |
| `uid(): string` | 32 random hex characters in four groups of 8 (not an RFC UUID — use `Laika\Core\Generator\Uid::make()` for that) |

```php
echo $users->where(['id' => 5])->debug();  // SELECT * FROM `users` WHERE `id` = 5;
$stmt = $users->execute('SELECT * FROM users WHERE email LIKE ?', ['%@example.com']);
```

## Casts

`$casts` converts columns as rows are read. `NULL` stays `NULL`; an unknown cast type throws.

| Cast | Result |
|---|---|
| `int` / `integer` | `int` |
| `float` / `double` | `float` |
| `decimal` | `string` (avoids float rounding) |
| `bool` / `boolean` | `bool` — `0`, `'0'`, `''`, `'false'`, `'f'`, `'off'`, `'no'` are false |
| `array` / `json` | `json_decode(..., true)` |
| `serialize` | `unserialize()`, objects disabled |
| `string` | `string` |

Casts apply on read only — encode JSON yourself when writing.

## Connection API

`Laika\Model\Connection` is the static connection registry:

| Method | |
|---|---|
| `add(array $config, ?string $name = null): void` | Register a config. **Always pass `$name`** — without it the config goes under the default name. |
| `get(?string $name = null): PDO` / `has(?string $name = null): bool` | The PDO (connecting if needed) / whether registered |
| `config(?string $name = null): array` / `names(): array` | Registered configs |
| `setDefault(string $name): void` / `getDefault(): string` | The default connection name |
| `driver(?string $name = null): string` | Canonical driver name |
| `close(?string $name = null)` / `reconnect(?string $name = null): PDO` / `closeAll()` / `purge()` | Lifecycle — useful in long-running workers |
| `applyTimezone(string $timezone, ?string $name = null): void` | Set the session time zone |
| `beginTransaction()` / `commit()` / `rollBack()` / `transactionLevel(?string $name = null)` | Manual transactions (savepoint-aware) |

## Schema Builder

`Laika\Model\Schema\Schema::on(?string $connection = null)` returns a builder for a connection:

| Method | |
|---|---|
| `create(string $table, \Closure $callback, array $options = []): void` | `CREATE TABLE` |
| `createIfNotExists(string $table, \Closure $callback): void` | |
| `table(string $table, \Closure $callback): void` | `ALTER TABLE` — **adds** columns only |
| `drop(string $table)` / `dropIfExists(string $table)` | |
| `rename(string $from, string $to)` | Not supported on Firebird |
| `hasTable(string $table): bool` / `hasColumn(string $table, string $column): bool` | Inspection |
| `statement(string $sql): bool` | Raw DDL |
| `disableForeignKeyChecks()` / `enableForeignKeyChecks()` | |
| `static registerGrammar(string $driver, string $grammarClass)` | Custom grammar |

```php
Schema::on()->table('users', function (Blueprint $t) {
    $t->string('phone', 30)->nullable()->default(null);
});
```

### Column Types (`Blueprint`)

| Method | Column |
|---|---|
| `id($name = 'id')` / `bigId($name = 'id')` | Auto-increment primary key (INT / BIGINT) |
| `integer`, `bigInteger`, `smallInteger`, `tinyInteger`, `unsignedInteger`, `unsignedBigInteger` | Integers |
| `float`, `double`, `decimal($name, $precision = 8, $scale = 2)`, `boolean` | Numbers |
| `string($name, $length = 255)`, `char($name, $length = 36)`, `text`, `mediumText`, `longText` | Text |
| `enum($name, array $values)`, `set($name, array $values)` | Native on MySQL, `VARCHAR + CHECK` / `TEXT` elsewhere |
| `json`, `serialize`, `binary`, `blob`, `tinyBlob`, `mediumBlob`, `longBlob` | Structured / binary |
| `date`, `time`, `dateTime`, `timestamp` | Dates. **`timestamp()` defaults to `CURRENT_TIMESTAMP`.** |
| `timestamps($created = 'created_at', $updated = 'updated_at')` | Both columns; `updated_at` is nullable and **not** maintained for you |
| `deleted($column = 'deleted_at')` | Nullable, default `NULL`, indexed — for soft deletes |
| `uid($name = 'uid')` | UUID column with a unique index |

### Modifiers

Chain onto a column: `nullable(bool $value = true)`, `default(mixed $value)`, `unsigned(bool $value = true)`, `autoIncrement(bool $value = true)`, `comment(string $comment)`.

### Indexes and Foreign Keys

```php
$t->primary(['order_id', 'product_id']);
$t->unique('email');
$t->index(['last_name', 'first_name'], 'name_idx');

$t->foreign('user_id')->reference('id')->on('users')->onDelete('CASCADE')->onUpdate('CASCADE');
```

> The method is **`reference()`**, singular. The laika-model README's `->references('id')` doesn't exist and is a fatal error.

## Query Log

Every query run by a model or schema is recorded in `Laika\Model\Log`:

```php
use Laika\Model\Log;

Log::get();    // ['default' => ['SELECT ...', 'INSERT ...'], ...]
Log::count();
```

The log grows for the life of the process and can't be cleared — keep that in mind in long-running workers.

## See Also

- [Models & Database](01_basic.md) — models, schemas, migrations
- [Backup & Convert](03_backup-and-convert.md)
