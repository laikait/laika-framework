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
    // Debug
    'debug' => true,

    // Time Zone
    'time.zone' => 'UTC',

    // Start Time
    'start.time'    =>  time(),

    // Memory Limit
    'memory.limit' => '256M',

    // CLI Memory Limit
    'cli.memory.limit' => '256M'
];