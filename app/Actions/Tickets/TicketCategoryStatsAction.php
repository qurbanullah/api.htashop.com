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
 * Action class for ticket category statistics.
 *
 * @category App\Actions\Tickets
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class TicketCategoryStatsAction
{
    /**
     * Get ticket statistics by category (stype)
     *
     * @return array
     */
    public function handle(): array
    {
        $results = DB::table('tickets')
            ->select('stype', DB::raw('count(*) as count'))
            ->groupBy('stype')
            ->get();

        // Use Laravel collections to optimize data transformation
        $stats = $results->mapWithKeys(function ($item) {
            return [$item->stype => (int) $item->count];
        })->toArray();

        // Ensure all category types are present with 0 count if not found
        // Map 'security-issue' to 'security' for display consistency
        return array_merge([
            'license' => 0,
            'question' => 0,
            'security' => 0,
            'bug' => 0,
        ], [
            'license' => $stats['license'] ?? 0,
            'question' => $stats['question'] ?? 0,
            'security' => $stats['security-issue'] ?? 0,
            'bug' => $stats['bug'] ?? 0,
        ]);
    }
}
