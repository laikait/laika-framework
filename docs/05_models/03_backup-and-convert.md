# Backup & Convert

laika-model ships two database tools: **`Backup`** backs up and restores a connection, and **`Converter`** translates SQL between database engines and can copy a live database from one engine to another.

Both run from PHP, so the natural place for them is a [custom CLI command](../01_getting-started/04_cli.md#writing-your-own-commands) or a [queue job](../12_queue/01_basic.md) — not a web request.

## Backup

```php
use Laika\Model\Backup;

$backup = new Backup('default');                 // a connection name; default = the default connection

$backup->create(APP_PATH . '/lf-storage/backups/db-' . date('Ymd-His') . '.sql');
$backup->dump(APP_PATH . '/lf-storage/backups/db.sql');   // always plain SQL
$backup->restore(APP_PATH . '/lf-storage/backups/db.sql');
```

| Method | Does |
|---|---|
| `__construct(?string $connection = null)` | Uses a registered connection |
| `create(string $path): string` | The engine's native backup format — a `mysqldump` file, a copy of the SQLite file, a SQL Server `.bak`, a Firebird `gbak` archive. Returns the path. |
| `dump(string $path): string` | Plain `CREATE TABLE`/`INSERT` SQL. MySQL/MariaDB, PostgreSQL, SQLite and SQL Server only. Returns the path. |
| `restore(string $path): void` | Restores a file produced by `create()` |

Failures throw `Laika\Model\Exceptions\BackupException`.

**Requirements.** MySQL and PostgreSQL backups shell out to `mysqldump`/`mysql` and `pg_dump`/`psql`; SQL Server uses `sqlcmd`; Firebird uses `gbak`. The tools must be installed and on the `PATH`, and PHP's `exec()` must be enabled — hardened PHP-FPM pools often disable it, which is another reason to run backups from the CLI.

> **Credentials on the command line.** For MySQL and SQL Server the password is passed as a command-line argument, visible in the host's process list. PostgreSQL uses the `PGPASSWORD` environment variable instead. Keep this in mind on shared machines.

## Converter

Translate SQL written for one engine into another — a single statement, a `.sql` file, or a multi-gigabyte dump.

```php
use Laika\Model\Converter;

$converter = new Converter(from: 'mysql', to: 'pgsql');

echo $converter->convert("CREATE TABLE `t` (`id` int unsigned NOT NULL AUTO_INCREMENT)");
// CREATE TABLE "t" (
//   "id" SERIAL NOT NULL
// );
```

Driver aliases work as in connection config (`mariadb`, `postgres`, ...). Targets: `mysql`, `pgsql`, `sqlite`, `sqlsrv`, `oci`, `firebird`. Oracle and Firebird are targets only.

| Method | |
|---|---|
| `__construct(string $from, string $to, bool $strict = false)` | `$strict` turns every warning into a `ConverterException` |
| `static between(string $fromConnection, string $toConnection, bool $strict = false): self` | Dialects read from two registered connections |
| `convert(mixed $source): string` | The whole result as a string |
| `stream(mixed $source): \Generator` | One converted statement at a time — constant memory |
| `convertFile(string $source, string $destination): int` | File to file; returns the statement count |
| `apply(mixed $source, ?string $connection = null): int` | Execute the converted SQL against a connection (foreign-key checks off meanwhile) |
| `migrate(?string $fromConnection = null, ?string $toConnection = null, ?string $keep = null): int` | Dump a live source, convert, and execute against the target |
| `report(): Report` | Warnings and statement counts |
| `reset(): void` | Reuse the instance for another source |

A source may be a SQL string, a file path, an open resource or an `SplFileObject`.

### Moving a Live Database to Another Engine

```php
use Laika\Model\{Connection, Converter};

Connection::add(config('database', 'legacy'), 'legacy');   // e.g. MySQL
Connection::add(config('database', 'default'), 'default'); // e.g. PostgreSQL

$executed = Converter::between('legacy', 'default')
    ->migrate(keep: APP_PATH . '/lf-storage/migration.sql'); // keep the intermediate SQL
```

> **The target is overwritten.** The generated SQL drops each table before recreating it. Point `migrate()` at an empty database, or one you have backed up.

The source is exported with `Backup::dump()`, so the source must be MySQL/MariaDB, PostgreSQL, SQLite or SQL Server (with `mysqldump`/`pg_dump` installed for the first two).

### The Report

The converter is best-effort: it converts what it can and records the rest.

```php
$report = $converter->report();

echo $report->summary();
// 36 statement(s) [create_table=2, insert=2, ...], 2 warning(s)

foreach ($report->warnings() as $w) {
    echo "#{$w->ordinal} [{$w->level}] {$w->reason}\n";
}
```

Warning levels: `lossy` (converted, but approximated), `passthrough` (not understood, emitted unchanged), `skipped` (deliberately dropped). Other `Report` methods: `hasWarnings()`, `warningCount()`, `warningsOfLevel(string $level)`, `statementCount()`, `counts()`.

### What Converts — and What Doesn't

Converts: `CREATE TABLE` (types, defaults, keys, single-column foreign keys), `CREATE INDEX`, `INSERT` (with literal re-encoding — escapes, hex blobs, booleans, zero dates), PostgreSQL `COPY ... FROM stdin`, `DROP TABLE`, `ALTER TABLE ... ADD CONSTRAINT`, and auto-increment/`ENUM` differences.

Doesn't convert: views, triggers, stored procedures, dialect-specific expressions inside `SELECT`, partitioning, generated columns, composite foreign keys, `CHECK`/`FULLTEXT`/`SPATIAL` constraints. Each is reported.

Take MySQL dumps with `mysqldump --hex-blob`, or binary columns arrive as text. A PostgreSQL → SQLite migration loses primary and foreign keys (SQLite can't add constraints afterwards); the losses are listed in the report.

The [laika-model README](https://github.com/laikait/laika-model#sql-converter) has the full conversion rules.

## See Also

- [Models & Database](01_basic.md)
- [Deployment](../13_deployment/01_basic.md) — `disable_functions` and PHP-FPM
