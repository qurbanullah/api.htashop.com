<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Services\Packages
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

namespace App\Services\Packages;

use App\Helpers\CacheHelper;
use App\Actions\Packages\PackageCreateAction;
use App\Actions\Packages\PackageDeleteAction;
use App\Actions\Packages\PackageReadAction;
use App\Actions\Packages\PackageSearchByIdAction;
use App\Actions\Packages\PackageSortAction;
use App\Actions\Packages\PackageUpdateAction;
use App\Models\Package;
use Illuminate\Database\Eloquent\Collection;
/**
 * CRUD operation class for Document Model.
 *
 * @category App\Services\Packages
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 *
 * @license  CC BY-NC-ND 4.0 Deed https://creativecommons.org/licenses/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class PackageService
{
    /**
     * Validate and update record in database.
     *
     * @param  array  $request  data to update
     */
    public function create(array $data): Package
    {
        $result = (new PackageCreateAction)->handle($data);
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
        $cacheKey = 'cached_packages_' . md5(serialize($data));

        if (! (!is_null(CacheHelper::get([], $cacheKey)))) {
            return CacheHelper::remember([], 
                $cacheKey, 31536000, function () use ($data) {
                    return (new PackageReadAction)->handle($data);
                }
            );
        } else {
            return CacheHelper::get([], $cacheKey);
        }
    }

    /**
     * Update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function update(array $data, int $id): Package
    {
        $result = (new PackageUpdateAction)->handle($data, $id);
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
        $result = (new PackageDeleteAction)->handle($id);
        $this->_clearReadCaches();

        return $result;
    }

    /**
     * Search record in database.
     *
     * @param  int  $id  model id
     */
    public function searchById(int $id): Package
    {
        $cacheKey = 'cached_package_' . $id;

        if (! (!is_null(CacheHelper::get([], $cacheKey)))) {
            return CacheHelper::remember([], 
                $cacheKey, 31536000,
                function () use ($id) {
                    return (new PackageSearchByIdAction)->handle($id);
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
     * @return \App\Models\Package
     */
    public function sort($modelId, $newPosition)
    {
        // in x-sort (sortablejs) index starts from 0
        $model = (new PackageSortAction)->handle($modelId, $newPosition + 1);

        $this->_clearCaching($model);
    }

    /**
     * Clear caching after update.
     *
     * @param  \App\Models\Package  $package  data to update
     */
    private function _clearCaching(Package $package): void
    {
        // Clear package-specific caches
        CacheHelper::forget([], 'cached_package_' . $package->id);
        CacheHelper::forget([], 'cached_package' . $package->slug);
        CacheHelper::forget([], 'form_key_' . $package->slug);

        // Clear all cached read queries
        $this->_clearReadCaches();
    }

    /**
     * Clear all cached read queries with dynamic keys.
     */
    private function _clearReadCaches(): void
    {
        // Clear the legacy cache key for backward compatibility
        CacheHelper::forget([], 'cached_packages');

        // For dynamic cache keys, clear common filter combinations
        $commonFilters = [
            ['is_active' => true],
            ['is_active' => false],
            ['is_active' => true, 'name' => ''],
            ['is_active' => false, 'name' => ''],
        ];

        foreach ($commonFilters as $filters) {
            $cacheKey = 'cached_packages_' . md5(serialize(['filters' => $filters]));
            CacheHelper::forget([], $cacheKey);
        }
    }
}
