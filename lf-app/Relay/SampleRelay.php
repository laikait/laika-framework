<?php
/**
 * Laika Example Relay Provider
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 */

declare(strict_types=1);

namespace App\Relay;

use Laika\Engine\Relay\RelayProvider;
use Laika\Engine\App\Template;

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