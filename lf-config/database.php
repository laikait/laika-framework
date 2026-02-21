<?php
/**
 * Laika PHP Micro Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Micro Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

return [
    'default'   =>  [
            // Driver
            'driver'    =>  'mysql',

            // Host
            'host'      =>  'localhost',

            // Port
            'port'      =>  3306,

            // Database Name
            'database'  =>  'test',

            // Username
            'username'  =>  'root',
            
            // Password
            'password'  =>  ''
        ]
];