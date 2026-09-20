# Files & Storage

The Core module's file helpers: uploads, image processing, reading and writing files, directories, archives, and storage drivers for local disk, S3, JSON documents, Redis and Memcached.

## Upload

`Laika\Engine\Services\Upload` validates and stores uploaded files. It's bound **per use** — each call to the relay gets a fresh instance.

```php
use Laika\Engine\Services\{Request, Upload};

$path = Upload::init(Request::file('avatar'))->single(APP_PATH . '/uploads/avatars', 'user-42', [
    'maxsize'      => 2 * 1024 * 1024,
    'extensions'   => ['jpg', 'png', 'webp'],
    'mimetypes'    => ['image/jpeg', 'image/png', 'image/webp'],
    'processimage' => true,
]);
// "/…/uploads/avatars/user-42.png", or false

if ($path === false) {
    Request::addError('avatar', 'Please upload a JPG, PNG or WebP image under 2 MB.');
}
```

| Method | Returns |
|---|---|
| `init(array $fields): static` | Takes a `$_FILES` entry. Required first. |
| `single(string $directory, ?string $name = null, array $options = []): string\|false` | The stored path, or `false` on any failure |
| `multiple(string $destinationDir, array $options = []): array` | `['success' => [name => ['slug', 'path']], 'errors' => [name => message]]` |

| Option | Meaning |
|---|---|
| `maxsize` | Maximum bytes |
| `extensions` | An allow-list that **replaces** the default one |
| `mimetypes` | Allowed MIME types, checked against the file content (needs `fileinfo`) |
| `processimage` | Re-encode images at quality 85, stripping anything smuggled after the image data (needs `gd`) |
| `basename` | `multiple()` only: name files `{basename}_{index}` |

Always applied:

- **Blocked extensions** — `php`, `php3`–`php8`, `phtml`, `phps`, `phar`, `htaccess`, `htpasswd`, `cgi`, `pl`, `py`, `sh`, `bash`, `exe`, `so`, `dll` are refused whatever `extensions` says.
- **Default allow-list** — common images, office documents, archives, audio and video. `svg` is deliberately excluded (it can run script).
- **Naming** — `single()` uses the slugified `$name` (or the original name) plus the original extension, and **overwrites** a file of the same name. `multiple()` prefixes a timestamp.

`multiple()` carries on past a bad file: a file that fails validation is listed in `errors` and not stored, and the rest are processed. (laika-core 5.1.1 and earlier still moved the rejected file — on those versions, call `single()` per file.)

