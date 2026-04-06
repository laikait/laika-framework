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

/*==================================================================================*/
/*================================== CUSTOM RELAYS =================================*/
/*==================================================================================*/
/**
 * Examples
 * 
 * $registry->singleton('config', Laika\Core\Helper\Config::class);
 * $registry->bind('session', Laika\Session\Session::class);
 * $registry->instance('cookie', new Laika\Core\Helper\Cookie());
 */