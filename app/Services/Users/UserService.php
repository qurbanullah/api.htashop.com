<?php

declare(strict_types=1);

/**
 * CRUD operation class file.
 * php version 8.4
 *
 * @category  App\Services\Users
 *
 * @author    Qurban Ullah <qurbanullah@gmail.com>
 * @copyright 2024 Qurban Ullah - All Rights Reserved
 * Unauthorized copying of this file, via any medium is strictly prohibited
 * Proprietary and confidential
 * Written by Qurban Ullah <qurbanullah@gmail.com>, 2024
 * @User   CC BY-NC-ND 4.0 Deed https://creativecommons.org/Users/by-nc-nd/4.0/
 *
 * @version   GIT: <git_id>
 *
 * @link      https://github.com/qurbanullah
 */

namespace App\Services\Users;

use App\Helpers\CacheHelper;
use App\Actions\Users\UserCreateAction;
use App\Actions\Users\UserDeleteAction;
use App\Actions\Users\UserSortAction;
use App\Actions\Users\UserUpdateAction;
use App\Actions\Users\UserPatchAction;
use App\Actions\Users\UserSearchByIdAction;
use App\Actions\Users\UserShowAction;
use App\Actions\Users\UserAllStatsAction;
use App\Actions\Users\UserStatusStatsAction;
use App\Actions\Users\UserRoleStatsAction;
use App\Actions\Users\UserTotalCountAction;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * CRUD operation class for Document Model.
 *
 * @category App\Services\Users
 *
 * @author   Qurban Ullah <qurbanullah@gmail.com>
 * @User  CC BY-NC-ND 4.0 Deed https://creativecommons.org/Users/by-nc-nd/4.0/
 *
 * @link     https://github.com/qurbanullah
 */
