# Utilities

Dates, precise arithmetic, cron jobs, shell commands, IP address maths and a few smaller helpers from the Core module.

## Date

`Laika\Engine\Services\Date` is an immutable wrapper around `DateTime` — every change returns a new instance.

```php
use Laika\Engine\Services\Date;

Date::now()->format('d M Y');                                  // "14 Sep 2026"
Date::parse('next Monday')->format('l');                       // "Monday"
Date::fromFormat('d/m/Y', '21/04/2025', 'Y-m-d')->format();    // "2025-04-21"
Date::parse('-3 days')->humanDiff();                           // "3 days ago"
Date::now()->modify('+2 hours')->setTimezone('Asia/Dhaka')->toIso8601();
```

| Method | |
|---|---|
| `now(): static` / `parse(string $time, ?string $timezone = null): static` | Create |
| `fromFormat(string $format, string $time, ?string $outputFormat = null, ?string $timezone = null): static` | Parse an exact format |
| `format(?string $format = null): string` | Default format `Y-m-d H:i:s` |
| `setFormat(string $format): static` | A copy with another default format |
| `modify(string $modifier): static` | `'+1 day'`, `'last day of this month'` |
| `setTimezone(string $timezone): static` / `toLocal(): static` | Convert |
| `setTimestamp(int $timestamp): static` / `getTimestamp(): int` | Unix time |
| `toIso8601(bool $extended = true): string` / `toArray(): array` / `getOffset(): string` | Output |
| `diff(Date $other): \DateInterval` | |
| `humanDiff(?Date $other = null): string` / `humanDiffShort(?Date $other = null): string` | `"3 days ago"` / `"3d"` |
| `setAppTimezone(string $timezone): void` / `getAppTimezone(): string` | PHP's default timezone |

**The framework sets PHP's default timezone to UTC at boot.** To run in another zone, set it in a hook file:

```php
// lf-hooks/timezone.php
\Laika\Engine\Services\Date::setAppTimezone('Asia/Dhaka');
```

## Math

`Laika\Engine\Services\Math` does arbitrary-precision arithmetic on strings — for money and anything floats get wrong. Requires `ext-bcmath`.

```php
use Laika\Engine\Services\Math;

Math::add('0.1', '0.2');            // "0.3000"   (default scale 4)
Math::scale(2)->mul('19.99', 3);    // "59.97"
Math::round('2.345', 2);            // "2.35"
Math::trim(Math::div(10, 4));       // "2.5"
Math::percent(45, 60);              // "75.0000"
```

- Arguments accept `int`, `float` or numeric strings; results are strings.
- Most methods take an optional `$scale`; the default is **4**. `scale(int $scale)` returns a configured **copy**.
- Results are **truncated** to the scale, not rounded (`Math::div(2, 3)` is `"0.6666"`) — use `round()`.

| Methods | |
|---|---|
| `add`, `sub`, `mul`, `div`, `mod($a, $b, ?int $scale = null)` | Arithmetic (`div`/`mod` by zero throws) |
| `pow`, `sqrt`, `powmod` | Powers and roots |
| `round($a, int\|string $precision = 0)`, `floor($a)`, `ceil($a)`, `abs`, `negate` | Rounding and sign |
| `compare($a, $b)`, `isEqual`, `isGt`, `isLt`, `isGte`, `isLte`, `isZero`, `isPositive`, `isNegative` | Comparisons |
| `max`, `min`, `sum(array $values)`, `avg(array $values)` | Aggregates |
| `percent($value, $total)`, `percentOf($percent, $value)` | Percentages |
| `trim($a)` | Drop trailing zeros |

## Cron

`Laika\Engine\Helper\Cron` manages a block of jobs in a user's crontab, between `# [LAIKA-CRON-START]` and `# [LAIKA-CRON-END]` markers — lines outside the block are never touched. Linux and macOS only; needs `shell_exec()` and `system()`.

```php
use Laika\Engine\Helper\Cron;

(new Cron())                                         // current user; new Cron('www-data') needs rights to that crontab
    ->everyMinute('php /var/www/app/laika report:tick', 'tick')
    ->daily('php /var/www/app/laika app:clear', '03:30', 'nightly')
    ->install();                                     // writes or replaces the Laika block
```

