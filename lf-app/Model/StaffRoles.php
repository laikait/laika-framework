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

namespace App\Model;

use Laika\Model\Model;

class StaffRoles extends Model
{
    /** @var string Table Name */
    protected string $table = 'staff_roles';

    /** @var string Primary Column Name */
    protected string $id = 'rid';

    /** @var string UID Column Name */
    protected string $uid = 'uid';

    /** @var string Database Connection Name */
    protected string $connection = 'default';

    /** @var bool Soft Delete */
    protected bool $softDelete = false;

    /** @var string Deleted At Column */
    protected string $deletedAtColumn = 'deleted_at';

    /** @var array<string,string> Casts. Example: ['column1' => 'int', 'column2' => 'string', [.....]] */
    protected array $casts = [];

    // Start writing from here
}
