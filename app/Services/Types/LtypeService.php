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
use App\Actions\Types\LicenseTypes\LtypeCreateAction;
use App\Actions\Types\LicenseTypes\LtypeReadAction;
use App\Actions\Types\LicenseTypes\LtypeSearchByIdAction;
use App\Actions\Types\LicenseTypes\LtypeSortAction;
use App\Actions\Types\LicenseTypes\LtypeUpdateAction;
use App\Models\Ltype;
use Illuminate\Database\Eloquent\Collection;
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
class LtypeService
{
    /**
     * Validate and update record in database.
     *
     * @param  array  $request  data to update
     */
    public function create(array $data): Ltype
    {
        $result = (new LtypeCreateAction)->handle($data);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Get all records from database.
     *
     * @param  array  $data  filter data
     */
    public function read(array $data = []): Collection
    {
        $cacheKey = 'cached_ltypes_' . md5(serialize($data));

        if (! (!is_null(CacheHelper::get([], $cacheKey)))) {
            return CacheHelper::remember([], 
                $cacheKey, 31536000, function () use ($data) {
                    return (new LtypeReadAction)->handle($data);
                }
            );
        } else {
            return CacheHelper::get([], $cacheKey);
        }
    }

    /**
     * Validate and update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function update(array $data, int $id): Ltype
    {
        $result = (new LtypeUpdateAction)->handle($data, $id);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Search record in database.
     *
     * @param  int  $id  model id
     */
    public function searchById(int $id): Ltype
    {
        $cacheKey = 'cached_ltype_' . $id;

        if (! (!is_null(CacheHelper::get([], $cacheKey)))) {
            return CacheHelper::remember([], 
                $cacheKey, 31536000,
                function () use ($id) {
                    return (new LtypeSearchByIdAction)->handle($id);
                }
            );
        } else {
            return CacheHelper::get([], $cacheKey);
        }
    }

    /**
     * Sort record in database.
     *
     * @param  int  $modelId  model id to be sorted
     * @param  int  $newPosition  new position of the model
     * @return \App\Models\Ltype
     */
    public function sort($modelId, $newPosition)
    {
        // in x-sort (sortablejs) index starts from 0
        $model = (new LtypeSortAction)->handle($modelId, $newPosition + 1);

        $this->_clearCaching($model);
    }

    /**
     * Delete data from the database.
     *
     * @param  int  $id  ID to delete
     */
    public function delete(int $id): bool
    {
        $ltype = $this->searchById($id);

        if ($ltype) {
            $ltype->delete();
            $this->_clearReadCaches(); // Use the new read cache clearing method

            return true;
        }

        return false;
    }

    /**
     * Clear caching after update.
     *
     * @param  \App\Models\Ltype  $ltype  data to update
     */
    private function _clearCaching(Ltype $ltype): void
    {
        // Clear ltype-specific caches
        CacheHelper::forget([], 'cached_ltype_' . $ltype->id);
        CacheHelper::forget([], 'cached_ltype' . $ltype->slug);
        CacheHelper::forget([], 'form_key_' . $ltype->slug);

        // Clear all cached read queries
        $this->_clearReadCaches();
    }

    /**
     * Clear all cached read queries with dynamic keys.
     */
    private function _clearReadCaches(): void
    {
        // Clear the legacy cache key for backward compatibility
        CacheHelper::forget([], 'cached_ltypes');

        // For dynamic cache keys, clear common filter combinations
        $commonFilters = [
            ['is_active' => true],
            ['is_active' => false],
            ['is_active' => true, 'name' => ''],
            ['is_active' => false, 'name' => ''],
        ];

        foreach ($commonFilters as $filters) {
            $cacheKey = 'cached_ltypes_' . md5(serialize(['filters' => $filters]));
            CacheHelper::forget([], $cacheKey);
        }
    }
}
