<?php

namespace App\Actions\Profile;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CreateProfileAction
{
    /**
     * Create a new profile.
     *
     * @param  User  $user  The user creating the profile
     * @param  array  $data  Profile data
     * @return Profile
     * @throws ValidationException
     */
    public function execute(User $user, array $data): Profile
    {
        // Validate the data
        $validated = $this->validate($user, $data);

        return DB::transaction(function () use ($user, $validated, $data) {
            // Check if user already has a profile
            $existingProfile = Profile::where('profilable_type', User::class)
                ->where('profilable_id', $user->id)
                ->first();

            if ($existingProfile) {
                throw ValidationException::withMessages([
                    'profile' => ['You already have a profile. Please update your existing profile instead.'],
                ]);
            }

            // Handle reviewer journals relationship separately
            $reviewerJournals = $data['reviewer_journals'] ?? null;

            // Remove reviewer_journals from validated data as it's not a column
            unset($validated['reviewer_journals']);

            // Create the profile
            $profile = new Profile();
            $profile->profilable_type = User::class;
            $profile->profilable_id = $user->id;
            $profile->fill($validated);
            $profile->save();

            // Attach reviewer journals if provided
            if ($reviewerJournals !== null && is_array($reviewerJournals)) {
                $profile->reviewerJournals()->attach($reviewerJournals);
            }

            return $profile->fresh(['reviewerJournals']);
        });
    }

    /**
     * Validate the profile data.
     *
     * @param  User  $user
     * @param  array  $data
     * @return array
     * @throws ValidationException
     */
    protected function validate(User $user, array $data): array
    {
        $validator = Validator::make($data, [
            'profile_type' => ['required', Rule::in(['academic', 'institutional', 'organizational', 'personal'])],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'orcid' => ['nullable', 'string', 'max:50'],
            'scopus_id' => ['nullable', 'string', 'max:50'],
            'google_scholar_id' => ['nullable', 'string', 'max:100'],
            'researcher_id' => ['nullable', 'string', 'max:100'],
            'primary_affiliation' => ['nullable', 'string', 'max:255'],
            'institution' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'position' => ['nullable', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'size:2'], // ISO 3166-1 alpha-2 country code
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'website' => ['nullable', 'url', 'max:255'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'research_areas' => ['nullable', 'array'],
            'research_areas.*' => ['string', 'max:255'],
            'expertise_keywords' => ['nullable', 'array'],
            'expertise_keywords.*' => ['string', 'max:255'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', 'max:100'],
            'career_stage' => ['nullable', 'string', 'max:100'],
            'review_expertise' => ['nullable', 'array'],
            'review_expertise.*' => ['string', 'max:255'],
            'review_preferences' => ['nullable', 'array'],
            'available_for_review' => ['nullable', 'boolean'],
            'reviewer_availability_status' => ['nullable', 'string', 'max:50'],
            'reviewer_journals' => ['nullable', 'array'],
            'reviewer_journals.*' => ['integer', 'exists:journals,id'],
            'max_active_reviews' => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_reviews_per_year' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'preferred_review_topics' => ['nullable', 'array'],
            'preferred_review_topics.*' => ['string', 'max:255'],
            'declined_review_topics' => ['nullable', 'array'],
            'declined_review_topics.*' => ['string', 'max:255'],
            'editorial_experience' => ['nullable', 'array'],
            'journal_associations' => ['nullable', 'array'],
            'available_for_editorial' => ['nullable', 'boolean'],
            'editorial_expertise' => ['nullable', 'array'],
            'editorial_expertise.*' => ['string', 'max:255'],
            'publication_history' => ['nullable', 'array'],
            'h_index' => ['nullable', 'integer', 'min:0'],
            'citation_count' => ['nullable', 'integer', 'min:0'],
            'preferred_manuscript_types' => ['nullable', 'array'],
            'preferred_manuscript_types.*' => ['string', 'max:255'],
            'social_links' => ['nullable', 'array'],
            'professional_memberships' => ['nullable', 'array'],
            'professional_memberships.*' => ['string', 'max:255'],
            'is_public' => ['nullable', 'boolean'],
            'preferred_language' => ['nullable', 'string', 'max:10'],
            'metadata' => ['nullable', 'array'],
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        return $validator->validated();
    }
}
