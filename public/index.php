<?php
/**
 * Laika PHP Micro Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Micro Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

// ################################################################
// ------------------------ USE ROUTER ------------------------- //
// ################################################################
use Laika\Engine\Route\Url;


// ################################################################
// ------------------------- LOAD APP -------------------------- //
// ################################################################
// public/ is the document root; the application lives one level up.
defined('APP_PATH') || define('APP_PATH', dirname(__DIR__));
require_once APP_PATH . '/lf-boot/app.php';


// ################################################################
// --------------------- DISPATCH ROUTERS ---------------------- //
// ################################################################
Url::dispatch();
// ################################################################