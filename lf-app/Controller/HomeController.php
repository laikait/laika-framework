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

// Namespace
namespace Laika\App\Controller;

// Deny Direct Access
defined('APP_PATH') || http_response_code(403) . die('403 Direct Access Denied!');

use Laika\Core\App\Template;

class HomeController
{
    /**
     * Args contains Request Object and Other Route Parameters
     */
    public function index()
    {
        // Template Object
        $tpl = new Template();
        // Assign Data
        $tpl->assign('title', 'Home');
        // Assign Data
        $tpl->assign('welcome', 'WELCOME!');
        $tpl->assign('description', 'Your Lightweight Micro Framework for Fast and Clean Development.');

        // load View File
        return $tpl->view('home');
    }
}
