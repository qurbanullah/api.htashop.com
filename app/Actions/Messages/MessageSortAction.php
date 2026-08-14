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

use App\Models\Message;

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
class MessageSortAction
{
    /**
     * Validate and create record in database.
     *
     * @param  int  $modelId  model id to be sorted
     * @param  int  $newPosition  new position of the model
     */
    public function handle($modelId, $newPosition): Message
    {
        $model = (new MessageSearchByIdAction)->handle($modelId);
        $currentPostion = $model->sorting;

        $modelNeedToBeShifted = Message::whereBetween(
            'sorting',
            [
                min($currentPostion, $newPosition),
                max($currentPostion, $newPosition),
            ]
        );

        if ($currentPostion < $newPosition) {
            $modelNeedToBeShifted->decrement('sorting');
        } else {
            $modelNeedToBeShifted->increment('sorting');
        }

        $model->update(
            [
                'sorting' => $newPosition,
            ]
        );

        return $model;
    }
}
