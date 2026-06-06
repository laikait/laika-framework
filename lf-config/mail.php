<?php
/**
 * Laika Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

return [
    /** Driver */
    'driver' => 'sendmail', // smtp, sendmail, mail, qmail, mail

    /** SMTP Debug */
    // 'debug' = false,

    /** SMTP CharSet */
    // 'charset' => 'UTF-8',

    /** From Email */
    // 'from_email' => 'user@example.com',

    // From Name
    // 'from_name' => 'Laika App',

    /** SMTP Host */
    // 'host' => 'localhost',

    /** SMTP Username */
    // 'username' => 'username',

    /** SMTP Password */
    // 'password' => 'password',

    /** Smtp Auth */
    // 'auth' => true,

    /** SMTP Port */
    // 'port' => 587,

    /** SMTP Secure */
    // 'secure' => 'ssl',

    /** SMTP Options */
    // 'options' => [
    //     'ssl' => [
    //         'verify_peer' => false,
    //         'verify_peer_name' => false,
    //         'allow_self_signed' => true,
    //     ],
    // ],
];