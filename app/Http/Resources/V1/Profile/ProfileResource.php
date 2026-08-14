<?php

namespace App\Http\Resources\V1\Profile;

use App\Http\Resources\V1\Users\UserResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProfileResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profilable_type' => $this->profilable_type,
            'profilable_id' => $this->profilable_id,
            'profile_type' => $this->profile_type,

            // Basic Information
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'middle_name' => $this->middle_name,
            'full_name' => $this->full_name,
            'formal_name' => $this->formal_name,
            'title' => $this->title,

            // Identifiers
            'orcid' => $this->orcid,
            'scopus_id' => $this->scopus_id,
            'google_scholar_id' => $this->google_scholar_id,
            'researcher_id' => $this->researcher_id,

            // Institutional Information
            'primary_affiliation' => $this->primary_affiliation,
            'department' => $this->department,
            'institution' => $this->institution,
            'secondary_affiliation' => $this->secondary_affiliation,
            'position' => $this->position,
            'display_affiliation' => $this->display_affiliation,

            // Location
            'country' => $this->country,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'address' => $this->address,

            // Contact Information
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,

            // Academic/Professional Details
            'bio' => $this->bio,
            'research_areas' => $this->research_areas,
            'expertise_keywords' => $this->expertise_keywords,
            'languages' => $this->languages,
            'career_stage' => $this->career_stage,

            // Reviewer Information
            'review_expertise' => $this->review_expertise,
            'review_preferences' => $this->review_preferences,
            'available_for_review' => $this->available_for_review,
            'max_reviews_per_year' => $this->max_reviews_per_year,
            'preferred_review_topics' => $this->preferred_review_topics,
            'declined_review_topics' => $this->declined_review_topics,

            // Editor Information
            'editorial_experience' => $this->editorial_experience,
            'journal_associations' => $this->journal_associations,
            'available_for_editorial' => $this->available_for_editorial,
            'editorial_expertise' => $this->editorial_expertise,

            // Author Information
            'publication_history' => $this->publication_history,
            'h_index' => $this->h_index,
            'citation_count' => $this->citation_count,
            'preferred_manuscript_types' => $this->preferred_manuscript_types,
            'research_impact_score' => $this->getResearchImpactScore(),

            // Social and Professional
            'social_links' => $this->social_links,
            'professional_memberships' => $this->professional_memberships,

            // Status
            'is_verified' => $this->is_verified,
            'verified_at' => $this->verified_at?->toISOString(),
            'is_active' => $this->is_active,
            'is_public' => $this->is_public,
            'preferred_language' => $this->preferred_language,

            // Metadata
            'metadata' => $this->metadata,

            // Timestamps
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),

            // Relationships
            'profilable' => $this->whenLoaded('profilable'),
            'verified_by_user' => new UserResource($this->whenLoaded('verifiedBy')),
        ];
    }
}
