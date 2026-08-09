<?php
/**
 * Laika Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

return [
    // Which queue backend the `worker` executable runs against:
    // 'database' | 'redis' | 'json'.
    'driver' => 'json',

    // Connection name (see lf-config/database.php) used when 'driver' (or
    // 'failed_driver') is 'database'.
    'connection' => 'default',

    // Failed-job provider: 'database' | 'json'. null (default) mirrors
    // 'driver' when it's 'database', and falls back to 'json' otherwise —
    // there's no Redis-backed failed-job provider, so a 'redis' queue
    // driver still needs an explicit database/json choice here.
    'failed_driver' => null,

    // The 'redis' driver connects using lf-config/redis.php as-is (host,
    // port, password) — no separate connection to configure here.

    // The 'json' driver has no config either — it always uses
    // lf-storage/queues/jobs.json (and lf-storage/queues/failed.json for
    // the matching failed-job provider).

    // Note: which Job subclasses bin/worker is allowed to unserialize()
    // from the queue isn't configured here — every class under lf-app/Job
    // is trusted automatically (see Laika\Queue\Abstracts\Job::registerTrustedClasses()
    // and bin/worker).
];
