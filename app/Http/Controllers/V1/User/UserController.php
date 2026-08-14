<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Actions\Users\AssignRolesAction;
use App\Actions\Users\CreateUserAction;
use App\Actions\Users\DeleteUserAction;
use App\Actions\Users\RestoreUserAction;
use App\Actions\Users\UpdateUserAction;
use App\Http\Requests\V1\Roles\AssignRolesRequest;
use App\Http\Requests\V1\User\StoreUserRequest;
use App\Http\Requests\V1\User\UpdateUserRequest;
use App\Http\Resources\V1\Role\RoleResource;
use App\Http\Resources\V1\User\UserResource;
use App\Jobs\Avatars\OptimizeAvatarJob;
use App\Models\User;
use App\Services\User\UserService;
use App\Services\Avatars\AvatarService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class UserController extends Controller
{
    public function __construct(
        protected UserService $userService,
        protected AvatarService $avatarService,
        protected CreateUserAction $createUserAction,
        protected UpdateUserAction $updateUserAction,
        protected DeleteUserAction $deleteUserAction,
        protected RestoreUserAction $restoreUserAction,
        protected AssignRolesAction $assignRolesAction,
        protected \App\Services\Audit\AuditService $auditService
    ) {}

    /**
     * Display a listing of users with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $filters = [
            'search' => $request->search,
            'role' => $request->role,
            'status' => $request->status,
        ];

        $users = $this->userService->getPaginatedUsers(
            $filters,
            $request->per_page ?? 15
        );

        return response()->json([
            'data' => UserResource::collection($users->items()),
            'meta' => [
                'current_page' => $users->currentPage(),
                'from' => $users->firstItem(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'to' => $users->lastItem(),
                'total' => $users->total(),
            ],
        ]);
    }

    /**
     * Store a newly created user
     */
    public function store(StoreUserRequest $request): JsonResponse
    {
        $user = $this->createUserAction->execute($request->validated());

        return response()->json([
            'message' => 'User created successfully',
            'data' => new UserResource($user),
        ], 201);
    }

    /**
     * Display the specified user
     */
    public function show(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id, withTrashed: true);

        $this->authorize('view', $user);

        // Only include sensitive relations (roles/permissions) for admin/editor/super-admin
        $authUser = request()->user();
        if ($authUser && $authUser->hasAnyRole(['super-admin', 'admin', 'editor'])) {
            $user->loadMissing(['roles', 'permissions', 'profile', 'academicProfile']);
        } else {
            // Load only profile/public data for non-admin viewers
            $user->loadMissing(['profile']);
        }

        return response()->json([
            'data' => new UserResource($user),
        ]);
    }

    /**
     * Admin / privileged endpoint to reset another user's password
     */
    public function adminResetPassword(Request $request, User $user): JsonResponse
    {
        $this->authorize('update', $user);

        $request->validate([
            'password' => 'required|min:8|confirmed',
        ]);

        try {
            $this->userService->updatePassword($user, $request->password);

            // Record audit
            try {
                $actor = $request->user('api') ?? request()->user();
                $this->auditService->record($actor, 'user.password.reset', $user, [
                    'event' => 'updated',
                    'auditable_type_name' => 'User',
                    'description' => 'Admin reset user password',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to record audit for admin password reset', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Password updated successfully for the user.',
            ]);
        } catch (\Exception $e) {
            Log::error('Admin password reset failed', ['user_id' => $user->id, 'error' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => 'Failed to update user password'], 500);
        }
    }

    /**
     * Admin endpoint to verify a user's email
     */
    public function verifyUserEmail(User $user): JsonResponse
    {
        $this->authorize('verifyEmail', $user);

        $this->userService->verifyEmail($user);

        // Record audit
        try {
            $actor = auth('api')->user() ?? request()->user();
            $this->auditService->record($actor, 'user.email.verified', $user, [
                'event' => 'updated',
                'auditable_type_name' => 'User',
                'description' => 'Admin verified user email',
            ]);
        } catch (\Throwable $e) {
            Log::warning('Failed to record audit for verifyUserEmail', ['error' => $e->getMessage()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'User email marked as verified.'
        ]);
    }

    /**
     * Update the specified user
     */
    public function update(UpdateUserRequest $request, int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->authorize('update', $user);

        $updatedUser = $this->updateUserAction->execute(
            $user,
            $request->validated(),
            $request->user() ?: $request->user('api')
        );

        return response()->json([
            'message' => 'User updated successfully',
            'data' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Soft delete the specified user
     */
    public function destroy(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->authorize('delete', $user);

        $this->deleteUserAction->execute($user);

        return response()->json([
            'message' => 'User deleted successfully',
        ]);
    }

    /**
     * Restore a soft-deleted user
     */
    public function restore(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id, withTrashed: true);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->authorize('restore', $user);

        $restoredUser = $this->restoreUserAction->execute($id);

        return response()->json([
            'message' => 'User restored successfully',
            'data' => new UserResource($restoredUser),
        ]);
    }

    /**
     * Permanently delete a user
     */
    public function forceDelete(int $id): JsonResponse
    {
        $user = $this->userService->getUserById($id, withTrashed: true);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->authorize('forceDelete', $user);

        $this->userService->forceDeleteUser($id);

        return response()->json([
            'message' => 'User permanently deleted',
        ]);
    }

    /**
     * Get all available roles
     */
    public function getRoles(): JsonResponse
    {
        $this->authorize('viewAny', User::class);

        $roles = $this->userService->getAllRoles();

        return response()->json([
            'data' => RoleResource::collection($roles),
        ]);
    }

    /**
     * Assign roles to a user
     */
    public function assignRoles(AssignRolesRequest $request, User $user): JsonResponse
    {
        // Ensure roles are loaded for authorization
        if (!$user->relationLoaded('roles')) {
            $user->load('roles');
        }

        $this->authorize('assignRoles', $user);

        $updatedUser = $this->assignRolesAction->execute(
            $user,
            $request->validated()['roles'],
            $request->user() ?: $request->user('api')
        );

        return response()->json([
            'message' => 'Roles assigned successfully',
            'data' => new UserResource($updatedUser),
        ]);
    }

    /**
     * Get user statistics
     */
    public function statistics(): JsonResponse
    {
        $this->authorize('viewStatistics', User::class);

        $stats = $this->userService->getUserStatistics();

        return response()->json([
            'data' => $stats,
        ]);
    }

    /**
     * Get user's publications
     */
    public function publications(int $id, Request $request): JsonResponse
    {
        $user = $this->userService->getUserById($id);

        if (!$user) {
            return response()->json(['message' => 'User not found'], 404);
        }

        $this->authorize('view', $user);

        $perPage = $request->input('per_page', 15);
        $publications = $user->publications()
            ->with(['publishable'])
            ->latest()
            ->paginate($perPage);

        return response()->json($publications);
    }

    /**
     * Upload user avatar
     * Frontend generates thumbnails and uploads all files to S3
     * Backend creates Avatar records and queues async optimization
     *
     * Request body:
     * {
     *   "avatar_variants": {
     *     "thumb": "s3/path/to/thumb.jpg",
     *     "small": "s3/path/to/small.jpg",
     *     "medium": "s3/path/to/medium.jpg"
     *   },
     *   "filename": "avatar-1234567890.jpg",
     *   "mime_type": "image/jpeg"
     * }
     */
    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        $request->validate([
            'avatar_variants' => 'required|array',
            'avatar_variants.original' => 'nullable|string',
            'avatar_variants.thumb' => 'required|string',
            'avatar_variants.small' => 'required|string',
            'avatar_variants.medium' => 'required|string',
            'filename' => 'required|string',
            'mime_type' => 'required|string|in:image/jpeg,image/png,image/webp',
        ]);

        try {
            $variantPaths = $request->input('avatar_variants');
            $filename = $request->input('filename');
            $mimeType = $request->input('mime_type');

            // Create avatars and clear caches
            $result = $this->avatarService->createAvatarFromUpload(
                $user,
                $variantPaths,
                $filename,
                $mimeType,
            );

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create avatar records',
                ], 500);
            }

            // Refresh user with new avatar data
            $user->load('avatars');

            // Record audit
            try {
                $this->auditService->record($user, 'user.avatar.uploaded', $user, [
                    'event' => 'created',
                    'auditable_type_name' => 'User',
                    'description' => 'User uploaded new avatar',
                    'filename' => $filename,
                    'variants_count' => count($variantPaths),
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to record audit for avatar upload', ['error' => $e->getMessage()]);
            }

            // Queue async optimization job (WebP generation, re-compression, etc.)
            // This runs in background so frontend gets immediate response
            try {
                OptimizeAvatarJob::dispatch($user->id, $filename);
            } catch (\Exception $e) {
                Log::warning('Failed to queue avatar optimization job', [
                    'user_id' => $user->id,
                    'error' => $e->getMessage(),
                ]);
                // Don't fail the upload if job queueing fails - user got their avatar
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar uploaded successfully',
                'data' => new UserResource($user),
            ], 201);

        } catch (\Exception $e) {
            Log::error('Avatar upload failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to upload avatar',
            ], 500);
        }
    }

    /**
     * Delete user avatar
     * Deletes all avatar variants from S3 and removes avatar records from database
     * Automatically clears all avatar-related caches
     */
    public function deleteAvatar(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            // Delete avatars and clear caches
            $result = $this->avatarService->deleteAvatar($user);

            if (!$result) {
                return response()->json([
                    'success' => false,
                    'message' => 'No avatar found to delete',
                ], 404);
            }

            // Refresh user to clear avatar relation
            $user->load('avatars');

            // Record audit
            try {
                $this->auditService->record($user, 'user.avatar.deleted', $user, [
                    'event' => 'deleted',
                    'auditable_type_name' => 'User',
                    'description' => 'User deleted avatar',
                ]);
            } catch (\Throwable $e) {
                Log::warning('Failed to record audit for avatar deletion', ['error' => $e->getMessage()]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Avatar deleted successfully',
                'data' => new UserResource($user),
            ]);

        } catch (\Exception $e) {
            Log::error('Avatar deletion failed', [
                'user_id' => $user->id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete avatar',
            ], 500);
        }
    }
}
