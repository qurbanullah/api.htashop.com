<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php Message 8.4
 *
 * @category  App\Actions\Messages
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @Message   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Actions\Messages;

use Illuminate\Support\Facades\DB;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\Messages
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class MessageDeleteAction
{
    /**
     * Validate and create record in database.
     *
     * @param  int  $id  model id
     */
    public function handle(int $id): bool
    {
        $model = Message::findOrFail($id);
        $currentSorting = $model->sorting;
        Message::where('sorting', '>', $currentSorting)->decrement('sorting');

        return DB::transaction(
            function () use ($id) {
                return tap(
                    Message::destroy($id)
                )->target;
            }
        );
    }
}
