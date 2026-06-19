<?php
/**
 * Laika PHP Micro Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Micro Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

// Define Directory Separator
if (!defined('DS')) define('DS', DIRECTORY_SEPARATOR);

class Loader
{
    /** @var ?Loader $instance */
    protected static ?Loader $instance = null;

    /** @var array $functions */
    protected array $functions = []; // All Are Files

    /** @var array $hooks */
    protected array $hooks = []; // All Are Files

    /** @var array $services */
    protected array $services = []; // All Are Classes

    /** @var array $migrations */
    protected array $migrations = []; // All Are Classes

    private function __construct()
    {
        $installed = $installed = json_decode(file_get_contents(APP_PATH . '/vendor/composer/installed.json'), true);

        foreach ($installed['packages'] ?? $installed as $package) {
            $base = APP_PATH . DS . 'vendor' . DS . $package['name'];

            // Load Functions
            $fnd = array_map(fn($f) => realpath($base . DS . $f), (array) ($package['extra']['laika']['functions'] ?? []));
            array_map(fn ($f) => array_push($this->functions, ...glob($f . DS . '*.func.php')), $fnd);

            // Load Hooks
            $hkd = array_map(fn($hk) => realpath($base . DS . $hk), (array) ($package['extra']['laika']['hooks'] ?? []));
            array_map(fn ($hd) => array_push($this->hooks, ...glob($hd . DS . '*.hook.php')), $hkd);

            // Load Relay Services
            $services = (array) ($package['extra']['laika']['relays'] ?? []);
            array_push($this->services, ...$services);
        }
    }

    /**
     * Singleton Instance
     * @return static
     */
    private static function instance(): static
    {
        self::$instance ??= new self();
        return self::$instance;
    }

    /**
     * Get Function Directories
     * @return array
     */
    public static function functions(): array
    {
        return self::instance()->functions;
    }

    /**
     * Get Hook Directories
     * @return array
     */
    public static function hooks(): array
    {
        $app_hooks = glob(APP_PATH . '/lf-hooks/*.hook.php');
        return array_merge($app_hooks, self::instance()->hooks);
    }

    /**
     * Get Provider Services
     * @return array
     */
    public static function services(): array
    {
        return self::instance()->services;
    }
}
