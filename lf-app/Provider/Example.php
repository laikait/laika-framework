<?php
/**
 * Laika Example Relay Provider
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 */

// declare(strict_types=1);

namespace Laika\Session\Provider;

use Laika\Core\Relay\RelayProvider;

class Example extends RelayProvider
{
    public function register(): void
    {
        $this->registry->singleton('example', Example::class);
    }
}