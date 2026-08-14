<?php

namespace App\Services\Journal;

use App\Models\Journal;
use App\Models\User;
use App\Actions\Journal\CreateJournal;
use App\Actions\Journal\UpdateJournal;
use App\Actions\Journal\AddEditorialBoardMember;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;

class JournalService
{
    public function __construct(
        private CreateJournal $createJournal,
        private UpdateJournal $updateJournal,
        private AddEditorialBoardMember $addEditorialBoardMember
    ) {}

    /**
     * Get paginated list of journals with optional filtering
     */
    public function getJournals(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Journal::with(['categories', 'editorialBoard'])
            ->when(isset($filters['search']), function ($q) use ($filters) {
                $q->where(function ($subQuery) use ($filters) {
                    $subQuery->where('title', 'like', "%{$filters['search']}%")
                        ->orWhere('description', 'like', "%{$filters['search']}%")
                        ->orWhere('issn', 'like', "%{$filters['search']}%")
                        ->orWhere('eissn', 'like', "%{$filters['search']}%");
                });
            })
            ->when(isset($filters['is_active']), function ($q) use ($filters) {
                $q->where('is_active', $filters['is_active']);
            })
            ->when(isset($filters['is_open_access']), function ($q) use ($filters) {
                $q->where('is_open_access', $filters['is_open_access']);
            })
            ->when(isset($filters['category_id']), function ($q) use ($filters) {
                $q->whereHas('categories', function ($subQuery) use ($filters) {
                    $subQuery->where('categories.id', $filters['category_id']);
                });
            })
            ->orderBy('title');

        return $query->paginate($perPage);
    }

    /**
     * Get a journal by ID with relationships
     */
    public function getJournal(int $journalId): Journal
    {
        return Journal::with([
            'categories',
            'editorialBoard',
            'manuscripts' => function ($query) {
                $query->with(['submittingAuthor', 'authors'])
                    ->latest('submission_date')
                    ->limit(10);
            }
        ])->findOrFail($journalId);
    }

    /**
     * Create a new journal
     */
    public function createJournal(array $data, User $user): Journal
    {
        return $this->createJournal->handle($data, $user);
    }

    /**
     * Update an existing journal
     */
    public function updateJournal(Journal $journal, array $data, User $user): Journal
    {
        return $this->updateJournal->handle($journal, $data, $user);
    }

    /**
     * Add or update editorial board member
     */
    public function addEditorialBoardMember(Journal $journal, array $data, User $currentUser): void
    {
        $this->addEditorialBoardMember->handle($journal, $data, $currentUser);
    }

    /**
     * Remove editorial board member
     */
    public function removeEditorialBoardMember(Journal $journal, int $userId, User $currentUser): void
    {
        $member = $journal->editorialBoard()
            ->where('user_id', $userId)
            ->firstOrFail();

        $member->update([
            'is_active' => false,
            'left_at' => now()
        ]);
    }

    /**
     * Get journal statistics
     */
    public function getJournalStatistics(Journal $journal): array
    {
        return [
            'total_manuscripts' => $journal->manuscripts()->count(),
            'published_manuscripts' => $journal->manuscripts()
                ->where('status', \App\Enums\ManuscriptStatus::PUBLISHED)
                ->count(),
            'under_review' => $journal->manuscripts()
                ->where('status', \App\Enums\ManuscriptStatus::UNDER_REVIEW)
                ->count(),
            'pending_review' => $journal->manuscripts()
                ->where('status', \App\Enums\ManuscriptStatus::SUBMITTED)
                ->count(),
            'editorial_board_size' => $journal->editorialBoard()
                ->where('is_active', true)
                ->count(),
            'submission_stats' => [
                'this_month' => $journal->manuscripts()
                    ->where('submission_date', '>=', now()->startOfMonth())
                    ->count(),
                'this_year' => $journal->manuscripts()
                    ->where('submission_date', '>=', now()->startOfYear())
                    ->count()
            ],
            'acceptance_rate' => $this->calculateAcceptanceRate($journal),
            'average_review_time' => $this->calculateAverageReviewTime($journal)
        ];
    }

