<?php
/**
 * Laika PHP MVC Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Micro Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

// Define Debug
define('DEBUG', true);

// Define LOG
define('DB_LOG', false); // Must Run 'php laika migrate' before true

// Memory Limits
define('MEMORY_LIMIT', '256M');

// CLI Memory Limits
define('CLI_MEMORY_LIMIT', '256M');

