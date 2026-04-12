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

namespace App\Afterware;

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

class LogAfterware
{
    /**
     * @param $next Pass Parameters to Next Afterware
     * @param $output Controller Output
     * @param $params Parameters
     */
    public function terminate($next, $output, $params): string
    {
        // After controller
        // Write Code From Here ......
        // You can modify the Output if needed
        // Insert Log If Required

        return $next($output);
    }
}
