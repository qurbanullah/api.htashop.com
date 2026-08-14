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
class MessageCreateAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  model values
     */
    public function handle(array $data): Message
    {
        return DB::transaction(function () use ($data) {
            $message = Message::create([
                'user_id' => data_get($data, 'user_id'),
                'message' => data_get($data, 'message'),
                'messageable_type' => data_get($data, 'messageable_type'),
                'messageable_id' => data_get($data, 'messageable_id'),
            ]);

            return $message->loadMissing('commenter');
        });
    }
}
