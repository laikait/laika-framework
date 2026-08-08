# Queue

[`laikait/laika-queue`](https://github.com/laikait/laika-queue) provides background job processing — database, Redis, and JSON drivers, delayed jobs, retry backoff, and failed-job tracking, run by a signal-aware `worker` process.

Installing the package generates a `worker` executable in your project root automatically (same idea as `laika-cli`'s `laika` executable):

```bash
php worker default
```

## Choosing a Driver

Configured in [`lf-config/queue.php`](../01_getting-started/03_configuration.md#lf-configqueuephp):

```php
return [
    'driver'        => 'json', // 'json' (default) | 'database' | 'redis'
    'connection'    => 'default',
    'failed_driver' => null,   // 'database' | 'json' — defaults per 'driver'
];
```

| Driver | Storage | Notes |
|---|---|---|
| `json` | `lf-storage/queues/jobs.json` | No config needed. Good for local/dev. |
| `database` | via [laika-model](../05_models/01_basic.md), the `connection` key | Portable across every laika-model driver. Table auto-created on first run. |
| `redis` | `lf-config/redis.php`, as-is | No separate queue connection — keys are namespaced under that file's `prefix` + `:queue`. Requires `ext-redis`. |

Failed jobs are wired independently via `failed_driver` — there's no Redis-backed failed-job provider, so a `redis` queue driver still needs `'database'` or `'json'` here.

## Defining a Job

```php
namespace App\Job;

use Laika\Queue\Abstracts\Job;

class SendWelcomeEmail extends Job
{
    public int $maxTries = 3;
    protected int|array|null $backoffStrategy = [10, 30, 60]; // per-attempt seconds

    public function __construct(protected int $userId) {}

    public function handle(): void
    {
        // send the email
    }

    public function failed(\Throwable $e): void
    {
        // log, notify, etc.
    }
}
```

`$backoffStrategy` accepts an `int` (linear), an `array` (per-attempt steps), or `null` (exponential, capped at 3600s).

## Pushing a Job

```php
use Laika\Model\Connection;
use Laika\Queue\Driver\DatabaseDriver;

$driver = new DatabaseDriver('default'); // a connection NAME, not a PDO instance
$driver->push(new SendWelcomeEmail($userId), queue: 'emails', delay: 10);
```

## Trusted Job Classes (automatic)

Job payloads are restored with PHP's `unserialize()`. To prevent PHP Object Injection, only explicitly trusted classes are allowed to be unserialized — by default `Job::unserializePayload()` trusts none, and throws rather than silently unserializing something unexpected.

The `worker` executable handles this for you: every `Job` subclass discovered under `lf-app/Job` (the same lookup `php laika job:list` uses, via `Laika\Service\Infra::getQueueJobsClasses()`) is registered as trusted automatically on startup — no config needed. It stays narrower than trusting the codebase at large, since discovery only admits classes that actually extend `Job`.

Calling `Job::registerTrustedClasses()` yourself (e.g. for a job class that lives outside `lf-app/Job`) still works — see the [laika-queue README](https://github.com/laikait/laika-queue#security-trusted-job-classes).

## Running the Worker

```bash
php worker default   # queue name — optional, defaults to 'default'
```

One worker process handles exactly one queue name — run it multiple times (different `numprocs`) for more than one queue. A typo'd queue name doesn't error; it just runs forever popping nothing.

Handles `SIGTERM`/`SIGINT` (graceful stop), `SIGUSR2`/`SIGCONT` (pause/resume), per-job timeout via `pcntl_fork`, and memory-limit auto-restart (based on the framework's `CLI_MEMORY_LIMIT` constant in `lf-inc/const.php`). Requires `ext-pcntl`/`ext-posix` (Linux/macOS) for timeouts — degrades to inline execution without them.

See [Deployment](../13_deployment/01_basic.md#queue-worker) for running it under supervisor/systemd in production.

## Concurrency Caveat

`DatabaseDriver::pop()` claims a row with a plain `SELECT` + `UPDATE` inside a transaction — no `FOR UPDATE`/`SKIP LOCKED` row lock. Running a single worker process per queue is always safe; running multiple concurrent workers on the **same** queue can double-process a job unless it's idempotent. `RedisDriver` doesn't have this issue (atomic pop via Lua script).

## Full Reference

See the [laika-queue README](https://github.com/laikait/laika-queue) for `ensureSchema()`, manual schema classes, the `FailedJobProviderInterface`, and known gaps (no stalled-job reaper for `DatabaseDriver`/`JsonDriver`).
