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
    // Where cached values live: 'file' | 'array' | 'redis' | 'memcached'.
    // 'file' needs no extension and no server. 'array' lasts one process only.
    // 'redis' and 'memcached' read their host, port and credentials from
    // lf-config/redis.php and lf-config/memcached.php.
    'driver' => 'file',

    // Namespace for shared backends. Keys become "<prefix>:cache:<key>", so the
    // cache cannot collide with the queue ("<prefix>:queue") on the same server.
    'prefix' => 'laika',

    // Seconds a value is kept when set() is given no TTL. 0 means never expire.
    'ttl' => 3600,

    // Directory for the 'file' driver. null uses lf-storage/cache/data. Keep it
    // out of lf-storage/cache itself: the resource manifest and compiled Twig
    // templates live there.
    'path' => null,

    // Classes unserialize() may rebuild when reading a cached value. false means
    // none -- a cached object comes back inert. A cache is shared, writable
    // state, and rebuilding arbitrary classes from it is an object-injection
    // risk, so list only classes you cache on purpose, e.g. [App\Dto\Price::class].
    'serialize' => [
        'allowed_classes' => false,
    ],

    // Database query results. Off by default, and when on, a query is cached
    // only if it asks: $model->table('posts')->where([...])->remember(300)->get().
    // A write through a model invalidates every cached query on that table and
    // on any table joined into it, once the write is committed. Writes the model
    // cannot see -- raw execute() SQL, another application -- are not noticed;
    // call Model::forgetQueryCache('posts') after those.
    'query' => [
        'enabled' => false,

        // Seconds, when remember() is called without its own TTL
        'ttl' => 60,
    ],
];
