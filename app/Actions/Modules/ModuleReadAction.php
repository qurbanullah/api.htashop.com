<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Modules
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Actions\Modules;

use App\Models\Module;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\Modules
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class ModuleReadAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  Data array
     */
    public function handle(array $data = []): Collection
    {
        $filters = data_get($data, 'filters');
        $where_filters = [];
        if (isset($filters['is_active'])) {
            $where_filters[] = [
                'is_active',
                '=',
                data_get($filters, 'is_active', true),
            ];
        }

        return DB::transaction(function () use ($where_filters) {
            return tap(Module::where($where_filters)->get())->target;
        });
    }
}
