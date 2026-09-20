# Security (Shield)

The [Shield module](https://github.com/laikait/laika-engine/tree/main/docs/shield) of `laikait/laika-engine` is a firewall that inspects each request before your routes run: rate limiting, IP and country blocking, SQL injection and XSS detection, and request filtering. It's installed with the framework but **does nothing until you add its pipeline**.

## Quick Start

Register the ready-made pipeline globally — in a route file or a hook file:

```php
// lf-routes/web.php
use Laika\Engine\Route\Url;
use Laika\Engine\Shield\Pipeline\ShieldPipeline;

Url::globalPipeline(ShieldPipeline::class);
```

That's all. Every matched route is now checked, with these rules **on by default**:

| Rule | Default |
|---|---|
| Rate limiting | 60 requests per 60 seconds, per client IP |
| SQL injection detection | Query string and body scanned (`strict` off) |
| XSS detection | Query string and body scanned (headers not scanned) |
| Request filtering | Blocks `TRACE` and `CONNECT`, and scanner user agents (`sqlmap`, `nikto`, `nessus`, `masscan`, `zgrab`, old `python-requests`) |
| IP blocking, IP version, country blocking | Off until you configure them |

When a rule blocks a request, `ShieldPipeline` sets the rule's status (403, or 429 for rate limits, with `Retry-After`), sends a JSON body and **stops the request** — no controller, no filters:

```json
{"status": false, "message": "Too Many Requests.", "ip": "203.0.113.7", "retry_after": 42}
```

Global pipelines run only for matched routes — static files, 404s and fallbacks aren't inspected.

## Configuring

Change settings with `Laika\Engine\Shield\ShieldConfig` in a hook file. It holds one shared configuration, which `ShieldPipeline` reads:

```php
// lf-hooks/shield.php
use Laika\Engine\Shield\ShieldConfig;

ShieldConfig::add('rate.limit', 'max.hits', 120);
ShieldConfig::add('rate.limit', 'storage.dir', APP_PATH . '/lf-storage/shield');
ShieldConfig::add('ip', 'blocklist', ['1.2.3.4', '192.168.100.0/24']);
ShieldConfig::add('sql.injection', 'skip.keys', ['password', 'content']);
ShieldConfig::add('trusted.proxies', ['10.0.0.0/8']);
ShieldConfig::add('trust.proxy', true);
```

Or apply a whole array — for example from your own config file:

```php
// lf-config/shield.php
return [
    'trust.proxy'     => true,
    'trusted.proxies' => ['10.0.0.0/8'],
    'rate.limit'      => ['max.hits' => 120, 'window' => 60, 'storage.dir' => APP_PATH . '/lf-storage/shield'],
    'sql.injection'   => ['skip.keys' => ['password', 'content']],
    'xss'             => ['skip.keys' => ['content']],
    'request.filter'  => ['blocked.methods' => ['TRACE', 'CONNECT', 'PUT']],
];
```

```php
// lf-hooks/shield.php
\Laika\Engine\Shield\ShieldConfig::instance()->fill(config('shield'));
```

`fill()` merges over the defaults — keys you don't mention keep their default values.

> **Behaviours to know:** `ShieldConfig::add()` with an array value **merges** into the existing list rather than replacing it, and an unknown section or key is **silently ignored** — check spelling against the table below. The `Laika\Engine\Shield\Service\ShieldConfig` relay forwards to the same shared instance, so either class works.

### Configuration Keys

| Key | Sub-key | Default | |
|---|---|---|---|
| `trust.proxy` | — | `false` | Read the client IP from proxy headers |
| `trusted.proxies` | — | `[]` | Your proxies' IPs/CIDRs. Without them, `CF-Connecting-IP`/`X-Real-IP` are ignored. |
| `ip.version` | — | `null` | `4` or `6` to allow only that family |
| `ip` | `blocklist`, `allowlist` | `[]` | IPs or CIDRs |
| `rate.limit` | `max.hits` | `60` | Requests allowed per window |
| | `window` | `60` | Seconds |
| | `storage.dir` | system temp | Where counters are kept — use a directory under `lf-storage/` (php-fpm's private `/tmp` is wiped on restart) |
| `sql.injection` | `skip.keys` | `[]` | Input keys not scanned |
| | `scan.body` | `true` | Scan the request body too |
| | `strict` | `false` | More aggressive patterns (more false positives) |
| `xss` | `skip.keys`, `scan.body`, `scan.headers` | `[]`, `true`, `false` | |
| `request.filter` | `blocked.methods` | `['TRACE', 'CONNECT']` | |
| | `blocked.uri.patterns` | `[]` | Regexes matched against the URI |
| | `blocked.user.agents` | scanner patterns | Regexes |
| | `headers.required` | `[]` | Lowercase header names that must be present |
| | `blocked.header.values` | `[]` | `header => [regex, ...]` |
| | `content.length.max`, `content.length.min` | `null` | Bytes |
| `country` | `db`, `blocklist`, `allowlist` | — | Country blocking with a MaxMind database; active only when `db` and a list are set |

A GeoLite2 country database ships with the package at `vendor/laikait/laika-engine/src/Shield/Storage/GeoLite2-Country.mmdb`:

```php
ShieldConfig::add('country', [
    'db'        => APP_PATH . '/vendor/laikait/laika-engine/src/Shield/Storage/GeoLite2-Country.mmdb',
    'blocklist' => ['KP', 'IR'],
]);
```

Other static methods: `ShieldConfig::get(?string $key = null)` (the configuration as arrays), `has(string $key)` (whether the key name is valid), `keys()`, `reset()`.

**Rich-text fields:** SQLi/XSS detection scans every input by default, so a CMS editor field or a password containing `<` or `'` can be blocked. Add such fields to `skip.keys`.

## Different Rules for Different Routes

`ShieldPipeline` accepts a config array in its constructor, but a pipeline registered by name is built without arguments. For per-route rules, write a small pipeline around the fluent builder:

```php
namespace App\Pipeline;

use Laika\Engine\Route\Contracts\PipelineInterface;
use Laika\Engine\Shield\Shield as Firewall;          // alias — the class name would clash
use Laika\Engine\Shield\Exceptions\{FirewallException, RateLimitExceededException};
use Laika\Engine\Services\Response;

class StrictShield implements PipelineInterface
{
    public function handle(callable $next, array &$params): ?string
    {
        try {
            (new Firewall())
                ->trustProxy(true, ['10.0.0.0/8'])     // call before adding rules
                ->rateLimit(maxHits: 10, windowSecs: 60, storageDir: APP_PATH . '/lf-storage/shield')
                ->detectSqlInjection(skipKeys: ['password'])
                ->detectXss()
                ->run();
        } catch (FirewallException $e) {
            if ($e instanceof RateLimitExceededException) {
                Response::setHeader('Retry-After', (string) $e->getRetryAfter());
            }
            Response::setStatus($e->getCode())->setContentType('application/json');
            return $e->payload();
        }

        return $next();
    }
}
```

```php
Url::post('/login', 'LoginController@login')->pipeline(['StrictShield']);
```

This version returns the error as a normal response, so your filters still run.

### Fluent Builder

| Method | |
|---|---|
| `trustProxy(bool $trust = true, array $trustedProxies = []): static` | **Call first** — rules capture it when added |
| `blockIps(array $blocklist = [], array $allowlist = []): static` / `allowIps(array $allowlist): static` | |
| `requireIpVersion(int $version): static` | |
| `blockCountries(string $mmdb, array $blocklist = [], array $allowlist = []): static` | |
| `rateLimit(int $maxHits = 60, int $windowSecs = 60, ?string $storageDir = null): static` | |
| `detectSqlInjection(array $skipKeys = [], bool $scanBody = true, bool $strict = false): static` | |
| `detectXss(array $skipKeys = [], bool $scanBody = true, bool $scanHeaders = false): static` | |
| `filterRequests(array $blockedMethods = [], array $blockedUriPatterns = [], array $blockedUserAgentPatterns = [], array $requiredHeaders = [], array $blockedHeaderValues = [], ?int $maxContentLength = null, ?int $minContentLength = null): static` | |
| `addRule(RuleInterface $rule): static` | A custom rule |
| `run(): void` | Check every rule; throws on the first failure |
| `static boot(): void` | Build from the shared `ShieldConfig` and run |
| `static fromConfig(ShieldConfig\|array $config = []): static` | Build (without running) from a config object or array |

On a block, `run()` sets the HTTP status (if headers haven't been sent) and throws `FirewallException` — or `RateLimitExceededException` (status 429, `getRetryAfter(): int`) for rate limits. Both have `payload(): string` (JSON) and `toArray(): array`.

## Custom Rules

```php
use Laika\Engine\Shield\Contract\RuleInterface;

class BlockBadReferrer implements RuleInterface
{
    public function passes(): bool
    {
        return !str_contains($_SERVER['HTTP_REFERER'] ?? '', 'spam.example');
    }
    public function message(): string { return 'Access Denied.'; }
    public function statusCode(): int { return 403; }
    public function additionalHeader(): void {}
}

(new \Laika\Engine\Shield\Shield())->addRule(new BlockBadReferrer())->run();
```

## See Also

- [CSRF & CORS](02_csrf-and-cors.md)
- [Encryption & Tokens](03_encryption-and-tokens.md)
- [Pipelines](../03_pipeline/01_basic.md)
- [Shield module docs](https://github.com/laikait/laika-engine/tree/main/docs/shield) — rule internals and `IpHelper`
