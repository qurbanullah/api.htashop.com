<?php

namespace App\Actions\Manuscript;

use App\Models\Journal;
use App\Models\Manuscript;
use App\Models\User;
use App\Services\Menuscript\ManuscriptNumberService;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash;
use App\Enums\ManuscriptStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class SaveManuscriptDraft
{
    public function __construct(
        private ManuscriptNumberService $manuscriptNumberService
    ) {}

    public function handle(Journal $journal, array $data, User $submittingUser, ?Manuscript $existingManuscript = null): Manuscript
    {
        return DB::transaction(function () use ($journal, $data, $submittingUser, $existingManuscript) {
            // Create or update manuscript
            if ($existingManuscript) {
                // Update existing draft
                $manuscript = $existingManuscript;
                $manuscript->update([
                    'title' => $data['title'] ?? $manuscript->title,
                    'abstract' => $data['abstract'] ?? $manuscript->abstract,
                    'keywords' => $data['keywords'] ?? $manuscript->keywords,
                    'manuscript_type' => $data['manuscript_type'] ?? $manuscript->manuscript_type,
                    'word_count' => $data['word_count'] ?? $manuscript->word_count,
                    'page_count' => $data['page_count'] ?? $manuscript->page_count,
                    'language' => $data['language'] ?? $manuscript->language,
                    'cover_letter' => $data['cover_letter'] ?? $manuscript->cover_letter,
                    'funding_information' => $data['funding_information'] ?? $manuscript->funding_information,
                    'conflict_of_interest' => $data['conflict_of_interest'] ?? $manuscript->conflict_of_interest,
                    'ethical_approval' => $data['ethical_approval'] ?? $manuscript->ethical_approval,
                    'data_availability' => $data['data_availability'] ?? $manuscript->data_availability,
                    'acknowledgments' => $data['acknowledgments'] ?? $manuscript->acknowledgments,
                    'metadata' => array_merge($manuscript->metadata ?? [], $data['metadata'] ?? []),
                ]);
            } else {
                // Create new draft
                $manuscriptNumber = $this->manuscriptNumberService->generateManuscriptNumber($journal);

                $manuscript = Manuscript::create([
                    'journal_id' => $journal->id,
                    'submitting_author_id' => $submittingUser->id,
                    'manuscript_number' => $manuscriptNumber,
                    'title' => $data['title'] ?? 'Untitled',
                    'abstract' => $data['abstract'] ?? '',
                    'keywords' => $data['keywords'] ?? [],
                    'manuscript_type' => $data['manuscript_type'] ?? 'research_article',
                    'status' => ManuscriptStatus::DRAFT,
                    'submission_date' => null, // No submission date for drafts
                    'word_count' => $data['word_count'] ?? null,
                    'page_count' => $data['page_count'] ?? null,
                    'language' => $data['language'] ?? 'en',
                    'is_resubmission' => false,
                    'funding_information' => $data['funding_information'] ?? null,
                    'conflict_of_interest' => $data['conflict_of_interest'] ?? null,
                    'ethical_approval' => $data['ethical_approval'] ?? null,
                    'data_availability' => $data['data_availability'] ?? null,
                    'acknowledgments' => $data['acknowledgments'] ?? null,
                    'cover_letter' => $data['cover_letter'] ?? null,
                    'version' => 1,
                    'metadata' => $data['metadata'] ?? []
                ]);
            }

            // Attach authors if provided
            if (!empty($data['authors']) && is_array($data['authors'])) {
                // Clear existing authors for draft
                if ($existingManuscript) {
                    $manuscript->authors()->delete();
                }

                foreach ($data['authors'] as $index => $a) {
                    $userId = null;
                    if (!empty($a['user_id'])) {
                        $userId = intval($a['user_id']);
                    } elseif (!empty($a['email'])) {
                        $email = trim(strval($a['email']));
                        $user = User::where('email', $email)->first();
                        if (!$user) {
                            $user = User::create([
                                'name' => trim((($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))),
                                'email' => $email,
                                'password' => Hash::make(Str::random(24)),
                            ]);
                            try {
                                if (method_exists($user, 'assignRole')) {
                                    $user->assignRole('author');
                                }
                            } catch (\Throwable $e) {
                                // ignore if roles are not configured
                            }
                        }
                        $userId = $user->id;
                    }

                    if ($userId) {
                        $author = $manuscript->authors()->create([
                            'user_id' => $userId,
                            'author_order' => isset($a['author_order']) ? intval($a['author_order']) : ($index + 1),
                            'is_corresponding' => !empty($a['is_corresponding']) ? 1 : 0,
                            'contribution' => $a['contribution'] ?? null,
                        ]);

                        // Save affiliation if provided
                        if (!empty($a['affiliation'])) {
                            $author->affiliations()->create([
                                'institution' => $a['affiliation'],
                                'is_current' => true,
                            ]);
                        }
                    }
                }
            }

            // Attach reviewer suggestions if provided
            if (!empty($data['reviewer_suggestions']) && is_array($data['reviewer_suggestions'])) {
                // Clear existing suggestions for draft
                if ($existingManuscript) {
                    $manuscript->reviewerSuggestions()->delete();
                }

                foreach ($data['reviewer_suggestions'] as $suggestion) {
                    $manuscript->reviewerSuggestions()->create([
                        'type' => $suggestion['type'] ?? 'suggested',
                        'name' => $suggestion['name'],
                        'email' => $suggestion['email'],
                        'affiliation' => $suggestion['affiliation'] ?? null,
                        'reason' => $suggestion['reason'] ?? null,
                        'expertise_area' => $suggestion['expertise_area'] ?? null,
                    ]);
                }
            }

            // Attach statements if provided
            if (!empty($data['statements']) && is_array($data['statements'])) {
                // Clear existing statements for draft
                if ($existingManuscript) {
                    $manuscript->statements()->detach();
                }

                foreach ($data['statements'] as $statement) {
                    $manuscript->statements()->attach($statement['statement_id'], [
                        'response' => $statement['response'] ?? null,
                        'details' => $statement['details'] ?? null,
                        'metadata' => $statement['metadata'] ?? null,
                    ]);
                }
            }

            // Attach categories if provided
            if (!empty($data['category_ids']) && is_array($data['category_ids'])) {
                // Clear existing categories for draft
                if ($existingManuscript) {
                    $manuscript->categories()->detach();
                }
                $manuscript->categories()->attach($data['category_ids']);
            }

            // Attach files via Spatie media library (only if provided)
            try {
                // Get manuscript number for file naming
                $manuscriptNumber = $manuscript->manuscript_number;

                // Attach manuscript file if provided
                if (!empty($data['manuscript_file'])) {
                    // Only add media if it's a new file (File object), not when keeping existing
                    if (is_object($data['manuscript_file']) && method_exists($data['manuscript_file'], 'getClientOriginalExtension')) {
                        // Delete old manuscript_file media if updating
                        if ($existingManuscript) {
                            $manuscript->clearMediaCollection('manuscript_file');
                        }

                        $extension = $data['manuscript_file']->getClientOriginalExtension();
                        $fileName = $manuscriptNumber . '.' . $extension;

                        $manuscript->addMedia($data['manuscript_file'])
                            ->usingFileName($fileName)
                            ->usingName($manuscriptNumber)
                            ->preservingOriginal()
                            ->toMediaCollection('manuscript_file', 'idrivee2');
                        Log::debug('Manuscript file attached to draft');
                    }
                }

                // Attach cover letter if provided
                if (!empty($data['cover_letter_file'])) {
                    if (is_object($data['cover_letter_file']) && method_exists($data['cover_letter_file'], 'getClientOriginalExtension')) {
                        // Delete old cover_letter media if updating
                        if ($existingManuscript) {
                            $manuscript->clearMediaCollection('cover_letter');
                        }

                        $manuscript->addMedia($data['cover_letter_file'])
                            ->preservingOriginal()
                            ->toMediaCollection('cover_letter', 'idrivee2');
                        Log::debug('Cover letter attached to draft');
                    }
                }

                // Attach graphical abstract if provided
                if (!empty($data['graphical_abstract'])) {
                    if (is_object($data['graphical_abstract']) && method_exists($data['graphical_abstract'], 'getClientOriginalExtension')) {
                        // Delete old graphical_abstract media if updating
                        if ($existingManuscript) {
                            $manuscript->clearMediaCollection('graphical_abstract');
                        }

                        $manuscript->addMedia($data['graphical_abstract'])
                            ->preservingOriginal()
                            ->toMediaCollection('graphical_abstract', 'idrivee2');
                        Log::debug('Graphical abstract attached to draft');
                    }
                }

                // Attach video abstract if provided
                if (!empty($data['video_abstract'])) {
                    if (is_object($data['video_abstract']) && method_exists($data['video_abstract'], 'getClientOriginalExtension')) {
                        // Delete old video_abstract media if updating
                        if ($existingManuscript) {
                            $manuscript->clearMediaCollection('video_abstract');
                        }

                        $manuscript->addMedia($data['video_abstract'])
                            ->preservingOriginal()
                            ->toMediaCollection('video_abstract', 'idrivee2');
                        Log::debug('Video abstract attached to draft');
                    }
                }

                // Attach supplementary files if provided
                if (!empty($data['supplementary_files']) && is_array($data['supplementary_files'])) {
                    // Delete old supplementary files if updating
                    if ($existingManuscript) {
                        $manuscript->clearMediaCollection('supplementary_files');
                    }

                    foreach ($data['supplementary_files'] as $supFile) {
                        if (!empty($supFile['file']) && is_object($supFile['file']) && method_exists($supFile['file'], 'getClientOriginalExtension')) {
                            $media = $manuscript->addMedia($supFile['file'])
                                ->preservingOriginal()
                                ->toMediaCollection('supplementary_files', 'idrivee2');

                            // Store metadata (title and description)
                            if (!empty($supFile['title']) || !empty($supFile['description'])) {
                                $media->setCustomProperty('title', $supFile['title'] ?? null)
                                    ->setCustomProperty('description', $supFile['description'] ?? null)
                                    ->save();
                            }

                            Log::debug('Supplementary file attached to draft', [
                                'title' => $supFile['title'] ?? 'Untitled',
                                'file_name' => $supFile['file']->getClientOriginalName()
                            ]);
                        }
                    }
                }

                // Attach copyright permissions if provided
                if (!empty($data['copyright_permissions']) && is_array($data['copyright_permissions'])) {
                    // Delete old copyright permissions if updating
                    if ($existingManuscript) {
                        $manuscript->clearMediaCollection('copyright_permissions');
                    }

                    foreach ($data['copyright_permissions'] as $permFile) {
                        if (!empty($permFile['file']) && is_object($permFile['file']) && method_exists($permFile['file'], 'getClientOriginalExtension')) {
                            $media = $manuscript->addMedia($permFile['file'])
                                ->preservingOriginal()
                                ->toMediaCollection('copyright_permissions', 'idrivee2');

                            // Store metadata (description)
                            if (!empty($permFile['description'])) {
                                $media->setCustomProperty('description', $permFile['description'])
                                    ->save();
                            }

                            Log::debug('Copyright permission attached to draft');
                        }
                    }
                }

                Log::debug('Media attachment completed for draft', [
                    'manuscript_id' => $manuscript->id
                ]);
            } catch (\Exception $e) {
                Log::error('Error attaching media to draft', [
                    'manuscript_id' => $manuscript->id,
                    'error' => $e->getMessage()
                ]);
                throw $e;
            }

            Log::info('Manuscript draft saved', [
                'manuscript_id' => $manuscript->id,
                'journal_id' => $journal->id,
                'is_update' => $existingManuscript ? true : false,
                'title' => $manuscript->title,
                'saved_by' => $submittingUser->id
            ]);

            return $manuscript;
        });
    }
}