    /**
     * Get journals where user is an editorial board member
     */
    public function getUserJournals(User $user): Collection
    {
        return Journal::whereHas('editorialBoard', function ($query) use ($user) {
            $query->where('user_id', $user->id)
                ->where('is_active', true);
        })->with(['editorialBoard' => function ($query) use ($user) {
            $query->where('user_id', $user->id);
        }])->get();
    }

    private function calculateAcceptanceRate(Journal $journal): float
    {
        $totalDecided = $journal->manuscripts()
            ->whereIn('status', [
                \App\Enums\ManuscriptStatus::ACCEPTED,
                \App\Enums\ManuscriptStatus::REJECTED,
                \App\Enums\ManuscriptStatus::PUBLISHED
            ])
            ->count();

        if ($totalDecided === 0) {
            return 0;
        }

        $accepted = $journal->manuscripts()
            ->whereIn('status', [
                \App\Enums\ManuscriptStatus::ACCEPTED,
                \App\Enums\ManuscriptStatus::PUBLISHED
            ])
            ->count();

        return round(($accepted / $totalDecided) * 100, 2);
    }

    private function calculateAverageReviewTime(Journal $journal): ?int
    {
        // This would calculate average time from submission to decision
        // Implementation depends on having proper timestamps in decisions
        return null; // Placeholder
    }

    /**
     * Delete a journal (soft delete)
     */
    public function deleteJournal(Journal $journal, User $user): bool
    {
        if (!$user->can('delete', $journal)) {
            throw new \Exception('You do not have permission to delete this journal.');
        }

        // Check if journal has any active manuscripts
        if ($journal->manuscripts()->whereNotIn('status', ['rejected', 'withdrawn'])->exists()) {
            throw new \Exception('Cannot delete journal with active manuscript submissions.');
        }

        return $journal->delete();
    }

    /**
     * Get editorial board members for a journal
     */
    public function getEditorialBoard(Journal $journal): Collection
    {
        return $journal->editorialBoard()
            ->with('user.profile')
            ->orderBy('role')
            ->orderBy('start_date')
            ->get();
    }

    /**
     * Get journal issues
     */
    public function getJournalIssues(Journal $journal): Collection
    {
        return $journal->issues()
            ->with('manuscripts')
            ->orderBy('year', 'desc')
            ->orderBy('volume', 'desc')
            ->orderBy('number', 'desc')
            ->get();
    }

    /**
     * Get recent submissions for a journal
     */
    public function getRecentSubmissions(Journal $journal, int $limit = 10): Collection
    {
        return $journal->manuscripts()
            ->with(['submittingAuthor', 'authors'])
            ->orderBy('submission_date', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Search journals
     */
    public function searchJournals(string $query, array $filters = []): Collection
    {
        $searchQuery = Journal::where('is_active', true)
            ->where(function ($q) use ($query) {
                // The Journal model uses `title` (not `name`) for the display title.
                // Use `title` in searches to ensure results include proper title values.
                $q->where('title', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('publisher', 'like', "%{$query}%")
                    ->orWhere('issn', 'like', "%{$query}%")
                    ->orWhere('e_issn', 'like', "%{$query}%");
            })
            ->when(isset($filters['category_id']), function ($q) use ($filters) {
                $q->whereHas('categories', function ($categoryQuery) use ($filters) {
                    $categoryQuery->where('categories.id', $filters['category_id']);
                });
            })
            ->when(isset($filters['is_open_access']), function ($q) use ($filters) {
                $q->where('is_open_access', $filters['is_open_access']);
            })
            ->when(isset($filters['publisher']), function ($q) use ($filters) {
                $q->where('publisher', 'like', "%{$filters['publisher']}%");
            });

    // Order by title so UI option lists return predictable alphabetical titles.
    return $searchQuery->orderBy('title')->get();
    }
}
