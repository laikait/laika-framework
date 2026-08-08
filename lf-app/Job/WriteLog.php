<?php
/**
 * Laika Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Job;

use Laika\Queue\Abstracts\Job;

/**
 * Demo job: appends a line to lf-storage/logs/write-log.log every time
 * handle() runs. Optional constructor flags let it simulate the two
 * non-happy-path outcomes a real job can hit:
 *
 *   - $failUntilLastTry: throws on every attempt except its last, so the
 *     Worker's backoff/retry logic runs before it finally succeeds.
 *   - $alwaysFail: throws every attempt, so it exhausts maxTries and ends
 *     up in the failed-job provider instead.
 */
class WriteLog extends Job
{
    public string $queue = 'default';
    public int $maxTries = 3;

    // Fast, deterministic backoff — just for this demo. Real jobs usually
    // leave $backoffStrategy null (exponential) or pick real-world values.
    protected int|array|null $backoffStrategy = [1, 2];
    protected bool $jitter = false;

    public function __construct(
        protected string $message,
        protected bool $failUntilLastTry = false,
        protected bool $alwaysFail = false,
    ) {
    }

    public function handle(): void
    {
        $this->log(sprintf('(attempt #%d) %s', $this->tries, $this->message));

        if ($this->alwaysFail) {
            throw new \RuntimeException("Simulated permanent failure on attempt #{$this->tries}");
        }

        if ($this->failUntilLastTry && $this->tries < $this->maxTries) {
            throw new \RuntimeException("Simulated transient failure on attempt #{$this->tries}");
        }
    }

    public function failed(\Throwable $e): void
    {
        // Note: Worker::process() calls failed() after every failed attempt,
        // not only the last one — check tries/maxTries yourself to tell
        // "will retry" apart from "exhausted and handed to the failed-job
        // provider".
        $status = $this->tries >= $this->maxTries ? 'permanently' : 'temporarily, will retry';
        $this->log("FAILED {$status} (attempt #{$this->tries}/{$this->maxTries}): {$e->getMessage()}");
    }

    protected function log(string $line): void
    {
        $dir = APP_PATH . '/lf-storage/logs';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        file_put_contents(
            $dir . '/write-log.log',
            sprintf('[%s] %s%s', date('Y-m-d H:i:s'), $line, PHP_EOL),
            FILE_APPEND
        );
    }
}
