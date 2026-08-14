<?php

/**
 * CRUD operation class file.
 * php version 8.3
 *
 * @category  App\Actions\UserActions
 * @package   App\Actions\UserActions\UserActionsearchByIdAction
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
 * CRUD operation class for User Model.
 *
 * @category App\Actions\UserActions
 * @package  App\Actions\UserActions\UserActionsearchByIdAction
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 * @link     https://github.com/qurbanullah
 */
class UserSearchByIdAction
{
    /**
     * Find User by ID with all necessary relationships
     *
     * @param mixed $id Model id
     * @param bool $withTrashed Include soft-deleted records
     *
     * @return \App\Models\User|null
     */
    public function handle($id, bool $withTrashed = false): ?User
    {
        return DB::transaction(
            function () use ($id, $withTrashed) {
                $query = $withTrashed ? User::withTrashed() : User::query();

                return $query->where('id', $id)
                    ->with([
                        'roles',
                        'permissions',
                        'profile',
                        'academicProfile',
                    ])
                    ->first();
            }
        );
    }
}
?>
