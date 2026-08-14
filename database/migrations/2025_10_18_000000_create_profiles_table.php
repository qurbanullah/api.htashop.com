<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            // Polymorphic relationship
            $table->morphs('profilable'); // Creates profilable_id and profilable_type

            // Profile type (academic, institutional, organizational, etc.)
            $table->string('profile_type')->default('academic');

            // Basic Information
            $table->string('first_name');
            $table->string('last_name');
            $table->string('middle_name')->nullable();
            $table->string('title')->nullable(); // Dr., Prof., Mr., Ms., etc.

            // Identifiers
            $table->string('orcid')->nullable()->unique();
            $table->string('scopus_id')->nullable();
            $table->string('google_scholar_id')->nullable();
            $table->string('researcher_id')->nullable();

            // Institutional Information
            $table->string('primary_affiliation')->nullable();
            $table->string('department')->nullable();
            $table->string('institution')->nullable();
            $table->string('secondary_affiliation')->nullable();
            $table->string('position')->nullable(); // Job title/position

            // Location
            $table->string('country', 2)->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code')->nullable();
            $table->text('address')->nullable();

            // Contact Information
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('website')->nullable();

            // Academic/Professional Details
            $table->text('bio')->nullable();
            $table->json('research_areas')->nullable(); // Array of research areas
            $table->json('expertise_keywords')->nullable(); // Array of keywords
            $table->json('languages')->nullable(); // Languages spoken
            $table->string('career_stage')->nullable(); // early-career, mid-career, senior

            // Reviewer-specific Information
            $table->json('review_expertise')->nullable(); // Areas willing to review
            $table->json('review_preferences')->nullable(); // Review type preferences
            $table->boolean('available_for_review')->default(false);
            $table->integer('max_reviews_per_year')->nullable();
            $table->json('preferred_review_topics')->nullable();
            $table->json('declined_review_topics')->nullable();

            // Editor-specific Information
            $table->json('editorial_experience')->nullable(); // Previous editorial roles
            $table->json('journal_associations')->nullable(); // Associated journals
            $table->boolean('available_for_editorial')->default(false);
            $table->json('editorial_expertise')->nullable();

            // Author-specific Information
            $table->json('publication_history')->nullable(); // Key publications
            $table->integer('h_index')->nullable();
            $table->integer('citation_count')->nullable();
            $table->json('preferred_manuscript_types')->nullable();

            // Social and Professional Links
            $table->json('social_links')->nullable(); // LinkedIn, Twitter, ResearchGate, etc.
            $table->json('professional_memberships')->nullable();

            // Verification and Status
            $table->boolean('is_verified')->default(false);
            $table->timestamp('verified_at')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users');
            $table->boolean('is_active')->default(true);
            $table->boolean('is_public')->default(true);
            $table->string('preferred_language', 5)->default('en');

            // Metadata (flexible JSON for profile-type specific data)
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Indexes
            $table->index(['profile_type', 'is_active']);
            $table->index(['country', 'is_active']);
            $table->index(['available_for_review', 'is_active']);
            $table->index(['available_for_editorial', 'is_active']);
            $table->index('is_verified');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
