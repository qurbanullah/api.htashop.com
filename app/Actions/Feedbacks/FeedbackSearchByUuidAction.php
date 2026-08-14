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

/**
 * CRUD operation class for Feedback Model - Search by UUID.
 *
 * @category App\Actions\Feedbacks
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class FeedbackSearchByUuidAction
{
    /**
     * Find feedback by UUID.
     *
     * @param  string  $uuid  feedback uuid
     */
    public function handle(string $uuid): ?Feedback
    {
        return Feedback::where('uuid', $uuid)->with('repliedBy')->first();
    }
}
