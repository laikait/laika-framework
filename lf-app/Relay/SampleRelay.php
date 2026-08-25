<?php
/**
 * Laika Example Relay Provider
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 */

declare(strict_types=1);

namespace App\Relay;

use Laika\Relay\RelayProvider;
use Laika\Core\App\Template;

class SampleRelay extends RelayProvider
{
    public function register(): void
    {
        $this->registry->singleton('sample.relay', Template::class);
    }

    public function boot(): void
    {
        // Write Your Code Here
    }
}