<?php

namespace App\Services\Profile;

use App\Actions\Profile\CreateProfileAction;
use App\Actions\Profile\UpdateProfileAction;
use App\Actions\Profile\DeleteProfileAction;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\UnauthorizedException;

class ProfileService
{
    public function __construct(
        protected CreateProfileAction $createAction,
        protected UpdateProfileAction $updateAction,
        protected DeleteProfileAction $deleteAction
    ) {}

    /**
     * Get paginated list of profiles.
     *
     * @param  array  $filters
     * @param  int  $perPage
     * @return LengthAwarePaginator
     */
    public function listProfiles(array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = Profile::query()
            ->with(['profilable', 'reviewerJournals'])
            ->where('is_active', true);

        // Filter by profile type
        if (!empty($filters['profile_type'])) {
            $query->where('profile_type', $filters['profile_type']);
        }

        // Filter by country
        if (!empty($filters['country'])) {
            $query->where('country', $filters['country']);
        }

        // Filter by availability for review
        if (isset($filters['available_for_review'])) {
            $query->where('available_for_review', (bool) $filters['available_for_review']);
        }

        // Filter by research area
        if (!empty($filters['research_area'])) {
            $query->whereJsonContains('research_areas', $filters['research_area']);
        }

        // Search by name or email
        if (!empty($filters['search'])) {
            $search = $filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Only show public profiles unless user is authenticated
        if (!auth()->check()) {
            $query->where('is_public', true);
        }

        return $query->orderBy('created_at', 'desc')->paginate($perPage);
    }

    /**
     * Get a single profile by ID.
     *
     * @param  int  $profileId
     * @return Profile
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function getProfile(int $profileId): Profile
    {
        $profile = Profile::with(['profilable', 'reviewerJournals'])->findOrFail($profileId);

        // Check authorization
        if (auth()->check()) {
            Gate::authorize('view', $profile);
        } elseif (!$profile->is_public || !$profile->is_active) {
            abort(404, 'Profile not found.');
        }

        return $profile;
    }

    /**
     * Get the authenticated user's profile.
     *
     * @param  User  $user
     * @return Profile|null
     */
    public function getUserProfile(User $user): ?Profile
    {
        return Profile::with(['profilable', 'reviewerJournals'])
            ->where('profilable_type', User::class)
            ->where('profilable_id', $user->id)
            ->first();
    }

    /**
     * Create a new profile.
     *
     * @param  User  $user
     * @param  array  $data
     * @return Profile
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function createProfile(User $user, array $data): Profile
    {
        // Check authorization
        Gate::authorize('create', Profile::class);

        return $this->createAction->execute($user, $data);
    }

    /**
     * Update an existing profile.
     *
     * @param  int  $profileId
     * @param  array  $data
     * @return Profile
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     */
    public function updateProfile(int $profileId, array $data): Profile
    {
        $profile = Profile::findOrFail($profileId);

        // Check authorization
        Gate::authorize('update', $profile);

        return $this->updateAction->execute($profile, $data);
    }

    /**
     * Delete a profile.
     *
     * @param  int  $profileId
     * @param  bool  $hardDelete
     * @return bool
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function deleteProfile(int $profileId, bool $hardDelete = false): bool
    {
        $profile = Profile::findOrFail($profileId);

        // Check authorization
        Gate::authorize('delete', $profile);

        return $this->deleteAction->execute($profile, $hardDelete);
    }

    /**
     * Toggle profile visibility (public/private).
     *
     * @param  int  $profileId
     * @param  bool  $isPublic
     * @return Profile
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function toggleVisibility(int $profileId, bool $isPublic): Profile
    {
        $profile = Profile::findOrFail($profileId);

        // Check authorization
        Gate::authorize('update', $profile);

        $profile->update(['is_public' => $isPublic]);

        return $profile->fresh();
    }

    /**
     * Update reviewer availability settings.
     *
     * @param  int  $profileId
     * @param  bool  $available
     * @param  int|null  $maxActiveReviews
     * @return Profile
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function updateReviewerAvailability(int $profileId, bool $available, ?int $maxActiveReviews = null): Profile
    {
        $profile = Profile::findOrFail($profileId);

        // Check authorization
        Gate::authorize('manageReviewerAvailability', $profile);

        $updateData = ['available_for_review' => $available];

        if ($available && $maxActiveReviews !== null) {
            $updateData['max_active_reviews'] = $maxActiveReviews;
        }

        $profile->update($updateData);

        return $profile->fresh();
    }

    /**
     * Verify a profile (admin/editor only).
     *
     * @param  int  $profileId
     * @param  User  $verifier
     * @return Profile
     * @throws \Illuminate\Auth\Access\AuthorizationException
     */
    public function verifyProfile(int $profileId, User $verifier): Profile
    {
        $profile = Profile::findOrFail($profileId);

        // Check authorization
        Gate::authorize('verify', $profile);

        $profile->markAsVerified($verifier);

        return $profile->fresh();
    }

    /**
     * Get profiles available for review in specific areas.
     *
     * @param  array  $researchAreas
     * @param  int  $limit
     * @return Collection
     */
    public function getAvailableReviewers(array $researchAreas, int $limit = 50): Collection
    {
        return Profile::availableForReview()
            ->public()
            ->byExpertise($researchAreas)
            ->limit($limit)
            ->get();
    }

    /**
     * Search profiles by expertise.
     *
     * @param  string  $keyword
     * @param  int  $limit
     * @return Collection
     */
    public function searchByExpertise(string $keyword, int $limit = 20): Collection
    {
        return Profile::public()
            ->where(function ($query) use ($keyword) {
                $query->whereJsonContains('research_areas', $keyword)
                    ->orWhereJsonContains('expertise_keywords', $keyword)
                    ->orWhereJsonContains('review_expertise', $keyword);
            })
            ->limit($limit)
            ->get();
    }
}
