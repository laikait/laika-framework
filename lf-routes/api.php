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

use Laika\Core\App\Router;

// Start Register API Http From Here
// ###### Sample: #######
// Http::get('/sample', 'SampleController@index');
// ##### With Middleware #####
// Http::get('/sample', 'SampleController@index', [CBM\App\Middleware\SampleMiddleware::class]);
// ##### Post Request #####
// Http::post('/sample', 'SampleController@index');

Router::get('/status', function() {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'API is Working With Get Method']);
});

Router::put('/status', function() {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'API is Working With Put Method']);
});