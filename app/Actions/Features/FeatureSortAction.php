<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Actions\Features
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

namespace App\Actions\Features;

use App\Models\Feature;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Actions\Feature
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class FeatureSortAction
{
    /**
     * Validate and create record in database.
     *
     * @param  int  $modelId  model id to be sorted
     * @param  int  $newPosition  new position of the model
     */
    public function handle($modelId, $newPosition): Feature
    {
        $model = (new FeatureearchByIdAction)->handle($modelId);
        $currentPostion = $model->sorting;

        $modelNeedToBeShifted = Feature::whereBetween(
            'sorting',
            [
                min($currentPostion, $newPosition),
                max($currentPostion, $newPosition),
            ]
        );

        if ($currentPostion < $newPosition) {
            $modelNeedToBeShifted->decrement('sorting');
        } else {
            $modelNeedToBeShifted->increment('sorting');
        }

        $model->update(
            [
                'sorting' => $newPosition,
            ]
        );

        return $model;
    }
}
