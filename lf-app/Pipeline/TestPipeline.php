<?php
/**
 * Laika Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Pipeline;

use Laika\Core\Interfaces\PipelineInterface;

class TestPipeline implements PipelineInterface
{
    /**
     * @param callable $next
     * @param array $params
     * @return ?string
     */
    public function handle(callable $next, array &$params)
    {
        // Start Code From Here

        /**
         * Continue Next Pipeline/Response/Filter
         * return $next();
         * 
         * Ignore Next Pipelines
         * return $next(false);
         * 
         * Ignore Next Pipelines & Response. Print The String
         * return 'Something';
         */
        return $next();
    }
}
