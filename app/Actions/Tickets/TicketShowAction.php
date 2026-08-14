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
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Pipeline;

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
class TicketShowAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  model values
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(array $data): LengthAwarePaginator
    {
        // Check if user can view any tickets
        Gate::authorize('viewAny', Ticket::class);

        $query = Ticket::query();

        // Apply user-specific filters based on permissions
        $user = auth()->user();
        if (!$user->hasRole(['super-admin', 'admin'])) {
            // Regular users can only see their own tickets.
            $query->where('user_id', $user->id);
        }

        return Pipeline::send($query)
            ->through(
                [
                    new \App\Filters\FilterByUserId(data_get($data, 'filters')),
                    new \App\Filters\FilterBySubmitterType(data_get($data, 'filters')),
                    new \App\Filters\FilterByStatus(data_get($data, 'filters')),
                    new \App\Filters\FilterByTitle(data_get($data, 'filters')),
                ]
            )
            ->thenReturn()
            ->select('*')
            ->with(['reporterWithTrashed:id,name,deleted_at'])
            ->latest()
            ->paginate(data_get($data, 'rows_per_page'));
    }
}
