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
    'guards' => [
        'web'   =>  ['driver' => 'session', 'provider' => 'users'],
        'admin' =>  ['driver' => 'session', 'provider' => 'admins'],
        'api'   =>  ['driver' => 'token'],
    ],
    'oauth' => [
        'google' => [
            'client_id'     =>  'GOOGLE_CLIENT_ID',
            'client_secret' =>  'GOOGLE_CLIENT_SECRET',
            'user_model'    =>  '\\App\\Models\\User::class',
        ],
        'facebook' => [
            'client_id'     =>  'FACEBOOK_CLIENT_ID',
            'client_secret' =>  'FACEBOOK_CLIENT_SECRET',
            'user_model'    =>  '\\App\\Models\\User::class',
        ],
    ],
];
