<?php

/**
 * CRUD operation class file.
 * php version 8.3
 *
 * @category  App\Actions\UserActions
 * @package   App\Actions\UserActions\UserUpdateAction
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
use Illuminate\Support\Facades\DB;


/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\UserActions
 * @package  App\Actions\UserActions\UserUpdateAction
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @link     https://github.com/qurbanullah
 */
class UserUpdateAction
{
    /**
     * Update record in database.
     *
     * @param array $input model values
     * @param int   $id    model id to be updated
     *
     * @return \App\Models\User
     */
    public function handle(array $data, $id): User
    {
        return DB::transaction(
            function () use ($data, $id) {
                return tap(User::findOrFail($id))->update($data);
            }
        );
    }
}
?>
