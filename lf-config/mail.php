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
    /** Driver */
    'mail.driver' => 'sendmail', // smtp, sendmail, mail, qmail, mail

    /** SMTP Debug */
    // 'mail.debug' = false,

    /** SMTP CharSet */
    // 'mail.charset' => 'UTF-8',

    /** From Email */
    // 'from.email' => 'user@example.com',

    // From Name
    // 'from.name' => 'Laika App',

    /** SMTP Host */
    // 'smtp.host' => 'localhost',

    /** SMTP Username */
    // 'smtp.username' => 'username',

    /** SMTP Password */
    // 'smtp.password' => 'password',

    /** Smtp Auth */
    // 'smtp.auth' => true,

    /** SMTP Port */
    // 'smtp.port' => 587,

    /** SMTP Secure */
    // 'smtp.secure' => 'ssl',

    /** SMTP Options */
    // 'smtp.options' => [
    //     'ssl' => [
    //         'verify_peer' => false,
    //         'verify_peer_name' => false,
    //         'allow_self_signed' => true,
    //     ],
    // ],
];