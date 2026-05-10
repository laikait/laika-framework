<?php

class Provider
{
    /** @var ?Provider $provider */
    protected static ?Provider $provider = null;

    /** @var array $functions */
    protected array $functions = [];

    /** @var array $hooks */
    protected array $hooks = [];

    /** @var array $services */
    protected array $services = [];

    private function __construct()
    {
        $installed = $installed = json_decode(file_get_contents(APP_PATH . '/vendor/composer/installed.json'), true);

        foreach ($installed['packages'] ?? $installed as $package) {
            $base = APP_PATH . '/vendor/' . $package['name'];

            foreach ($package['extra']['laika']['functions'] ?? [] as $function_dir) {
                array_push($this->functions, ...glob(realpath("{$base}/{$function_dir}") . "/*.func.php"));
            }

            foreach ($package['extra']['laika']['hooks'] ?? [] as $hook_dir) {
                array_push($this->hooks, ...glob(realpath("{$base}/{$hook_dir}") . "/*.hook.php"));
            }

            foreach ($package['extra']['laika']['providers'] ?? [] as $service) {
                array_push($this->services, $service);
            }
        }
    }

    /**
     * Singleton Instance
     * @return static
     */
    public static function instance(): static
    {
        self::$provider ??= new self();
        return self::$provider;
    }

    /**
     * Get Function Directories
     * @return array
     */
    public function functions(): array
    {
        return self::$provider->functions;
    }

    /**
     * Get Hook Directories
     * @return array
     */
    public function hooks(): array
    {
        $app_hooks = glob(APP_PATH . '/lf-hooks/*.hook.php');
        return array_merge($app_hooks, self::$provider->hooks);
    }

    /**
     * Get Provider Services
     * @return array
     */
    public function services(): array
    {
        return self::$provider->services;
    }
}
