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

/**
 * Optimized action class for all ticket statistics in one query.
 *
 * @category App\Actions\Tickets
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class TicketAllStatsAction
{
    /**
     * Get all ticket statistics in a single optimized query
     *
     * @return array
     */
    public function handle(): array
    {
        // Single query to get all statistics at once - maximum optimization
        $results = DB::table('tickets')
            ->select([
                DB::raw('COUNT(*) as total_count'),
                DB::raw('SUM(CASE WHEN status = "open" THEN 1 ELSE 0 END) as open_count'),
                DB::raw('SUM(CASE WHEN status = "closed" THEN 1 ELSE 0 END) as closed_count'),
                DB::raw('SUM(CASE WHEN status = "resolved" THEN 1 ELSE 0 END) as resolved_count'),
                DB::raw('SUM(CASE WHEN status = "archived" THEN 1 ELSE 0 END) as archived_count'),
                DB::raw('SUM(CASE WHEN priority = "low" THEN 1 ELSE 0 END) as low_priority_count'),
                DB::raw('SUM(CASE WHEN priority = "normal" THEN 1 ELSE 0 END) as normal_priority_count'),
                DB::raw('SUM(CASE WHEN priority = "high" THEN 1 ELSE 0 END) as high_priority_count'),
                DB::raw('SUM(CASE WHEN priority = "urgent" THEN 1 ELSE 0 END) as urgent_priority_count'),
                DB::raw('SUM(CASE WHEN stype = "license" THEN 1 ELSE 0 END) as license_category_count'),
                DB::raw('SUM(CASE WHEN stype = "question" THEN 1 ELSE 0 END) as question_category_count'),
                DB::raw('SUM(CASE WHEN stype = "security-issue" THEN 1 ELSE 0 END) as security_category_count'),
                DB::raw('SUM(CASE WHEN stype = "bug" THEN 1 ELSE 0 END) as bug_category_count'),
            ])
            ->first();

        return [
            'totalTickets' => (int) $results->total_count,
            'statusStats' => [
                'open' => (int) $results->open_count,
                'closed' => (int) $results->closed_count,
                'resolved' => (int) $results->resolved_count,
                'archived' => (int) $results->archived_count,
            ],
            'priorityStats' => [
                'low' => (int) $results->low_priority_count,
                'normal' => (int) $results->normal_priority_count,
                'high' => (int) $results->high_priority_count,
                'urgent' => (int) $results->urgent_priority_count,
            ],
            'categoryStats' => [
                'license' => (int) $results->license_category_count,
                'question' => (int) $results->question_category_count,
                'security' => (int) $results->security_category_count,
                'bug' => (int) $results->bug_category_count,
            ],
        ];
    }
}
