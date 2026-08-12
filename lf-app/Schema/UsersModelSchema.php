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

use App\Model\UsersModel;
use Laika\Model\Schema\Schema;
use Laika\Model\Schema\Blueprint;
use Laika\Model\Contract\SchemaAbstract;
use Laika\Core\Exceptions\SchemaException;

class UsersModelSchema extends SchemaAbstract
{
    /** @var string Database Table Name */
    protected string $table = 'users';

    /** @var string Database Connection Name */
    protected string $connection = 'default';

    public function up(): void
    {
        Schema::on($this->connection)->createIfNotExists($this->table, function (Blueprint $t) {
            $t->bigId('id'); // Auto Primary Column
            $t->string('uid'); // Auto Unique Column
            $t->string('email');
            $t->string('first_name');
            $t->string('last_name');
            $t->string('username')->nullable()->default(NULL);
            $t->string('password')->nullable()->default(NULl);
            $t->enum('is_active', ['yes', 'no'])->default('no');
            $t->timestamp('deleted_at')->nullable()->default(NULL);

            $t->unique('email');
            $t->index('username');
        });
    }
}
