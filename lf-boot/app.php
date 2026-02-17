<?php

/**
 * Laika PHP MVC Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Define App Path
defined('APP_PATH') || define('APP_PATH', dirname(__DIR__));

use Laika\Core\App\Route\Asset;

################################################################
// ------------------- LOAD CONSTANTS ----------------------- //
################################################################
require_once APP_PATH . '/lf-boot/const.php';

################################################################
// ----------------------- AUTOLOADER ----------------------- //
################################################################
require_once APP_PATH . '/vendor/autoload.php';
// ---------------------------------------------------------- //

################################################################
// ----------------- RESOURCE REGISTER ---------------------- //
################################################################
/**
 * Add Resource URL Path Name. URL Example: https://yoursite.com/app-src/{name}
 * Example:
 *  App Resource '/app-src/{name}'
 *  Template Resource '/template-src/{name}'
 * To change App/Template Resource Slug Use Asset Class
 * Example:
 *  Asset::$app = 'default-src';
 *  Asset::$template = 'tpl-src';
 */

################################################################
