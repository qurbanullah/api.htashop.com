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

class SubmitManuscript
{
    public function __construct(
        private ManuscriptNumberService $manuscriptNumberService
    ) {}

    public function handle(Journal $journal, array $data, User $submittingUser): Manuscript
    {
        return DB::transaction(function () use ($journal, $data, $submittingUser) {
            // Generate unique manuscript number
            $manuscriptNumber = $this->manuscriptNumberService->generateManuscriptNumber($journal);

            // Create the manuscript
            $manuscript = Manuscript::create([
                'journal_id' => $journal->id,
                'submitting_author_id' => $submittingUser->id,
                'manuscript_number' => $manuscriptNumber,
                'title' => $data['title'],
                'abstract' => $data['abstract'],
                'keywords' => $data['keywords'] ?? [],
                'classifications' => $data['classifications'] ?? null,
                'manuscript_type' => $data['manuscript_type'] ?? 'research_article',
                'status' => ManuscriptStatus::SUBMITTED,
                'submission_date' => now(),
                'word_count' => $data['word_count'] ?? null,
                'page_count' => $data['page_count'] ?? null,
                'language' => $data['language'] ?? 'en',
                'is_resubmission' => $data['is_resubmission'] ?? false,
                'original_submission_id' => $data['original_submission_id'] ?? null,
                'funding_information' => $data['funding_information'] ?? null,
                'conflict_of_interest' => $data['conflict_of_interest'] ?? null,
                'ethical_approval' => $data['ethical_approval'] ?? null,
                'data_availability' => $data['data_availability'] ?? null,
                'acknowledgments' => $data['acknowledgments'] ?? null,
                'corresponding_author_email' => $data['corresponding_author_email'] ?? $submittingUser->email,
                'suggested_reviewers' => $data['suggested_reviewers'] ?? [],
                'excluded_reviewers' => $data['excluded_reviewers'] ?? [],
                'cover_letter' => $data['cover_letter'] ?? null,
                'author_contribution' => $data['author_contribution'] ?? null,
                'supplementary_info' => $data['supplementary_info'] ?? null,
                'version' => 1,
                'metadata' => $data['metadata'] ?? []
            ]);

            // Attach authors if provided.
            // Support legacy 'author_ids' (array of user IDs) or new 'authors' (array of objects { user_id, is_corresponding, author_order, contribution })
            if (!empty($data['authors']) && is_array($data['authors'])) {
                foreach ($data['authors'] as $index => $a) {
                    // Determine user id: prefer explicit user_id, else try to find by email, else create a lightweight user
                    $userId = null;
                    if (!empty($a['user_id'])) {
                        $userId = intval($a['user_id']);
                    } elseif (!empty($a['email'])) {
                        $email = trim(strval($a['email']));
                        $user = User::where('email', $email)->first();
                        if (!$user) {
                            // create a simple user record. Use a random password.
                            $user = User::create([
                                'name' => trim((($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))),
                                'email' => $email,
                                'password' => Hash::make(Str::random(24)),
                            ]);
                            // Optionally assign author role if roles system is in use
                            try {
                                if (method_exists($user, 'assignRole')) {
                                    $user->assignRole('author');
                                }
                            } catch (\Throwable $e) {
                                // ignore if roles are not configured
                            }
                        }
                        $userId = $user->id;
                    } else {
                        // No user identifier provided. Create a lightweight placeholder user with random email.
                        $user = User::create([
                            'name' => trim((($a['first_name'] ?? '') . ' ' . ($a['last_name'] ?? ''))),
                            'email' => 'author+' . Str::random(8) . '@example.invalid',
                            'password' => Hash::make(Str::random(24)),
                        ]);
                        $userId = $user->id;
                    }

                    $manuscript->authors()->create([
                        'user_id' => $userId,
                        'author_order' => isset($a['author_order']) ? intval($a['author_order']) : ($index + 1),
                        'is_corresponding' => !empty($a['is_corresponding']) ? 1 : 0,
                        'contribution' => $a['contribution'] ?? null,
                    ]);

                    // Save affiliation if provided
                    if (!empty($a['affiliation'])) {
                        $author = $manuscript->authors()->where('user_id', $userId)->first();
                        if ($author) {
                            $author->affiliations()->create([
                                'institution' => $a['affiliation'],
                                'is_current' => true,
                            ]);
                        }
                    }
                }
            } elseif (!empty($data['author_ids'])) {
                foreach ($data['author_ids'] as $index => $authorId) {
                    $author = $manuscript->authors()->create([
                        'user_id' => intval($authorId),
                        'author_order' => $index + 1,
                        'is_corresponding' => ($authorId == ($data['corresponding_author_id'] ?? false)) ? 1 : 0,
                        'contribution' => $data['author_contributions'][$authorId] ?? null,
                    ]);

                    // Save affiliation if provided in author_affiliations mapping
                    if (!empty($data['author_affiliations'][$authorId])) {
                        $author->affiliations()->create([
                            'institution' => $data['author_affiliations'][$authorId],
                            'is_current' => true,
                        ]);
                    }
                }
            }

            // Attach manuscript file via Spatie medialibrary if provided
            try {
                Log::debug('Starting media attachment', [
                    'manuscript_id' => $manuscript->id,
                    'has_manuscript_file' => !empty($data['manuscript_file']),
                    'has_cover_letter' => !empty($data['cover_letter_file']),
                    'has_graphical_abstract' => !empty($data['graphical_abstract']),
                    'has_video_abstract' => !empty($data['video_abstract']),
                    'supplementary_files_count' => !empty($data['supplementary_files']) ? count($data['supplementary_files']) : 0,
                    'copyright_permissions_count' => !empty($data['copyright_permissions']) ? count($data['copyright_permissions']) : 0
                ]);

                if (!empty($data['manuscript_file'])) {
                    // Get file extension
                    $extension = $data['manuscript_file']->getClientOriginalExtension();

                    // Generate clean filename using manuscript number
                    $fileName = $manuscriptNumber . '.' . $extension;

                    // $data['manuscript_file'] is expected to be an instance of UploadedFile
                    $manuscript->addMedia($data['manuscript_file'])
                        ->usingFileName($fileName)
                        ->usingName($manuscriptNumber) // Human-readable name
                        ->preservingOriginal()
                        ->toMediaCollection('manuscript_file', 'idrivee2');
                    Log::debug('Manuscript file attached successfully');
                }

                // Attach cover letter
                if (!empty($data['cover_letter_file'])) {
                    $manuscript->addMedia($data['cover_letter_file'])
                        ->preservingOriginal()
                        ->toMediaCollection('cover_letter', 'idrivee2');
                    Log::debug('Cover letter attached successfully');
                }

                // Attach graphical abstract
                if (!empty($data['graphical_abstract'])) {
                    $manuscript->addMedia($data['graphical_abstract'])
                        ->preservingOriginal()
                        ->toMediaCollection('graphical_abstract', 'idrivee2');
                    Log::debug('Graphical abstract attached successfully');
                }

                // Attach video abstract
                if (!empty($data['video_abstract'])) {
                    $manuscript->addMedia($data['video_abstract'])
                        ->preservingOriginal()
                        ->toMediaCollection('video_abstract', 'idrivee2');
                    Log::debug('Video abstract attached successfully');
                }

                // Attach supplementary files (array of ['file' => UploadedFile, 'title' => '', 'description' => ''])
                if (!empty($data['supplementary_files']) && is_array($data['supplementary_files'])) {
                    Log::debug('Processing supplementary files in action', ['count' => count($data['supplementary_files'])]);
                    foreach ($data['supplementary_files'] as $index => $sup) {
                        Log::debug('Supplementary file iteration', [
                            'index' => $index,
                            'has_file_key' => isset($sup['file']),
                            'file_is_null' => isset($sup['file']) ? ($sup['file'] === null) : 'key_missing',
                            'file_type' => isset($sup['file']) && $sup['file'] ? get_class($sup['file']) : 'null',
                            'title' => $sup['title'] ?? 'no_title'
                        ]);

                        if (isset($sup['file']) && $sup['file'] !== null) {
                            $media = $manuscript->addMedia($sup['file'])
                                ->preservingOriginal()
                                ->withCustomProperties([
                                    'title' => $sup['title'] ?? null,
                                    'description' => $sup['description'] ?? null,
                                ])
                                ->toMediaCollection('supplementary_files', 'idrivee2');
                            Log::debug('Supplementary file attached successfully', ['index' => $index, 'media_id' => $media->id]);
                        } else {
                            Log::warning('Supplementary file skipped - file is null or missing', ['index' => $index]);
                        }
                    }
                }

                // Attach copyright permissions
                if (!empty($data['copyright_permissions']) && is_array($data['copyright_permissions'])) {
                    Log::debug('Processing copyright permissions in action', ['count' => count($data['copyright_permissions'])]);
                    foreach ($data['copyright_permissions'] as $index => $perm) {
                        Log::debug('Copyright permission iteration', [
                            'index' => $index,
                            'has_file_key' => isset($perm['file']),
                            'file_is_null' => isset($perm['file']) ? ($perm['file'] === null) : 'key_missing',
                            'file_type' => isset($perm['file']) && $perm['file'] ? get_class($perm['file']) : 'null'
                        ]);

                        if (isset($perm['file']) && $perm['file'] !== null) {
                            $media = $manuscript->addMedia($perm['file'])
                                ->preservingOriginal()
                                ->withCustomProperties([
                                    'description' => $perm['description'] ?? null,
                                ])
                                ->toMediaCollection('copyright_permissions', 'idrivee2');
                            Log::debug('Copyright permission attached successfully', ['index' => $index, 'media_id' => $media->id]);
                        } else {
                            Log::warning('Copyright permission skipped - file is null or missing', ['index' => $index]);
                        }
                    }
                }
            } catch (\Exception $e) {
                // Log but don't break the transaction; record warning. The file storage may fail if S3 is unreachable.
                Log::error('Failed to attach manuscript media', [
                    'manuscript_id' => $manuscript->id,
                    'error' => $e->getMessage(),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]);
            }

            // Log media attachment completion
            $mediaCount = $manuscript->getMedia()->count();
            Log::info('Media attachment completed', [
                'manuscript_id' => $manuscript->id,
                'total_media_count' => $mediaCount,
                'collections' => $manuscript->getMedia()->groupBy('collection_name')->map->count()
            ]);

            // Attach reviewer suggestions (suggested and excluded)
            if (!empty($data['reviewer_suggestions']) && is_array($data['reviewer_suggestions'])) {
                foreach ($data['reviewer_suggestions'] as $suggestion) {
                    $manuscript->reviewerSuggestions()->create([
                        'type' => $suggestion['type'] ?? 'suggested', // 'suggested' or 'excluded'
                        'name' => $suggestion['name'],
                        'email' => $suggestion['email'],
                        'affiliation' => $suggestion['affiliation'] ?? null,
                        'reason' => $suggestion['reason'] ?? null,
                        'expertise_area' => $suggestion['expertise_area'] ?? null,
                    ]);
                }
            }

            // Attach statements with responses
            if (!empty($data['statements']) && is_array($data['statements'])) {
                foreach ($data['statements'] as $statement) {
                    $manuscript->statements()->attach($statement['statement_id'], [
                        'response' => $statement['response'] ?? null, // 'yes', 'no', 'na'
                        'details' => $statement['details'] ?? null,
                        'metadata' => $statement['metadata'] ?? null,
                    ]);
                }
            }

            // Attach categories if provided
            if (!empty($data['category_ids'])) {
                $manuscript->categories()->attach($data['category_ids']);
            }

            // Create initial editorial decision log
            $manuscript->decisions()->create([
                'user_id' => $submittingUser->id,
                'type' => \App\Enums\DecisionType::EDITORIAL,
                'decision' => 'submitted',
                'content' => 'Manuscript submitted for review',
                'completed_at' => now()
            ]);

            Log::info('Manuscript submitted successfully', [
                'manuscript_id' => $manuscript->id,
                'journal_id' => $journal->id,
                'title' => $manuscript->title,
                'submitted_by' => $submittingUser->id
            ]);

            return $manuscript;
        });
    }
}
