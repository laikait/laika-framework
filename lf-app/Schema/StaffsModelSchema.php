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

use App\Model\StaffsModel;
use Laika\Model\Schema\Schema;
use Laika\Model\Schema\Blueprint;
use Laika\Core\Abstracts\SchemaAbstract;
use Laika\Core\Exceptions\SchemaException;

class StaffsModelSchema extends SchemaAbstract
{
    /** @var string Database Table Name */
    protected string $table = 'staffs';

    /** @var string Database Connection Name */
    protected string $connection = 'default';

    public function up(): void
    {
        Schema::on($this->connection)->createIfNotExists($this->table, function (Blueprint $t) {
            $t->bigId('id');
            $t->string('uid');
            $t->timestamps();
            $t->enum('deleted', ['yes', 'no'])->default('no');
            $t->timestamp('deleted_at')->nullable();

            $t->index('deleted');
        });
    }
}
