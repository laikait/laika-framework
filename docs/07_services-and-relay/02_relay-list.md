# Relay List

Every framework service is reachable through a relay in the `Laika\Engine\Services` namespace. Each relay forwards static calls to the instance bound under its key by `Laika\Engine\Relay\CoreProviders`.

```php
use Laika\Engine\Services\{Request, Response, Url, Vault};

Request::input('email');
Response::setStatus(201);
Url::base();
Vault::encrypt('secret');
```

`php laika relay:list` prints the live bindings.

## Core Relays

| Relay `Laika\Engine\Services\…` | Key | Class `Laika\Engine\…` | Lifetime | Docs |
|---|---|---|---|---|
| `Activity` | `activity` | `Log\Activity` | singleton | [Errors & Logging](../18_errors-and-logging/01_basic.md#activity-log) |
| `AppKey` | `app.key` | `App\Key` | singleton | [Encryption & Tokens](../10_security/03_encryption-and-tokens.md#app-key) |
| `Asset` | `template.asset` | `Template\Asset` | singleton | [Assets](../06_templates/02_assets-meta-nav.md#styles-and-scripts) |
| `Cache` | `cache` | `\Laika\Engine\Cache\Cache` (the Cache module) | singleton | [Caching](../20_cache/01_basic.md) |
| `Config` | `config` | `Helper\Config` | singleton | [Configuration](../01_getting-started/03_configuration.md) |
| `Context` | `template.context` | `Template\Context` | singleton | [Assets](../06_templates/02_assets-meta-nav.md#context--data-for-every-template) |
| `Cookie` | `cookie` | `Helper\Cookie` | singleton | [Responses](../02_routing/04_responses.md#cookies) |
| `CORS` | `cors` | `Http\CORS` | singleton | [CSRF & CORS](../10_security/02_csrf-and-cors.md#cors) |
| `CSRF` | `csrf` | `Http\CSRF` | singleton | [CSRF & CORS](../10_security/02_csrf-and-cors.md#csrf) |
| `Date` | `date` | `Helper\Date` | singleton | [Utilities](../19_utilities/01_basic.md#date) |
| `Directory` | `directory` | `Helper\Directory` | singleton | [Files & Storage](../16_files-and-storage/01_basic.md#directory) |
| `File` | `file` | `Helper\File` | singleton | [Files & Storage](../16_files-and-storage/01_basic.md#file) |
| `Hook` | `hook` | `Helper\Hook` | singleton | [Hooks](../08_hooks/01_basic.md) |
| `Icon` | `icon` | `Generator\Icon` | singleton | [Assets](../06_templates/02_assets-meta-nav.md#icons) |
| `Image` | `image` | `Helper\Image` | **new per use** | [Files & Storage](../16_files-and-storage/01_basic.md#image) |
| `Infra` | `infra` | `App\Infra` | singleton | [Resources](../14_resources/01_basic.md) |
| `Init` | `init` | `Helper\Init` | singleton | [Sessions](../11_sessions/01_basic.md) |
| `IP` | `ip` | `IP\IP` | singleton | [Utilities](../19_utilities/01_basic.md#ip-addresses) |
| `Local` | `local` | `Helper\Local` | singleton | [Configuration](../01_getting-started/03_configuration.md#language-files) |
| `Math` | `math` | `Helper\Math` | singleton | [Utilities](../19_utilities/01_basic.md#math) |
| `Meta` | `template.meta` | `Template\Meta` | singleton | [Assets](../06_templates/02_assets-meta-nav.md#meta-tags) |
| `MimeType` | `mime` | `Helper\MimeType` | singleton | [Files & Storage](../16_files-and-storage/01_basic.md#mimetype) |
| `Nav` | `nav` | `Nav\Builder` | singleton | [Assets](../06_templates/02_assets-meta-nav.md#navigation-menus) |
| `Option` | `option` | `Model\OptionModel` | singleton | [Errors & Logging](../18_errors-and-logging/01_basic.md#options) |
| `Page` | `page` | `Helper\Page` | singleton | [Requests](../02_routing/03_requests.md#the-current-url) |
| `PhpMetadataParser` | `php.metadata.parser` | `Helper\PhpMetadataParser` | singleton | [Utilities](../19_utilities/01_basic.md#phpmetadataparser) |
| `Redirect` | `redirect` | `Http\Redirect` | singleton | [Responses](../02_routing/04_responses.md#redirects) |
| `Regex` | `regex` | `Regex\Regex` | singleton | [Encryption & Tokens](../10_security/03_encryption-and-tokens.md#regex-rules) |
| `Request` | `request` | `Http\Request` | singleton | [Requests](../02_routing/03_requests.md) |
| `Resource` | `resource` | `App\Resource` | singleton | [Resources](../14_resources/01_basic.md) |
| `Response` | `response` | `Http\Response` | singleton | [Responses](../02_routing/04_responses.md) |
| `Token` | `token` | `Generator\Token` | singleton | [Encryption & Tokens](../10_security/03_encryption-and-tokens.md#token-jwt) |
| `Uid` | `uid` | `Generator\Uid` | singleton | [Encryption & Tokens](../10_security/03_encryption-and-tokens.md#uid-and-unique) |
| `Unique` | `unique` | `Generator\Unique` | singleton | [Encryption & Tokens](../10_security/03_encryption-and-tokens.md#uid-and-unique) |
| `Upload` | `upload` | `Helper\Upload` | **new per use** | [Files & Storage](../16_files-and-storage/01_basic.md#upload) |
| `Url` | `url` | `Helper\Url` | singleton | [Requests](../02_routing/03_requests.md#the-current-url) |
| `Vault` | `vault` | `Helper\Vault` | singleton | [Encryption & Tokens](../10_security/03_encryption-and-tokens.md#vault) |
| `Visitor` | `visitor` | `Helper\Client` | singleton | [Requests](../02_routing/03_requests.md#client-information) |

Two relays don't share their class's name: `Visitor` fronts `Client`, and `AppKey` fronts `App\Key`.

`Image` and `Upload` are bound per use because they hold state for one file; every other relay shares one instance for the whole process. That also means singletons keep request state (`Request` parses input once, `Visitor` caches the IP) — reset them between jobs in a long-running worker with `X::clearResolvedInstance()`.

## Relays From Packages

| Relay | Key | Class | Notes |
|---|---|---|---|
| `Laika\Engine\Shield\Service\Shield` | `shield` | `Laika\Engine\Shield\Shield` | The firewall — see [Security (Shield)](../10_security/01_basic.md) |
| `Laika\Engine\Shield\Service\ShieldConfig` | `shield.config` | `Laika\Engine\Shield\ShieldConfig` | The shared configuration (`ShieldConfig::instance()`), the same object the static class uses. laika-shield 2.0.3 and earlier couldn't resolve it — call the static class there. |

`Laika\Engine\Session\Session` is **not** a relay — it's a plain static class, as are `SessionConfig` and `SessionManager`. The same goes for `Laika\Engine\Route\Url` (the router) and `Laika\Engine\Model\Connection`.

## Static Classes Without a Relay

These are used directly, by class name:

| Class | Docs |
|---|---|
| `Laika\Engine\App\Template` | [Templates](../06_templates/01_basic.md) |
| `Laika\Engine\Http\Validator` | [Requests](../02_routing/03_requests.md#validating-any-array) |
| `Laika\Engine\Http\ProxyTrust` | [Configuration](../01_getting-started/03_configuration.md#lf-configappphp) |
| `Laika\Engine\Helper\Zip`, `Laika\Engine\Storage\*` | [Files & Storage](../16_files-and-storage/01_basic.md) |
| `Laika\Engine\Helper\Cron`, `Laika\Engine\System\Command\Runner` | [Utilities](../19_utilities/01_basic.md) |
| `Laika\Engine\Worker\Queue` | [Queue](../12_queue/01_basic.md) |
| `Laika\Engine\Exceptions\*` | [Errors & Logging](../18_errors-and-logging/01_basic.md) |

## Using a Class Directly

A relay only forwards to a shared instance, so you can construct any class yourself when you want your own copy:

```php
use Laika\Engine\Http\Request;
use Laika\Engine\Sanitizer\NullSanitizer;

$raw = new Request(new NullSanitizer()); // a request that doesn't HTML-encode input
```

Some classes keep all their state in static properties — `CORS`, `Hook`, `Config`, `MimeType`, `Resource`, `Asset`, `Meta`, `Context`, `Icon`, `Uid` — so calling them through the relay or on the class does the same thing.

## See Also

- [Services & Relays](01_basic.md) — adding your own
