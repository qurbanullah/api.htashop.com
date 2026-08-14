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
 * CRUD operation class for Feedback Model - Update feedback.
 *
 * @category App\Actions\Feedbacks
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class FeedbackUpdateAction
{
    /**
     * Update a feedback record in database.
     *
     * @param  int  $feedbackId  feedback id
     * @param  array  $data  feedback values
     */
    public function handle(int $feedbackId, array $data): bool
    {
        $feedback = Feedback::find($feedbackId);
        if (!$feedback) {
            return false;
        }

        return $feedback->update($data);
    }
}
