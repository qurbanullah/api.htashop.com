<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Types
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

namespace App\Actions\Types\LicenseTypes;

use App\Models\Ltype;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Pipeline;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\Types
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class LtypeReadAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  Data array
     */
    public function handle(array $data = []): Collection
    {
        return Pipeline::send(Ltype::query())
            ->through(
                [
                    new \App\Filters\FilterByIsActive(data_get($data, 'filters', ['is_active' => true])),
                    new \App\Filters\FilterByName(data_get($data, 'filters', ['name' => ''])),
                ]
            )
            ->thenReturn()
            ->select('*')
            ->orderBy('sorting', 'asc')
            ->get();
    }
}
