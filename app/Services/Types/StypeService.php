<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Services\Types
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

namespace App\Services\Types;

use App\Helpers\CacheHelper;
use App\Actions\Types\SupportTypes\StypeCreateAction;
use App\Actions\Types\SupportTypes\StypeSortAction;
use App\Actions\Types\SupportTypes\StypeUpdateAction;
use App\Actions\Types\SupportTypes\StypeSearchByIdAction;
use App\Models\Stype;
/**
 * CRUD operation class for Document Model.
 *
 * @category App\Services\Types
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class StypeService
{
    /**
     * Validate and update record in database.
     *
     * @param  array  $request  data to update
     */
    public function create(array $data): Stype
    {
        $result = (new StypeCreateAction)->handle($data);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Validate and update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function update(array $data, int $id): Stype
    {
        $result = (new StypeUpdateAction)->handle($data, $id);
        $this->_clearCaching($result);

        return $result;
    }


    /**
     * Search record in database.
     *
     * @param  int  $id  model id
     */
    public function searchById(int $id): Stype
    {
        return CacheHelper::remember([], 
            'cached_stype_' . $id, 31536000,
            fn () => (new StypeSearchByIdAction)->handle($id)
        );
    }

    /**
     * Sort record in database.
     *
     * @param  int  $modelId  model id to be sorted
     * @param  int  $newPosition  new position of the model
     * @return \App\Models\Stype
     */
    public function sort($modelId, $newPosition)
    {
        // in x-sort (sortablejs) index starts from 0
        $model = (new StypeSortAction)->handle($modelId, $newPosition + 1);

        $this->_clearCaching($model);
    }

    /**
     * Clear caching after update.
     *
     * @param  \App\Models\Stype  $stype  data to update
     */
    private function _clearCaching(Stype $stype): void
    {
        CacheHelper::forget([], 'cached_stypes');
        CacheHelper::forget([], 'cached_stype_'.$stype->id);
        CacheHelper::forget([], 'cached_stype'.$stype->slug);
        CacheHelper::forget([], 'form_key_'.$stype->slug);
    }
}
