<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Feedbacks
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

namespace App\Actions\Feedbacks;

use App\Models\Feedback;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Pipeline;

/**
 * CRUD operation class for Feedback Model.
 *
 * @category App\Actions\Feedbacks
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class FeedbackShowAction
{
    /**
     * Get feedback records with filters and pagination.
     *
     * @param  array  $data  filter and pagination data
     */
    public function handle(array $data): LengthAwarePaginator
    {
        return Pipeline::send(Feedback::query())
            ->through(
                [
                    new \App\Filters\Feedbacks\FeedbackSearchFilter(data_get($data, 'filters.search')),
                    new \App\Filters\Feedbacks\FeedbackTypeFilter(data_get($data, 'filters.type')),
                    new \App\Filters\Feedbacks\FeedbackStatusFilter(data_get($data, 'filters.status')),
                    new \App\Filters\Feedbacks\FeedbackPriorityFilter(data_get($data, 'filters.priority')),
                ]
            )
            ->thenReturn()
            ->with('repliedBy')
            ->latest()
            ->paginate(data_get($data, 'rows', 15));
    }
}
