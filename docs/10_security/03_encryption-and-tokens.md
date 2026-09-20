# Encryption & Tokens

The Core module's cryptography helpers, all keyed from the application secret: encryption, password hashing, signatures, random tokens, JWTs and IDs.

## App Key

`lf-storage/keys/app.key` (mode `0600`) is the application secret. `Vault`, `Token` and `CSRF` derive their keys from it.

- It's generated on `composer install` (through `php laika app:sync`) when missing or invalid.
- `php laika secret:fix` generates it only if needed; `php laika secret:generate` forces a new one.
- **A new key invalidates everything signed or encrypted with the old one** — `Vault` ciphertexts, `hash()` values, signatures, JWTs and CSRF tokens. Password hashes are not affected.
- Generate the key once per environment, keep it out of version control, and back it up with the database if you store encrypted data.

| `Laika\Engine\Services\AppKey` method | |
|---|---|
| `get(): string` | The key; throws `AppKeyException` if missing or invalid |
| `generate(int $byte = 32): void` | Write a new key, replacing any existing one |
| `validate(int $byte = 32): bool` | Check the key's length; throws otherwise |
| `fix(int $byte = 32): void` | Generate only if missing or invalid |

## Vault

`Laika\Engine\Services\Vault` — encryption, keyed hashing, password hashing, signing and random values. Requires `ext-openssl`.

```php
use Laika\Engine\Services\Vault;

// Encryption (authenticated, AES-256-GCM by default)
$secret = Vault::encrypt('card-ending-4242');
Vault::decrypt($secret);                         // 'card-ending-4242'

// Passwords (Argon2id)
$hash = Vault::hashPassword($password);
Vault::verifyPassword($password, $hash);         // true
if (Vault::needsRehash($hash)) { /* re-hash on next login */ }

// Tamper-proof, readable values (e.g. an unsubscribe link)
$signed = Vault::sign('user=42');
Vault::verify($signed);                          // 'user=42', or throws

// Random values
Vault::token();          // URL-safe random token from 32 bytes
Vault::numericOtp(6);    // "048213"
```

| Method | |
|---|---|
| `encrypt(string $text): string` / `decrypt(string $encryptedBase64): string` | Authenticated encryption, base64. `decrypt()` throws `RuntimeException` on invalid or tampered data. |
| `encryptArray(array $data): string` / `decryptArray(string $encrypted): array` | The same, via JSON |
| `setCipher(string $cipher): static` | A **copy** using `aes-256-gcm` (default), `aes-128-gcm` or `chacha20-poly1305` |
| `hash(string $text, string $algo = 'sha256'): string` | HMAC keyed with the app key (`sha256`, `sha384`, `sha512`, `sha3-*`) |
| `hashVerify(string $text, string $hash, string $algo = 'sha256'): bool` | Constant-time check |
| `hashPassword(string $password): string` | Argon2id |
| `verifyPassword(string $password, string $hash): bool` | `password_verify()` |
| `needsRehash(string $hash): bool` | Whether the hash predates the current settings |
| `sign(string $text): string` / `verify(string $signed): string` | HMAC-SHA256 signature; `verify()` returns the text or throws |
| `token(int $length = 32): string` | URL-safe random token from `$length` bytes |
| `numericOtp(int $digits = 6): string` | Zero-padded numeric code, 4–12 digits |

`setCipher()` returns a configured copy and leaves the shared instance alone — use the return value: `Vault::setCipher('chacha20-poly1305')->encrypt($data)`. Ciphertexts record their cipher, so `decrypt()` handles any of them.

`hash()` is keyed, so it's the right tool for looking up a secret by its hash (an API key, a remember-me token) without storing the secret itself.

## Token (JWT)

`Laika\Engine\Services\Token` issues stateless tokens that carry a user payload:

```php
use Laika\Engine\Services\Token;

$token = Token::generate(['id' => 7, 'role' => 'staff']);

if (Token::validateToken($token)) {
    $user = Token::user();   // ['id' => 7, 'role' => 'staff']
}
```

| Method | |
|---|---|
| `generate(?array $user = null): string` | Issue a token carrying `$user` |
| `validateToken(?string $token): bool` | Check it and load its payload; `false` if invalid or expired |
| `check(): bool` | Whether a validated payload is loaded |
| `user(): ?array` | The payload from the last successful `validateToken()` |
| `flush(): void` | Forget the loaded payload |
| `refresh(string $token): ?string` | A new token with the same payload and a new expiry, or `null` |
| `setTtl(int $ttl): void` | Lifetime in seconds, default 3600 |

The payload is an HS256 JWT signed with the app key, then **encrypted with `Vault`** — clients and standard JWT tools can't read it. Tokens can't be revoked before they expire; for revocable, database-backed tokens use the [token guard](../09_authentication/01_basic.md#token-guard).

> `iat` and `exp` are fixed when the `Token` instance is created — once per request under PHP-FPM. In a long-running worker, call `Token::clearResolvedInstance()` before issuing, or every token carries the worker's start time.

## Uid and Unique

```php
use Laika\Engine\Generator\Uid;
use Laika\Engine\Services\Unique;

Uid::make();                               // "3f2b8c1e-9a4d-4c2f-8e7b-1d6a0f5c9b21" (RFC 4122 v4)
Uid::isValid($routeParam);                 // reject malformed ids before querying
Uid::stamp([['name' => 'A'], ['name' => 'B']]); // add 'uid' to each row that lacks one

Unique::generate('{y}{d}{c}{c}{c}{n}{n}', 'INV-');   // "INV-2615k3b07"
```

| `Uid` | |
|---|---|
| `make(): string` | A lowercase v4 UUID |
| `isValid(mixed $uid): bool` | Canonical v4 UUID? (The validator's `uid` rule is looser.) |
| `stamp(array $rows): array` | Adds `uid` to a row, or to every row in a list |

`Unique::generate(string $pattern, string $prefix = '', string $suffix = ''): string` builds readable reference numbers from tokens. The pattern needs at least 3 tokens.

| Token | Replaced with |
|---|---|
| `{Y}` / `{y}` | Year, 4 / 2 digits |
| `{d}` / `{D}` | Day of month / day name |
| `{m}` / `{i}` | **Minutes** (there is no month token) |
| `{G}` / `{H}` / `{h}` | Hour |
| `{s}` | Seconds |
| `{c}` | A random character from `a-z0-9` |
| `{n}` | A random digit |

Uniqueness comes only from `{c}` and `{n}` — put a unique index on the column and retry on collision.

## Regex Rules

`Laika\Engine\Services\Regex` holds named, reusable validation patterns:

```php
use Laika\Engine\Services\Regex;

Regex::validate('email', $input);                          // bool
Regex::validate('minimum', $password, 12);                 // extra args configure the rule
Regex::validate('password', $pw, 10, true, true, true, false); // min, upper, lower, numeric, special
Regex::checkRules('Abc123!');                              // ['alpha' => false, 'hasupper' => true, ...]
```

Built-in rules: `alpha`, `alphanumeric`, `numeric`, `email`, `url`, `hasupper`, `haslower`, `hasnumeric`, `hasspecial`, `minimum` (default 6), `maximum` (default 100), `password`. Pass the password minimum explicitly — its default is 6.

A custom rule extends `Laika\Engine\Regex\Abstracts\Rule`, implements `pattern(): string`, and is registered with `Regex::addRule()`. See the [Regex README](https://github.com/laikait/laika-engine/blob/main/src/Regex/README.MD).

## See Also

- [CSRF & CORS](02_csrf-and-cors.md)
- [Authentication](../09_authentication/01_basic.md)
