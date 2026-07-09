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

use Laika\Core\Interfaces\AfterwareInterface;

class LogAfterware implements AfterwareInterface
{
    /**
     * @param callable $next
     * @param ?string $output
     * @param array $params
     * @return ?string
     */
    public function terminate(callable $next, ?string $output, array $params): ?string
    {
        // Write Code From Here

        return $next($output, $params);
    }
}
