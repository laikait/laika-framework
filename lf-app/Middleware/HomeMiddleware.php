<?php
/**
 * Laika Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP MVC Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Middleware;

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

use Closure;
use Laika\Session\Relay\Session;

class HomeMiddleware
{
    /**
     * @param Closure $next Pass Parameters to Next Middleware or Controller
     * @param array $params Parameters
     */
    public function handle(Closure $next, array $params)
    {
        // Start Code From Here....

        return $next($params);
    }
}
