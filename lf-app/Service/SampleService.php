<?php
/**
 * Laika Example Relay Provider
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 */

declare(strict_types=1);

namespace App\Service;

use Laika\Relay\Relay;

/**
 * @method static void assign(string|array $key, mixed $value = null)
 */
class SampleService extends Relay
{
    protected static function getRelayAccessor(): string
    {
        return 'sample.relay';
    }
}