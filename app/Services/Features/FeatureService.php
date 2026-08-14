<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Services\Features
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 *
 * @license   CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Services\Features;

use App\Helpers\CacheHelper;
use App\Actions\Features\FeatureCreateAction;
use App\Actions\Features\FeatureDeleteAction;
use App\Actions\Features\FeatureSearchByIdAction;
use App\Actions\Features\FeatureSortAction;
use App\Actions\Features\FeatureUpdateAction;
use App\Models\Feature;
/**
 * CRUD operation class for Document Model.
 *
 * @category App\Services\Features
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 *
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class FeatureService
{
    /**
     * Validate and update record in database.
     *
     * @param  array  $request  data to update
     */
    public function create(array $data): Feature
    {
        $result = (new FeatureCreateAction)->handle($data);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function update(array $data, int $id): Feature
    {
        $result = (new FeatureUpdateAction)->handle($data, $id);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Delete record in database.
     *
     * @param  int  $id  model id
     */
    public function delete(int $id): bool
    {
        $result = (new FeatureDeleteAction)->handle($id);
        CacheHelper::forget([], 'cached_Features');

        return $result;
    }

    /**
     * Search record in database.
     *
     * @param  int  $id  model id
     */
    public function searchById(int $id): Feature
    {
        return CacheHelper::remember([], 
            'cached_feature_' . $id, 31536000,
            fn () => (new FeatureSearchByIdAction)->handle($id)
        );
    }

    /**
     * Sort record in database.
     *
     * @param  int  $modelId  model id to be sorted
     * @param  int  $newPosition  new position of the model
     * @return \App\Models\Feature
     */
    public function sort($modelId, $newPosition)
    {
        // in x-sort (sortablejs) index starts from 0
        $model = (new FeatureSortAction)->handle($modelId, $newPosition + 1);

        $this->_clearCaching($model);
    }

    /**
     * Clear caching after update.
     *
     * @param  \App\Models\Feature  $feature  data to update
     */
    private function _clearCaching(Feature $feature): void
    {
        CacheHelper::forget([], 'cached_features');
        CacheHelper::forget([], 'cached_feature_'.$feature->id);
        CacheHelper::forget([], 'cached_feature'.$feature->slug);
        CacheHelper::forget([], 'form_key_'.$feature->slug);
    }
}
