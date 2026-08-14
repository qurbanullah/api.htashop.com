<?php

/**
 * CRUD operation class file.
 * php version 8.3
 *
 * @category  App\Actions\UserActions
 * @package   App\Actions\UserActions\UserActionshowAction
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @version   GIT: <git_id>
 * @link      https://github.com/qurbanullah
 */
namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Pipeline;
use \Illuminate\Pagination\LengthAwarePaginator;



/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\UserActions
 * @package  App\Actions\UserActions\UserActionshowAction
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @link     https://github.com/qurbanullah
 */
class UserShowAction
{
    /**
     * Validate and create record in database.
     *
     * @param array $data model values
     *
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function handle(array $data): LengthAwarePaginator
    {
        return Pipeline::send(User::query())
            ->through(
                [
                    new \App\Filters\FilterByIsActive(data_get($data, 'filters')),
                    new \App\Filters\FilterByName(data_get($data, 'filters')),
                    new \App\Filters\FilterByUserId(data_get($data, 'filters')),
                ]
            )
            ->thenReturn()
            ->select("*")
            ->with(
                [
                    'btype:id,name',
                    'bstype:id,name,btype_id'
                ]
            )
            ->orderBy('sorting', 'asc')
            ->paginate(data_get($data, 'rows_per_page'));
    }
}
?>
