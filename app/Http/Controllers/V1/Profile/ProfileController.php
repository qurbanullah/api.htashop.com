<?php

namespace App\Http\Controllers\V1\Profile;

use App\Http\Controllers\Controller;
use App\Http\Responses\V1\ApiResponse;
use App\Models\User;
use App\Services\Profile\ProfileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ProfileController extends Controller
{
    public function __construct(
        protected ProfileService $profileService
    ) {}

    /**
     * Get a paginated list of profiles.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only([
                'profile_type',
                'country',
                'available_for_review',
                'research_area',
                'search',
            ]);

            $perPage = $request->input('per_page', 20);

            $profiles = $this->profileService->listProfiles($filters, $perPage);

            return response()->json([
                'success' => true,
                'data' => $profiles->items(),
                'meta' => [
                    'current_page' => $profiles->currentPage(),
                    'from' => $profiles->firstItem(),
                    'last_page' => $profiles->lastPage(),
                    'per_page' => $profiles->perPage(),
                    'to' => $profiles->lastItem(),
                    'total' => $profiles->total(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch profiles', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profiles.',
            ], 500);
        }
    }

    /**
     * Get a single profile by ID.
     *
     * @param  string  $id
     * @return JsonResponse
     */
    public function show(string $id): JsonResponse
    {
        try {
            $profile = $this->profileService->getProfile((int) $id);

            return response()->json([
                'success' => true,
                'data' => $profile,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this profile.',
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch profile', [
                'profile_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile.',
            ], 500);
        }
    }

    /**
     * Get the authenticated user's profile.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = $this->profileService->getUserProfile($user);

            if (!$profile) {
                return response()->json([
                    'success' => false,
                    'data' => null,
                    'message' => 'No profile found. Please create a profile.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $profile,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to fetch user profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch user profile.',
            ], 500);
        }
    }

    /**
     * Get a profile by user ID.
     *
     * @param  string  $userId
     * @return JsonResponse
     */
    public function getByUserId(string $userId): JsonResponse
    {
        try {
            $user = User::findOrFail((int) $userId);
            $profile = $this->profileService->getUserProfile($user);

            if (!$profile) {
                return response()->json([
                    'success' => true,
                    'data' => null,
                    'message' => 'No profile found for this user.',
                ], 404);
            }

            // Check authorization
            if (auth()->check()) {
                Gate::authorize('view', $profile);
            } elseif (!$profile->is_public || !$profile->is_active) {
                return response()->json([
                    'success' => false,
                    'message' => 'Profile not found or not accessible.',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $profile,
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to view this profile.',
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'User or profile not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to fetch profile by user ID', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch profile.',
            ], 500);
        }
    }

    /**
     * Create a new profile.
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = $this->profileService->createProfile($user, $request->all());

            return response()->json([
                'success' => true,
                'data' => $profile,
                'message' => 'Profile created successfully.',
            ], 201);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to create a profile.',
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to create profile', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage() ?: 'Failed to create profile.',
            ], 500);
        }
    }

    /**
     * Update an existing profile.
     *
     * @param  Request  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function update(Request $request, string $id): JsonResponse
    {
        try {
            $profile = $this->profileService->updateProfile((int) $id, $request->all());

            return response()->json([
                'success' => true,
                'data' => $profile,
                'message' => 'Profile updated successfully.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to update this profile.',
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to update profile', [
                'profile_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile.',
            ], 500);
        }
    }

    /**
     * Delete a profile.
     *
     * @param  string  $id
     * @return JsonResponse
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $this->profileService->deleteProfile((int) $id);

            return response()->json([
                'success' => true,
                'message' => 'Profile deleted successfully.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to delete this profile.',
            ], 403);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Profile not found.',
            ], 404);
        } catch (\Exception $e) {
            Log::error('Failed to delete profile', [
                'profile_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete profile.',
            ], 500);
        }
    }

    /**
     * Toggle profile visibility.
     *
     * @param  Request  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function toggleVisibility(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'is_public' => ['required', 'boolean'],
            ]);

            $profile = $this->profileService->toggleVisibility((int) $id, $request->input('is_public'));

            return response()->json([
                'success' => true,
                'data' => $profile,
                'message' => 'Profile visibility updated successfully.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify this profile.',
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to toggle profile visibility', [
                'profile_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update profile visibility.',
            ], 500);
        }
    }

    /**
     * Update reviewer availability settings.
     *
     * @param  Request  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function updateReviewerAvailability(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'available_for_review' => ['required', 'boolean'],
                'max_active_reviews' => ['nullable', 'integer', 'min:0', 'max:100'],
            ]);

            $profile = $this->profileService->updateReviewerAvailability(
                (int) $id,
                $request->input('available_for_review'),
                $request->input('max_active_reviews')
            );

            return response()->json([
                'success' => true,
                'data' => $profile,
                'message' => 'Reviewer availability updated successfully.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to modify reviewer settings.',
            ], 403);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Failed to update reviewer availability', [
                'profile_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update reviewer availability.',
            ], 500);
        }
    }

    /**
     * Verify a profile (admin/editor only).
     *
     * @param  Request  $request
     * @param  string  $id
     * @return JsonResponse
     */
    public function verify(Request $request, string $id): JsonResponse
    {
        try {
            $user = $request->user();
            $profile = $this->profileService->verifyProfile((int) $id, $user);

            return response()->json([
                'success' => true,
                'data' => $profile,
                'message' => 'Profile verified successfully.',
            ]);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized to verify profiles.',
            ], 403);
        } catch (\Exception $e) {
            Log::error('Failed to verify profile', [
                'profile_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to verify profile.',
            ], 500);
        }
    }

    /**
     * Complete onboarding - Initialize user profile during onboarding flow
     *
     * @param  Request  $request
     * @return JsonResponse
     */
    public function completeOnboarding(Request $request): JsonResponse
    {
        try {
            $user = $request->user();
            $user->refresh();

            if ($user->onboarding_completed) {
                return ApiResponse::error('Onboarding already completed.', null, 422);
            }

            // Normalize empty strings to null for nullable fields
            $request->merge([
                'website' => $request->input('website') ?: null,
            ]);

            $validated = $request->validate([
                'organization' => ['required', 'string', 'max:255'],
                'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'],
                'website' => ['nullable', 'url:https', 'max:255'],
                'store_name' => ['required', 'string', 'max:255'],
                'business_type' => ['required', 'string', 'in:Supplier,Distributor,Manufacturer,Reseller'],
            ]);

            $action = new \App\Actions\Onboarding\CompleteOnboardingAction();
            $updatedUser = $action->execute($user, $validated);

            $updatedUser->load(['roles', 'avatars']);

            return ApiResponse::success([
                'user' => [
                    'id' => $updatedUser->id,
                    'uuid' => $updatedUser->uuid,
                    'name' => $updatedUser->name,
                    'first_name' => $updatedUser->first_name,
                    'last_name' => $updatedUser->last_name,
                    'email' => $updatedUser->email,
                    'email_verified_at' => $updatedUser->email_verified_at,
                    'avatar_urls' => $updatedUser->getAvatarUrls(),
                    'onboarding_completed' => $updatedUser->onboarding_completed,
                    'onboarding_completed_at' => $updatedUser->onboarding_completed_at,
                    'created_at' => $updatedUser->created_at,
                    'updated_at' => $updatedUser->updated_at,
                    'roles' => $updatedUser->roles->pluck('name')->toArray(),
                    'can_access_admin' => $updatedUser->hasAnyRole(['super-admin', 'admin']),
                    'can_access_manage' => true,
                ],
            ], 'Onboarding completed successfully.', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Validation errors',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Onboarding completion failed', [
                'user_id' => $request->user()?->id,
                'error' => $e->getMessage(),
            ]);

            return ApiResponse::error('Failed to complete onboarding.', [
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
