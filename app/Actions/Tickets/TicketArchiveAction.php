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

use App\Enums\StatusEnum;
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
class TicketArchiveAction
{
    /**
     * Archive a ticket record in database.
     *
     * @param  int  $id  model id
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(int $id): Ticket
    {
        return DB::transaction(
            function () use ($id) {
                $ticket = Ticket::findOrFail($id);

                // Check if user can archive this ticket
                Gate::authorize('archive', $ticket);

                return tap($ticket)->update([
                    'status' => StatusEnum::ARCHIVED,
                    'archived_on' => now(),
                ]);
            }
        );
    }
}
