<?php

namespace App\Services\User;

use App\Helpers\CacheHelper;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Collection;

class UserService
{
    /**
     * Get paginated users with filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildUserQuery($filters);

        // Don't include manuscript counts here — some deployments do not have the
        // manuscripts table. Load only essential relations.
        return $query->with(['roles', 'profile', 'academicProfile'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Build user query with filters
     */
    protected function buildUserQuery(array $filters): Builder
    {
        $query = User::query();

        // Search filter
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                                $q->where('name', 'LIKE', "%{$search}%")
                                    ->orWhere('email', 'LIKE', "%{$search}%")
                                    ->orWhere('organization', 'LIKE', "%{$search}%");
            });
        }

        // Role filter
        if (!empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        // Status filter (soft deletes)
        $status = $filters['status'] ?? 'all';
        if ($status === 'deleted') {
            $query->onlyTrashed();
        } elseif ($status === 'active') {
            $query->whereNull('deleted_at');
        } else {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Get a single user by ID with caching
     */
    public function getUserById(int $id, bool $withTrashed = false): ?User
    {
        $cacheKey = "user.{$id}" . ($withTrashed ? '.with_trashed' : '');

        return CacheHelper::remember([], $cacheKey, now()->addMinutes(30), function () use ($id, $withTrashed) {
            $query = User::with([
                'roles',
                'permissions',
                'profile',
                'academicProfile',
                'profile', 'academicProfile'
            ]);

            if ($withTrashed) {
                $query->withTrashed();
            }

            return $query->find($id);
        });
    }

    /**
     * Clear user cache
     */
    protected function clearUserCache(int $id): void
    {
        CacheHelper::forget([], "user.{$id}");
        CacheHelper::forget([], "user.{$id}.with_trashed");
    }

    /**
     * Create a new user
     */
    public function createUser(array $data): User
    {
        $userData = [
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
        ];

        $user = User::create($userData);

        // Assign roles if provided
        if (!empty($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        $user->load(['roles', 'profile', 'academicProfile']);

        return $user;
    }

    /**
     * Update an existing user
     */
    public function updateUser(User $user, array $data): User
    {
        $updateData = [];

        if (isset($data['name'])) {
            $updateData['name'] = $data['name'];
        }

        if (isset($data['email'])) {
            $updateData['email'] = $data['email'];
        }

        // Update password if provided
        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        // Update roles if provided
        if (isset($data['roles'])) {
            $user->syncRoles($data['roles']);
        }

        // Clear cache
        $this->clearUserCache($user->id);

        $user->load(['roles', 'profile', 'academicProfile']);

        return $user->fresh();
    }

    /**
     * Soft delete a user
     */
    public function deleteUser(User $user): bool
    {
        $result = $user->delete();

        // Clear cache
        $this->clearUserCache($user->id);

        return $result;
    }

    /**
     * Restore a soft-deleted user
     */
    public function restoreUser(int $id): ?User
    {
        $user = User::withTrashed()->find($id);

        if ($user && $user->trashed()) {
            $user->restore();

            // Clear cache
            $this->clearUserCache($id);

            return $user->load(['roles', 'profile', 'academicProfile']);
        }

        return null;
    }

    /**
     * Permanently delete a user
     */
    public function forceDeleteUser(int $id): bool
    {
        $user = User::withTrashed()->find($id);

        if ($user) {
            // Clear cache before deletion
            $this->clearUserCache($id);

            return $user->forceDelete();
        }

        return false;
    }

    /**
     * Assign roles to a user
     */
    public function assignRoles(User $user, array $roles): User
    {
        $user->syncRoles($roles);

        // Clear cache
        $this->clearUserCache($user->id);

        $user->load(['roles', 'profile', 'academicProfile']);

        return $user->fresh();
    }

    /**
     * Get all available roles with caching
     */
    public function getAllRoles(): Collection
    {
        return CacheHelper::remember([], 'roles.all', now()->addHours(24), function () {
            return \Spatie\Permission\Models\Role::all();
        });
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        return [
            'total_users' => User::withTrashed()->count(),
            'active_users' => User::whereNotNull('email_verified_at')->count(),
            'deleted_users' => User::onlyTrashed()->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
            'users_by_role' => $this->getUsersByRole(),
            'recent_registrations' => User::where('created_at', '>=', now()->subDays(30))->count(),
            'users_registered_today' => User::whereDate('created_at', today())->count(),
            'users_registered_this_week' => User::where('created_at', '>=', now()->startOfWeek())->count(),
            'users_registered_this_month' => User::where('created_at', '>=', now()->startOfMonth())->count(),
        ];
    }

    /**
     * Get users grouped by role
     */
    protected function getUsersByRole(): array
    {
        $users = User::with('roles')->get();

        $grouped = $users->groupBy(function ($user) {
            return $user->roles->first()?->name ?? 'no-role';
        });

        return $grouped->map(function ($group) {
            return $group->count();
        })->toArray();
    }

    /**
     * Check if email exists
     */
    public function emailExists(string $email, ?int $excludeUserId = null): bool
    {
        $query = User::where('email', $email);

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        return $query->exists();
    }

    /**
     * Verify user email
     */
    public function verifyEmail(User $user): User
    {
        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        return $user;
    }

    /**
     * Update user's password (admin operation)
     */
    public function updatePassword(User $user, string $password): User
    {
        $user->password = Hash::make($password);
        // Clear remember token and force save
        $user->setRememberToken(null);
        $user->save();

        // Optionally clear cached user state
        $this->clearUserCache($user->id);

        return $user->fresh();
    }

    /**
     * Search users by term
     */
    public function searchUsers(string $term, int $limit = 20): Collection
    {
        return User::where('name', 'LIKE', "%{$term}%")
            ->orWhere('email', 'LIKE', "%{$term}%")
            ->with('roles')
            ->limit($limit)
            ->get();
    }
}
