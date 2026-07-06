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
namespace App\Schema;

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

use Laika\Model\Schema\Blueprint;
use Laika\Model\Schema\Schema;
use Laika\Core\Abstracts\SchemaAbstract;

class ServiceSchema extends SchemaAbstract
{
    protected string $table = 'services';

    public function up(): void
    {
        Schema::on()->createIfNotExists($this->table, function (Blueprint $t) {
            $t->id('sid');
            $t->uid();
            $t->string('name');
            $t->string('status', 50)->comment('This is services Status');
            $t->timestamps();
            $t->deleted('deletedAtColumn');
            
            // Indexes
            $t->index('status');
        });
    }

    public function seed(): void {}
}
