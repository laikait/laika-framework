<?php
/**
* Laika PHP MVC Framework
* Author: Showket Ahmed
* Email: riyadhtayf@gmail.com
* License: MIT
* This file is part of the Laika PHP MVC Framework.
* For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
*/

declare(strict_types=1);

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

return [
    // Extensions the framework may serve. Anything not listed is a 404, whatever
    // directory it sits in -- that is what keeps .twig sources, .env, lf-logs/*.log
    // and lf-storage/keys/app.key unreachable. Content-Type comes from MimeType.
    'extensions' => [
        'css', 'js', 'map',
        'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'ico', 'bmp',
        'woff', 'woff2', 'ttf', 'otf',
        'mp3', 'wav', 'ogg', 'mp4', 'webm',
        'pdf', 'zip', 'txt', 'csv',
    ],

    // Refused everywhere, even if listed above. PHP-family source must never be
    // handed out as bytes; json is refused because composer.json and config
    // exports are json.
    'blocked' => ['php', 'phar', 'phtml', 'phps', 'json'],

    // Roots written by users rather than by you. Markup served from here is stored
    // XSS, so these extensions are refused inside them even though the same file
    // is servable from template/ or assets/.
    'untrusted' => [
        'roots'   => ['uploads'],
        'blocked' => ['html', 'htm', 'svg', 'xml'],
    ],

    // How long the browser may keep a served file. A 304 is still possible at
    // any max-age, so revalidation stays cheap even for a short-lived type.
    'cache' => [
        // Seconds, for any extension with no entry below
        'default' => 3600,

        // Per-extension overrides. Types you rarely touch earn a long life; a
        // source map should not be cached hard or it outlives its bundle.
        'max_age' => [
            'woff2' => 31536000, 'woff' => 31536000, 'ttf' => 31536000, 'otf' => 31536000,
            'png'   => 2592000,  'jpg'  => 2592000,  'jpeg' => 2592000,
            'gif'   => 2592000,  'webp' => 2592000,  'ico'  => 2592000, 'svg' => 2592000,
            'css'   => 604800,   'js'   => 604800,
            'map'   => 0,
        ],

        // enqueue_style()/enqueue_script() append "?v={version}" to every tag,
        // so a request carrying a version query is already content-addressed
        // and may be cached immutably. Bump the version when the file changes.
        // Set to 0 to treat a versioned URL like any other.
        'versioned_max_age' => 31536000,
    ],

    // Hand the transfer to the web server instead of holding a PHP worker for
    // it. The checks above still decide what may be served; only the bytes
    // move. null, 'x-sendfile' (Apache mod_xsendfile) or 'x-accel-redirect'
    // (nginx, which needs a matching "internal" location). See the deployment
    // docs before turning this on -- a misconfigured server serves nothing.
    'sendfile' => null,
];
