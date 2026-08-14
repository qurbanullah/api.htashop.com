<?php

namespace App\Services\Publication;

use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class PublicationService
{
    /**
     * Get paginated publications with filters.
     */
    public function getPaginatedPublications(array $filters = [], int $perPage = 15): LengthAwarePaginator
    {
        $query = Publication::query()
            ->with(['publishable', 'verifiedBy']);

        $this->applyFilters($query, $filters);

        return $query->latest()->paginate($perPage);
    }

    /**
     * Get all publications.
     */
    public function getAllPublications(array $filters = []): Collection
    {
        $query = Publication::query()
            ->with(['publishable', 'verifiedBy']);

        $this->applyFilters($query, $filters);

        return $query->latest()->get();
    }

    /**
     * Find publication by ID.
     */
    public function findPublication(int $id): ?Publication
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->findOrFail($id);
    }

    /**
     * Find publication by slug.
     */
    public function findPublicationBySlug(string $slug): ?Publication
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->where('slug', $slug)
            ->firstOrFail();
    }

    /**
     * Create a new publication.
     */
    public function createPublication(array $data): Publication
    {
        return Publication::create($data);
    }

    /**
     * Update a publication.
     */
    public function updatePublication(Publication $publication, array $data): Publication
    {
        $publication->update($data);
        return $publication->fresh(['publishable', 'verifiedBy']);
    }

    /**
     * Delete a publication.
     */
    public function deletePublication(Publication $publication): bool
    {
        return $publication->delete();
    }

    /**
     * Restore a soft-deleted publication.
     */
    public function restorePublication(int $id): Publication
    {
        $publication = Publication::withTrashed()->findOrFail($id);
        $publication->restore();
        return $publication->fresh(['publishable', 'verifiedBy']);
    }

    /**
     * Permanently delete a publication.
     */
    public function forceDeletePublication(int $id): bool
    {
        $publication = Publication::withTrashed()->findOrFail($id);
        return $publication->forceDelete();
    }

    /**
     * Verify a publication.
     */
    public function verifyPublication(Publication $publication, int $verifiedBy): Publication
    {
        $publication->markAsVerified($verifiedBy);
        return $publication->fresh(['publishable', 'verifiedBy']);
    }

    /**
     * Unverify a publication.
     */
    public function unverifyPublication(Publication $publication): Publication
    {
        $publication->markAsUnverified();
        return $publication->fresh(['publishable', 'verifiedBy']);
    }

    /**
     * Toggle featured status.
     */
    public function toggleFeatured(Publication $publication): Publication
    {
        $publication->toggleFeatured();
        return $publication->fresh(['publishable', 'verifiedBy']);
    }

    /**
     * Toggle public status.
     */
    public function togglePublic(Publication $publication): Publication
    {
        $publication->togglePublic();
        return $publication->fresh(['publishable', 'verifiedBy']);
    }

    /**
     * Increment view count.
     */
    public function incrementViews(Publication $publication, int $count = 1): void
    {
        $publication->incrementViews($count);
    }

    /**
     * Increment download count.
     */
    public function incrementDownloads(Publication $publication, int $count = 1): void
    {
        $publication->incrementDownloads($count);
    }

    /**
     * Increment citation count.
     */
    public function incrementCitations(Publication $publication, int $count = 1): void
    {
        $publication->incrementCitations($count);
    }

    /**
     * Get publications by type.
     */
    public function getPublicationsByType(string $type, int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->ofType($type)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get journal articles.
     */
    public function getJournalArticles(int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->journalArticles()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get conference papers.
     */
    public function getConferencePapers(int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->conferencePapers()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get books.
     */
    public function getBooks(int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->books()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get verified publications.
     */
    public function getVerifiedPublications(int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->verified()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get unverified publications.
     */
    public function getUnverifiedPublications(int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->unverified()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get open access publications.
     */
    public function getOpenAccessPublications(int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->openAccess()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get featured publications.
     */
    public function getFeaturedPublications(int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->featured()
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get highly cited publications.
     */
    public function getHighlyCitedPublications(int $minCitations = 10, int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->highlyCited($minCitations)
            ->paginate($perPage);
    }

    /**
     * Get recent publications.
     */
    public function getRecentPublications(int $years = 5, int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->recent($years)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get publications by year.
     */
    public function getPublicationsByYear(int $year, int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->byYear($year)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get publications by year range.
     */
    public function getPublicationsByYearRange(int $startYear, int $endYear, int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->byYearRange($startYear, $endYear)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Search publications.
     */
    public function searchPublications(string $search, int $perPage = 15): LengthAwarePaginator
    {
        return Publication::with(['publishable', 'verifiedBy'])
            ->search($search)
            ->latest()
            ->paginate($perPage);
    }

    /**
     * Get publication statistics.
     */
    public function getStatistics(): array
    {
        $total = Publication::count();
        $verified = Publication::verified()->count();
        $unverified = Publication::unverified()->count();
        $openAccess = Publication::openAccess()->count();
        $peerReviewed = Publication::peerReviewed()->count();
        $featured = Publication::featured()->count();

        $byType = Publication::selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $byYear = Publication::selectRaw('publication_year, COUNT(*) as count')
            ->groupBy('publication_year')
            ->orderBy('publication_year', 'desc')
            ->limit(10)
            ->pluck('count', 'publication_year')
            ->toArray();

        $totalCitations = Publication::sum('citation_count');
        $avgCitations = Publication::avg('citation_count');
        $totalViews = Publication::sum('view_count');
        $totalDownloads = Publication::sum('download_count');

        return [
            'total' => $total,
            'verified' => $verified,
            'unverified' => $unverified,
            'open_access' => $openAccess,
            'peer_reviewed' => $peerReviewed,
            'featured' => $featured,
            'by_type' => $byType,
            'by_year' => $byYear,
            'total_citations' => (int) $totalCitations,
            'avg_citations' => round($avgCitations, 2),
            'total_views' => (int) $totalViews,
            'total_downloads' => (int) $totalDownloads,
        ];
    }

    /**
     * Apply filters to query.
     */
    protected function applyFilters(Builder $query, array $filters): void
    {
        // Search
        if (!empty($filters['search'])) {
            $query->search($filters['search']);
        }

        // Type filter
        if (!empty($filters['type'])) {
            $query->ofType($filters['type']);
        }

        // Access type filter
        if (!empty($filters['access_type'])) {
            $query->byAccessType($filters['access_type']);
        }

        // Verified filter
        if (isset($filters['is_verified'])) {
            if ($filters['is_verified']) {
                $query->verified();
            } else {
                $query->unverified();
            }
        }

        // Public filter
        if (isset($filters['is_public'])) {
            $query->where('is_public', $filters['is_public']);
        }

        // Featured filter
        if (isset($filters['is_featured']) && $filters['is_featured']) {
            $query->featured();
        }

        // Open access filter
        if (isset($filters['is_open_access']) && $filters['is_open_access']) {
            $query->openAccess();
        }

        // Peer reviewed filter
        if (isset($filters['is_peer_reviewed']) && $filters['is_peer_reviewed']) {
            $query->peerReviewed();
        }

        // Year filter
        if (!empty($filters['year'])) {
            $query->byYear($filters['year']);
        }

        // Year range filter
        if (!empty($filters['start_year']) && !empty($filters['end_year'])) {
            $query->byYearRange($filters['start_year'], $filters['end_year']);
        }

        // Publishable type filter
        if (!empty($filters['publishable_type'])) {
            $query->where('publishable_type', $filters['publishable_type']);
        }

        // Publishable ID filter
        if (!empty($filters['publishable_id'])) {
            $query->where('publishable_id', $filters['publishable_id']);
        }

        // Min citations filter
        if (isset($filters['min_citations'])) {
            $query->where('citation_count', '>=', $filters['min_citations']);
        }

        // Include trashed
        if (!empty($filters['include_trashed'])) {
            $query->withTrashed();
        }

        // Only trashed
        if (!empty($filters['only_trashed'])) {
            $query->onlyTrashed();
        }
    }
}
