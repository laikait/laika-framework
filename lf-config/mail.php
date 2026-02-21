<?php
/**
 * Laika PHP Micro Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Micro Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

return [
    // Driver
    'driver' => 'smtp', // smtp, sendmail, mail

    // // IS SMTP
    // 'is.smtp' => true,

    // SMTP Host
    'host' => 'laikait.com',

    // Smtp Auth
    'auth' => true,

    // SMTP Username
    'username' => '',

    // SMTP Password
    'password' => '',

    // SMTP Secure: 'tls' or 'ssl'
    'secure' => 'ssl',

    // SMTP Port
    'port' => 465,

    // SMTP CharSet
    'charset' => 'UTF-8',

    // From Email
    'from.email' => '',

    // From Name
    'from.name' => '',

    // // SMTP Options
    // 'options' => [
    //     'ssl' => [
    //         'verify_peer' => false,
    //         'verify_peer_name' => false,
    //         'allow_self_signed' => true,
    //     ],
    // ],
];