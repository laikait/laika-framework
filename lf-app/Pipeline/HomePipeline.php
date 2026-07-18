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

namespace App\Pipeline;

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

use Laika\Route\Interfaces\PipelineInterface;

class HomePipeline implements PipelineInterface
{
    /**
     * @param callable $next
     * @param array $params
     * @return ?string
     */
    public function handle(callable $next, array &$params): ?string
    {
        // Start Code From Here....

        return $next();
    }
}
