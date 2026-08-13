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

// S3 Config

return [
    // Region. Example: us-east-1
    'region'    =>  '',

    // Access Key ID
    'key'       =>  '',

    // Secret Access Key
    'secret'    =>  '',

    // Bucket Name
    'bucket'    =>  '',

    // Key Prefix Every Object Sits Under
    'root'      =>  'lf-storage',

    // Canned ACL Applied to Uploads
    'acl'       =>  'public-read',

    // Public Base Url. A CDN or Custom Domain.
    // Empty Falls Back to The Bucket Url
    'url'       =>  '',

    // API Version
    'version'   =>  'latest',

    // Custom Endpoint For S3 Compatible Services.
    // Example: MinIO, Cloudflare R2, DigitalOcean Spaces. Empty Uses AWS
    'endpoint'  =>  '',

    // Path Style Addressing. Required By Most S3 Compatible Services.
    // Only Applied When 'endpoint' is Set
    'path_style' => true
];
