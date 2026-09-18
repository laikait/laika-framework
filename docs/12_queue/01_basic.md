# Queue

[`laikait/laika-queue`](https://github.com/laikait/laika-queue) runs slow work — sending mail, generating reports, calling third-party APIs — in a background `worker` process instead of the web request. It supports JSON, database and Redis drivers, delayed jobs, retries with backoff, and failed-job tracking.

```php
use App\Job\SendWelcomeEmail;
use Laika\Core\Worker\Queue;

Queue::driver()->push(new SendWelcomeEmail($userId), queue: 'emails', delay: 10);
```

```bash
php worker emails
```

This page covers everyday use. Driver internals, stuck jobs, worker hooks and custom drivers are in [Queue Drivers & Operations](02_drivers-and-operations.md).

## Choosing a Driver

Set in [`lf-config/queue.php`](../01_getting-started/03_configuration.md#lf-configqueuephp):

```php
return [
    'driver'          => 'json',    // 'json' | 'database' | 'redis'
    'connection'      => 'default', // used by the database driver / database failed-job store
    'failed_driver'   => null,      // 'database' | 'json'; null = 'database' for the database driver, else 'json'
    'reserve_timeout' => 90,        // redis only
    'max_tries'       => 3,         // redis only
];
```

**Always set `driver`.** The web app (`Queue::driver()`) and the `worker` choose the driver separately, and if the key is missing they fall back to different ones — `json` and `database` respectively — so pushed jobs would never be run.

| Driver | Storage | Good for | Notes |
|---|---|---|---|
| `json` | `lf-storage/queues/jobs.json` | Development, one server | No setup. Claiming a job is locked, so workers never share one. No recovery of a job whose worker crashed. |
| `database` | `laika_queue_jobs` table, via laika-model | Small production setups | **One worker per queue.** Create the tables first (below). |
| `redis` | Redis, from [`lf-config/redis.php`](../01_getting-started/03_configuration.md#lf-configredisphp) | Production, several workers | Requires `ext-redis`. Atomic pop, stalled-job recovery. |

Failed jobs go to a separate store, chosen by `failed_driver` — `database` (`laika_failed_jobs`) or `json` (`lf-storage/queues/failed.json`). There is no Redis failed-job store.

**`connection`** names a connection in `lf-config/database.php`, and may contain **letters only** — `analytics` works, `analytics_db` and `db2` throw `DriverException`.

### Creating the Database Tables

The `database` driver and the `database` failed-job store need their tables, and **nothing creates them for you** — not `php laika app:migrate` (since laika-queue 1.1.1), and not first use. Create them once, with a user that has `CREATE` rights; a [custom command](../01_getting-started/04_cli.md#writing-your-own-commands) is a good place:

```php
(new \Laika\Queue\Schema\QueueModelSchema())->up();      // laika_queue_jobs
(new \Laika\Queue\Schema\FailedJobModelSchema())->up();  // laika_failed_jobs
```

With no argument both follow `queue.connection`. They use `createIfNotExists`, so running them again is harmless.

> The drivers also have an `install()` method. It **drops the table first**, deleting every pending or failed job — use it for a fresh install or a test, never on a live queue.

## Defining a Job

```bash
php laika job:make SendWelcomeEmail
```

Job names — and the `--queue` value — may contain letters and underscores only.

```php
namespace App\Job;

use Laika\Queue\Abstracts\Job;

class SendWelcomeEmail extends Job
{
    public int $maxTries = 3;
    protected int|array|null $backoffStrategy = [10, 30, 60]; // seconds before retry 1, 2, 3...

    public function __construct(protected int $userId) {}

    public function handle(): void
    {
        $user = (new \App\Model\UsersModel())->find($this->userId);
        // ... send the email; throw to fail this attempt ...
    }

    public function failed(\Throwable $e): void
    {
        if ($this->tries >= $this->maxTries) {
            error_log("Welcome email to {$this->userId} failed for good: {$e->getMessage()}");
        }
    }
}
```

The job object is serialized into the queue, so constructor properties are available in `handle()`. Pass IDs, not models or connections — anything that can't be serialized (a PDO, a closure) fails.

| Property / method | Default | |
|---|---|---|
| `public int $maxTries` | `3` | Attempts before the job moves to the failed store |
| `public int $retryAfter` | `60` | Base delay for exponential backoff |
| `protected int\|array\|null $backoffStrategy` | `null` | `int`: `n × attempt` seconds. `array`: seconds per attempt, the last repeated; an empty array means exponential. `null`: `retryAfter × 2^(attempt-1)`, capped at 3600. |
| `protected bool $jitter` | `true` | ±20% random jitter, applied to every strategy |
| `public int $tries` | — | Attempts so far, including the current one (set by the worker) |
| `public string $id` | — | Set by the driver when the job is pushed |
| `abstract public function handle(): void` | | The work. Throw to fail the attempt. |
| `public function failed(\Throwable $e): void` | | Called after **every** failed attempt — compare `$this->tries` with `$this->maxTries` to tell a retry from a final failure |

- **The queue is chosen when you push**, not by the job. A `public string $queue` property (which `job:make` generates) is overwritten by `push()`.
- **The delay is chosen when you push, too.** `Job` has a `public int $delay` property, but no driver reads it — pass `delay:` to `push()`.
- **Keep `failed()` from throwing.** Nothing catches an exception from it, and on Windows that stops the worker.
- **Timeouts.** A job still running after 60 seconds is killed and treated like a failed attempt — retried with backoff until `maxTries` is used up, then moved to the failed store — except that `failed()` is **not** called. Timeouts need `ext-pcntl`; see [Running the Worker](#running-the-worker).

## Pushing a Job

`Laika\Core\Worker\Queue::driver()` builds the driver configured in `lf-config/queue.php`:

```php
use Laika\Core\Worker\Queue;

$queue = Queue::driver();

$queue->push(new SendWelcomeEmail($userId));                        // 'default' queue, now
$queue->push(new SendWelcomeEmail($userId), queue: 'emails');       // another queue
$queue->push(new GenerateReport($month), queue: 'reports', delay: 300); // in 5 minutes

$queue->size('emails');   // pending jobs, including delayed ones
```

| Driver method | |
|---|---|
| `push(Job $job, string $queue = 'default', int $delay = 0): string` | Returns the job id |
| `size(string $queue = 'default'): int` | Jobs waiting, delayed ones included |
| `pop(string $queue = 'default'): ?Job` | Used by the worker |
| `ack()`, `release()`, `delete()` | Used by the worker to finish, retry or drop a job |
| `install(): void` | Creates the driver's storage — for the database driver, by dropping and recreating the table |

Build drivers with `Queue::driver()` rather than `new DatabaseDriver(...)`/`new RedisDriver(...)`: it registers the database connection and gives the Redis driver the ability to reconnect in a forked worker.

## Running the Worker

```bash
php worker                 # the 'default' queue
php worker emails          # one named queue
php laika queue:work emails  # the same, through the CLI
```

- **One worker process handles one queue.** Start one per queue name. A misspelled queue name doesn't error — the worker just waits forever.
- The worker polls every 3 seconds when idle, and gives each job up to 60 seconds. Neither is configurable from `php worker`.
- It checks memory **between** jobs and exits cleanly at about 90% of `memory_limit` (128 MB when the limit is unlimited or unreadable) — run it under a supervisor that restarts it. `CLI_MEMORY_LIMIT` from `lf-inc/const.php` is applied at boot, and only ever lowers the limit.
- Restart workers after deploying new code; a running worker keeps the old classes in memory. Config files and options are re-read before every job, so a changed setting does not need a restart — see [Caching → Long-Running Processes](../20_cache/01_basic.md#long-running-processes).

**Signals** (Linux/macOS with `ext-pcntl`): `SIGTERM`/`SIGINT` finish the current job and stop; `SIGUSR2` pauses; `SIGCONT` resumes. Each job runs in a forked child, which is what makes the timeout possible; it needs `ext-pcntl` and `ext-posix`.

**On Windows** there's no `pcntl`: no graceful stop, no pause, no per-job timeout — the worker runs jobs inline, in its own process. Fine for development. Run it as `php worker`; the old `worker.bat` is no longer generated.

See [Deployment → Queue Worker](../13_deployment/01_basic.md#queue-worker) for supervisor and systemd configuration.

## Failed Jobs

```bash
php laika queue:failed                   # list failed jobs
php laika queue:failed --queue=emails
php laika queue:retry 9f86d081884c7d65…  # push one back by id, attempts reset
php laika queue:retry --all              # push all back
php laika queue:retry --all --queue=emails
php laika queue:flush                    # delete all failed jobs (asks first)
php laika queue:flush --hours=48         # delete those older than 48 hours
```

- Failed-job ids are 32-character hex strings; copy one from `queue:failed`.
- A retried job goes back on its original queue, immediately, with its original job id and `tries` reset to 0.
- A failed row stores the full exception, stack trace included.
- Jobs that the Redis driver gave up on as stalled are **not** failed jobs — they go to a separate dead set. See [Stalled Jobs on Redis](02_drivers-and-operations.md#stalled-jobs-on-redis).

## Trusted Job Classes

Job payloads are restored with `unserialize()`. To prevent PHP object injection, only trusted classes may be unserialized — anything else throws.

The `worker` (and `queue:retry`) trusts every class in the `jobs` resource automatically — by default every `Job` subclass in `lf-app/Job/`, plus any job directory an installed package declares. A job class that lives elsewhere must be registered before the worker processes it — for example in a hook file:

```php
\Laika\Queue\Abstracts\Job::registerTrustedClasses([\Acme\Billing\ChargeJob::class]);
```

A job the worker can't restore — untrusted, renamed or deleted — **blocks its queue** on the `json` and `database` drivers. See [A Job That Can't Be Restored](02_drivers-and-operations.md#a-job-that-cant-be-restored).

## Concurrency

- **`json`**: claiming a job happens under an exclusive file lock, so several workers can share a queue on one server. The lock is not reliable on network file systems (NFS).
- **`database`**: run **one worker per queue**. `DatabaseDriver` claims a job with a `SELECT` + `UPDATE` inside a transaction but without a row lock, so two workers on the same queue can run the same job.
- **`redis`**: pops atomically, so any number of workers can share a queue. A job reserved for longer than `reserve_timeout` seconds is assumed stalled and put back.
- Neither `json` nor `database` recovers a job from a worker that crashed mid-job — it stays reserved.

Write jobs to be **idempotent** (safe to run twice) wherever you can.

## CLI Reference

| Command | Description |
|---|---|
| `php laika job:make <name> [--queue=default] [--max=3]` | Create a job class. `--max` sets `$maxTries`. |
| `php laika job:list` | List job classes |
| `php laika job:remove <name>` | Delete a job class |
| `php laika job:rename --old=<name> --new=<name>` | Rename a job class |
| `php laika queue:work [queue]` | Run a worker in the foreground (same as `php worker [queue]`) |
| `php laika queue:failed [--queue=<name>]` | List failed jobs |
| `php laika queue:retry <id>` / `--all [--queue=<name>]` | Retry failed jobs |
| `php laika queue:flush [--hours=N]` | Delete failed jobs |

## See Also

- [Queue Drivers & Operations](02_drivers-and-operations.md) — driver internals, stuck jobs, worker hooks, custom drivers
- [Deployment → Queue Worker](../13_deployment/01_basic.md#queue-worker)
- [Mail](../17_mail/01_basic.md) — a common thing to queue
- [laika-queue README](https://github.com/laikait/laika-queue)
