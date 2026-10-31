<?php

namespace App\Services\User;

use App\Helpers\CacheHelper;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

class UserService
{
    /**
     * Get paginated users with filters
     */
    public function getPaginatedUsers(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = $this->buildUserQuery($filters);

        // Load only the relations the admin UI renders. `avatars` is eager loaded
        // so UserResource does not trigger one query per row.
        return $query->with(['roles', 'avatars'])
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Build user query with filters.
     *
     * Supported filters:
     *  - search: name / first_name / last_name / email (partial match)
     *  - role: role name
     *  - account_status: active | suspended | banned | pending
     *  - verified: 1 = email verified, 0 = unverified
     *  - trashed: without (default) | with | only
     *  - status: legacy alias (active | deleted | all) kept for older clients
     */
    protected function buildUserQuery(array $filters): Builder
    {
        $query = User::query();

        // Search filter
        if (! empty($filters['search'])) {
            $search = trim((string) $filters['search']);
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('first_name', 'LIKE', "%{$search}%")
                    ->orWhere('last_name', 'LIKE', "%{$search}%")
                    ->orWhere('email', 'LIKE', "%{$search}%");
            });
        }

        // Role filter
        if (! empty($filters['role'])) {
            $query->whereHas('roles', function ($q) use ($filters) {
                $q->where('name', $filters['role']);
            });
        }

        // Account status filter (active / suspended / banned / pending)
        if (! empty($filters['account_status'])) {
            $query->where('status', $filters['account_status']);
        }

        // Email verification filter
        if (isset($filters['verified']) && $filters['verified'] !== '') {
            if (filter_var($filters['verified'], FILTER_VALIDATE_BOOLEAN)) {
                $query->whereNotNull('email_verified_at');
            } else {
                $query->whereNull('email_verified_at');
            }
        }

        // Soft-delete scope. `trashed` wins; the legacy `status` param is
        // translated so existing callers keep working.
        $trashed = $filters['trashed'] ?? null;
        if ($trashed === null || $trashed === '') {
            $trashed = match ($filters['status'] ?? null) {
                'deleted' => 'only',
                'all' => 'with',
                default => 'without',
            };
        }

        if ($trashed === 'only') {
            $query->onlyTrashed();
        } elseif ($trashed === 'with') {
            $query->withTrashed();
        }

        return $query;
    }

    /**
     * Get a single user by ID with caching
     */
    public function getUserById(int $id, bool $withTrashed = false): ?User
    {
        $cacheKey = "user.{$id}".($withTrashed ? '.with_trashed' : '');

        return CacheHelper::remember([], $cacheKey, now()->addMinutes(30), function () use ($id, $withTrashed) {
            $query = User::with([
                'roles.permissions',
                'permissions',
                'profile',
                'academicProfile',
                'avatars',
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
            'name' => $data['name'] ?? $this->composeName($data),
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? User::STATUS_ACTIVE,
        ];

        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null && $data[$field] !== '') {
                $userData[$field] = $data[$field];
            }
        }

        $user = User::create($userData);

        // Assign roles if provided
        if (! empty($data['roles'])) {
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

        foreach (['first_name', 'middle_name', 'last_name'] as $field) {
            if (array_key_exists($field, $data)) {
                $updateData[$field] = $data[$field];
            }
        }

        if (! empty($data['status'])) {
            $updateData['status'] = $data['status'];
        }

        // Keep the display name in sync when the caller only sends name parts.
        if (! isset($updateData['name']) && (isset($updateData['first_name']) || isset($updateData['last_name']))) {
            $updateData['name'] = $this->composeName([
                'first_name' => $updateData['first_name'] ?? $user->first_name,
                'last_name' => $updateData['last_name'] ?? $user->last_name,
            ]) ?: $user->name;
        }

        // Update password if provided
        if (! empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
        }

        if (! empty($updateData)) {
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
            return Role::all();
        });
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        return [
            'total_users' => User::count(),
            'active_users' => User::where('status', User::STATUS_ACTIVE)->count(),
            'suspended_users' => User::where('status', User::STATUS_SUSPENDED)->count(),
            'banned_users' => User::where('status', User::STATUS_BANNED)->count(),
            'pending_users' => User::where('status', User::STATUS_PENDING)->count(),
            'verified_users' => User::whereNotNull('email_verified_at')->count(),
            'unverified_users' => User::whereNull('email_verified_at')->count(),
            'deleted_users' => User::onlyTrashed()->count(),
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
        return Role::withCount('users')
            ->get()
            ->mapWithKeys(fn ($role) => [$role->name => $role->users_count])
            ->all();
    }

    /**
     * Build a display name from the name parts when `name` was not supplied.
     */
    protected function composeName(array $data): string
    {
        return trim(sprintf('%s %s', $data['first_name'] ?? '', $data['last_name'] ?? ''));
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
        if (! $user->email_verified_at) {
            $user->email_verified_at = now();
            $user->save();
        }

        $this->clearUserCache($user->id);

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