class UserService
{
    /**
     * Validate and update record in database.
     *
     * @param  array  $request  data to update
     */
    public function create(array $data): User
    {
        $result = (new UserCreateAction)->handle($data);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Validate and update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function update(array $data, int $id): User
    {
        $result = (new UserUpdateAction)->handle($data, $id);
        $this->_clearCaching($result);

        return $result;
    }

    /**
     * Update record in database.
     *
     * @param  array  $data  data to update
     * @param  int  $id  model id
     */
    public function patch(array $data, int $id): User
    {
        $result = (new UserPatchAction)->handle($data, $id);
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
        // Get the user before deletion for cache clearing
        $user = $this->searchById($id);

        $result = (new UserDeleteAction)->handle($id);

        if ($result && $user) {
            $this->_clearCaching($user);
            $this->clearStatsCache();
        }

        return $result;
    }

    /**
     * Search record in database.
     *
     * @param  int  $id  model id
     */
    public function searchById(int $id): User
    {
        return (new UserSearchByIdAction)->handle($id);
    }

    /**
     * Sort record in database.
     *
     * @param  int  $modelId  model id to be sorted
     * @param  int  $newPosition  new position of the model
     * @return \App\Models\User
     */
    public function sort($modelId, $newPosition)
    {
        // in x-sort (sortablejs) index starts from 0
        $model = (new UserSortAction)->handle($modelId, $newPosition + 1);

        $this->_clearCaching($model);
    }

    /**
     * Get paginated users with filters and caching
     *
     * @param array $filters
     * @param int $perPage
     * @return \Illuminate\Pagination\LengthAwarePaginator
     */
    public function show(array $filters = [], int $perPage = 12): \Illuminate\Pagination\LengthAwarePaginator
    {
        $input = [
            'filters' => $filters,
            'relations' => ['roles:id,name', 'online'],
            'rows_per_page' => $perPage,
        ];

        return (new UserShowAction)->handle($input);
    }

    /**
     * Get user by ID including soft deleted
     *
     * @param int $id
     * @return \App\Models\User|null
     */
    public function findWithTrashed(int $id): ?\App\Models\User
    {
        return \App\Models\User::withTrashed()->find($id);
    }

    /**
     * Get safe user display data
     *
     * @param int $id
     * @return array
     */
    public function getSafeUserDisplay(int $id): array
    {
        return safe_user_display($id);
    }

    /**
     * Clear caching after update.
     *
     * @param  \App\Models\User  $user  data to update
     */
    private function _clearCaching(User $user): void
    {
        CacheHelper::forget([], 'cached_users');
        CacheHelper::forget([], 'cached_user_'.$user->id);
        // Remove slug references since User model doesn't have slug attribute
        // CacheHelper::forget([], 'cached_user'.$user->slug);
        // CacheHelper::forget([], 'form_key_'.$user->slug);
        $this->clearListCache();
    }

    /**
     * Get all user statistics (optimized single query approach)
     *
     * @return array
     */
    public function getAllStatistics(): array
    {
        $stats = CacheHelper::remember([], 'user_all_stats', 300, function () {
            return (new UserAllStatsAction)->handle();
        });

        // Ensure all numeric values are integers (handle Redis string serialization)
        if (isset($stats['totalUsers'])) {
            $stats['totalUsers'] = (int) $stats['totalUsers'];
        }
        if (isset($stats['statusStats'])) {
            foreach ($stats['statusStats'] as $key => $value) {
                $stats['statusStats'][$key] = (int) $value;
            }
        }
        if (isset($stats['roleStats'])) {
            foreach ($stats['roleStats'] as $key => $value) {
                $stats['roleStats'][$key] = (int) $value;
            }
        }

        return $stats;
    }

    /**
     * Get user statistics by status
     *
     * @return array
     */
    public function getStatusStatistics(): array
    {
        return $this->getAllStatistics()['statusStats'];
    }

    /**
     * Get user statistics by role
     *
     * @return array
     */
    public function getRoleStatistics(): array
    {
        return $this->getAllStatistics()['roleStats'];
    }

    /**
     * Get total user count
     *
     * @return int
     */
    public function getTotalUsers(): int
    {
        return $this->getAllStatistics()['totalUsers'];
    }

    /**
     * Clear statistics cache
     *
     * @return void
     */
    public function clearStatsCache(): void
    {
        CacheHelper::forget([], 'user_all_stats');
        CacheHelper::forget([], 'user_status_stats');
        CacheHelper::forget([], 'user_role_stats');
        CacheHelper::forget([], 'total_users_count');
    }

    /**
     * Update user roles
     *
     * @param int $userId
     * @param array $roles
     * @return User
     */
    public function updateRoles(int $userId, array $roles): User
    {
        $user = $this->searchById($userId);

        // Get current roles
        $currentRoles = $user->roles->pluck('name')->toArray();

        // Find roles to add and remove
        $rolesToAdd = array_diff($roles, $currentRoles);
        $rolesToRemove = array_diff($currentRoles, $roles);

        // Add new roles
        foreach($rolesToAdd as $role) {
            $user->assignRole($role);
        }

        // Remove unchecked roles
        foreach($rolesToRemove as $role) {
            $user->removeRole($role);
        }

        $this->_clearCaching($user);
        $this->clearStatsCache();

        return $user->fresh(['roles']);
    }

    /**
     * Update user status
     *
     * @param int $userId
     * @param bool $isActive
     * @return bool
     */
    public function updateStatus(int $userId, bool $isActive): bool
    {
        try {
            $user = User::findOrFail($userId);
            $updated = $user->update(['is_active' => $isActive]);

            if ($updated) {
                // Clear user cache
                CacheHelper::forget([], 'cached_user_' . $userId);
                CacheHelper::forget([], 'cached_users');

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error updating user status: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update user information
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateUser(int $userId, array $data): bool
    {
        try {
            $user = User::findOrFail($userId);
            $updated = $user->update($data);

            if ($updated) {
                // Clear user cache
                CacheHelper::forget([], 'cached_user_' . $userId);
                CacheHelper::forget([], 'cached_users');

                return true;
            }

            return false;
        } catch (\Exception $e) {
            Log::error('Error updating user: ' . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Clear users list cache
     *
     * @return void
     */
    public function clearListCache(): void
    {
        // Clear all user list cache entries
        // Since we can't easily get all keys with database cache driver,
        // we'll flush the entire cache or use specific known patterns
        try {
            // If using Redis cache driver
            if (Cache::getStore() instanceof \Illuminate\Cache\RedisStore) {
                $cacheKeys = Cache::getRedis()->keys('*users_*');
                if ($cacheKeys) {
                    Cache::getRedis()->del($cacheKeys);
                }
            } else {
                // For other cache drivers (file, database), clear specific known patterns
                // This is less efficient but works with all cache drivers
                CacheHelper::forget([], 'user_all_stats');
                CacheHelper::forget([], 'user_status_stats');
                CacheHelper::forget([], 'user_role_stats');
                CacheHelper::forget([], 'total_users_count');
                CacheHelper::forget([], 'cached_users');

                // Clear paginated cache entries (we'll clear a reasonable range)
                for ($page = 1; $page <= 10; $page++) {
                    for ($perPage = 10; $perPage <= 50; $perPage += 10) {
                        $cacheKey = 'users_' . md5(serialize([]) . $perPage . $page);
                        CacheHelper::forget([], $cacheKey);
                    }
                }
            }
        } catch (\Exception $e) {
            Log::warning('Cache clearing failed: ' . $e->getMessage());
            // Fallback: just clear the main caches
            CacheHelper::forget([], 'user_all_stats');
            CacheHelper::forget([], 'cached_users');
        }
    }
}
