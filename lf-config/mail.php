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

/**
 * Passed as-is to Laika\Mailman\Mailer: new Mailer(config('mail')).
 * Key names are the Mailer's own; anything else is ignored.
 */
return [
    /** Driver */
    'driver' => 'sendmail', // smtp, sendmail, mail, qmail

    /** From Email */
    // 'from' => 'user@example.com',

    /** From Name */
    // 'from_name' => 'Laika App',

    /** SMTP Host */
    // 'host' => 'localhost',

    /** SMTP Port */
    // 'port' => 587,

    /** SMTP Encryption */
    // 'encryption' => 'tls', // tls (STARTTLS, port 587), ssl (port 465) or '' for none

    /** SMTP Username */
    // 'username' => 'username',

    /** SMTP Password */
    // 'password' => 'password',

    /** Verify The SMTP Server's TLS Certificate */
    // 'validate_cert' => true,

    /** SMTP Timeout in Seconds */
    // 'timeout' => 30,

    /** SMTP Debug Level */
    // 'debug' => 0, // 0 (off) to 4

    /** CharSet */
    // 'charset' => 'UTF-8',
];
