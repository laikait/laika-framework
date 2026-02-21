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
use Laika\Core\App\Router;


// ################################################################
// ------------------------- LOAD APP -------------------------- //
// ################################################################
require_once __DIR__ . '/lf-boot/app.php';


// ################################################################
// --------------------- DISPATCH ROUTERS ---------------------- //
// ################################################################
Router::dispatch();
// ################################################################