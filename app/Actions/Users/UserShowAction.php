<?php

/**
 * CRUD operation class file.
 * php version 8.3
 *
 * @category  App\Actions\UserActions
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

namespace App\Actions\Users;

use App\Filters\FilterByIsActive;
use App\Filters\FilterByName;
use App\Filters\FilterByUserId;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Pipeline;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\UserActions
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class UserShowAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  model values
     */
    public function handle(array $data): LengthAwarePaginator
    {
        return Pipeline::send(User::query())
            ->through(
                [
                    new FilterByIsActive(data_get($data, 'filters')),
                    new FilterByName(data_get($data, 'filters')),
                    new FilterByUserId(data_get($data, 'filters')),
                ]
            )
            ->thenReturn()
            ->select('*')
            ->with(
                [
                    'btype:id,name',
                    'bstype:id,name,btype_id',
                ]
            )
            ->orderBy('sorting', 'asc')
            ->paginate(data_get($data, 'rows_per_page'));
    }
}
