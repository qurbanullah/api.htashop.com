<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
        use HasFactory, SoftDeletes;

    protected $fillable = [
        'profilable_type',
        'profilable_id',
        'profile_type',
        'first_name',
        'last_name',
        'middle_name',
        'title',
        'orcid',
        'scopus_id',
        'google_scholar_id',
        'researcher_id',
        'primary_affiliation',
        'department',
        'institution',
        'secondary_affiliation',
        'position',
        'country',
        'city',
        'postal_code',
        'address',
        'email',
        'phone',
        'website',
        'bio',
        'research_areas',
        'expertise_keywords',
        'languages',
        'career_stage',
        'review_expertise',
        'review_preferences',
        'available_for_review',
        'reviewer_availability_status',
        'max_active_reviews',
        'completed_reviews_count',
        'review_quality_score',
        'avg_review_turnaround_days',
        'max_reviews_per_year',
        'preferred_review_topics',
        'declined_review_topics',
        'editorial_experience',
        'journal_associations',
        'available_for_editorial',
        'editorial_expertise',
        'publication_history',
        'h_index',
        'citation_count',
        'preferred_manuscript_types',
        'social_links',
        'professional_memberships',
        'is_verified',
        'verified_at',
        'verified_by',
        'is_active',
        'is_public',
        'preferred_language',
        'metadata',
    ];

    protected $casts = [
        'research_areas' => 'array',
        'expertise_keywords' => 'array',
        'languages' => 'array',
        'review_expertise' => 'array',
        'review_preferences' => 'array',
        'preferred_review_topics' => 'array',
        'declined_review_topics' => 'array',
        'editorial_experience' => 'array',
        'journal_associations' => 'array',
        'editorial_expertise' => 'array',
        'publication_history' => 'array',
        'preferred_manuscript_types' => 'array',
        'social_links' => 'array',
        'professional_memberships' => 'array',
        'available_for_review' => 'boolean',
        'available_for_editorial' => 'boolean',
        'is_verified' => 'boolean',
        'is_active' => 'boolean',
        'is_public' => 'boolean',
        'verified_at' => 'datetime',
        'metadata' => 'array',
        'h_index' => 'integer',
        'citation_count' => 'integer',
        'max_reviews_per_year' => 'integer',
        'max_active_reviews' => 'integer',
        'completed_reviews_count' => 'integer',
        'review_quality_score' => 'decimal:2',
        'avg_review_turnaround_days' => 'decimal:2',
    ];

    protected $appends = ['reviewer_journal_ids'];

    /**
     * Profile types
     */
    const TYPE_ACADEMIC = 'academic';
    const TYPE_INSTITUTIONAL = 'institutional';
    const TYPE_ORGANIZATIONAL = 'organizational';
    const TYPE_PERSONAL = 'personal';

    /**
     * Get the parent profilable model (User, Organization, etc.)
     */
    public function profilable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Get the user who verified this profile
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    /**
     * Get the journals this profile is a reviewer for
     */
    public function reviewerJournals()
    {
        return $this->belongsToMany(Journal::class, 'profile_reviewer_journals')
            ->withPivot('is_active')
            ->withTimestamps()
            ->wherePivot('is_active', true);
    }

    /**
     * Get reviewer journal IDs as an array (for frontend)
     */
    public function getReviewerJournalIdsAttribute(): array
    {
        return $this->reviewerJournals()->pluck('journals.id')->toArray();
    }

    /**
     * Scope to get profiles by type
     */
    public function scopeOfType($query, string $type)
    {
        return $query->where('profile_type', $type);
    }

    /**
     * Scope to get academic profiles
     */
    public function scopeAcademic($query)
    {
        return $query->where('profile_type', self::TYPE_ACADEMIC);
    }

    /**
     * Scope to get profiles available for review
     */
    public function scopeAvailableForReview($query)
    {
        return $query->where('available_for_review', true)
                    ->where('is_active', true);
    }

    /**
     * Scope to get profiles available for editorial work
     */
    public function scopeAvailableForEditorial($query)
    {
        return $query->where('available_for_editorial', true)
                    ->where('is_active', true);
    }

    /**
     * Scope to get verified profiles
     */
    public function scopeVerified($query)
    {
        return $query->where('is_verified', true);
    }

    /**
     * Scope to get public profiles
     */
    public function scopePublic($query)
    {
        return $query->where('is_public', true)
                    ->where('is_active', true);
    }

    /**
     * Scope to get profiles by country
     */
    public function scopeByCountry($query, string $country)
    {
        return $query->where('country', $country);
    }

    /**
     * Scope to get profiles by expertise
     */
    public function scopeByExpertise($query, array $expertise)
    {
        return $query->where(function ($q) use ($expertise) {
            foreach ($expertise as $area) {
                $q->orWhereJsonContains('research_areas', $area)
                  ->orWhereJsonContains('review_expertise', $area)
                  ->orWhereJsonContains('expertise_keywords', $area);
            }
        });
    }

    /**
     * Get full name
     */
    public function getFullNameAttribute(): string
    {
        $name = $this->first_name;
        if ($this->middle_name) {
            $name .= ' ' . $this->middle_name;
        }
        $name .= ' ' . $this->last_name;

        return $this->title ? $this->title . ' ' . $name : $name;
    }

    /**
     * Get formal name with title
     */
    public function getFormalNameAttribute(): string
    {
        return $this->title
            ? $this->title . ' ' . $this->first_name . ' ' . $this->last_name
            : $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Get display affiliation
     */
    public function getDisplayAffiliationAttribute(): ?string
    {
        if (!$this->primary_affiliation) {
            return $this->institution;
        }

        $affiliation = $this->primary_affiliation;
        if ($this->department) {
            $affiliation = $this->department . ', ' . $affiliation;
        }
        return $affiliation;
    }

    /**
     * Check if profile can review in specific area
     */
    public function canReviewIn(string $area): bool
    {
        if (!$this->available_for_review || !$this->is_active) {
            return false;
        }

        $expertise = array_merge(
            $this->review_expertise ?? [],
            $this->research_areas ?? [],
            $this->expertise_keywords ?? []
        );

        return in_array(strtolower($area), array_map('strtolower', $expertise));
    }

    /**
     * Get all areas this profile can review in
     */
    public function getReviewAreas(): array
    {
        if (!$this->available_for_review || !$this->is_active) {
            return [];
        }

        return array_unique(array_merge(
            $this->review_expertise ?? [],
            $this->research_areas ?? [],
            $this->expertise_keywords ?? []
        ));
    }

    /**
     * Check if profile has editorial experience
     */
    public function hasEditorialExperience(): bool
    {
        return !empty($this->editorial_experience) || $this->available_for_editorial;
    }

    /**
     * Get research impact score (simple calculation)
     */
    public function getResearchImpactScore(): float
    {
        $hIndex = $this->h_index ?? 0;
        $citations = $this->citation_count ?? 0;

        // Simple scoring algorithm
        return ($hIndex * 2) + ($citations / 100);
    }

    /**
     * Mark profile as verified
     */
    public function markAsVerified(User $verifier): void
    {
        $this->update([
            'is_verified' => true,
            'verified_at' => now(),
            'verified_by' => $verifier->id,
        ]);
    }

    /**
     * Check if profile is of a specific type
     */
    public function isAcademic(): bool
    {
        return $this->profile_type === self::TYPE_ACADEMIC;
    }

    public function isInstitutional(): bool
    {
        return $this->profile_type === self::TYPE_INSTITUTIONAL;
    }

    public function isOrganizational(): bool
    {
        return $this->profile_type === self::TYPE_ORGANIZATIONAL;
    }

    public function isPersonal(): bool
    {
        return $this->profile_type === self::TYPE_PERSONAL;
    }
}
