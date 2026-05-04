<?php
/**
 * Laika Example Relay Provider
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 */

declare(strict_types=1);

namespace App\Relays;

use Laika\Core\Relay\Relay;

/**
 * @method static void yourMethod()
 */
class Example extends Relay
{
    protected static function getRelayAccessor(): string
    {
        return 'example';
    }
}