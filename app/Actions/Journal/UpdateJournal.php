<?php

namespace App\Actions\Journal;

use App\Models\Journal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateJournal
{
    public function handle(Journal $journal, array $data, User $user): Journal
    {
        return DB::transaction(function () use ($journal, $data, $user) {
            // Update journal fields
            $journal->update(array_filter([
                'title' => $data['title'] ?? $journal->title,
                'abbreviation' => $data['abbreviation'] ?? $journal->abbreviation,
                'description' => $data['description'] ?? $journal->description,
                'issn' => $data['issn'] ?? $journal->issn,
                'e_issn' => $data['e_issn'] ?? $journal->e_issn,
                'publisher' => $data['publisher'] ?? $journal->publisher,
                'publication_frequency' => $data['publication_frequency'] ?? $journal->publication_frequency,
                'is_open_access' => $data['is_open_access'] ?? $journal->is_open_access,
                'is_peer_reviewed' => $data['is_peer_reviewed'] ?? $journal->is_peer_reviewed,
                'is_active' => $data['is_active'] ?? $journal->is_active,
                'submission_fee' => $data['submission_fee'] ?? $journal->submission_fee,
                'publication_fee' => $data['publication_fee'] ?? $journal->publication_fee,
                'impact_factor' => $data['impact_factor'] ?? $journal->impact_factor,
                'h_index' => $data['h_index'] ?? $journal->h_index,
                'citation_score' => $data['citation_score'] ?? $journal->citation_score,
                'contact_email' => $data['contact_email'] ?? $journal->contact_email,
                'website_url' => $data['website_url'] ?? $journal->website_url,
                'country' => $data['country'] ?? $journal->country,
                'status' => $data['status'] ?? $journal->status,
                'logo_url' => $data['logo_url'] ?? $journal->logo_url,
                'cover_image_url' => $data['cover_image_url'] ?? $journal->cover_image_url,
                'peer_review_type' => $data['peer_review_type'] ?? $journal->peer_review_type,
                'aims' => $data['aims'] ?? $journal->aims,
                'scope' => $data['scope'] ?? $journal->scope,
                'submission_guidelines' => $data['submission_guidelines'] ?? $journal->submission_guidelines,
                'review_policy' => $data['review_policy'] ?? $journal->review_policy,
                'editorial_policies' => $data['editorial_policies'] ?? $journal->editorial_policies,
                'admin_notes' => $data['admin_notes'] ?? $journal->admin_notes,
                'indexing_databases' => $data['indexing_databases'] ?? $journal->indexing_databases,
                'subject_areas' => $data['subject_areas'] ?? $journal->subject_areas,
                'keywords' => $data['keywords'] ?? $journal->keywords,
                'languages' => $data['languages'] ?? $journal->languages,
                'manuscript_types' => $data['manuscript_types'] ?? $journal->manuscript_types,
                'settings' => array_merge($journal->settings ?? [], $data['settings'] ?? []),
                'metadata' => array_merge($journal->metadata ?? [], $data['metadata'] ?? [])
            ], function ($value) {
                return $value !== null;
            }));

            // Update categories if provided
            if (isset($data['category_ids'])) {
                $journal->categories()->sync($data['category_ids']);
            }

            Log::info('Journal updated successfully', [
                'journal_id' => $journal->id,
                'title' => $journal->title,
                'updated_by' => $user->id,
            ]);

            // Reload with relationships
            return $journal->fresh(['categories', 'editorialBoard']);
        });
    }
}
