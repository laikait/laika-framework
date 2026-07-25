<?php
/**
 * Laika Framework
 * Author: Showket Ahmed
 * Email: riyadhtayf@gmail.com
 * License: MIT
 * This file is part of the Laika PHP Framework.
 * For the full copyright and license information, please view the LICENSE file that was distributed with this source code.
 */

declare(strict_types=1);

namespace App\Schema;

use App\Model\StaffRoles;
use Laika\Model\Schema\Schema;
use Laika\Model\Schema\Blueprint;
use Laika\Core\Abstracts\SchemaAbstract;
use Laika\Core\Exceptions\SchemaException;

class StaffRolesSchema extends SchemaAbstract
{
    /** @var string Database Table Name */
    protected string $table = 'staff_roles';

    /** @var string Database Connection Name */
    protected string $connection = 'default';

    public function up(): void
    {
        Schema::on($this->connection)->createIfNotExists($this->table, function (Blueprint $t) {
            $t->id('rid'); // Auto Primary Column
            $t->string('uid'); // Auto Unique Column
            $t->string('role_name')->comment('Example: superadmin');
            $t->json('permissions')->comment('JSON Data');

            $t->unique('role_name');
        });
    }
}
