# Security (Shield)

[`laikait/laika-shield`](https://github.com/laikait/laika-shield) is a firewall middleware: country/IP blocking, rate limiting, SQL injection & XSS detection, and general request filtering. `laikait/laika-core` requires it, so it is **already installed** in every Laika project — there is nothing to add. It does nothing until you wire it in, which is what the rest of this page covers.

## Configuring

There is no config file to publish — every option carries its own default. Create one only if you want your settings in a file, returning the keys you wish to override:

```php
// lf-storage/shield.config.php
return [
    'ip' => [
        'blocklist' => ['1.2.3.4', '192.168.100.0/24'],
        'allowlist' => [],
    ],
    'rate.limit' => [
        'max.hits' => 60,
        'window'   => 60,
    ],
    'sql.injection' => ['skip.keys' => [], 'scan.body' => true, 'strict' => true],
    'xss'           => ['skip.keys' => [], 'scan.body' => true],
    'request.filter' => [
        'blocked.methods' => ['TRACE', 'CONNECT'],
    ],
];
```

See the [laika-shield README](https://github.com/laikait/laika-shield#%EF%B8%8F-configuration-reference) for every available key, including MaxMind GeoLite2 country blocking.

## Wiring It In

`Shield::boot()`/`run()` **throw `Laika\Shield\Exceptions\FirewallException`** when a rule blocks the request (after already setting the appropriate HTTP status code). The natural integration point is a global [pipeline](../03_pipeline/01_basic.md) that catches it and turns it into a response:

```bash
php laika pipeline:make Shield
```

```php
namespace App\Pipeline;

use Laika\Shield\Shield;
use Laika\Shield\Exceptions\FirewallException;
use Laika\Route\Contracts\PipelineInterface;

class Shield implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        try {
            // boot() takes no arguments and reads the shared ShieldConfig.
            // To apply an array, hand it to fromConfig() instead:
            Shield::fromConfig(require APP_PATH . '/lf-storage/shield.config.php')->run();
        } catch (FirewallException $e) {
            // Status code is already set by Shield; just supply a body.
            return $e->getMessage();
        }

        return $next();
    }
}
```

```php
// lf-routes/web.php or a dedicated bootstrap file
Url::globalPipeline(['Shield']);
```

Running it as a **global** pipeline means every request is checked before any route-specific pipeline or controller runs.

## Fluent Builder (alternative)

For programmatic configuration instead of a config file:

```php
use Laika\Shield\Shield;

(new Shield())
    ->trustProxy()
    ->blockIps(['1.2.3.4', '10.10.0.0/16'])
    ->rateLimit(maxHits: 100, windowSecs: 60)
    ->detectSqlInjection(skipKeys: ['password'])
    ->detectXss(skipKeys: ['html_content'])
    ->run(); // throws FirewallException on a match
```

## Runtime Config Changes

`Laika\Shield\ShieldConfig` gives dot-notation access without touching the config file directly:

```php
use Laika\Shield\ShieldConfig;

ShieldConfig::add('rate.limit', 'max.hits', 30);
ShieldConfig::add('sql.injection', 'skip.keys', ['password', 'token']);

Shield::boot(); // no arguments - always reads the shared ShieldConfig
```

## Custom Rules

```php
use Laika\Shield\Contract\RuleInterface;

class BlockBadReferrer implements RuleInterface
{
    public function passes(): bool { /* ... */ return true; }
    public function message(): string { return 'Access Denied.'; }
    public function statusCode(): int { return 403; }
    public function additionalHeader(): void {}
}

(new Shield())->addRule(new BlockBadReferrer())->run();
```

## Full Reference

See the [laika-shield README](https://github.com/laikait/laika-shield) for the complete rule set (`IpRule`, `IpVersionRule`, `RateLimitRule`, `CountryRule`, `SqlInjectionRule`, `XssRule`, `RequestFilterRule`), the `IpHelper` utility class, and architecture overview.
