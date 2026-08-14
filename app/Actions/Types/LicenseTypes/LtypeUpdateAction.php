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
use Illuminate\Support\Facades\DB;

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
class LtypeUpdateAction
{
    /**
     * Update record in database.
     *
     * @param  array  $input  model values
     * @param  int  $id  model id to be updated
     */
    public function handle(array $data, $id): Ltype
    {
        return DB::transaction(
            function () use ($data, $id) {
                return tap(Ltype::findOrFail($id))->update(
                    [
                        'name' => data_get($data, 'name'),
                        'slug' => data_get($data, 'slug'),
                        'image' => data_get($data, 'image'),
                        'summary' => data_get($data, 'summary'),
                        'description' => data_get($data, 'description'),
                        'is_active' => data_get($data, 'is_active'),
                        'sorting' => data_get($data, 'sorting'),
                    ]
                );
            }
        );
    }
}
