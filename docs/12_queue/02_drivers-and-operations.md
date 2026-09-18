# Queue Drivers & Operations

How each queue driver stores and claims jobs, what goes wrong in production and how to fix it, and how to extend the worker. For pushing jobs and running workers, start with [Queue](01_basic.md).

## How Each Driver Stores Jobs

### `json`

Every queue shares one file, `lf-storage/queues/jobs.json`; failed jobs go to `lf-storage/queues/failed.json`. Each read-modify-write holds an exclusive `flock` on the file, so pushes and pops from several processes on **one server** never corrupt it or hand a job to two workers.

- A job is claimed by marking it reserved. If its worker dies, it stays reserved forever — there is no recovery.
- A file that is not valid JSON is treated as empty and **overwritten** on the next write. Don't edit it by hand while workers run.
- `flock` is advisory and unreliable on NFS and other network file systems. Use `redis` across servers.

### `database`

Jobs live in `laika_queue_jobs`, failed jobs in `laika_failed_jobs`, on the `queue.connection` connection. See [Creating the Database Tables](01_basic.md#creating-the-database-tables).

| `laika_queue_jobs` column | |
|---|---|
| `id` | CHAR(36), primary key |
| `queue` | Queue name |
| `payload` | The serialized job |
| `attempts` | Deliveries so far |
| `reserved_at` | Unix time a worker claimed it; `NULL` while waiting |
| `available_at` | Unix time it may run — later than now for a delayed job |
| `created_at` | Unix time it was pushed |

`pop()` claims the oldest available row with a `SELECT` then an `UPDATE`, inside a transaction but **without a row lock** (`FOR UPDATE`), so that it works on every database laika-model supports. Two workers on the same queue can therefore both claim the same row — run one worker per queue. As with `json`, a job whose worker dies stays reserved.

### `redis`

Keys live under `{redis.prefix}:queue:{queue}:…`, so the queue never collides with the cache or sessions on a shared server:

| Key | Type | Holds |
|---|---|---|
| `…:pending` | list | Ids ready to run |
| `…:delayed` | sorted set | Ids waiting for their delay, scored by run time |
| `…:reserved` | sorted set | Ids claimed by a worker, scored by claim time |
| `…:jobs` | hash | id → payload |
| `…:attempts` | hash | id → deliveries so far |
| `…:dead` | sorted set | Ids given up on as stalled |

Pushing, popping and reclaiming are Lua scripts, so each is atomic and any number of workers can share a queue. Every `pop()` first moves due delayed jobs onto `pending`, and reclaims stalled ones.

#### Stalled Jobs on Redis

A job still reserved `reserve_timeout` seconds (default 90) after it was claimed is assumed to belong to a dead worker, and is put back on `pending`. Keep `reserve_timeout` **above** the worker's 60-second job timeout, or a slow job that is still running is handed to a second worker.

`max_tries` in `lf-config/queue.php` caps this. It is compared with the job's **delivery count** — every `pop()` adds one, including ordinary retries — and once a stalled job has reached it, it goes to `…:dead` instead of back on the queue, so a job that keeps crashing its worker stops cycling. `0` disables the cap. This is separate from the job's own `$maxTries`, which governs retries after an exception.

Dead jobs don't appear in `queue:failed` and `queue:retry` can't see them. Inspect them with `redis-cli`:

```bash
redis-cli ZRANGE laika:queue:emails:dead 0 -1          # dead ids
redis-cli HGET  laika:queue:emails:jobs <id>          # a payload
```

## A Job That Can't Be Restored

The worker restores each job with `unserialize()`, and only [trusted classes](01_basic.md#trusted-job-classes) are allowed. A payload whose class is untrusted, renamed or deleted throws while it is being popped — and on `json` and `database` that happens **before the job is claimed**. The same job is tried again on every poll, every 3 seconds, and every job behind it waits: **the queue is blocked**. The worker prints the reason to STDERR each time:

```
[laika-queue] driver->pop() failed: ...
```

To fix it, make the class loadable again (restore it, or register it with `Job::registerTrustedClasses()`), or remove the job:

- **`database`**: delete the row from `laika_queue_jobs`.
- **`json`**: stop the workers, remove the record from `jobs.json`, start them again.
- **`redis`**: nothing blocks. The job is left reserved, reclaimed after `reserve_timeout`, and ends in `…:dead` after `max_tries` deliveries.

Before renaming or removing a job class, let its queue drain.

## Worker Hooks

`Worker::beforeJob()` registers a callback that runs before every job, with the job and the queue name:

```php
use Laika\Queue\Worker;

Worker::beforeJob(function ($job, string $queue): void {
    // reset per-job state
});
```

The framework already registers one: `Laika\Core\System\ProcessState::reset()`, which drops cached config, options, cache-driver memory and other per-process state, so each job sees current settings — see [Caching → Long-Running Processes](../20_cache/01_basic.md#long-running-processes). A callback that throws is reported to STDERR and doesn't stop the job.

With `ext-pcntl` the callbacks run in the worker's own process before it forks, so the job's child process starts from the reset state.

## Signals and Timeouts

With `ext-pcntl` and `ext-posix` (Linux/macOS), the worker forks a child process for each job and waits for it:

| | |
|---|---|
| `SIGTERM`, `SIGINT` | Finish the current job, then exit |
| `SIGUSR2` | Pause after the current job |
| `SIGCONT` | Resume |
| Timeout (60 s) | The child is killed with `SIGKILL`; the job is retried or failed as described in [Defining a Job](01_basic.md#defining-a-job) |

A child that holds a database or Redis connection must not share its parent's socket. The Redis driver built by `Queue::driver()` opens its own connection in each child; so does any driver implementing `ReconnectableDriver`.

Without `pcntl` — every Windows host — jobs run inline in the worker's process: no timeout, no pause, and `Ctrl+C` stops the worker immediately, mid-job.

## Custom Drivers

A driver implements `Laika\Queue\Interfaces\QueueDriverInterface`:

```php
interface QueueDriverInterface
{
    public function install(): void;
    public function push(Job $job, string $queue = 'default', int $delay = 0): string;
    public function pop(string $queue = 'default'): ?Job;
    public function ack(string $id, string $queue = 'default'): void;
    public function release(string $id, string $queue = 'default', int $delay = 0): void;
    public function delete(string $id, string $queue = 'default'): void;
    public function size(string $queue = 'default'): int;
}
```

- `pop()` must set `$job->id`, `$job->queue` and `$job->tries` (this delivery included) on the job it returns, and restore it with `Job::unserializePayload()` so that trusted classes are enforced.
- `install()` was added in laika-queue 1.1.0. A driver written earlier must add it — an empty body is fine.
- If the driver holds a connection, also implement `Laika\Queue\Interfaces\ReconnectableDriver` (`reconnect(): void`); the worker calls it in each forked child.
- Throw `Laika\Queue\Exceptions\DriverException` for misconfiguration.

`php worker` only knows the three built-in drivers. Run a custom one from your own script or [command](../01_getting-started/04_cli.md#writing-your-own-commands):

```php
use Laika\Queue\Worker;
use Laika\Core\Worker\Queue;

$worker = new Worker(new MyDriver(), Queue::failedProvider());
$worker->work('emails');   // queue, sleep = 3, timeout = 60, memoryLimit (MB) = auto
```

A failed-job store implements `Laika\Queue\Interfaces\FailedJobProviderInterface` (`log`, `all`, `find`, `forget`, `flush`).

## The `worker` Executable

`worker` in the project root is generated by `Laika\Queue\ScriptHandler::generate`, which runs in the root `composer.json`'s `post-autoload-dump` — so `composer install` or `composer dump-autoload` recreates it. It boots the app through `lf-boot/app.php` (hook files included), trusts the `jobs` resource, builds the driver and failed-job store from `lf-config/queue.php`, and runs one queue: `php worker <queue>`.

## See Also

- [Queue](01_basic.md)
- [Deployment → Queue Worker](../13_deployment/01_basic.md#queue-worker)
- [Caching → Long-Running Processes](../20_cache/01_basic.md#long-running-processes)
