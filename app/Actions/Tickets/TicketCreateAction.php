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
class TicketCreateAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  model values
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(array $data): Ticket
    {
        // Check if user can create tickets
        Gate::authorize('create', Ticket::class);

        return DB::transaction(
            function () use ($data) {
                return tap(
                    Ticket::create(
                        [
                            'uuid' => data_get($data, 'uuid'),
                            'user_id' => data_get($data, 'user_id'),
                            'guest_name' => data_get($data, 'guest_name'),
                            'guest_email' => data_get($data, 'guest_email'),
                            'title' => data_get($data, 'title'),
                            'slug' => data_get($data, 'slug'),
                            'stype' => data_get($data, 'stype'),
                            'severity' => data_get($data, 'severity'),
                            'reproducibility' => data_get($data, 'reproducibility'),
                            'priority' => data_get($data, 'priority'),
                            'status' => data_get($data, 'status'),
                            'is_visible' => data_get($data, 'is_visible'),
                            'is_resolved' => data_get($data, 'is_resolved'),
                            'is_locked' => data_get($data, 'is_locked'),
                            'is_archived' => data_get($data, 'is_archived'),
                            'description' => data_get($data, 'description'),
                            'steps_to_reproduce' => data_get($data, 'steps_to_reproduce'),
                            'additional_information' => data_get($data, 'additional_information'),
                            'resolved_on' => data_get($data, 'resolved_on'),
                            'archived_on' => data_get($data, 'archived_on'),
                        ]
                    )
                )->target;
            }
        );
    }
}
