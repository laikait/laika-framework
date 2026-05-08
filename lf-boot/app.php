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

// Define App Path
defined('APP_PATH') || define('APP_PATH', dirname(__DIR__));

################################################################
// ----------------------- INCLUDES ------------------------- //
################################################################
require_once APP_PATH . '/lf-inc/const.php';
require __DIR__ . '/provider.php';

// Require Functions
array_map(fn ($f) => require_once $f, Provider::instance()->functions());
// ---------------------------------------------------------- //

################################################################
// ----------------------- AUTOLOADER ----------------------- //
################################################################
require_once APP_PATH . '/vendor/autoload.php';
// ---------------------------------------------------------- //

// Require Hooks
array_map(fn ($h) => require_once $h, Provider::instance()->hooks());
