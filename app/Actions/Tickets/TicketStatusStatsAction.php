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

use Illuminate\Support\Facades\DB;

/**
 * Action class for ticket status statistics.
 *
 * @category App\Actions\Tickets
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class TicketStatusStatsAction
{
    /**
     * Get ticket statistics by status
     *
     * @return array
     */
    public function handle(): array
    {
        $results = DB::table('tickets')
            ->select('status', DB::raw('count(*) as count'))
            ->groupBy('status')
            ->get();

        // Use Laravel collections to optimize data transformation
        $stats = $results->mapWithKeys(function ($item) {
            return [$item->status => (int) $item->count];
        })->toArray();

        // Ensure all status types are present with 0 count if not found
        return array_merge([
            'open' => 0,
            'closed' => 0,
            'resolved' => 0,
            'archived' => 0,
        ], $stats);
    }
}
