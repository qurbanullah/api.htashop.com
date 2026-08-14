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
class TicketSearchByUuidAction
{
    /**
     * Validate and create record in database.
     *
     * @param  mixed  $uuid  Model uuid
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle($uuid): ?Ticket
    {
        $ticket = DB::transaction(function () use ($uuid) {
            return Ticket::where('uuid', $uuid)
                ->with([
                    'reporter:id,name,email,photo',
                    'messages' => function ($query) {
                        return $query->with('commenter:id,name,email,photo');
                    },
                    'assignments' => function ($query) {
                        return $query->with(['assignedBy:id,name,email', 'assignedTo:id,name,email,photo'])
                            ->orderBy('assigned_at', 'desc');
                    },
                    'softwares' => function ($query) {
                        return $query->with('versions');
                    },
                    'versions',
                    'ltypes',
                    'packages',
                    'tags',
                ])
                ->first();
        });

        // Check authorization if ticket exists
        if ($ticket) {
            Gate::authorize('view', $ticket);
        }

        return $ticket;
    }
}
