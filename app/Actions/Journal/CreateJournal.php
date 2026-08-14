<?php

namespace App\Actions\Journal;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CreateJournal
{
    public function handle(array $data, User $user): Journal
    {
        return DB::transaction(function () use ($data, $user) {
            // Create the journal
            $journal = Journal::create([
                'title' => $data['title'],
                'abbreviation' => $data['abbreviation'] ?? null,
                'description' => $data['description'],
                'issn' => $data['issn'] ?? null,
                'e_issn' => $data['e_issn'] ?? null,
                'publisher' => $data['publisher'] ?? null,
                'editor_in_chief_id' => $data['editor_in_chief_id'] ?? $user->id,
                'publication_frequency' => $data['publication_frequency'] ?? 'quarterly',
                'is_open_access' => $data['is_open_access'] ?? false,
                'is_peer_reviewed' => $data['is_peer_reviewed'] ?? true,
                'is_active' => $data['is_active'] ?? true,
                'submission_fee' => $data['submission_fee'] ?? 0,
                'publication_fee' => $data['publication_fee'] ?? 0,
                'impact_factor' => $data['impact_factor'] ?? null,
                'h_index' => $data['h_index'] ?? null,
                'citation_score' => $data['citation_score'] ?? null,
                'contact_email' => $data['contact_email'] ?? null,
                'website_url' => $data['website_url'] ?? null,
                'country' => $data['country'] ?? null,
                'status' => $data['status'] ?? 'active',
                'logo_url' => $data['logo_url'] ?? null,
                'cover_image_url' => $data['cover_image_url'] ?? null,
                'peer_review_type' => $data['peer_review_type'] ?? null,
                'aims' => $data['aims'] ?? null,
                'scope' => $data['scope'] ?? null,
                'admin_notes' => $data['admin_notes'] ?? null,
                'submission_guidelines' => $data['submission_guidelines'] ?? null,
                'review_policy' => $data['review_policy'] ?? null,
                'editorial_policies' => $data['editorial_policies'] ?? null,
                'indexing_databases' => $data['indexing_databases'] ?? [],
                'subject_areas' => $data['subject_areas'] ?? [],
                'keywords' => $data['keywords'] ?? [],
                'languages' => $data['languages'] ?? ['en'],
                'manuscript_types' => $data['manuscript_types'] ?? ['research_article'],
                'settings' => $data['settings'] ?? [],
                'metadata' => $data['metadata'] ?? []
            ]);

            // Add creator to editorial board as editor-in-chief
            $journal->editorialBoard()->attach($user->id, [
                'role' => 'editor_in_chief',
                'is_active' => true,
                'joined_at' => now(),
            ]);

            // Attach categories if provided
            if (!empty($data['category_ids']) || !empty($data['category_id'])) {
                $categoryIds = $data['category_ids'] ?? [$data['category_id']];
                $journal->categories()->attach($categoryIds);
            }

            Log::info('Journal created successfully', [
                'journal_id' => $journal->id,
                'title' => $journal->title,
                'created_by' => $user->id,
            ]);

            // Reload with relationships
            return $journal->fresh(['categories', 'editorialBoard']);
        });
    }
}
