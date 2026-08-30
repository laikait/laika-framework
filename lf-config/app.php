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
    // Provider
    'name'          =>  'Laika Framework',

    // Provider_url
    'url'           =>  'https://laikaframework.com',

    // Docs_url
    'documentation' =>  'https://docs.laikaframework.com',

    // Proxies allowed to set X-Forwarded-* / CF-Visitor / X-Real-IP.
    // IPs or CIDR ranges, e.g. ['10.0.0.0/8', '192.168.1.5'].
    // Empty means trust nothing, which is correct for a directly reached
    // server. '*' trusts every forwarded header and is a last resort.
    'trusted_proxies' => []
];
