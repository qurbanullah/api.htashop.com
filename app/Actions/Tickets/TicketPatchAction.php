<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Tickets
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

namespace App\Actions\Tickets;

use App\Models\Ticket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\Tickets
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class TicketPatchAction
{
    /**
     * Update record in database.
     *
     * @param  array  $input  model values
     * @param  int  $id  model id to be updated
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(array $data, $id): Ticket
    {
        return DB::transaction(
            function () use ($data, $id) {
                $ticket = Ticket::findOrFail($id);

                // Check authorization based on what's being updated
                if (array_key_exists('status', $data)) {
                    Gate::authorize('changeStatus', $ticket);
                } elseif (array_key_exists('is_locked', $data)) {
                    Gate::authorize('lock', $ticket);
                } else {
                    Gate::authorize('update', $ticket);
                }

                return tap($ticket)->update($data);
            }
        );
    }
}
