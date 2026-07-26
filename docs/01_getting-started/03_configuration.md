# Laika Framework — Configuration

Config lives in `lf-config/*.php` (no `.env`). Each file returns/sets config values, loaded on boot.

## lf-config/app.php

- App-level settings.

```php
return [
    // Provider
    'name'          =>  'Laika Framework',

    // Provider_url
    'url'           =>  'https://laikaframework.com',

    // Docs_url
    'documentation' =>  'https://docs.laikaframework.com'
];
```

## lf-config/database.php

```php
return [
    'driver'   => 'mysql',
    'host'     => '127.0.0.1',
    'database' => 'laika_db',
    'username' => 'root',
    'password' => '',
    'charset'  => 'utf8mb4',
];
```

Used by `Laika\Model\Model` for PDO connections.

## Auth config (laika-auth)

- Guards: `session`, `cookie`, `token` (SessionGuard, CookieGuard, TokenGuard)
- Each guard has its own session key, e.g. `laika_auth_admin`
- OAuth: `OauthGuard` (Google, Facebook) — configure client id/secret per provider
- Config-driven guard/provider resolution via `AuthManager`

```php
return [
    'guards' => [
        'web'   => ['driver' => 'session', 'provider' => 'users'],
        'admin' => ['driver' => 'session', 'provider' => 'admins'],
        'api'   => ['driver' => 'token', 'provider' => ApiModel::class],
        'user'  => ['driver' => 'token', 'provider' => UserModel::class],
    ]
];
```

## Shield config (laika-shield)

- Dot-notation config via `Config` singleton: `add()`, `get()`, `has()`
- Rules implement `RuleInterface` (`statusCode()`, `additionalHeader()`)
- Country blocking via MaxMind GeoLite2 (set DB path)

```php
Config::add('shield.rules', [...]);
Config::add('shield.geoip.database', 'lf-storage/GeoLite2-Country.mmdb');
```

## CORS

Set via `Laika\Service\CORS` static setters (merged with defaults: `X-Content-Type-Options`, `Referrer-Policy`, `X-Frame-Options`, `Content-Security-Policy`).

## Hooks

`lf-hooks/` — hook definitions using `Hook::add()` / `Hook::do()` / `Hook::apply()` (or `add_hook()` / `apply_hook()` globals).

## Routes

`lf-routes/` — auto-loaded route files, supports `{name}` and `{name:pattern}` placeholders.

## Language (if used)

Set via `Laika\Service\Local`
`en.local.php` — Sample language file; Managed via `Local::set('en')` / `Local::get()` / `Local::path(...additional path)`.