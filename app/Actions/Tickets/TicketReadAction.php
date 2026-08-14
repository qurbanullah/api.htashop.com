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
use Illuminate\Database\Eloquent\Collection;
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
class TicketReadAction
{
    /**
     * Validate and create record in database.
     *
     * @param  array  $data  Data array
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function handle(array $data = []): Collection
    {
        // Check if user can view any tickets
        Gate::authorize('viewAny', Ticket::class);

        $filters = data_get($data, 'filters');
        $where_filters = [];
        if (isset($filters['is_active'])) {
            $where_filters[] = [
                'is_active',
                '=',
                data_get($filters, 'is_active', true),
            ];
        }

        // Apply user-specific filters based on permissions
        $user = auth()->user();
        if (!$user->hasRole(['super-admin', 'admin'])) {
            // Regular users can only see their own tickets.
            $where_filters[] = function ($query) use ($user) {
                $query->where('user_id', $user->id);
            };
        }

        return DB::transaction(function () use ($where_filters) {
            $query = Ticket::query();
            foreach ($where_filters as $filter) {
                $query->where($filter);
            }
            return tap($query->get())->target;
        });
    }
}
