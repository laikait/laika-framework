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

use Laika\Route\Url;
use Laika\Service\Response;

// // Start Register API Http From Here
// // ###### Sample: #######
// // Url::get('/sample', 'SampleController@index');
// // ##### With Middleware #####
// // Url::get('/sample', 'SampleController@index', [CBM\App\Middleware\SampleMiddleware::class]);
// // ##### Post Request #####
// // Url::post('/sample', 'SampleController@index');

Url::get('/status', function() {
    Response::setContentType('application/json');
    return json_encode(['status' => 'API is Working With Put Method']);
});

// Url::put('/status', function() {
//     Response::setContentType('application/json');
//     echo json_encode(['status' => 'API is Working With Put Method']);
// });