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
];
