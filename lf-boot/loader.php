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

/**
 * Boot Loader Class
 */
class Loader
{
    /** @var ?Loader Instance */
    protected static ?Loader $instance = null;

    /** @var array Functions */
    protected array $functions = []; // All Are Files

    /** @var array Hooks */
    protected array $hooks = []; // All Are Files

    /** @var array Services */
    protected array $services = []; // All Are Classes

    /** @var array Schemas */
    protected array $schemas = []; // All Are Classes

    /** @var array Models */
    protected array $models = []; // All Are Classes

    /** @var array Middlewares */
    protected array $middlewares = []; // All Are Classes

    /** @var array Afterwares */
    protected array $afterwares = []; // All Are Classes

    // ===============================================================
    // ======================= EXTERNAL API ======================= //
    // ===============================================================
    /**
     * Get Function Files
     * @return array
     */
    public static function functions(): array
    {
        return self::instance()->functions;
    }

    /**
     * Get Hook Files
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

    // ===============================================================
    // ======================= INTERNAL API ======================= //
    // ===============================================================
    private function __construct()
    {
        $installed = json_decode(file_get_contents(APP_PATH.DS.'vendor'.DS.'composer'.DS.'installed.json'), true);

        foreach ($installed['packages'] ?? $installed as $package) {
            $base = realpath(APP_PATH . DS . 'vendor' . DS . $package['name']);
            $psrs = $package['autoload']['psr-4'] ?? [];

            // Generate Function Files
            $fnd = array_map(fn($f) => realpath($base . DS . $f), (array) ($package['extra']['laika']['functions'] ?? []));
            foreach ($fnd as $fd) array_push($this->functions, ...glob($fd . DS . '*.func.php'));
            // array_map(fn ($f) => array_push($this->functions, ...glob($f . DS . '*.func.php')), $fnd);

            // Generate Hook Files
            $hkd = array_map(fn($hk) => realpath($base . DS . $hk), (array) ($package['extra']['laika']['hooks'] ?? []));
            foreach ($hkd as $hd) array_push($this->hooks, ...glob($hd . DS . '*.hook.php'));
            // array_map(fn ($hd) => array_push($this->hooks, ...glob($hd . DS . '*.hook.php')), $hkd);

            // Generate Support Classes
            foreach ($psrs as $pk => $pv) {
                // Schemas
                if (isset($package['extra']['laika']['schema']) && $package['extra']['laika']['schema']) {
                    $md = $base . DS . trim($pv, '/\\') . DS . 'Schema';
                    if (is_dir($md)) {
                        foreach (glob($md . DS . '*.php') as $mf) {
                            array_push($this->schemas, $pk . "Schema\\" . pathinfo($mf, PATHINFO_FILENAME));
                        }
                    }
                }
                // Models
                if (isset($package['extra']['laika']['model']) && $package['extra']['laika']['model']) {
                    $md = $base . DS . trim($pv, '/\\') . DS . 'Model';
                    if (is_dir($md)) {
                        foreach (glob($md . DS . '*.php') as $mf) {
                            array_push($this->models, $pk . "Model\\" . pathinfo($mf, PATHINFO_FILENAME));
                        }
                    }
                }
                // Middlewares
                if (isset($package['extra']['laika']['middleware']) && $package['extra']['laika']['middleware']) {
                    $md = $base . DS . trim($pv, '/\\') . DS . 'Middleware';
                    if (is_dir($md)) {
                        foreach (glob($md . DS . '*.php') as $mf) {
                            array_push($this->middlewares, $pk . "Middleware\\" . pathinfo($mf, PATHINFO_FILENAME));
                        }
                    }
                }
                // Afterwares
                if (isset($package['extra']['laika']['afterware']) && $package['extra']['laika']['afterware']) {
                    $md = $base . DS . trim($pv, '/\\') . DS . 'Afterware';
                    if (is_dir($md)) {
                        foreach (glob($md . DS . '*.php') as $mf) {
                            array_push($this->afterwares, $pk . "Afterware\\" . pathinfo($mf, PATHINFO_FILENAME));
                        }
                    }
                }
            }

            // Generate Relay Classes
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
}