| Method | |
|---|---|
| `add(string $expression, string $command, ?string $label = null): static` | Any cron expression |
| `everyMinute`, `every5Minutes`, `every10Minutes`, `every15Minutes`, `every30Minutes`, `hourly(string $command, ?string $label = null)` | Fixed schedules |
| `daily(string $command, string $time = '00:00', ?string $label = null)` | At `HH:MM` |
| `weekly(string $command, int $dayOfWeek = 0, string $time = '00:00', ?string $label = null)` | 0 = Sunday |
| `monthly(...)`, `yearly(...)` | |
| `jobs()`, `pop(string $label)`, `flush()`, `render(): string` | The pending list |
| `install(): bool` / `uninstall(): bool` / `installed(): array` | Write / remove / read the Laika block |

Run it from a [custom command](../01_getting-started/04_cli.md#writing-your-own-commands) during deployment, not from a web request.

## Shell Commands

`Laika\Engine\System\Command\Runner` runs external commands with a timeout:

```php
use Laika\Engine\System\Command\Runner;

$result = Runner::make()->timeout(30)->cwd(APP_PATH)->run(['git', 'rev-parse', 'HEAD']);

if ($result->success()) {
    echo $result->output;
}

Runner::make()->onOutput(fn (string $chunk, string $stream) => print $chunk)
              ->run(['composer', 'install', '--no-dev']);
```

| `Runner` method | |
|---|---|
| `make(): self` | A new runner |
| `timeout(int $seconds): self` | Default 60 |
| `cwd(string $path): self` / `env(array $env): self` | Working directory, environment |
| `onOutput(callable $callback): self` | Stream output: `fn(string $chunk, string $stream)` (`out` or `err`) |
| `async(bool $async = true): self` | Run in the background, returning an `AsyncJob` |
| `run(string\|array $command): Result\|AsyncJob` | Run it |

- **Pass an array** — each argument is escaped. A string goes through `escapeshellcmd()`, so pipes, redirects and `&&` don't work.
- `Result` has `command`, `output`, `error`, `exitCode`, `timedOut`, `pid` and `success()`.
- `AsyncJob` has `pid()`, `isRunning()`, `stop(int $signal = 15)` and `status()`. On Linux/macOS `stop()` sends the signal (default SIGTERM). On Windows the PID is the `cmd.exe` wrapper's, and `stop()` ends the whole process tree, ignoring the signal. An async run's output is discarded — have the command write to a file if you need it.
- `(new ProcessPool())->add($cmd)->add($cmd2)->run(concurrency: 4)` runs commands in parallel.

These use `proc_open()`/`exec()`, which hardened PHP-FPM pools often disable — run them from the CLI.

## IP Addresses

`Laika\Engine\Services\IP` does CIDR maths for IPv4 and IPv6:

```php
use Laika\Engine\Services\IP;

$net = IP::parse('192.168.10.0/24');
$net->getBroadcastAddress();                          // 192.168.10.255
$net->getUsableHosts();                               // 254
$net->contains('192.168.10.42');                      // true

IP::ipInCidr('2001:db8::1', '2001:db8::/32');         // true
IP::summarise(['10.0.0.0/25', '10.0.0.128/25']);      // ['10.0.0.0/24']
```

| Method | |
|---|---|
| `parse(string $cidr): IPv4\|IPv6` | A network object |
| `fromRange(string $startIp, string $endIp): IPv4\|IPv6` | The smallest block covering a range |
| `fromMask(string $networkIp, string $subnetMask): IPv4` | From a dotted mask |
| `ipInCidr(string $ip, string $cidr): bool` | |
| `summarise(array $cidrs): array` | Merge adjacent blocks |

Network objects also offer `overlaps()`, `split()`, `supernet()`, host enumeration and more — see the [IP README](https://github.com/laikait/laika-engine/blob/main/src/IP/README.MD). For the *client's* IP, use `Visitor::ip()` (see [Requests](../02_routing/03_requests.md#client-information)).

## PhpMetadataParser

`Laika\Engine\Services\PhpMetadataParser::parse(string $file): array` reads `Key: Value` lines from a PHP file's first docblock — useful for describing modules or themes:

```php
/**
 * Name: Blog Module
 * Version: 1.0.0
 */
// → ['name' => 'Blog Module', 'version' => '1.0.0']
```

## See Also

- [Files & Storage](../16_files-and-storage/01_basic.md)
- [Helper Functions](../15_helpers/01_basic.md)
