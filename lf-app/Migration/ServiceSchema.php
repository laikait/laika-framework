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
namespace App\Migration;

// Deny Direct Access
defined('APP_PATH') || http_response_code(403).die('403 Direct Access Denied!');

use Laika\Core\Exceptions\MigrationException;
use Laika\Model\Schema\Blueprint;
use Laika\Model\Schema\Schema;
use App\Model\ServiceModel;

class ServiceSchema
{
    /**
     * Migrate Table
     */
    public function migrate()
    {
        Schema::on()->createIfNotExists('services', function (Blueprint $t) {
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

    /**
     * Default Values to Insert
     * @return void
     */
    public function default(): void
    {
        // $model = new ServiceModel();
        // $model->transaction(function (ServiceModel $m) {
        //     try {
        //         $default = [
        //             'uid' => $m->uid(),
        //             'status' => 'active'
        //         ];
        //         $m->insert($default);
        //     } catch (\Throwable $e) {
        //         throw new MigrationException("Unable to Insert Into 'services'. {$e->getMessage()}", (int) $e->getCode(), $e);
        //     }
        // });
        return;
    }
}