Files under `uploads/` are served directly, except markup types (`html`, `svg`, `xml`) — see [`lf-config/assets.php`](../01_getting-started/03_configuration.md#lf-configassetsphp). For private files, store them under `lf-storage/` and serve them through a controller with `File::download()`.

## Image

`Laika\Engine\Services\Image` (per use, requires GD):

```php
use Laika\Engine\Services\Image;

Image::path($upload)->thumbnail(300, 300, 'cover')->convertTo('webp')->save($thumbPath, 80);
```

`path()` must come first.

| Method | |
|---|---|
| `path(string $path): static` | Load JPEG, PNG, GIF or WebP (plus BMP/AVIF where GD supports them) |
| `resize(int $width, int $height, bool $keepAspect = true): static` | Fit inside the box |
| `crop(int $x, int $y, int $width, int $height): static` | |
| `thumbnail(int $width, int $height, string $mode = 'fit'): static` | `fit` shrinks; `cover` fills and centre-crops |
| `watermark(string $text, int $gdfont = 5, ?array $rgb = null, int $x = 10, int $y = 20): static` | Text watermark |
| `watermarkImage(string $logoPath, int $x = 0, int $y = 0, int $opacity = 100): static` | Image watermark |
| `rotate(float\|int $angle)`, `flipHorizontal()`, `flipVertical()`, `grayscale(): static` | Transforms |
| `convertTo(string $format): static` | Output format for `save()`/`show()`/`toBase64()` |
| `save(string $path, ?int $quality = null): bool` | Write; quality 0–100 (default 85). Give the path a matching extension. |
| `show(): void` / `toBase64(): string` | Send to the browser / a `data:` URI |
| `info(): array` / `destroy(): void` / `unlink(): bool` | Size and type / free memory / delete the **source** file |

## File

`Laika\Engine\Services\File`:

| Method | |
|---|---|
| `exists(string $file): bool`, `readable()`, `writable()` | Checks |
| `size(string $file): int\|false`, `mime(string $file): string\|false`, `info(string $file): array` | Details (`mime()` reads the content) |
| `extension()`, `name()`, `base()`, `path(string $file): string` | Extension, name without extension, basename, directory |
| `read(string $file): string\|false` | Contents |
| `write(string $content, string $file, int $flags = 0): bool` | Write, **creating missing directories** |
| `append(string $str, string $file): bool` | Append with a lock, creating the file if needed |
| `pop(string $file): bool` / `move(string $from, string $to)` / `copy(string $from, string $to)` | Delete / move / copy |
| `touch(string $file, ?int $mtime = null, ?int $atime = null): bool` | Timestamps |
| `require(string $file, bool $require_once = false): mixed` | Include; throws if missing |
| `download(string $file, ?string $as = null): void` | Stream as an attachment — see [Responses](../02_routing/04_responses.md#file-downloads) |

```php
File::append(date('c') . " import finished\n", APP_PATH . '/lf-storage/logs/import.log');
```

## Directory

`Laika\Engine\Services\Directory`:

| Method | |
|---|---|
| `folders(string $path): array` | Immediate sub-directories |
| `files(string $path, string\|array $ext = '*'): array` | Immediate files, optionally by extension (`'php'`, `['jpg', 'png']`) |
| `scan(string $path, bool $includeDirs = true, string\|array $ext = '*'): array` | Recursive listing |
| `exists(string $path): bool` | |
| `make(string $path, int $permissions = 0755, bool $recursive = true): bool` | Create (race-safe) |
| `empty(string $path): bool` | Delete the contents, keep the directory |
| `pop(string $path): bool` | Delete the directory and its contents |

Symlinks are removed as links — `pop()` and `empty()` never delete what a link points to.

## Zip

`Laika\Engine\Helper\Zip` (requires `ext-zip`):

```php
use Laika\Engine\Helper\Zip;

(new Zip(APP_PATH . '/lf-storage/backup.zip'))->create(APP_PATH . '/uploads');
(new Zip($archive))->extract(APP_PATH . '/lf-storage/import');
```

| Method | |
|---|---|
| `__construct(string $path)` | The archive path |
| `create(string\|array $files): bool` | A directory (relative paths kept) or a list of files (flattened) |
| `extract(string $to, int $maxBytes = 536870912, int $maxEntries = 10000): bool` | Refuses absolute/`..` entries, archives over 512 MB uncompressed, and over 10 000 entries |

## MimeType

`Laika\Engine\Services\MimeType`: `fromExtension(string $extension): string`, `fromFile(string $filename): string` (by extension), `fromContent(string $content): string` (by bytes), `all(): array`, `register(string $extension, string $mimeType): void`.

Registering a type only teaches the framework its `Content-Type`; whether it may be served is decided by `lf-config/assets.php`.

## Storage Drivers

The storage classes aren't relays — construct them where you need them.

### LocalStorage

```php
use Laika\Engine\Storage\LocalStorage;

$disk = new LocalStorage(APP_PATH . '/uploads', app_host() . 'uploads');

$url = $disk->upload(Request::file('document'), 'documents');
// https://example.com/uploads/documents/report-6650f1c2a3b4d-1718000000.pdf

$disk->delete('documents/' . $disk->name());
```

`__construct(?string $root = null, ?string $publicBaseUrl = null)`

| Method | |
|---|---|
| `upload(array\|string $file, ?string $destination = null): string` | Store a `$_FILES` entry or a local file; returns the public URL. The folder defaults to `Y/m/d`. |
| `delete(string $file): bool` | By path relative to the root |
| `url(string $file): string` | Public URL of a stored file |
| `root()`, `name()`, `path()`, `mime(): string` | The root, and the last upload's name, absolute path and MIME type |

Stored names get a `-uniqid-timestamp` suffix, so uploads never overwrite each other.

> The default root is `lf-storage/files`, which is never served — the URLs `upload()` returns for it give a 404. Point the root at `uploads/` for public files, or serve private files through a controller.

### S3Storage

`Laika\Engine\Storage\S3Storage(array $overrides = [], ?string $publicBaseUrl = null)` has the same methods as `LocalStorage`, and reads [`lf-config/s3.php`](../01_getting-started/03_configuration.md#lf-configs3php). Works with AWS and S3-compatible services (MinIO, Cloudflare R2, DigitalOcean Spaces) via `endpoint`.

```php
use Laika\Engine\Storage\S3Storage;

$s3  = new S3Storage(['acl' => 'private']);
$url = $s3->upload(Request::file('invoice'), 'invoices');
```

> Uploads are **public** by default (`'acl' => 'public-read'`). Buckets with ACLs disabled ("bucket owner enforced") need `'acl' => ''` or an ACL they accept.

### JsonStorage

`Laika\Engine\Storage\JsonStorage(?string $path = null)` keeps small JSON documents in `lf-storage/json/{name}.json`:

```php
use Laika\Engine\Storage\JsonStorage;

$store = new JsonStorage();
$store->set('settings', ['theme' => 'dark']);            // merge
$store->get('settings', 'theme');                          // 'dark'
$store->pop('settings', 'theme');

// Atomic read-modify-write
$next = $store->mutate('counters', fn (array $r) => [
    'records' => ['orders' => ($r['orders'] ?? 0) + 1] + $r,
    'return'  => ($r['orders'] ?? 0) + 1,
]);
```

| Method | |
|---|---|
| `set(string $name, array $array, bool $merge = true): bool` | Merge into (or replace) the document |
| `get(string $name, ?string $key = null): mixed` | The document or one key |
| `pop(string $name, string $key): bool` | Remove a key |
| `mutate(string $name, callable $fn): mixed` | Read-modify-write under one lock — use when the new value depends on the old one |

### RedisStorage and MemcachedStorage

> For caching, use [`Laika\Engine\Services\Cache`](../20_cache/01_basic.md) instead. It has per-call TTLs, `has()`, `remember()` and `flush()`, a file driver that needs no server, and it tells a stored `null` apart from a miss — these classes cannot.

Simple key/value stores, reading [`lf-config/redis.php`](../01_getting-started/03_configuration.md#lf-configredisphp) and [`lf-config/memcached.php`](../01_getting-started/03_configuration.md#lf-configmemcachedphp):

```php
use Laika\Engine\Storage\RedisStorage;

$cache = new RedisStorage();
$cache->expire(600);                       // TTL for later set() calls; 0 = none
$cache->set('stats', $stats);              // values are serialized
$stats = $cache->get('stats') ?? computeStats();
$cache->pop('stats');
```

Both have `set(string $key, mixed $value): bool`, `get(string $key): mixed` (null when missing), `pop(string $key): bool`, `expire(int $seconds): void` and `prefix(string $prefix): void`. A dead Memcached server isn't reported up front — it shows up as `false` from `set()` and `null` from `get()`.

### Connection Factories

`Laika\Engine\Storage\Connection\RedisConnection::make(array $overrides = []): Redis`, `MemcachedConnection::make(): Memcached` and `S3Connection::make(): S3Client` build bare clients from the same config files — use them when you need the client itself. A missing extension or a failed Redis connect throws `ExtensionException`.

## See Also

- [Requests](../02_routing/03_requests.md#uploaded-files) · [Responses → File Downloads](../02_routing/04_responses.md#file-downloads)
- [Configuration](../01_getting-started/03_configuration.md)
