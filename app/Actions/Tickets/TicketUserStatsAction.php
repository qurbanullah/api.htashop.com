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
 * Optimized action class for user-specific ticket statistics in one query.
 *
 * @category App\Actions\Tickets
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class TicketUserStatsAction
{
    /**
     * Get user-specific ticket statistics in a single optimized query
     *
     * @param int $userId
     * @return array
     */
    public function handle(int $userId): array
    {
        // Single query to get all user-specific statistics at once - maximum optimization
        $results = DB::table('tickets')
            ->where('user_id', $userId)
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

        // Handle case where user has no tickets or results are null
        if (!$results) {
            return [
                'totalTickets' => 0,
                'statusStats' => [
                    'open' => 0,
                    'closed' => 0,
                    'resolved' => 0,
                    'archived' => 0,
                ],
                'priorityStats' => [
                    'low' => 0,
                    'normal' => 0,
                    'high' => 0,
                    'urgent' => 0,
                ],
                'categoryStats' => [
                    'license' => 0,
                    'question' => 0,
                    'security' => 0,
                    'bug' => 0,
                ],
            ];
        }

        return [
            'totalTickets' => (int) ($results->total_count ?? 0),
            'statusStats' => [
                'open' => (int) ($results->open_count ?? 0),
                'closed' => (int) ($results->closed_count ?? 0),
                'resolved' => (int) ($results->resolved_count ?? 0),
                'archived' => (int) ($results->archived_count ?? 0),
            ],
            'priorityStats' => [
                'low' => (int) ($results->low_priority_count ?? 0),
                'normal' => (int) ($results->normal_priority_count ?? 0),
                'high' => (int) ($results->high_priority_count ?? 0),
                'urgent' => (int) ($results->urgent_priority_count ?? 0),
            ],
            'categoryStats' => [
                'license' => (int) ($results->license_category_count ?? 0),
                'question' => (int) ($results->question_category_count ?? 0),
                'security' => (int) ($results->security_category_count ?? 0),
                'bug' => (int) ($results->bug_category_count ?? 0),
            ],
        ];
    }
}
